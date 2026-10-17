<?php

namespace app\services\import\fabric;

/**
 * Кэш распарсенных строк Excel рядом с файлом импорта (для продолжения async-воркеров без повторного PhpSpreadsheet).
 */
class FabricRegistrySpreadsheetRowCache
{
    public static function cachePathFor(string $spreadsheetPath): string
    {
        return $spreadsheetPath . '.fabric-rows.json';
    }

    /**
     * @return FabricRegistryRowDto[]|null
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
     * @param FabricRegistryRowDto[] $rows
     */
    public static function save(string $spreadsheetPath, array $rows): void
    {
        $payload = array_map(static fn (FabricRegistryRowDto $row): array => self::rowToArray($row), $rows);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Не удалось сериализовать строки реестра для кэша.');
        }

        $cachePath = self::cachePathFor($spreadsheetPath);
        if (file_put_contents($cachePath, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Не удалось записать кэш строк реестра.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function rowToArray(FabricRegistryRowDto $row): array
    {
        return [
            'rowNumber' => $row->rowNumber,
            'materialKind' => $row->materialKind,
            'collectionName' => $row->collectionName,
            'colorName' => $row->colorName,
            'composition' => $row->composition,
            'priceCategoryLabelA' => $row->priceCategoryLabelA,
            'priceCategoryLabelLine1' => $row->priceCategoryLabelLine1,
            'texture' => $row->texture,
            'colorLabel' => $row->colorLabel,
            'martindale' => $row->martindale,
            'properties' => $row->properties,
            'rollWidthCm' => $row->rollWidthCm,
            'densityGsm' => $row->densityGsm,
            'textureUrl' => $row->textureUrl,
            'isRecommendedFabric' => $row->isRecommendedFabric,
            'positionNumber' => $row->positionNumber,
            'description' => $row->description,
            'importComment' => $row->importComment,
        ];
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function rowFromArray(array $item): ?FabricRegistryRowDto
    {
        if (!isset($item['rowNumber'], $item['materialKind'], $item['collectionName'], $item['colorName'])) {
            return null;
        }

        return new FabricRegistryRowDto(
            (int)$item['rowNumber'],
            (string)$item['materialKind'],
            (string)$item['collectionName'],
            (string)$item['colorName'],
            isset($item['composition']) ? (string)$item['composition'] : null,
            isset($item['priceCategoryLabelA']) ? (string)$item['priceCategoryLabelA'] : null,
            isset($item['priceCategoryLabelLine1']) ? (string)$item['priceCategoryLabelLine1'] : null,
            isset($item['texture']) ? (string)$item['texture'] : null,
            isset($item['colorLabel']) ? (string)$item['colorLabel'] : null,
            isset($item['martindale']) ? (int)$item['martindale'] : null,
            isset($item['properties']) ? (string)$item['properties'] : null,
            isset($item['rollWidthCm']) ? (int)$item['rollWidthCm'] : null,
            isset($item['densityGsm']) ? (int)$item['densityGsm'] : null,
            isset($item['textureUrl']) ? (string)$item['textureUrl'] : null,
            (bool)($item['isRecommendedFabric'] ?? false),
            isset($item['positionNumber']) ? (int)$item['positionNumber'] : null,
            isset($item['description']) ? (string)$item['description'] : null,
            isset($item['importComment']) ? (string)$item['importComment'] : null,
        );
    }
}
