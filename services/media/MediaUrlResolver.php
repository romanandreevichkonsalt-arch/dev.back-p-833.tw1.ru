<?php

namespace app\services\media;

use app\models\MediaFile;

class MediaUrlResolver
{
    /** @var array<int, string> */
    private array $urlCache = [];

    /** @var array<int, array<string, mixed>> */
    private array $imagePayloadCache = [];

    /** @var array<string, MediaFile> */
    private array $pathMediaCache = [];

    public function __construct(
        private readonly string $defaultSrcVariant = 'medium',
        private readonly MediaPathLookup $pathLookup = new MediaPathLookup(),
    ) {
    }

    public static function forPageContent(): self
    {
        return new self('original');
    }

    public static function forJournalArticles(): self
    {
        return new self('medium');
    }

    /**
     * @return array{ref: string, variant: string|null}
     */
    public static function splitMediaReference(string $src): array
    {
        $src = trim($src);
        if ($src === '') {
            return ['ref' => '', 'variant' => null];
        }

        if (preg_match('/^(\d+)#(' . self::VARIANT_PATTERN . ')$/', $src, $matches) === 1) {
            return ['ref' => $matches[1], 'variant' => $matches[2]];
        }

        return ['ref' => $src, 'variant' => null];
    }

    public static function isAllowedVariant(?string $variant): bool
    {
        return $variant !== null
            && $variant !== ''
            && preg_match('/^' . self::VARIANT_PATTERN . '$/', $variant) === 1;
    }

    private const VARIANT_PATTERN = 'mini|medium|large|original';

