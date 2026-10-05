<?php

use app\models\CatalogPriceCategory;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var CatalogPriceCategory $priceCategory */
/** @var string $priceDisplay */
$categoryId = Html::encode((string)$priceCategory->id);
$categoryNumber = Html::encode((string)$priceCategory->number);
$categoryLabel = Html::encode($priceCategory->getDisplayLabel());
?>
<div
    class="form-group admin-model-prices__item"
    data-admin-model-prices-item
    data-category-id="<?= $categoryId ?>"
    data-category-number="<?= $categoryNumber ?>"
>
    <div class="admin-model-prices__label-row">
        <label class="form-label" for="model-price-<?= $categoryId ?>">
            <?= $categoryLabel ?>
        </label>
        <button
            type="button"
            class="admin-icon-btn admin-icon-btn--danger admin-model-prices__remove"
            data-admin-model-prices-remove
            title="Удалить"
            aria-label="Удалить"
        >
            <svg class="admin-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path>
            </svg>
        </button>
    </div>
    <input
        type="text"
        class="form-control"
        id="model-price-<?= $categoryId ?>"
        name="model_prices[<?= $categoryId ?>]"
        value="<?= Html::encode($priceDisplay) ?>"
        placeholder="120 000 ₽"
        required
    >
</div>
