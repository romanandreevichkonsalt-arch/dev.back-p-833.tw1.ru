<?php

namespace app\services\media;

use app\models\MediaFile;
use Yii;

/**
 * Переносит кадры каталога из path_medium/path_mini в отдельные listing-файлы и восстанавливает авто-варианты из оригинала.
 */
final class ListingTileLegacyMigrator
{
    public function __construct(
        private readonly LocalMediaStorage $storage = new LocalMediaStorage(),
        private readonly MediaVariantRegenerator $regenerator = new MediaVariantRegenerator(),
    ) {
    }

    public function migrateLockedMedia(MediaFile $media): MediaFile
    {
        if (!$media->isListingFrameLocked()) {
            return $media;
        }

        [$listingMedium, $listingMini] = $this->ensureListingPathsOnDisk($media);
        $media->path_listing_medium = $listingMedium;
        $media->path_listing_mini = $listingMini;
        if (!$media->save(false, ['path_listing_medium', 'path_listing_mini'])) {
            throw new \RuntimeException('Не удалось сохранить пути кадра каталога.');
        }

        return $this->regenerator->regenerateStandardVariants($media);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function ensureListingPathsOnDisk(MediaFile $media): array
    {
        [$targetMedium, $targetMini] = $this->canonicalListingRelativePaths($media);

        $existingMedium = trim((string)($media->path_listing_medium ?: $media->path_medium));
        $existingMini = trim((string)($media->path_listing_mini ?: $media->path_mini));

        $finalMedium = $this->relocateVariantFile($existingMedium, $targetMedium);
        $finalMini = $this->relocateVariantFile($existingMini, $targetMini);

        return [$finalMedium, $finalMini];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function canonicalListingRelativePaths(MediaFile $media): array
    {
        $subdir = trim(str_replace(
            Yii::$app->params['mediaPublicPrefix'] ?? 'uploads/media',
            '',
            dirname($media->path)
        ), '/');
        $basenameNoExt = pathinfo(basename($media->path), PATHINFO_FILENAME);
        $prefix = Yii::$app->params['mediaPublicPrefix'] ?? 'uploads/media';
        $base = $prefix . ($subdir !== '' ? '/' . $subdir : '');

        return [
            $base . '/' . $basenameNoExt . '_listing_m.webp',
            $base . '/' . $basenameNoExt . '_listing_s.webp',
        ];
    }

    private function relocateVariantFile(string $currentRelative, string $targetRelative): string
    {
        $currentRelative = ltrim($currentRelative, '/');
        $targetRelative = ltrim($targetRelative, '/');
        if ($currentRelative === '') {
            return $targetRelative;
        }

        if ($currentRelative === $targetRelative) {
            return $targetRelative;
        }

        $currentFull = $this->storage->resolveFullPath($currentRelative);
        $targetFull = $this->storage->resolveFullPath($targetRelative);
        if (!is_file($currentFull)) {
            return $targetRelative;
        }

        $dir = dirname($targetFull);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Не удалось создать каталог для кадра каталога.');
        }

        if (is_file($targetFull)) {
            @unlink($targetFull);
        }

        if (!rename($currentFull, $targetFull)) {
            if (!copy($currentFull, $targetFull)) {
                throw new \RuntimeException('Не удалось перенести файл кадра каталога.');
            }
            @unlink($currentFull);
        }

        return $targetRelative;
    }
}
