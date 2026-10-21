<?php

namespace app\services\catalog;

use app\models\CatalogProduct;
use app\models\ContentBlock;
use app\models\ContentPage;
final class CatalogListingSeoService
{
    public const BLOCK_KEY = 'catalogSeo';

    /**
     * @return array<string, array{title: string, description: string, h1: string}>
     */
    public static function defaultTemplates(): array
    {
        return [
            'direction' => [
                'title' => '{direction} — каталог моделей | {brand}',
                'description' => 'Каталог {direction_gen}: мебель на заказ, выбор ткани и размеров. {count} позиций. {brand}.',
                'h1' => '{direction}',
            ],
            'category' => [
                'title' => '{category} {direction} — купить от производителя | {brand}',
                'description' => '{category} {direction_gen}: модели на заказ, выбор ткани и размеров. {count} позиций в каталоге. {brand}.',
                'h1' => '{category} {direction}',
            ],
            'subcategory' => [
                'title' => '{subcategory} {category} {direction} | {brand}',
                'description' => '{subcategory} {category_gen} {direction_gen}. {count} моделей в каталоге {brand}.',
                'h1' => '{subcategory} {category} {direction}',
            ],
        ];
    }

    /**
     * @return array<string, array{title: string, description: string, h1: string}>
     */
    public function getTemplatesForAdmin(int $pageId): array
    {
        $stored = $this->loadTemplatesFromPage($pageId);

        return $this->mergeWithDefaults($stored);
    }

    /**
     * @param array<string, mixed> $post
     */
    public function saveTemplatesFromPost(int $pageId, array $post): bool
    {
        $block = $this->findOrCreateBlock($pageId);
        $block->setDataArray($this->templatesFromPost($post));

        return $block->save(false);
    }

    /**
     * @return array{title?: string, description?: string, h1?: string}
     */
    public function resolveForScope(CatalogScope $scope, ?int $productCount = null): array
    {
        return $this->resolveFallbackForScope($scope, $productCount);
    }

    /**
     * @return array{title?: string, description?: string, h1?: string}
     */
    public function resolveFallbackForScope(CatalogScope $scope, ?int $productCount = null): array
    {
        $page = ContentPage::find()->where(['slug' => 'catalog', 'is_active' => true])->one();
        if ($page === null) {
            return [];
        }

        $templates = $this->mergeWithDefaults($this->loadTemplatesFromPage((int)$page->id));
        $level = $this->resolveTemplateLevel($scope);
        if ($level === null) {
            return [];
        }

        return $this->renderStoredSeo($templates[$level], $scope, $productCount);
    }

    /**
     * @param array<string, mixed> $rawSeo
     * @return array{title?: string, description?: string, h1?: string}
     */
    public function renderStoredSeo(array $rawSeo, CatalogScope $scope, ?int $productCount = null): array
    {
        $variables = $this->buildVariables($scope, $productCount);

        $result = [];
        foreach (['title', 'description', 'h1'] as $field) {
            $rendered = $this->renderTemplate((string)($rawSeo[$field] ?? ''), $variables);
            if ($rendered !== '') {
                $result[$field] = $rendered;
            }
        }

        return $result;
    }

    /**
     * @return array{title: string, description: string, h1: string}
     */
    public function defaultTemplateForAdminScope(int $categoryId, int $subcategoryId): array
    {
        if ($subcategoryId > 0) {
            $level = 'subcategory';
        } elseif ($categoryId > 0) {
            $level = 'category';
        } else {
            $level = 'direction';
        }

        return self::defaultTemplates()[$level];
    }

