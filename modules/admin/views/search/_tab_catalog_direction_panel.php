<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var array<string, mixed> $direction */
/** @var string $searchUrl */
/** @var bool $isActive */

$directionId = (int)$direction['id'];
$items = $direction['items'] ?? [];
?>
<section
    class="admin-catalog-priority-panel<?= $isActive ? ' admin-catalog-priority-panel--active' : '' ?>"
    data-catalog-priority-panel
    data-direction-id="<?= $directionId ?>"
    data-search-url="<?= Html::encode($searchUrl) ?>"
    <?= $isActive ? '' : ' hidden' ?>
>
    <p class="admin-muted admin-page-block-section__lead">
        Товары из списка показываются первыми в выдаче поиска для направления «<?= Html::encode($direction['label']) ?>».
        № сортировки (с 1) совпадает с отметкой «Приоритет в поиске» у SKU в карточке модели.
    </p>

    <div class="admin-catalog-priority-search admin-home-product-search" data-catalog-priority-search>
        <label class="form-label" for="catalog_priority_search_<?= $directionId ?>">Добавить товар-образец</label>
        <input
            type="search"
            class="form-control"
            id="catalog_priority_search_<?= $directionId ?>"
            placeholder="Поиск по наименованию товара (от 3 символов)"
            autocomplete="off"
            data-product-search-input
        >
        <p class="admin-field-hint">Выберите SKU из подсказки. Для той же модели можно сменить образец; порядок строк задаёт место в выдаче поиска.</p>
        <div class="admin-home-product-search__results" data-product-search-results hidden></div>
        <p class="admin-catalog-priority-search__notice admin-muted" data-catalog-priority-notice hidden></p>
    </div>

    <div class="admin-catalog-priority-list">
        <table class="admin-table admin-catalog-priority-table">
            <thead>
                <tr>
                    <th>Товар-образец</th>
                    <th class="admin-catalog-priority-table__sort-col">№ сортировки</th>
                    <th class="admin-table-actions admin-catalog-priority-table__actions-col"></th>
                </tr>
            </thead>
            <tbody data-catalog-priority-list>
                <?php foreach ($items as $index => $item): ?>
                    <?= $this->render('_tab_catalog_priority_row', [
                        'directionId' => $directionId,
                        'index' => $index,
                        'item' => $item,
                    ]) ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="admin-catalog-priority-list__empty admin-muted" data-catalog-priority-empty <?= $items !== [] ? 'hidden' : '' ?>>
            Список пуст. Найдите товар через поиск выше, чтобы добавить модель.
        </p>
    </div>

    <template data-catalog-priority-row-template>
        <?= $this->render('_tab_catalog_priority_row', [
            'directionId' => $directionId,
            'index' => '__INDEX__',
            'item' => [
                'catalog_model_id' => '__MODEL_ID__',
                'sample_catalog_product_id' => '__PRODUCT_ID__',
                'sort_order' => '__SORT_ORDER__',
                'model_title' => '__MODEL_TITLE__',
                'product_search' => '__PRODUCT_SEARCH__',
                'collection_label' => '__COLLECTION_LABEL__',
                'subcategory_label' => '__SUBCATEGORY_LABEL__',
                'swatch_style' => '__SWATCH_STYLE__',
            ],
        ]) ?>
    </template>
</section>
