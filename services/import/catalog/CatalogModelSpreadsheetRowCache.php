<?php

namespace app\services\import\catalog;

/**
 * Кэш распарсенных строк прайса рядом с файлом (resume async-воркера без повторного PhpSpreadsheet).
 */
final class CatalogModelSpreadsheetRowCache
{
    public static function cachePathFor(string $spreadsheetPath): string
    {
        return $spreadsheetPath . '.model-rows.json';
    }

    /**
     * @return CatalogModelImportRowDto[]|null
     */
    public static function loadIfFresh(string $spreadsheetPath): ?array
    {
        $cachePath = self::cachePathFor($spreadsheetPath);
        if (!is_file($cachePath) || !is_file($spreadsheetPath)) {
            return null;
        }

        if (filemtime($cachePath) < filemtime($spreadsheetPath)) {
            return null;
        }

        $json = file_get_contents($cachePath);
        if (!is_string($json) || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }

        $rows = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                return null;
            }
            $row = self::rowFromArray($item);
            if ($row === null) {
                return null;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param CatalogModelImportRowDto[] $rows
     */
    public static function save(string $spreadsheetPath, array $rows): void
    {
        $payload = array_map(static fn (CatalogModelImportRowDto $row): array => self::rowToArray($row), $rows);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Не удалось сериализовать строки прайса для кэша.');
        }

        $cachePath = self::cachePathFor($spreadsheetPath);
        if (file_put_contents($cachePath, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Не удалось записать кэш строк прайса.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function rowToArray(CatalogModelImportRowDto $row): array
    {
        return [
            'rowNumber' => $row->rowNumber,
            'directionLabel' => $row->directionLabel,
            'collectionName' => $row->collectionName,
            'displayLabel' => $row->displayLabel,
            'productTitlePart' => $row->productTitlePart,
            'categoryLabel' => $row->categoryLabel,
            'subcategoryLabel' => $row->subcategoryLabel,
            'isModule' => $row->isModule,
            'pricesByCategoryNumber' => $row->pricesByCategoryNumber,
            'fabricCollectionNames' => $row->fabricCollectionNames,
            'fittingRoomUrl' => $row->fittingRoomUrl,
            'polygons3d' => $row->polygons3d,
            'file3dUrl' => $row->file3dUrl,
            'videoUrl' => $row->videoUrl,
            'galleryPhotoUrls' => $row->galleryPhotoUrls,
            'dimensionPhotoUrls' => $row->dimensionPhotoUrls,
            'dimensionPhotoFolderUrl' => $row->dimensionPhotoFolderUrl,
            'subtitle' => $row->subtitle,
            'description' => $row->description,
            'overallSize' => $row->overallSize,
            'seatDepth' => $row->seatDepth,
            'seatHeight' => $row->seatHeight,
            'armrestWidth' => $row->armrestWidth,
            'legHeight' => $row->legHeight,
            'frameSpec' => $row->frameSpec,
            'mechanism' => $row->mechanism,
            'fillingSpec' => $row->fillingSpec,
            'additional' => $row->additional,
            'frame' => $row->frame,
            'foundation' => $row->foundation,
            'filling' => $row->filling,
            'upholstery' => $row->upholstery,
            'supports' => $row->supports,
            'filterFunction' => $row->filterFunction,
            'sleepingPlaceSize' => $row->sleepingPlaceSize,
        ];
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function rowFromArray(array $item): ?CatalogModelImportRowDto
    {
        if (!isset($item['rowNumber'], $item['collectionName'], $item['subcategoryLabel'])) {
            return null;
        }

        return new CatalogModelImportRowDto(
            (int)$item['rowNumber'],
            (string)($item['directionLabel'] ?? ''),
            (string)$item['collectionName'],
            (string)($item['displayLabel'] ?? ''),
            (string)($item['productTitlePart'] ?? ''),
            (string)($item['categoryLabel'] ?? ''),
            (string)$item['subcategoryLabel'],
            (bool)($item['isModule'] ?? false),
            is_array($item['pricesByCategoryNumber'] ?? null) ? $item['pricesByCategoryNumber'] : [],
            is_array($item['fabricCollectionNames'] ?? null) ? $item['fabricCollectionNames'] : [],
            isset($item['fittingRoomUrl']) ? (string)$item['fittingRoomUrl'] : null,
            isset($item['polygons3d']) ? (string)$item['polygons3d'] : null,
            isset($item['file3dUrl']) ? (string)$item['file3dUrl'] : null,
            isset($item['videoUrl']) ? (string)$item['videoUrl'] : null,
            is_array($item['galleryPhotoUrls'] ?? null) ? $item['galleryPhotoUrls'] : [],
            is_array($item['dimensionPhotoUrls'] ?? null) ? $item['dimensionPhotoUrls'] : [],
            isset($item['dimensionPhotoFolderUrl']) ? (string)$item['dimensionPhotoFolderUrl'] : null,
            isset($item['subtitle']) ? (string)$item['subtitle'] : null,
            isset($item['description']) ? (string)$item['description'] : null,
            isset($item['overallSize']) ? (string)$item['overallSize'] : null,
            isset($item['seatDepth']) ? (string)$item['seatDepth'] : null,
            isset($item['seatHeight']) ? (string)$item['seatHeight'] : null,
            isset($item['armrestWidth']) ? (string)$item['armrestWidth'] : null,
            isset($item['legHeight']) ? (string)$item['legHeight'] : null,
            isset($item['frameSpec']) ? (string)$item['frameSpec'] : null,
            isset($item['mechanism']) ? (string)$item['mechanism'] : null,
            isset($item['fillingSpec']) ? (string)$item['fillingSpec'] : null,
            isset($item['additional']) ? (string)$item['additional'] : null,
            isset($item['frame']) ? (string)$item['frame'] : null,
            isset($item['foundation']) ? (string)$item['foundation'] : null,
            isset($item['filling']) ? (string)$item['filling'] : null,
            isset($item['upholstery']) ? (string)$item['upholstery'] : null,
            isset($item['supports']) ? (string)$item['supports'] : null,
            (string)($item['filterFunction'] ?? \app\services\catalog\CatalogFilterFunction::NONE),
            isset($item['sleepingPlaceSize']) ? (string)$item['sleepingPlaceSize'] : null,
        );
    }
}
