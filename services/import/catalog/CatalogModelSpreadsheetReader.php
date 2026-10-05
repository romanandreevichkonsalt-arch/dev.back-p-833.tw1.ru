<?php

namespace app\services\import\catalog;

use app\services\catalog\CatalogFilterFunction;
use app\services\catalog\CatalogListingValueParser;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CatalogModelSpreadsheetReader
{
    /** @var array<string, string> */
    private const FIELD_HEADERS = [
        'направление' => 'direction',
        'коллекция' => 'collection',
        'категория' => 'category',
        'подкатегория' => 'subcategory',
        'подзаголовок' => 'subtitle',
        'описание' => 'description',
        'размер (шxвxг)' => 'overall_size',
        'глубина посадочного места' => 'seat_depth',
        'высота посадочного места' => 'seat_height',
        'ширина подлокотника' => 'armrest_width',
        'высота опоры' => 'leg_height',
        'клиренс' => 'leg_height',
        'механизм' => 'mechanism',
        'дополнительно' => 'additional',
        'каркас (красивое)' => 'frame',
        'основание (красивое)' => 'foundation',
        'опоры (красивое)' => 'supports',
        'детали (красивое)' => 'supports',
        'наполнение (красивое)' => 'filling',
        'обивка (красивое)' => 'upholstery',
        'основание' => 'foundation',
        'обивка' => 'upholstery',
        'опоры' => 'supports',
        'ткань (выбор из списка)' => 'fabrics',
        'ткань' => 'fabrics',
        'ссылка на примерочную 3d' => 'fitting_room_url',
        'полигоны для 3д' => 'polygons_3d',
        'полигоны для 3d' => 'polygons_3d',
        'ссылка на файл 3д' => 'file_3d_url',
        'ссылка на файл 3d' => 'file_3d_url',
        'видео титульное (если нет то берется первое фото из интерьера)' => 'video_url',
        'видео титульное' => 'video_url',
        'фото ссылки по одной через «, »' => 'gallery_photos',
        'фото ссылки по одной через ", "' => 'gallery_photos',
        'фото ссылки' => 'gallery_photos',
        'тех.фото по одной через «, »' => 'dimension_photos',
        'тех.фото по одной через ", "' => 'dimension_photos',
        'тех.фото' => 'dimension_photos',
        'тех фото' => 'dimension_photos',
        'active' => 'import_active',
        'функция (для фильтра)' => 'filter_function',
        'размеры спального места (шxг), мм (если есть, иначе оставьте пустым)' => 'sleeping_place_size',
        'описание (красивое)' => 'description',
    ];

    public function __construct(
        private readonly PriceListRowClassifier $classifier = new PriceListRowClassifier(),
    ) {
    }

    public function isModelsSheet(Worksheet $sheet): bool
    {
        $headerRow = $this->findHeaderRow($sheet);
        if ($headerRow === null) {
            return false;
        }

        $columns = $this->mapColumns($sheet, $headerRow);

        return isset($columns['collection'], $columns['subcategory']);
    }

    /**
     * @return CatalogModelImportRowDto[]
     */
    public function readSheet(Worksheet $sheet): array
    {
        $headerRow = $this->findHeaderRow($sheet);
        if ($headerRow === null) {
            return [];
        }

        $columns = $this->mapColumns($sheet, $headerRow);
        if (!isset($columns['collection'], $columns['subcategory'])) {
            return [];
        }

        $rows = [];
        $highestRow = (int)$sheet->getHighestDataRow();
        for ($rowNumber = $headerRow + 1; $rowNumber <= $highestRow; $rowNumber++) {
            $dto = $this->readRow($sheet, $rowNumber, $columns);
            if ($dto !== null) {
                $rows[] = $dto;
            }
        }

        return $rows;
    }

    private function findHeaderRow(Worksheet $sheet): ?int
    {
        $highestRow = min(30, (int)$sheet->getHighestDataRow());
        for ($rowNumber = 1; $rowNumber <= $highestRow; $rowNumber++) {
            $columns = $this->mapColumns($sheet, $rowNumber);
            if (isset($columns['collection'], $columns['subcategory'])) {
                return $rowNumber;
            }
        }

        return null;
    }

    /**
     * @return array<string, string> field => column letter
     */
    private function mapColumns(Worksheet $sheet, int $rowNumber): array
    {
        $maxColIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn($rowNumber));
        $headersByColumn = [];

        for ($colIndex = 1; $colIndex <= $maxColIndex; $colIndex++) {
            $col = Coordinate::stringFromColumnIndex($colIndex);
            $headersByColumn[$col] = $this->cellValue($sheet, $col, $rowNumber);
        }

        $usesNewMaterialLayout = false;
        foreach ($headersByColumn as $rawHeader) {
            $header = $this->normalizeHeader($rawHeader);
            if (str_contains($header, '(красивое)')) {
                $usesNewMaterialLayout = true;
                break;
            }
        }

        $columns = [];
        foreach ($headersByColumn as $col => $rawHeader) {
            if (trim($rawHeader) === '') {
                continue;
            }

            if (CatalogModelImportPhotoParser::isCombinedGalleryTechHeader($rawHeader)) {
                $columns['gallery_photos'] = $col;
                $columns['dimension_photos'] = $col;
                continue;
            }

            $header = $this->normalizeHeader($rawHeader);
            if ($header === '') {
                continue;
            }

            $field = $this->resolveFieldForHeader($header, $usesNewMaterialLayout);
            if ($field !== null) {
                $columns[$field] = $col;
                continue;
            }

            if (preg_match('/^(\d+)\s*кат/u', $header, $matches)) {
                $columns['price_' . $matches[1]] = $col;
            }
        }

        return $columns;
    }

    private function resolveFieldForHeader(string $header, bool $usesNewMaterialLayout): ?string
    {
        if ($usesNewMaterialLayout && $header === 'описание') {
            return 'subtitle';
        }

        if (isset(self::FIELD_HEADERS[$header])) {
            return self::FIELD_HEADERS[$header];
        }

        if ($header === 'каркас') {
            return $usesNewMaterialLayout ? 'frame_spec' : 'frame';
        }

        if ($header === 'наполнение') {
            return $usesNewMaterialLayout ? 'filling_spec' : 'filling';
        }

        return null;
    }

    /**
     * @param array<string, string> $columns
     */
    private function readRow(Worksheet $sheet, int $rowNumber, array $columns): ?CatalogModelImportRowDto
    {
        if ($this->isImportInactive($sheet, $columns, $rowNumber)) {
            return null;
        }

        $collectionName = $this->cellValue($sheet, $columns['collection'], $rowNumber);
        $subcategoryLabel = $this->cellValue($sheet, $columns['subcategory'], $rowNumber);
        if ($collectionName === '' || $subcategoryLabel === '') {
            return null;
        }

        $categoryLabel = isset($columns['category'])
            ? trim($this->cellValue($sheet, $columns['category'], $rowNumber))
            : '';

        if ($this->isInvalidCategorySubcategoryPair($categoryLabel, $subcategoryLabel)) {
            return null;
        }

        $prices = $this->readPrices($sheet, $rowNumber, $columns);
        if ($prices === []) {
            return null;
        }

        $directionLabel = isset($columns['direction'])
            ? $this->cellValue($sheet, $columns['direction'], $rowNumber)
            : '';
        if ($directionLabel === '') {
            return null;
        }

        $rowValues = $this->readAttributeValues($sheet, $rowNumber, $columns);

        if ($categoryLabel !== '') {
            $displayLabel = $this->buildModelDisplayLabel($subcategoryLabel, $collectionName);
            $isModule = $categoryLabel === PriceListRowClassifier::CATEGORY_MODULES;
            $productTitlePart = $subcategoryLabel;

            return $this->buildRowDto(
                rowNumber: $rowNumber,
                directionLabel: $directionLabel,
                collectionName: $collectionName,
                displayLabel: $displayLabel,
                productTitlePart: $productTitlePart,
                categoryLabel: $categoryLabel,
                subcategoryLabel: $subcategoryLabel,
                isModule: $isModule,
                pricesByCategoryNumber: $prices,
                rowValues: $rowValues,
            );
        }

        $classified = $this->classifier->classifyModelRow($collectionName, $rowNumber, $subcategoryLabel, $prices);
        if ($classified === null) {
            return null;
        }

        return $this->buildRowDto(
            rowNumber: $classified->rowNumber,
            directionLabel: $directionLabel,
            collectionName: $classified->collectionName,
            displayLabel: $this->buildModelDisplayLabel($subcategoryLabel, $collectionName),
            productTitlePart: $classified->productTitlePart,
            categoryLabel: $classified->categoryLabel,
            subcategoryLabel: $classified->subcategoryLabel,
            isModule: $classified->isModule,
            pricesByCategoryNumber: $classified->pricesByCategoryNumber,
            rowValues: $rowValues,
        );
    }

    /**
     * @param array<string, mixed> $rowValues
     */
    private function buildRowDto(
        int $rowNumber,
        string $directionLabel,
        string $collectionName,
        string $displayLabel,
        string $productTitlePart,
        string $categoryLabel,
        string $subcategoryLabel,
        bool $isModule,
        array $pricesByCategoryNumber,
        array $rowValues,
    ): CatalogModelImportRowDto {
        return new CatalogModelImportRowDto(
            rowNumber: $rowNumber,
            directionLabel: $directionLabel,
            collectionName: $collectionName,
            displayLabel: $displayLabel,
            productTitlePart: $productTitlePart,
            categoryLabel: $categoryLabel,
            subcategoryLabel: $subcategoryLabel,
            isModule: $isModule,
            pricesByCategoryNumber: $pricesByCategoryNumber,
            fabricCollectionNames: $rowValues['fabricCollectionNames'],
            fittingRoomUrl: $rowValues['fittingRoomUrl'],
            polygons3d: $rowValues['polygons3d'],
            file3dUrl: $rowValues['file3dUrl'],
            videoUrl: $rowValues['videoUrl'],
            galleryPhotoUrls: $rowValues['galleryPhotoUrls'],
            dimensionPhotoUrls: $rowValues['dimensionPhotoUrls'],
            dimensionPhotoFolderUrl: $rowValues['dimensionPhotoFolderUrl'],
            subtitle: $rowValues['subtitle'],
            description: $rowValues['description'],
            overallSize: $rowValues['overallSize'],
            seatDepth: $rowValues['seatDepth'],
            seatHeight: $rowValues['seatHeight'],
            armrestWidth: $rowValues['armrestWidth'],
            legHeight: $rowValues['legHeight'],
            frameSpec: $rowValues['frameSpec'],
            mechanism: $rowValues['mechanism'],
            fillingSpec: $rowValues['fillingSpec'],
            additional: $rowValues['additional'],
            frame: $rowValues['frame'],
            foundation: $rowValues['foundation'],
            filling: $rowValues['filling'],
            upholstery: $rowValues['upholstery'],
            supports: $rowValues['supports'],
            filterFunction: $rowValues['filterFunction'],
            sleepingPlaceSize: $rowValues['sleepingPlaceSize'],
        );
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, mixed>
     */
    private function readAttributeValues(Worksheet $sheet, int $rowNumber, array $columns): array
    {
        return [
            'fabricCollectionNames' => $this->readListValue($sheet, $columns, 'fabrics', $rowNumber),
            'fittingRoomUrl' => $this->optionalValue($sheet, $columns, 'fitting_room_url', $rowNumber),
            'polygons3d' => $this->optionalValue($sheet, $columns, 'polygons_3d', $rowNumber),
            'file3dUrl' => $this->optionalValue($sheet, $columns, 'file_3d_url', $rowNumber),
            'videoUrl' => $this->optionalValue($sheet, $columns, 'video_url', $rowNumber),
            ...$this->readPhotoFieldValues($sheet, $columns, $rowNumber),
            'subtitle' => $this->optionalValue($sheet, $columns, 'subtitle', $rowNumber),
            'description' => $this->optionalValue($sheet, $columns, 'description', $rowNumber),
            'overallSize' => $this->optionalValue($sheet, $columns, 'overall_size', $rowNumber),
            'seatDepth' => $this->optionalValue($sheet, $columns, 'seat_depth', $rowNumber),
            'seatHeight' => $this->optionalValue($sheet, $columns, 'seat_height', $rowNumber),
            'armrestWidth' => $this->optionalValue($sheet, $columns, 'armrest_width', $rowNumber),
            'legHeight' => $this->optionalValue($sheet, $columns, 'leg_height', $rowNumber),
            'frameSpec' => $this->optionalValue($sheet, $columns, 'frame_spec', $rowNumber),
            'mechanism' => $this->optionalValue($sheet, $columns, 'mechanism', $rowNumber),
            'fillingSpec' => $this->optionalValue($sheet, $columns, 'filling_spec', $rowNumber),
            'additional' => $this->optionalValue($sheet, $columns, 'additional', $rowNumber),
            'frame' => $this->optionalValue($sheet, $columns, 'frame', $rowNumber),
            'foundation' => $this->optionalValue($sheet, $columns, 'foundation', $rowNumber),
            'filling' => $this->optionalValue($sheet, $columns, 'filling', $rowNumber),
            'upholstery' => $this->optionalValue($sheet, $columns, 'upholstery', $rowNumber),
            'supports' => $this->optionalValue($sheet, $columns, 'supports', $rowNumber),
            'filterFunction' => $this->readFilterFunction($sheet, $columns, $rowNumber),
            'sleepingPlaceSize' => $this->optionalValue($sheet, $columns, 'sleeping_place_size', $rowNumber),
        ];
    }

    /**
     * @param array<string, string> $columns
     */
    private function isImportInactive(Worksheet $sheet, array $columns, int $rowNumber): bool
    {
        if (!isset($columns['import_active'])) {
            return false;
        }

        $raw = trim($this->cellValue($sheet, $columns['import_active'], $rowNumber));
        if ($raw === '') {
            return false;
        }

        $normalized = mb_strtolower($raw, 'UTF-8');
        $normalized = preg_replace('/[^\p{L}\p{N}\s\-—]/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', trim($normalized)) ?? trim($normalized);

        if (in_array($normalized, ['yes', 'да', '1', 'true'], true)) {
            return false;
        }

        if (in_array($normalized, ['no', 'нет', '0', 'false', '-', '—'], true)) {
            return true;
        }

        return (bool)preg_match('/\b(no|нет)\b/u', $normalized);
    }

    private function isInvalidCategorySubcategoryPair(string $categoryLabel, string $subcategoryLabel): bool
    {
        $category = mb_strtolower(trim($categoryLabel), 'UTF-8');
        $subcategory = mb_strtolower(trim($subcategoryLabel), 'UTF-8');

        return $category === 'диван' && $subcategory === 'кресло';
    }

    /**
     * @param array<string, string> $columns
     */
    private function readFilterFunction(Worksheet $sheet, array $columns, int $rowNumber): string
    {
        if (!isset($columns['filter_function'])) {
            return CatalogFilterFunction::NONE;
        }

        $value = trim($this->cellValue($sheet, $columns['filter_function'], $rowNumber));

        return CatalogListingValueParser::parseFilterFunction($value !== '' ? $value : null);
    }

    /**
     * @param array<string, string> $columns
     * @return array<int, int>
     */
    private function readPrices(Worksheet $sheet, int $rowNumber, array $columns): array
    {
        $prices = [];
        foreach ($columns as $key => $column) {
            if (!str_starts_with($key, 'price_')) {
                continue;
            }

            $categoryNumber = (int)substr($key, 6);
            if ($categoryNumber <= 0) {
                continue;
            }

            $amount = $this->parsePrice($this->cellValue($sheet, $column, $rowNumber));
            if ($amount !== null && $amount > 0) {
                $prices[$categoryNumber] = $amount;
            }
        }

        return $prices;
    }

    /**
     * @param array<string, string> $columns
     * @return array{
     *     galleryPhotoUrls: list<string>,
     *     dimensionPhotoUrls: list<string>,
     *     dimensionPhotoFolderUrl: ?string
     * }
     */
    private function readPhotoFieldValues(Worksheet $sheet, array $columns, int $rowNumber): array
    {
        $galleryCol = $columns['gallery_photos'] ?? null;
        $dimensionCol = $columns['dimension_photos'] ?? null;

        if ($galleryCol !== null && $dimensionCol !== null && $galleryCol === $dimensionCol) {
            $raw = trim($this->cellValue($sheet, $galleryCol, $rowNumber));

            return CatalogModelImportPhotoParser::parseCombinedGalleryTechField($raw);
        }

        $gallery = $galleryCol !== null
            ? CatalogModelImportPhotoParser::parseGalleryUrls($this->cellValue($sheet, $galleryCol, $rowNumber))
            : [];
        $dimension = $dimensionCol !== null
            ? CatalogModelImportPhotoParser::parseDimensionField($this->cellValue($sheet, $dimensionCol, $rowNumber))
            : ['dimensionPhotoUrls' => [], 'dimensionPhotoFolderUrl' => null];

        return [
            'galleryPhotoUrls' => $gallery,
            'dimensionPhotoUrls' => $dimension['dimensionPhotoUrls'],
            'dimensionPhotoFolderUrl' => $dimension['dimensionPhotoFolderUrl'],
        ];
    }

    /**
     * @param array<string, string> $columns
     * @return list<string>
     */
    private function readListValue(Worksheet $sheet, array $columns, string $field, int $rowNumber): array
    {
        if (!isset($columns[$field])) {
            return [];
        }

        $raw = trim($this->cellValue($sheet, $columns[$field], $rowNumber));
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/\s*[,;]\s*/u', $raw) ?: [];
        $items = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $items[] = $part;
            }
        }

        return $items;
    }

    /**
     * @param array<string, string> $columns
     */
    private function optionalValue(Worksheet $sheet, array $columns, string $field, int $rowNumber): ?string
    {
        if (!isset($columns[$field])) {
            return null;
        }

        $value = trim($this->cellValue($sheet, $columns[$field], $rowNumber));

        return $value !== '' ? $value : null;
    }

    private function normalizeHeader(string $header): string
    {
        if (str_contains($header, "\n")) {
            $header = trim(strtok($header, "\n"));
        }

        $header = mb_strtolower(trim($header), 'UTF-8');
        $header = str_replace('*', '', $header);
        $header = str_replace('×', 'x', $header);
        $header = preg_replace('/,\s*мм$/u', '', $header) ?? $header;
        $header = preg_replace('/\s*мм$/u', '', $header) ?? $header;
        $header = preg_replace('/\s*\(/u', ' (', $header) ?? $header;
        $header = preg_replace('/\s+/u', ' ', $header) ?? $header;

        if (str_contains($header, 'размер') || preg_match('/ш\s*[xх×]/u', $header)) {
            $header = str_replace(['х', 'X'], 'x', $header);
            if (preg_match('/размер\s*\(шxвxг\)/u', $header)) {
                $header = 'размер (шxвxг)';
            }
        }

        return trim($header);
    }

    private function buildModelDisplayLabel(string $subcategoryLabel, string $collectionName): string
    {
        $subcategoryLabel = trim($subcategoryLabel);
        $collectionName = trim($collectionName);
        if ($subcategoryLabel === '') {
            return $this->capitalize($collectionName);
        }
        if ($collectionName === '') {
            return $this->capitalize($subcategoryLabel);
        }

        return $this->capitalize($subcategoryLabel) . ' ' . $collectionName;
    }

    private function capitalize(string $label): string
    {
        if ($label === '') {
            return '';
        }

        return mb_strtoupper(mb_substr($label, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_substr($label, 1, null, 'UTF-8');
    }

    private function cellValue(Worksheet $sheet, string $column, int $rowNumber): string
    {
        $value = $sheet->getCell($column . $rowNumber)->getCalculatedValue();
        if ($value === null) {
            return '';
        }

        return trim((string)$value);
    }

    private function parsePrice(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int)round($value);
        }

        $normalized = preg_replace('/[^\d]/u', '', (string)$value) ?? '';
        if ($normalized === '') {
            return null;
        }

        return (int)$normalized;
    }
}
