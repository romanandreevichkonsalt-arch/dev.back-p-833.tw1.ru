<?php

namespace app\services\catalog;

use app\models\CatalogCategory;
use app\models\CatalogModel;
use app\models\CatalogProduct;
use app\models\CatalogSubcategory;
use app\services\cache\ApiCacheInvalidator;
use Yii;
use yii\db\Exception as DbException;

final class CatalogCategoryDuplicateMergeService
{
    /** @var array<string, list<string>> canonical slug => duplicate category slugs */
    private const DUPLICATE_CATEGORY_SLUGS = [
        'divan' => ['sofa'],
        'kreslo' => ['armchair'],
    ];

    /** @var array<string, string> */
    private const CATEGORY_URL_SLUGS = [
        'divan' => 'divany',
        'kreslo' => 'kresla',
    ];

    /**
     * Эквивалентные slug подкатегорий (импорт / seed / url_slug).
     *
     * @var list<list<string>>
     */
    private const SUBCATEGORY_EQUIVALENCE_GROUPS = [
        ['straight', 'pryamoy-divan', 'pryamye'],
        ['corner', 'uglovoy-divan', 'uglovye'],
        ['compact', 'kompaktnye', 'malogabaritnyy-divan'],
        ['modular', 'modul', 'modulnye', 'modulnyy-divan'],
        ['armchair', 'kreslo'],
        ['chair-bed', 'kreslo-krovat'],
    ];

    /** @var list<string> */
    private const LEGACY_SUBCATEGORY_SLUGS = [
        'straight',
        'corner',
        'compact',
        'modular',
        'armchair',
        'chair-bed',
    ];

    /** @var array<string, string> slug => frontend url_slug */
    private const SUBCATEGORY_URL_SLUGS = [
        'straight' => 'pryamye',
        'corner' => 'uglovye',
        'compact' => 'kompaktnye',
        'modular' => 'modulnye',
        'armchair' => 'kreslo',
        'chair-bed' => 'kreslo-krovat',
    ];

    /** @var array<string, string> */
    private array $slugGroupKeys;

    /** @var array<string, string> */
    private array $urlSlugGroupKeys;

    /** @var list<string> */
    private array $log = [];

    public function __construct()
    {
        $this->slugGroupKeys = $this->buildSlugGroupKeys();
        $this->urlSlugGroupKeys = $this->buildUrlSlugGroupKeys();
    }

    /**
     * @return list<string>
     */
    public function getLog(): array
    {
        return $this->log;
    }

    public function merge(bool $dryRun = false): void
    {
        $this->log = [];
        $transaction = Yii::$app->db->beginTransaction();

        try {
            foreach (self::DUPLICATE_CATEGORY_SLUGS as $canonicalSlug => $duplicateSlugs) {
                $this->mergeCategoryGroup($canonicalSlug, $duplicateSlugs, $dryRun);
            }

            $this->syncModelCategoriesFromSubcategories($dryRun);

            if ($dryRun) {
                $transaction->rollBack();
                $this->log('Dry run: изменения не сохранены.');
            } else {
                $transaction->commit();
                ApiCacheInvalidator::touch();
                $this->log('Кэш API инвалидирован.');
            }
        } catch (\Throwable $exception) {
            $transaction->rollBack();
            throw $exception;
        }
    }

