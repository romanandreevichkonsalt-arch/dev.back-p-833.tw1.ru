<?php

use app\modules\admin\helpers\HomePageProductsHelper;
use yii\helpers\Html;

/** @var int|string $index */
/** @var array{catalog_product_id?: int|string, product_search?: string} $row */

$index = (string)$index;
$productId = (int)($row['catalog_product_id'] ?? 0);
$picker = $productId > 0 ? HomePageProductsHelper::pickerItemByProductId($productId) : null;
$title = trim((string)($picker['productTitle'] ?? $picker['title'] ?? $row['product_search'] ?? ''));
$meta = trim((string)($picker['collection'] ?? ''));
if ($meta !== '' && !empty($picker['fabric'])) {
    $meta .= ' · ' . trim((string)$picker['fabric']);
}
$swatchStyle = trim((string)($picker['swatchStyle'] ?? ''));
?>
<div class="admin-journal-recommended-card" data-journal-recommended-card data-product-id="<?= $productId > 0 ? $productId : '' ?>">
    <?= Html::hiddenInput("recommended_products[{$index}][catalog_product_id]", $productId > 0 ? $productId : '', [
        'data-product-id-input' => true,
    ]) ?>
    <?= Html::hiddenInput("recommended_products[{$index}][product_search]", $title, [
        'data-product-search-label' => true,
    ]) ?>
    <div class="admin-journal-recommended-card__body">
        <span
            class="admin-home-product-search__swatch<?= $swatchStyle === '' ? ' admin-home-product-search__swatch--empty' : '' ?>"
            <?= $swatchStyle !== '' ? ' style="' . Html::encode($swatchStyle) . '"' : '' ?>
        ></span>
        <div class="admin-journal-recommended-card__text">
            <span class="admin-journal-recommended-card__title"><?= Html::encode($title !== '' ? $title : '—') ?></span>
            <span class="admin-journal-recommended-card__meta"<?= $meta === '' ? ' hidden' : '' ?>><?= Html::encode($meta) ?></span>
        </div>
    </div>
    <button type="button" class="admin-journal-recommended-card__remove" data-journal-recommended-remove aria-label="Удалить">
        ×
    </button>
</div>
