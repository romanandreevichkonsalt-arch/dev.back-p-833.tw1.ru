<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var string $inputName */
/** @var array{catalog_product_id: int|string, product_search: string} $slot */
/** @var string $slotLabel */
/** @var string $searchUrl */
/** @var int|null $directionId */
/** @var int|null $categoryId */
/** @var int|null $subcategoryId */

$directionId = $directionId ?? null;
$categoryId = $categoryId ?? null;
$subcategoryId = $subcategoryId ?? null;

$inputId = preg_replace('/[^a-z0-9_]+/i', '_', $inputName);
?>
<div
    class="admin-page-field admin-home-product-search admin-search-recommended-slot"
    data-product-picker
    data-home-product-picker
    data-search-url="<?= Html::encode($searchUrl) ?>"
    <?php if ($directionId !== null): ?>data-direction-id="<?= (int)$directionId ?>"<?php endif; ?>
    <?php if ($categoryId !== null): ?>data-category-id="<?= (int)$categoryId ?>"<?php endif; ?>
    <?php if ($subcategoryId !== null): ?>data-subcategory-id="<?= (int)$subcategoryId ?>"<?php endif; ?>
>
    <label class="form-label" for="<?= Html::encode($inputId) ?>_search"><?= Html::encode($slotLabel) ?></label>
    <?= Html::hiddenInput($inputName . '[catalog_product_id]', $slot['catalog_product_id'] ?? '', [
        'id' => $inputId . '_id',
        'data-product-id-input' => true,
    ]) ?>
    <input
        type="search"
        class="form-control"
        id="<?= Html::encode($inputId) ?>_search"
        name="<?= Html::encode($inputName) ?>[product_search]"
        value="<?= Html::encode($slot['product_search'] ?? '') ?>"
        placeholder="Поиск по наименованию товара (от 3 символов)"
        autocomplete="off"
        data-product-search-input
    >
    <div class="admin-home-product-search__results" data-product-search-results hidden></div>
</div>