    /**
     * @param list<string> $duplicateSlugs
     */
    private function mergeCategoryGroup(string $canonicalSlug, array $duplicateSlugs, bool $dryRun): void
    {
        $canonical = $this->resolveCanonicalCategory($canonicalSlug, $duplicateSlugs, $dryRun);
        if ($canonical === null) {
            $this->log("Категория «{$canonicalSlug}» не найдена, пропуск.");

            return;
        }

        $this->ensureCategoryUrlSlug($canonical, $canonicalSlug, $dryRun);

        $duplicateCategories = CatalogCategory::find()
            ->where(['slug' => $duplicateSlugs])
            ->all();

        if ($duplicateCategories === []) {
            $this->log("Дубликаты категории «{$canonicalSlug}» не найдены.");
        }

        $categoryIds = [(int)$canonical->id];
        foreach ($duplicateCategories as $duplicateCategory) {
            $categoryIds[] = (int)$duplicateCategory->id;
        }

        $subcategories = CatalogSubcategory::find()
            ->where(['category_id' => $categoryIds])
            ->orderBy(['category_id' => SORT_ASC, 'sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        /** @var array<string, CatalogSubcategory> $canonicalSubByKey */
        $canonicalSubByKey = [];
        /** @var list<CatalogSubcategory> $duplicateSubs */
        $duplicateSubs = [];

        foreach ($subcategories as $subcategory) {
            if ((int)$subcategory->category_id === (int)$canonical->id) {
                $key = $this->resolveSubcategoryKey($subcategory);
                if (!isset($canonicalSubByKey[$key])) {
                    $canonicalSubByKey[$key] = $subcategory;
                } elseif ($this->shouldPreferSubcategory($subcategory, $canonicalSubByKey[$key])) {
                    $duplicateSubs[] = $canonicalSubByKey[$key];
                    $canonicalSubByKey[$key] = $subcategory;
                } else {
                    $duplicateSubs[] = $subcategory;
                }
                continue;
            }

            $duplicateSubs[] = $subcategory;
        }

        foreach ($duplicateSubs as $duplicateSub) {
            $key = $this->resolveSubcategoryKey($duplicateSub);
            $target = $canonicalSubByKey[$key] ?? null;

            if ($target === null) {
                $this->reparentSubcategory($duplicateSub, $canonical, $dryRun);
                $canonicalSubByKey[$key] = $duplicateSub;
                continue;
            }

            if ((int)$target->id === (int)$duplicateSub->id) {
                continue;
            }

            $this->reassignSubcategory((int)$duplicateSub->id, (int)$target->id, (int)$canonical->id, $dryRun);
            $this->deleteSubcategory($duplicateSub, $dryRun);
        }

        foreach ($duplicateCategories as $duplicateCategory) {
            if (!$dryRun) {
                $remainingSubs = CatalogSubcategory::find()
                    ->where(['category_id' => (int)$duplicateCategory->id])
                    ->count();
                if ($remainingSubs > 0) {
                    throw new DbException(sprintf(
                        'У категории %s остались подкатегории (%d), удаление отменено.',
                        $duplicateCategory->slug,
                        $remainingSubs
                    ));
                }
            }

            $this->deleteCategory($duplicateCategory, $dryRun);
        }
    }

    /**
     * @param list<string> $duplicateSlugs
     */
    private function resolveCanonicalCategory(string $canonicalSlug, array $duplicateSlugs, bool $dryRun): ?CatalogCategory
    {
        $canonical = CatalogCategory::findOne(['slug' => $canonicalSlug]);
        if ($canonical !== null) {
            return $canonical;
        }

        foreach ($duplicateSlugs as $duplicateSlug) {
            $duplicate = CatalogCategory::findOne(['slug' => $duplicateSlug]);
            if ($duplicate === null) {
                continue;
            }

            $this->log(sprintf(
                'Категория %s переименована в %s.',
                $duplicateSlug,
                $canonicalSlug
            ));

            if (!$dryRun) {
                $duplicate->slug = $canonicalSlug;
                $duplicate->save(false);
            }

            return $duplicate;
        }

        return null;
    }

    private function ensureCategoryUrlSlug(CatalogCategory $category, string $canonicalSlug, bool $dryRun): void
    {
        $expected = self::CATEGORY_URL_SLUGS[$canonicalSlug] ?? null;
        if ($expected === null || trim((string)$category->url_slug) === $expected) {
            return;
        }

        $this->log(sprintf(
            'Категории %s установлен url_slug=%s.',
            $category->slug,
            $expected
        ));

        if (!$dryRun) {
            $category->url_slug = $expected;
            $category->save(false);
        }
    }

    private function reparentSubcategory(CatalogSubcategory $subcategory, CatalogCategory $canonical, bool $dryRun): void
    {
        $this->log(sprintf(
            'Подкатегория %s (%s) перенесена в категорию %s.',
            $subcategory->slug,
            $subcategory->label,
            $canonical->slug
        ));

        if ($dryRun) {
            return;
        }

        $subcategory->category_id = (int)$canonical->id;
        $subcategory->save(false);
        CatalogModel::updateAll(
            ['category_id' => (int)$canonical->id],
            ['subcategory_id' => (int)$subcategory->id]
        );
    }

    private function reassignSubcategory(int $fromId, int $toId, int $canonicalCategoryId, bool $dryRun): void
    {
        $productCount = (int)CatalogProduct::find()->where(['subcategory_id' => $fromId])->count();
        $modelCount = (int)CatalogModel::find()->where(['subcategory_id' => $fromId])->count();

        if ($productCount === 0 && $modelCount === 0) {
            return;
        }

        $this->log(sprintf(
            'Подкатегория id=%d → id=%d: товаров %d, моделей %d.',
            $fromId,
            $toId,
            $productCount,
            $modelCount
        ));

        if ($dryRun) {
            return;
        }

        CatalogProduct::updateAll(['subcategory_id' => $toId], ['subcategory_id' => $fromId]);
        CatalogModel::updateAll(
            ['subcategory_id' => $toId, 'category_id' => $canonicalCategoryId],
            ['subcategory_id' => $fromId]
        );
    }

    private function deleteSubcategory(CatalogSubcategory $subcategory, bool $dryRun): void
    {
        $this->log(sprintf(
            'Удалена подкатегория %s (%s), id=%d.',
            $subcategory->slug,
            $subcategory->label,
            $subcategory->id
        ));

        if (!$dryRun) {
            $subcategory->delete();
        }
    }

    private function deleteCategory(CatalogCategory $category, bool $dryRun): void
    {
        $this->log(sprintf(
            'Удалена категория %s (%s), id=%d.',
            $category->slug,
            $category->label,
            $category->id
        ));

        if (!$dryRun) {
            $category->delete();
        }
    }

    private function syncModelCategoriesFromSubcategories(bool $dryRun): void
    {
        if ($dryRun) {
            $count = (int)Yii::$app->db->createCommand(<<<'SQL'
SELECT COUNT(*)
FROM {{%catalog_models}} model
INNER JOIN {{%catalog_subcategories}} subcategory ON subcategory.id = model.subcategory_id
WHERE model.category_id <> subcategory.category_id
SQL)->queryScalar();
            if ($count > 0) {
                $this->log("Будет синхронизировано category_id у {$count} моделей.");
            }

            return;
        }

        $updated = Yii::$app->db->createCommand(<<<'SQL'
UPDATE {{%catalog_models}} model
INNER JOIN {{%catalog_subcategories}} subcategory ON subcategory.id = model.subcategory_id
SET model.category_id = subcategory.category_id
WHERE model.category_id <> subcategory.category_id
SQL)->execute();

        if ($updated > 0) {
            $this->log("Синхронизировано category_id у {$updated} моделей.");
        }
    }

    private function resolveSubcategoryKey(CatalogSubcategory $subcategory): string
    {
        $slugKey = $this->slugGroupKeys[$subcategory->slug] ?? null;
        if ($slugKey !== null) {
            return $slugKey;
        }

        $urlSlug = trim((string)$subcategory->url_slug);
        if ($urlSlug !== '') {
            return $this->urlSlugGroupKeys[$urlSlug] ?? ('url:' . $urlSlug);
        }

        return 'label:' . $this->normalizeLabel((string)$subcategory->label);
    }

    private function shouldPreferSubcategory(CatalogSubcategory $candidate, CatalogSubcategory $current): bool
    {
        $candidateLegacy = in_array($candidate->slug, self::LEGACY_SUBCATEGORY_SLUGS, true);
        $currentLegacy = in_array($current->slug, self::LEGACY_SUBCATEGORY_SLUGS, true);

        if ($candidateLegacy !== $currentLegacy) {
            return !$candidateLegacy;
        }

        return (int)$candidate->id < (int)$current->id;
    }

    /**
     * @return array<string, string>
     */
    private function buildSlugGroupKeys(): array
    {
        $map = [];
        foreach (self::SUBCATEGORY_EQUIVALENCE_GROUPS as $group) {
            $key = 'group:' . $group[0];
            foreach ($group as $slug) {
                $map[$slug] = $key;
            }
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    private function buildUrlSlugGroupKeys(): array
    {
        $map = [];
        foreach (self::SUBCATEGORY_URL_SLUGS as $slug => $urlSlug) {
            $groupKey = $this->slugGroupKeys[$slug] ?? null;
            if ($groupKey !== null) {
                $map[$urlSlug] = $groupKey;
            }
        }

        return $map;
    }

    private function normalizeLabel(string $label): string
    {
        $label = preg_replace('/\s+/u', ' ', trim($label)) ?? trim($label);

        return mb_strtolower($label);
    }

    private function log(string $message): void
    {
        $this->log[] = $message;
    }
}
