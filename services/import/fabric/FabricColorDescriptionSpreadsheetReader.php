<?php

namespace app\services\import\fabric;

use app\services\import\SimpleXlsxSheetReader;

class FabricColorDescriptionSpreadsheetReader
{
    private const HEADER_FABRIC_NAME = 'название ткани';
    private const HEADER_DESIGN_CODE = 'нумерация оттенка ткани';
    private const HEADER_DESCRIPTION = 'название цвета и описание';
    private const HEADER_DESCRIPTION_ALT = 'описание цветодизайна';

    public function __construct(
        private readonly SimpleXlsxSheetReader $sheetReader = new SimpleXlsxSheetReader(),
    ) {
    }

    /**
     * @return FabricColorDescriptionRowDto[]
     */
    public function read(string $filePath): array
    {
        $grid = $this->sheetReader->readFirstSheetGrid($filePath);

        $headerRow = $this->findHeaderRow($grid);
        if ($headerRow === null) {
            throw new \InvalidArgumentException(
                'Не найдена строка заголовков с колонками «Название ткани», «Нумерация оттенка ткани» и «Название цвета и описание».'
            );
        }

        $columns = $this->resolveColumns($grid, $headerRow);
        $rows = [];
        $lastFabricName = '';
        $highestRow = $grid === [] ? 0 : max(array_keys($grid));

        for ($rowNumber = $headerRow + 1; $rowNumber <= $highestRow; $rowNumber++) {
            $fabricName = $this->cellValue($grid, $columns['fabricName'], $rowNumber);
            if ($fabricName !== '') {
                $lastFabricName = $fabricName;
            }

            $designCode = FabricDesignCodeNormalizer::fromRegistry(
                $this->cellValue($grid, $columns['designCode'], $rowNumber)
            );
            $description = $this->normalizeDescription(
                $this->cellValue($grid, $columns['description'], $rowNumber)
            );

            if ($this->isHeaderLikeDataRow($lastFabricName, $designCode, $description)) {
                continue;
            }

            if ($designCode === '' && $description === '') {
                continue;
            }

            if ($lastFabricName === '' || $designCode === '') {
                continue;
            }

            if ($description === '') {
                continue;
            }

            $rows[] = new FabricColorDescriptionRowDto(
                rowNumber: $rowNumber,
                fabricCollectionName: $lastFabricName,
                designCode: $designCode,
                description: $description,
            );
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, string>> $grid
     */
    private function findHeaderRow(array $grid): ?int
    {
        $headerRow = null;
        $maxRow = min($grid === [] ? 0 : max(array_keys($grid)), 10);
        for ($rowNumber = 1; $rowNumber <= $maxRow; $rowNumber++) {
            $headers = $this->readRowHeaders($grid, $rowNumber);
            if ($this->hasRequiredHeaders($headers)) {
                // В клиентском файле часто две строки шапки подряд — берём нижнюю.
                $headerRow = $rowNumber;
            }
        }

        return $headerRow;
    }

    /**
     * @param array<int, array<string, string>> $grid
     * @return array<string, string> normalized header => column letter
     */
    private function readRowHeaders(array $grid, int $rowNumber): array
    {
        $headers = [];
        foreach ($grid[$rowNumber] ?? [] as $columnLetter => $value) {
            $header = mb_strtolower(trim($value));
            if ($header !== '') {
                $headers[$header] = $columnLetter;
            }
        }

        return $headers;
    }

    /**
     * @param array<string, string> $headers
     */
    private function hasRequiredHeaders(array $headers): bool
    {
        $hasFabric = false;
        $hasDesignCode = false;
        $hasDescription = false;

        foreach (array_keys($headers) as $header) {
            if (str_contains($header, self::HEADER_FABRIC_NAME)) {
                $hasFabric = true;
            }
            if (str_contains($header, self::HEADER_DESIGN_CODE)) {
                $hasDesignCode = true;
            }
            if (
                str_contains($header, self::HEADER_DESCRIPTION)
                || str_contains($header, self::HEADER_DESCRIPTION_ALT)
            ) {
                $hasDescription = true;
            }
        }

        return $hasFabric && $hasDesignCode && $hasDescription;
    }

    /**
     * @param array<int, array<string, string>> $grid
     * @return array{fabricName: string, designCode: string, description: string}
     */
    private function resolveColumns(array $grid, int $headerRow): array
    {
        $headers = $this->readRowHeaders($grid, $headerRow);
        $fabricName = '';
        $designCode = '';
        $description = '';

        foreach ($headers as $header => $columnLetter) {
            if ($fabricName === '' && str_contains($header, self::HEADER_FABRIC_NAME)) {
                $fabricName = $columnLetter;
            }
            if ($designCode === '' && str_contains($header, self::HEADER_DESIGN_CODE)) {
                $designCode = $columnLetter;
            }
            if (
                $description === ''
                && (
                    str_contains($header, self::HEADER_DESCRIPTION)
                    || str_contains($header, self::HEADER_DESCRIPTION_ALT)
                )
            ) {
                $description = $columnLetter;
            }
        }

        if ($fabricName === '' || $designCode === '' || $description === '') {
            throw new \InvalidArgumentException('Не удалось определить колонки файла описаний цветодизайнов.');
        }

        return [
            'fabricName' => $fabricName,
            'designCode' => $designCode,
            'description' => $description,
        ];
    }

    /**
     * @param array<int, array<string, string>> $grid
     */
    private function cellValue(array $grid, string $column, int $rowNumber): string
    {
        return trim((string)($grid[$rowNumber][$column] ?? ''));
    }

    private function normalizeDescription(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", trim($value));
        $value = preg_replace("/\n{3,}/", "\n\n", $value) ?? $value;

        return trim($value);
    }

    private function isHeaderLikeDataRow(string $fabricName, string $designCode, string $description): bool
    {
        $fabricName = mb_strtolower(trim($fabricName));
        $designCode = mb_strtolower(trim($designCode));
        $description = mb_strtolower(trim($description));

        if ($fabricName === self::HEADER_FABRIC_NAME) {
            return true;
        }

        if ($designCode === self::HEADER_DESIGN_CODE) {
            return true;
        }

        return $description === self::HEADER_DESCRIPTION
            || $description === self::HEADER_DESCRIPTION_ALT;
    }
}