    /**
     * @return array<string, string>
     */
    public function variableHints(): array
    {
        return [
            '{direction}' => 'Название направления (A+)',
            '{direction_gen}' => '«коллекции A+» — для фразы «в …»',
            '{category}' => 'Категория (Диваны)',
            '{category_gen}' => '«диваны» — строчные',
            '{subcategory}' => 'Подкатегория (Угловые)',
            '{subcategory_gen}' => '«угловые диваны»',
            '{brand}' => 'Бренд (MF Anna)',
            '{count}' => 'Число моделей в текущем разделе',
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, array{title: string, description: string, h1: string}>
     */
    public function templatesFromPost(array $post): array
    {
        $result = [];
        foreach (['direction', 'category', 'subcategory'] as $level) {
            $result[$level] = [
                'title' => trim((string)($post["seo_{$level}_title"] ?? '')),
                'description' => trim((string)($post["seo_{$level}_description"] ?? '')),
                'h1' => trim((string)($post["seo_{$level}_h1"] ?? '')),
            ];
        }

        return $result;
    }

    /**
     * @param array<string, array{title: string, description: string, h1: string}> $templates
     * @return array<string, mixed>
     */
    public function templatesToForm(array $templates): array
    {
        $form = [];
        foreach (['direction', 'category', 'subcategory'] as $level) {
            $row = $templates[$level] ?? [];
            $form["seo_{$level}_title"] = $row['title'] ?? '';
            $form["seo_{$level}_description"] = $row['description'] ?? '';
            $form["seo_{$level}_h1"] = $row['h1'] ?? '';
        }

        return $form;
    }

    /**
     * @return array<string, array{title: string, description: string, h1: string}>
     */
    private function loadTemplatesFromPage(int $pageId): array
    {
        $block = ContentBlock::find()
            ->where(['page_id' => $pageId, 'block_key' => self::BLOCK_KEY])
            ->one();

        if ($block === null) {
            return [];
        }

        $data = $block->getDataArray();

        return is_array($data) ? $data : [];
    }

    private function findOrCreateBlock(int $pageId): ContentBlock
    {
        $block = ContentBlock::find()
            ->where(['page_id' => $pageId, 'block_key' => self::BLOCK_KEY])
            ->one();

        if ($block !== null) {
            return $block;
        }

        $block = new ContentBlock([
            'page_id' => $pageId,
            'block_key' => self::BLOCK_KEY,
            'block_type' => \app\services\content\BlockTypeRegistry::TYPE_CATALOG_SEO_TEMPLATES,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $block->setDataArray(self::defaultTemplates());
        $block->save(false);

        return $block;
    }

    /**
     * @param array<string, array{title?: string, description?: string, h1?: string}> $stored
     * @return array<string, array{title: string, description: string, h1: string}>
     */
    private function mergeWithDefaults(array $stored): array
    {
        $defaults = self::defaultTemplates();
        $merged = [];
        foreach ($defaults as $level => $defaultRow) {
            $row = $stored[$level] ?? [];
            $merged[$level] = [
                'title' => trim((string)($row['title'] ?? $defaultRow['title'])),
                'description' => trim((string)($row['description'] ?? $defaultRow['description'])),
                'h1' => trim((string)($row['h1'] ?? $defaultRow['h1'])),
            ];
        }

        return $merged;
    }

    private function resolveTemplateLevel(CatalogScope $scope): ?string
    {
        if ($scope->subcategory !== null) {
            return 'subcategory';
        }
        if ($scope->category !== null) {
            return 'category';
        }
        if ($scope->direction !== null || $scope->collection?->direction !== null) {
            return 'direction';
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function buildVariables(CatalogScope $scope, ?int $productCount): array
    {
        if ($scope->direction === null && $scope->collection?->direction !== null) {
            $scope->direction = $scope->collection->direction;
        }

        $directionLabel = $scope->direction !== null ? trim((string)$scope->direction->label) : '';
        $categoryLabel = $scope->category !== null ? trim((string)$scope->category->label) : '';
        $subcategoryLabel = $scope->subcategory !== null ? trim((string)$scope->subcategory->label) : '';

        $count = $productCount ?? $this->countProductsInScope($scope);

        return [
            '{direction}' => $directionLabel,
            '{direction_gen}' => $directionLabel !== '' ? 'коллекции ' . $directionLabel : '',
            '{category}' => $categoryLabel,
            '{category_gen}' => $categoryLabel !== '' ? mb_strtolower($categoryLabel, 'UTF-8') : '',
            '{subcategory}' => $subcategoryLabel,
            '{subcategory_gen}' => $this->subcategoryGenitivePhrase($subcategoryLabel, $categoryLabel),
            '{brand}' => trim((string)(\Yii::$app->params['catalogSeoBrand'] ?? 'MF Anna')),
            '{count}' => (string)$count,
        ];
    }

    private function subcategoryGenitivePhrase(string $subcategory, string $category): string
    {
        if ($subcategory === '') {
            return '';
        }

        $sub = mb_strtolower($subcategory, 'UTF-8');
        if ($category !== '') {
            $cat = mb_strtolower($category, 'UTF-8');

            return $sub . ' ' . $cat;
        }

        return $sub;
    }

    private function countProductsInScope(CatalogScope $scope): int
    {
        $query = CatalogProduct::find()
            ->alias('p')
            ->where(['p.is_active' => true]);

        if ($scope->subcategory !== null) {
            $query->andWhere(['p.subcategory_id' => $scope->subcategory->id]);
        } elseif ($scope->category !== null) {
            $query->innerJoin('{{%catalog_subcategories}} sc', 'sc.id = p.subcategory_id')
                ->andWhere(['sc.category_id' => $scope->category->id]);
        }

        if ($scope->collection !== null) {
            $query->andWhere(['p.collection_id' => $scope->collection->id]);
        } elseif ($scope->direction !== null) {
            $query->innerJoin('{{%catalog_collections}} c', 'c.id = p.collection_id')
                ->andWhere(['c.direction_id' => $scope->direction->id, 'c.is_active' => true]);
        }

        return (int)$query->count();
    }

    /**
     * @param array<string, string> $variables
     */
    private function renderTemplate(string $template, array $variables): string
    {
        $template = trim($template);
        if ($template === '') {
            return '';
        }

        $result = strtr($template, $variables);
        $result = preg_replace('/\s+/u', ' ', $result) ?? $result;

        return trim($result);
    }
}
