<?php

namespace app\services\media;

use app\models\MediaFile;
use Yii;

class MediaVariantRegenerator
{
    public function __construct(
        private readonly LocalMediaStorage $storage = new LocalMediaStorage(),
        private readonly ImageVariantGenerator $generator = new ImageVariantGenerator(),
    ) {
    }

    /**
     * @return array{processed:int,regenerated:int,skipped:int,failed:int,messages:string[]}
     */
    public function regenerateMissing(bool $dryRun = false): array
    {
        $result = [
            'processed' => 0,
            'regenerated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'messages' => [],
        ];

        $query = MediaFile::find()
            ->where(['kind' => MediaFile::KIND_IMAGE])
            ->orderBy(['id' => SORT_ASC]);

        foreach ($query->each() as $media) {
            /** @var MediaFile $media */
            $result['processed']++;

            if (!MediaContentValidator::isReadable($media, 'original')) {
                $result['failed']++;
                $result['messages'][] = sprintf('#%d %s: оригинал недоступен.', $media->id, $media->filename);
                continue;
            }

            $needsLarge = !MediaContentValidator::isVariantFileReadable($media, 'large');
            $needsMini = !MediaContentValidator::isVariantFileReadable($media, 'mini');
            $needsMedium = !MediaContentValidator::isVariantFileReadable($media, 'medium');
            if (!$needsLarge && !$needsMini && !$needsMedium) {
                $result['skipped']++;
                continue;
            }

            if ($dryRun) {
                $result['regenerated']++;
                $result['messages'][] = sprintf('#%d %s: требуется пересборка вариантов.', $media->id, $media->filename);
                continue;
            }

            try {
                $this->regenerateForMedia($media);
                $result['regenerated']++;
                $result['messages'][] = sprintf('#%d %s: варианты пересобраны.', $media->id, $media->filename);
            } catch (\Throwable $exception) {
                $result['failed']++;
                $result['messages'][] = sprintf('#%d %s: %s', $media->id, $media->filename, $exception->getMessage());
            }
        }

        return $result;
    }

    public function regenerateForMedia(MediaFile $media): MediaFile
    {
        if (!$media->isImage()) {
            throw new \RuntimeException('Пересборка вариантов доступна только для изображений.');
        }

        $media = $this->regenerateStandardVariants($media);

        if ($media->isListingFrameLocked()) {
            return (new ListingTileService($this->storage))->reapplyLockedFrame($media);
        }

        return $media;
    }

    public function regenerateStandardVariants(MediaFile $media): MediaFile
    {
        if (!$media->isImage()) {
            throw new \RuntimeException('Пересборка вариантов доступна только для изображений.');
        }

        $fullPath = $this->storage->resolveFullPath($media->path);
        if (!is_file($fullPath)) {
            throw new \RuntimeException('Оригинал не найден на диске.');
        }

        $this->unlinkPaths([$media->path_large, $media->path_medium, $media->path_mini]);

        $basenameNoExt = pathinfo(basename($media->path), PATHINFO_FILENAME);
        $variants = $this->generator->generate($fullPath, $basenameNoExt);
        $subdir = trim(str_replace(
            Yii::$app->params['mediaPublicPrefix'] ?? 'uploads/media',
            '',
            dirname($media->path)
        ), '/');

        $pathLarge = null;
        $pathMedium = null;
        $pathMini = null;
        if ($variants['large'] !== null) {
            $pathLarge = $this->buildRelativePath($subdir, basename($variants['large']));
        }
        if ($variants['medium'] !== null) {
            $pathMedium = $this->buildRelativePath($subdir, basename($variants['medium']));
        }
        if ($variants['mini'] !== null) {
            $pathMini = $this->buildRelativePath($subdir, basename($variants['mini']));
        }

        $media->path_large = $pathLarge;
        $media->path_medium = $pathMedium;
        $media->path_mini = $pathMini;
        if (!$media->save()) {
            throw new \RuntimeException('Не удалось обновить запись медиафайла.');
        }

        return $media;
    }

    /**
     * @param array<int, string|null> $paths
     */
    private function unlinkPaths(array $paths): void
    {
        foreach ($paths as $relativePath) {
            if ($relativePath === null || $relativePath === '') {
                continue;
            }
            $fullPath = $this->storage->resolveFullPath($relativePath);
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
    }

    private function buildRelativePath(string $subdir, string $basename): string
    {
        $prefix = Yii::$app->params['mediaPublicPrefix'] ?? 'uploads/media';

        return $prefix . '/' . $subdir . '/' . $basename;
    }
}