    public function resolve(string $src): string
    {
        $src = trim($src);
        if ($src === '' || $this->looksLikeUrl($src)) {
            return $src;
        }

        ['ref' => $ref, 'variant' => $variantHint] = self::splitMediaReference($src);
        if (!ctype_digit($ref)) {
            return $src;
        }

        $id = (int)$ref;
        if ($id <= 0) {
            return $src;
        }

        if (!array_key_exists($id, $this->urlCache)) {
            $this->preloadIds([$id]);
        }

        if ($variantHint !== null) {
            $media = MediaFile::findOne($id);
            if ($media !== null) {
                return $media->getPublicUrl($variantHint);
            }
        }

        return $this->urlCache[$id] !== '' ? $this->urlCache[$id] : $ref;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveImagePayload(?string $src, ?string $alt = null): ?array
    {
        $src = trim((string)$src);
        if ($src === '') {
            return null;
        }

        ['ref' => $ref, 'variant' => $variantHint] = self::splitMediaReference($src);
        $variant = self::isAllowedVariant($variantHint) ? $variantHint : $this->defaultSrcVariant;

        if (ctype_digit($ref)) {
            $id = (int)$ref;
            if ($id > 0) {
                $this->preloadIds([$id]);
                $media = MediaFile::findOne($id);
                if ($media !== null) {
                    $payload = $this->buildPayloadForMedia($media, $alt, $variant);
                    if ($alt !== null && trim($alt) !== '') {
                        $payload['alt'] = trim($alt);
                    }

                    return $payload;
                }
            }
        }

        if ($this->looksLikeUrl($ref)) {
            $media = $this->findMediaByPublicPath($ref);
            if ($media !== null) {
                $payload = $this->buildPayloadForMedia($media, $alt, $variant);

                return $payload;
            }

            return [
                'src' => $ref,
                'alt' => trim((string)$alt),
            ];
        }

        return [
            'src' => $this->resolve($ref . ($variantHint !== null ? '#' . $variantHint : '')),
            'alt' => trim((string)$alt),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function resolveTree(array $data): array
    {
        $ids = $this->collectMediaIds($data);
        if ($ids !== []) {
            $this->preloadIds($ids);
        }

        /** @var array<string, mixed> $resolved */
        $resolved = $this->resolveNode($data);

        return $resolved;
    }

    private function looksLikeUrl(string $src): bool
    {
        return str_starts_with($src, '/')
            || str_starts_with($src, 'http://')
            || str_starts_with($src, 'https://');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getImagePayload(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        if (!array_key_exists($id, $this->imagePayloadCache)) {
            $this->preloadIds([$id]);
        }

        return $this->imagePayloadCache[$id] ?? null;
    }

    /**
     * @param array<mixed> $data
     * @return array<int, int>
     */
    private function collectMediaIds(array $data): array
    {
        $ids = [];
        $this->walkCollectIds($data, $ids);

        return array_values(array_unique($ids));
    }

    /**
     * @param array<int, int> $ids
     */
    private function walkCollectIds(mixed $node, array &$ids): void
    {
        if (!is_array($node)) {
            return;
        }

        if ($this->isImageNode($node)) {
            $src = trim((string)($node['src'] ?? ''));
            if ($src === '') {
                return;
            }

            ['ref' => $ref] = self::splitMediaReference($src);
            if (ctype_digit($ref)) {
                $ids[] = (int)$ref;
            }
        }

        foreach ($node as $value) {
            $this->walkCollectIds($value, $ids);
        }
    }

    /**
     * @param array<int, int> $ids
     */
    private function preloadIds(array $ids): void
    {
        $missing = [];
        foreach ($ids as $id) {
            if ($id > 0 && !array_key_exists($id, $this->urlCache)) {
                $missing[$id] = $id;
            }
        }

        if ($missing === []) {
            return;
        }

        foreach ($missing as $id) {
            $this->urlCache[$id] = '';
            $this->imagePayloadCache[$id] = null;
        }

        $files = MediaFile::find()
            ->where(['id' => array_values($missing)])
            ->all();

        foreach ($files as $file) {
            $this->cacheMedia($file);
        }
    }

    private function findMediaByPublicPath(string $url): ?MediaFile
    {
        $path = MediaPathLookup::normalizePublicPath($url);
        if ($path === '') {
            return null;
        }

        if (array_key_exists($path, $this->pathMediaCache)) {
            return $this->pathMediaCache[$path];
        }

        $media = $this->pathLookup->findByPublicPath($url);
        if ($media !== null) {
            $this->cacheMedia($media);
            foreach ([
                $media->path,
                $media->path_large,
                $media->path_medium,
                $media->path_mini,
                $media->path_listing_medium,
                $media->path_listing_mini,
            ] as $variantPath) {
                if ($variantPath !== null && $variantPath !== '') {
                    $this->pathMediaCache[ltrim($variantPath, '/')] = $media;
                }
            }
        }

        return $media;
    }

    private function cacheMedia(MediaFile $file): void
    {
        $id = (int)$file->id;
        $payload = $this->buildPayloadForMedia($file);
        $this->imagePayloadCache[$id] = $payload;
        $this->urlCache[$id] = (string)($payload['src'] ?? $file->getPublicUrl($this->defaultSrcVariant));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayloadForMedia(MediaFile $file, ?string $alt = null, ?string $defaultSrc = null): array
    {
        $payload = $file->toApiImagePayload($alt, $defaultSrc ?? $this->defaultSrcVariant);
        if ($alt !== null && trim($alt) !== '') {
            $payload['alt'] = trim($alt);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isImageNode(array $node): bool
    {
        return array_key_exists('src', $node)
            && is_scalar($node['src'])
            && !array_key_exists('srcSet', $node);
    }

    private function resolveNode(mixed $node): mixed
    {
        if (!is_array($node)) {
            return $node;
        }

        if ($this->isImageNode($node)) {
            $payload = $this->resolveImagePayload(
                (string)$node['src'],
                isset($node['alt']) ? (string)$node['alt'] : null
            );

            return $payload ?? $node;
        }

        $result = [];
        foreach ($node as $key => $value) {
            $result[$key] = $this->resolveNode($value);
        }

        return $result;
    }
}
