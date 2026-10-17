<?php

namespace app\services\import\fabric;

use app\helpers\SlugHelper;
use app\models\CatalogFabricCollection;
use app\models\CatalogFabricColor;
use app\models\CatalogPriceCategory;

/**
 * Prefetch существующих коллекций и связей перед циклом импорта.
 */
final class FabricRegistryImportLookup
{
    /** @var array<string, CatalogFabricCollection> */
    public array $collectionsBySlug = [];

    /** @var array<string, CatalogFabricColor> key: "{fabric_collection_id}:{design_code}" */
    public array $linksByKey = [];

    /** @var array<int, CatalogPriceCategory> */
    public array $priceCategoriesByNumber = [];

    public int $collectionsCacheHits = 0;
    public int $linksCacheHits = 0;

    /**
     * @param FabricRegistryRowDto[] $rows
     */
    public static function buildFromRows(array $rows): self
    {
        $lookup = new self();

        $slugs = [];
        foreach ($rows as $row) {
            if ($row->isSkippable()) {
                continue;
            }
            $slug = SlugHelper::slugify($row->collectionName);
            if ($slug !== '') {
                $slugs[$slug] = true;
            }
        }

        if ($slugs !== []) {
            $lookup->collectionsBySlug = CatalogFabricCollection::find()
                ->where(['slug' => array_keys($slugs)])
                ->indexBy('slug')
                ->all();
        }

        $collectionIds = [];
        foreach ($lookup->collectionsBySlug as $collection) {
            $collectionIds[] = (int)$collection->id;
        }

        if ($collectionIds !== []) {
            foreach (
                CatalogFabricColor::find()
                    ->where(['fabric_collection_id' => $collectionIds])
                    ->all() as $link
            ) {
                $key = (int)$link->fabric_collection_id . ':' . (string)$link->design_code;
                $lookup->linksByKey[$key] = $link;
            }
        }

        $maxCategory = 0;
        foreach ($rows as $row) {
            if ($row->isSkippable()) {
                continue;
            }
            foreach ([$row->priceCategoryLabelA, $row->priceCategoryLabelLine1] as $label) {
                if ($label === null || trim($label) === '') {
                    continue;
                }
                $parsed = PriceCategoryLabelParser::parse($label);
                if ($parsed !== null) {
                    $maxCategory = max($maxCategory, (int)$parsed['number']);
                }
            }
        }

        if ($maxCategory > 0) {
            $lookup->priceCategoriesByNumber = CatalogPriceCategory::find()
                ->where(['between', 'number', 1, $maxCategory])
                ->indexBy('number')
                ->all();
        }

        return $lookup;
    }

    public function collectionBySlug(string $slug): ?CatalogFabricCollection
    {
        return $this->collectionsBySlug[$slug] ?? null;
    }

    public function rememberCollection(CatalogFabricCollection $collection): void
    {
        $this->collectionsBySlug[$collection->slug] = $collection;
    }

    public function linkFor(int $collectionId, string $designCode): ?CatalogFabricColor
    {
        $key = $collectionId . ':' . $designCode;

        return $this->linksByKey[$key] ?? null;
    }

    public function rememberLink(CatalogFabricColor $link): void
    {
        $key = (int)$link->fabric_collection_id . ':' . (string)$link->design_code;
        $this->linksByKey[$key] = $link;
    }

    public function priceCategory(int $number): ?CatalogPriceCategory
    {
        return $this->priceCategoriesByNumber[$number] ?? null;
    }

    public function rememberPriceCategory(CatalogPriceCategory $category): void
    {
        $this->priceCategoriesByNumber[(int)$category->number] = $category;
    }
}
