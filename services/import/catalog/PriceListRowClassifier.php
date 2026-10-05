<?php

namespace app\services\import\catalog;

class PriceListRowClassifier
{
    public const CATEGORY_MODULES = 'Модули';

    /** @var list<string> */
    private const MODULE_KEYWORDS = [
        'боковин' => 'Боковина',
        'оттоман' => 'Оттоманка',
        'пуфик' => 'Пуфик',
        'пуф ' => 'Пуфик',
        'пуф,' => 'Пуфик',
        'модуль' => 'Модуль',
        'секци' => 'Секция',
        'остров' => 'Модуль',
        'подлокотник' => 'Подлокотник',
        'полукресл' => 'Полукресло',
        'трансформер' => 'Трансформер',
        'прямая часть' => 'Секция',
        'угловая часть' => 'Угловая часть',
        'круглая часть' => 'Круглая часть',
        'уловой' => 'Угловой диван',
    ];

    /**
     * @param array<int, int> $pricesByCategoryNumber
     */
    public function classifyModelRow(
        string $collectionName,
        int $rowNumber,
        string $rawLabel,
        array $pricesByCategoryNumber
    ): ?CatalogModelImportRowDto {
        $rawLabel = trim($rawLabel);
        if ($rawLabel === '' || $pricesByCategoryNumber === []) {
            return null;
        }

        if ($this->shouldSkipLabel($rawLabel)) {
            return null;
        }

        $stripped = $this->stripDimensions($rawLabel);
        if ($stripped === '') {
            return null;
        }

        $isModule = $this->isModuleLabel($rawLabel, $stripped);
        [$categoryLabel, $subcategoryLabel, $productTitlePart] = $isModule
            ? $this->resolveModuleTaxonomy($stripped)
            : $this->resolveFurnitureTaxonomy($stripped);

        return new CatalogModelImportRowDto(
            rowNumber: $rowNumber,
            directionLabel: '',
            collectionName: $collectionName,
            displayLabel: $this->capitalizeLabel($stripped),
            productTitlePart: $productTitlePart,
            categoryLabel: $categoryLabel,
            subcategoryLabel: $subcategoryLabel,
            isModule: $isModule,
            pricesByCategoryNumber: $pricesByCategoryNumber,
        );
    }

    public function shouldSkipLabel(string $label): bool
    {
        $normalized = mb_strtolower(trim($label), 'UTF-8');
        if ($normalized === '' || $normalized === 'угол') {
            return true;
        }

        if ($this->looksLikePriceHeader($label)) {
            return true;
        }

        return str_contains($normalized, 'является')
            || str_contains($normalized, 'оптовый прайс')
            || str_contains($normalized, 'наименование мебели')
            || str_contains($normalized, 'цены от производителя')
            || str_contains($normalized, 'габариты указаны');
    }

    public function stripDimensions(string $label): string
    {
        $label = trim($label);
        $label = preg_replace('/,\s*спальное место.*$/ui', '', $label) ?? $label;
        $label = preg_replace('/\d+(?:[.,]\d+)?\s*см/ui', '', $label) ?? $label;
        $label = preg_replace('/\d+\s*[xх×]\s*\d+(?:\s*см)?/ui', '', $label) ?? $label;
        $label = preg_replace('/\d+\s*х\b/ui', '', $label) ?? $label;
        $label = preg_replace('/\bD\d+\b/ui', '', $label) ?? $label;
        $label = preg_replace('/\d+\s*градус(?:ов)?/ui', '', $label) ?? $label;
        $label = preg_replace('/\d+(?:[.,]\d+)?\s*м\b/ui', '', $label) ?? $label;
        $label = preg_replace('/\(\s*1\s*шт\s*\)/ui', '', $label) ?? $label;
        $label = preg_replace('/\(\s*секция\s*\)/ui', '', $label) ?? $label;
        $label = preg_replace('/\(\s*остров\s*\)/ui', '', $label) ?? $label;
        $label = preg_replace('/\s+/u', ' ', $label) ?? $label;

        return trim($label, " \t\n\r\0\x0B.,;:-");
    }

    private function isModuleLabel(string $rawLabel, string $stripped): bool
    {
        $haystack = mb_strtolower($rawLabel . ' ' . $stripped, 'UTF-8');

        foreach (array_keys(self::MODULE_KEYWORDS) as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return true;
            }
        }

        return (bool)preg_match('/\d+\s*см/ui', $rawLabel)
            || (bool)preg_match('/\d+\s*[xх×]\s*\d+/ui', $rawLabel)
            || (bool)preg_match('/\bD\d+\b/ui', $rawLabel);
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function resolveModuleTaxonomy(string $stripped): array
    {
        $lower = mb_strtolower($stripped, 'UTF-8');
        $subcategoryLabel = 'Модуль';

        foreach (self::MODULE_KEYWORDS as $keyword => $label) {
            if (str_contains($lower, $keyword)) {
                $subcategoryLabel = $label;
                break;
            }
        }

        return [self::CATEGORY_MODULES, $subcategoryLabel, $subcategoryLabel];
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function resolveFurnitureTaxonomy(string $stripped): array
    {
        $lower = mb_strtolower($stripped, 'UTF-8');

        if (str_contains($lower, 'кресло-кровать') || str_contains($lower, 'кресло кровать')) {
            return ['Кресло', 'Кресло-кровать', 'Кресло-кровать'];
        }

        if (str_contains($lower, 'углов') && str_contains($lower, 'диван')) {
            return ['Диван', 'Угловой диван', 'Угловой диван'];
        }

        if (str_contains($lower, 'диван')) {
            return ['Диван', 'Прямой диван', 'Диван'];
        }

        if (str_contains($lower, 'кресло')) {
            return ['Кресло', 'Кресло', 'Кресло'];
        }

        if (str_contains($lower, 'пуф')) {
            return [self::CATEGORY_MODULES, 'Пуфик', 'Пуфик'];
        }

        $label = $this->capitalizeLabel($stripped);

        return [$label, $label, $label];
    }

    private function capitalizeLabel(string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }

        return mb_strtoupper(mb_substr($label, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_substr($label, 1, null, 'UTF-8');
    }

    private function looksLikePriceHeader(string $label): bool
    {
        $lower = mb_strtolower($label, 'UTF-8');

        return str_contains($lower, 'цена ткани')
            || str_contains($lower, 'кат ')
            || str_contains($lower, 'руб');
    }

}
