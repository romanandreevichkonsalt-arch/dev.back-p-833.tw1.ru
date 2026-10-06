<?php

namespace app\services\catalog;

use app\models\CatalogFabricCollection;
use app\models\CatalogFabricColor;
use app\services\import\fabric\FabricDesignCodeNormalizer;
use app\services\media\MediaContentValidator;
use ZipArchive;

class FabricLibraryPhotoZipBuilder
{
    /**
     * @param list<CatalogFabricCollection> $collections
     * @return array{fileCount: int, skipped: int}
     */
    public function buildZip(string $targetPath, array $collections): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('На сервере не включено расширение PHP zip.');
        }

        $zip = new ZipArchive();
        if ($zip->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Не удалось создать ZIP-архив.');
        }

        $fileCount = 0;
        $skipped = 0;
        $usedPaths = [];

        foreach ($collections as $collection) {
            if (!$collection instanceof CatalogFabricCollection) {
                continue;
            }

            $textureDir = $this->sanitizePathSegment(
                trim((string)($collection->texture ?? '')),
                'Без фактуры'
            );
            $collectionDir = $this->sanitizePathSegment(
                trim((string)$collection->name),
                'collection-' . (int)$collection->id
            );

            foreach ($collection->activeColors as $color) {
                if (!$color instanceof CatalogFabricColor) {
                    continue;
                }

                $media = $color->swatchMedia;
                if ($media === null) {
                    $skipped++;
                    continue;
                }

                $variant = MediaContentValidator::resolveReadableVariant($media, 'original');
                if ($variant === null) {
                    $skipped++;
                    continue;
                }

                $sourcePath = MediaContentValidator::absolutePath($media, $variant);
                if (!is_file($sourcePath) || filesize($sourcePath) === 0) {
                    $skipped++;
                    continue;
                }

                $designCode = FabricDesignCodeNormalizer::fromRegistry((string)$color->design_code);
                $baseName = $this->sanitizePathSegment($designCode, 'color-' . (int)$color->id);
                $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
                if ($extension === '') {
                    $extension = 'jpg';
                }

                $zipPath = $textureDir . '/' . $collectionDir . '/' . $baseName . '.' . $extension;
                $zipPath = $this->ensureUniqueZipPath($zipPath, $usedPaths);
                $usedPaths[$zipPath] = true;

                if (!$zip->addFile($sourcePath, $zipPath)) {
                    $skipped++;
                    continue;
                }

                $fileCount++;
            }
        }

        if (!$zip->close()) {
            throw new \RuntimeException('Не удалось сохранить ZIP-архив.');
        }

        if ($fileCount === 0) {
            throw new \RuntimeException('В архив не попало ни одного фото образца. Загрузите фото к цветам тканей.');
        }

        return [
            'fileCount' => $fileCount,
            'skipped' => $skipped,
        ];
    }

    private function sanitizePathSegment(string $value, string $fallback): string
    {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }

        $value = preg_replace('/[\\\\\\/:*?"<>|]+/u', '-', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value, " \t\n\r\0\x0B.-");

        return $value !== '' ? $value : $fallback;
    }

    /**
     * @param array<string, bool> $usedPaths
     */
    private function ensureUniqueZipPath(string $zipPath, array &$usedPaths): string
    {
        if (!isset($usedPaths[$zipPath])) {
            return $zipPath;
        }

        $directory = dirname($zipPath);
        $filename = pathinfo($zipPath, PATHINFO_FILENAME);
        $extension = pathinfo($zipPath, PATHINFO_EXTENSION);
        $suffix = 2;

        do {
            $candidate = $directory . '/' . $filename . '-' . $suffix;
            if ($extension !== '') {
                $candidate .= '.' . $extension;
            }
            $suffix++;
        } while (isset($usedPaths[$candidate]));

        return $candidate;
    }
}
