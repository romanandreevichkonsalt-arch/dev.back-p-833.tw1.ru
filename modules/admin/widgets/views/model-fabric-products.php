<?php

use app\models\CatalogFabricCollection;
use app\models\CatalogModel;
use app\models\CatalogProduct;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var CatalogModel|null $catalogModel */
/** @var CatalogFabricCollection[] $fabricCollections */
/** @var int[] $linkedFabricIds */
/** @var array<int, CatalogProduct> $productsByColorId */
/** @var array<string, mixed> $searchPriority */
/** @var bool $lazyLoadBodies */
/** @var string $fabricGroupBodyUrl */
$searchPriority = $searchPriority ?? ['enabled' => false];
$lazyLoadBodies = $lazyLoadBodies ?? false;
$fabricGroupBodyUrl = $fabricGroupBodyUrl ?? '';

$linkedSet = array_fill_keys($linkedFabricIds, true);
$linkedCollections = [];
$availableCollections = [];

foreach ($fabricCollections as $fabricCollection) {
    if (isset($linkedSet[(int)$fabricCollection->id])) {
        $linkedCollections[] = $fabricCollection;
    } else {
        $availableCollections[] = $fabricCollection;
    }
}

$buildSelectLabel = static function (CatalogFabricCollection $collection): string {
    $texture = trim((string)($collection->texture ?? ''));
    $label = $collection->name;
    if ($texture !== '') {
        $label .= ' (' . $texture . ')';
    }

    return $label;
};
?>
<section
    class="admin-model-fabrics"
    id="admin-model-fabrics"
    data-model-fabrics
    data-model-id="<?= (int)($catalogModel?->id ?? 0) ?>"
    data-fabric-body-url="<?= Html::encode($fabricGroupBodyUrl) ?>"
    data-model-slug="<?= Html::encode($catalogModel?->slug ?? '') ?>"
    data-type-label="<?= Html::encode($catalogModel !== null ? \app\services\catalog\ProductTitleBuilder::resolveTypeLabel($catalogModel) : '') ?>"
    data-collection-name="<?= Html::encode($catalogModel?->collection?->name ?? '') ?>"
    <?php if (($searchPriority['enabled'] ?? false) && !$catalogModel?->isNewRecord): ?>
        data-search-priority-next-sort="<?= (int)($searchPriority['nextSortOrder'] ?? 1) ?>"
    <?php endif; ?>
>
    <h3 class="admin-form-section-title">Ткани и товары (SKU)</h3>
    <p class="admin-muted">Добавьте коллекции тканей к модели — для каждого цвета можно задать фото. Название SKU: подкатегория, коллекция модели, цвет, коллекция ткани и код цветодизайна; slug формируется автоматически при сохранении модели.</p>
    <?php if (($searchPriority['enabled'] ?? false) && !$catalogModel?->isNewRecord): ?>
        <?php
        $priorityProductId = (int)($searchPriority['sampleCatalogProductId'] ?? 0);
        $prioritySortOrder = (int)($searchPriority['sortOrder'] ?? 0);
        $catalogTabUrl = ['/admin/search/index', 'tab' => 'catalog', 'direction_id' => (int)($searchPriority['directionId'] ?? 0)];
        ?>
        <?= Html::hiddenInput(
            'catalog_listing_priority[sample_catalog_product_id]',
            $priorityProductId > 0 ? $priorityProductId : '',
            ['data-search-priority-product-input' => true]
        ) ?>
        <?= Html::hiddenInput(
            'catalog_listing_priority[sort_order]',
            $prioritySortOrder > 0 ? $prioritySortOrder : '',
            ['data-search-priority-sort-input' => true]
        ) ?>
        <p class="admin-muted admin-model-fabrics__priority-hint">
            Отметка «Приоритет в поиске» и № сортировки — то же, что список в
            <?= Html::a('Поиск → Каталог', $catalogTabUrl, ['class' => 'admin-link']) ?>
            (направление «<?= Html::encode($searchPriority['directionLabel'] ?? '') ?>»).
        </p>
    <?php endif; ?>

    <?php if ($fabricCollections === []): ?>
        <p class="admin-muted">Сначала создайте коллекции тканей.</p>
    <?php else: ?>
        <p
            class="admin-muted admin-model-fabrics__empty"
            data-fabric-empty-state
            <?= $linkedCollections !== [] ? ' hidden' : '' ?>
        >
            Ткани не добавлены. Выберите коллекцию ниже.
        </p>

        <div class="admin-model-fabrics__list" data-fabric-list>
            <?php foreach ($linkedCollections as $fabricCollection): ?>
                <?= $this->render('_fabric-group', [
                    'fabricCollection' => $fabricCollection,
                    'isLinked' => true,
                    'productsByColorId' => $productsByColorId,
                    'searchPriority' => $searchPriority,
                    'lazyLoadBody' => $lazyLoadBodies,
                ]) ?>
            <?php endforeach; ?>
        </div>

        <div class="admin-model-fabrics__pool" data-fabric-pool hidden>
            <?php foreach ($availableCollections as $fabricCollection): ?>
                <?= $this->render('_fabric-group', [
                    'fabricCollection' => $fabricCollection,
                    'isLinked' => false,
                    'productsByColorId' => $productsByColorId,
                    'searchPriority' => $searchPriority,
                    'lazyLoadBody' => $lazyLoadBodies,
                ]) ?>
            <?php endforeach; ?>
        </div>

        <?php if ($availableCollections !== []): ?>
            <div class="admin-model-fabrics__add" data-fabric-add-panel>
                <label class="admin-model-fabrics__add-label" for="fabric-add-select">Добавить ткань</label>
                <div class="admin-model-fabrics__add-row">
                    <select id="fabric-add-select" class="form-control admin-model-fabrics__add-select" data-fabric-add-select>
                        <option value="">Выберите коллекцию…</option>
                        <?php foreach ($availableCollections as $fabricCollection): ?>
                            <option value="<?= (int)$fabricCollection->id ?>">
                                <?= Html::encode($buildSelectLabel($fabricCollection)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="admin-btn admin-btn--secondary" data-fabric-add disabled>
                        Добавить
                    </button>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
