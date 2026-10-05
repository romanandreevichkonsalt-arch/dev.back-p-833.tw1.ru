<?php

namespace app\services\media;

use app\models\MediaFile;
use Yii;

final class ListingTileService
{
    private readonly ListingTileConfig $config;
    private readonly ListingTileRenderer $renderer;

    public function __construct(
        private readonly LocalMediaStorage $storage = new LocalMediaStorage(),
        ?ListingTileConfig $config = null,
    ) {
        $this->config = $config ?? ListingTileConfig::fromParams();
        $this->renderer = new ListingTileRenderer($this->config);
    }

    /**
     * @throws \RuntimeException
     */
    public function saveFrame(MediaFile $media, ListingFrameData $frame): MediaFile
    {
        if (!$media->isImage()) {
            throw new \RuntimeException('Кадр каталога доступен только для изображений.');
        }

        $originalPath = $this->storage->resolveFullPath($media->path);
        if (!is_file($originalPath)) {
            throw new \RuntimeException('Оригинал не найден на диске.');
        }

        $mediumPath = $this->resolveVariantFullPath($media, 'medium');
        $miniPath = $this->resolveVariantFullPath($media, 'mini');

        $this->renderer->renderFile($originalPath, $frame, $mediumPath);

        $miniWidth = $this->config->miniMaxWidth;
        $miniHeight = $this->config->miniHeightForWidth($miniWidth);
        $miniCanvas = $this->renderer->renderCanvas($originalPath, $frame, $miniWidth, $miniHeight);
        $this->saveCanvasToPath($miniCanvas, $miniPath);
        imagedestroy($miniCanvas);

        $media->listing_frame_json = $frame->encodeJson();
        $media->listing_frame_locked = true;
        $media->path_medium = $this->toRelativePath($mediumPath);
        $media->path_mini = $this->toRelativePath($miniPath);
        if (!$media->save(false, ['listing_frame_json', 'listing_frame_locked', 'path_medium', 'path_mini'])) {
            throw new \RuntimeException('Не удалось сохранить параметры кадра.');
        }

        return $media;
    }

    /**
     * @throws \RuntimeException
     */
    public function reapplyLockedFrame(MediaFile $media): MediaFile
    {
        $frame = ListingFrameData::fromArray($this->decodeFrameJson($media->listing_frame_json));
        if (!$media->listing_frame_locked || $frame === null) {
            throw new \RuntimeException('Для файла не сохранён кадр каталога.');
        }

        return $this->saveFrame($media, $frame);
    }

    public function clearLockedFrame(MediaFile $media): void
    {
        $media->listing_frame_json = null;
        $media->listing_frame_locked = false;
        $media->save(false, ['listing_frame_json', 'listing_frame_locked']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decodeFrameJson(?string $json): ?array
    {
        if ($json === null || trim($json) === '') {
            return null;
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    public function getConfig(): ListingTileConfig
    {
        return $this->config;
    }

    private function resolveVariantFullPath(MediaFile $media, string $variant): string
    {
        $relative = match ($variant) {
            'medium' => $media->path_medium,
            'mini' => $media->path_mini,
            default => null,
        };

        if ($relative !== null && $relative !== '') {
            return $this->storage->resolveFullPath($relative);
        }

        $basenameNoExt = pathinfo(basename($media->path), PATHINFO_FILENAME);
        $suffix = $variant === 'mini' ? '_s' : '_m';
        $subdir = trim(str_replace(
            Yii::$app->params['mediaPublicPrefix'] ?? 'uploads/media',
            '',
            dirname($media->path)
        ), '/');
        $filename = $basenameNoExt . $suffix . '.webp';
        $relativePath = (Yii::$app->params['mediaPublicPrefix'] ?? 'uploads/media')
            . ($subdir !== '' ? '/' . $subdir : '')
            . '/' . $filename;

        return $this->storage->resolveFullPath($relativePath);
    }

    private function toRelativePath(string $fullPath): string
    {
        $webroot = rtrim(Yii::getAlias('@webroot'), '/');

        return ltrim(str_replace($webroot, '', $fullPath), '/');
    }

    private function saveCanvasToPath(\GdImage $canvas, string $targetPath): void
    {
        $dir = dirname($targetPath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Не удалось создать каталог для файла.');
        }

        $extension = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
        if ($extension === 'jpg' || $extension === 'jpeg') {
            if (!imagejpeg($canvas, $targetPath, 90)) {
                throw new \RuntimeException('Не удалось сохранить mini-вариант.');
            }

            return;
        }

        if (!function_exists('imagewebp') || !imagewebp($canvas, $targetPath, $this->config->webpQuality)) {
            throw new \RuntimeException('Не удалось сохранить mini-вариант.');
        }
    }
}
