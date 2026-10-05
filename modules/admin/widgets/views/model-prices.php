<?php

use app\models\CatalogPriceCategory;
use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var CatalogPriceCategory[] $visiblePriceCategories */
/** @var array<int, string> $priceMap */
/** @var string $allCategoriesJson */
/** @var string $createUrl */
/** @var string $nextCategoryLabel */
/** @var int|null $modelId */
/** @var string $csrfParam */
/** @var string $csrfToken */

$filledPricesCount = 0;
foreach ($visiblePriceCategories as $priceCategory) {
    if (trim((string)($priceMap[(int)$priceCategory->id] ?? '')) !== '') {
        $filledPricesCount++;
    }
}
?>
<details
    class="admin-form-details admin-model-prices"
    data-admin-model-prices
    data-all-categories="<?= Html::encode($allCategoriesJson) ?>"
    data-create-url="<?= Html::encode($createUrl) ?>"
    data-model-id="<?= (int)($modelId ?? 0) ?>"
    data-csrf-param="<?= Html::encode($csrfParam) ?>"
    data-csrf-token="<?= Html::encode($csrfToken) ?>"
>
    <summary class="admin-form-details__summary admin-model-prices__summary">
        <span class="admin-model-prices__summary-lead">
            <span class="admin-model-prices__toggle-icon" aria-hidden="true">
                <?= AdminHtml::icon('chevron-down') ?>
            </span>
            <span class="admin-model-prices__summary-title">Цены по категориям ткани</span>
        </span>
        <?php if ($visiblePriceCategories !== []): ?>
            <span class="admin-model-prices__summary-meta">
                <?= count($visiblePriceCategories) ?> кат.
                <?php if ($filledPricesCount > 0): ?>
                    · заполнено <?= $filledPricesCount ?>
                <?php endif; ?>
            </span>
        <?php endif; ?>
    </summary>

    <div class="admin-form-details__body">
        <div class="admin-model-prices__header">
            <button
                type="button"
                class="admin-icon-btn admin-model-prices__add"
                data-admin-model-prices-add
                title="Добавить <?= Html::encode($nextCategoryLabel) ?>"
                aria-label="Добавить <?= Html::encode($nextCategoryLabel) ?>"
            >
                <svg class="admin-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 5v14"></path><path d="M5 12h14"></path>
                </svg>
            </button>
        </div>

        <div class="admin-form-grid admin-form-grid--prices admin-model-prices__grid">
            <?php foreach ($visiblePriceCategories as $priceCategory): ?>
                <?= $this->render('_model_price_item', [
                    'priceCategory' => $priceCategory,
                    'priceDisplay' => $priceMap[(int)$priceCategory->id] ?? '',
                ]) ?>
            <?php endforeach; ?>
        </div>

        <p class="admin-muted admin-model-prices__error" data-admin-model-prices-error hidden></p>
    </div>

    <template data-admin-model-prices-template>
        <?= $this->render('_model_price_item', [
            'priceCategory' => new CatalogPriceCategory([
                'id' => '__ID__',
                'number' => '__NUMBER__',
                'label' => '__LABEL__',
            ]),
            'priceDisplay' => '',
        ]) ?>
    </template>
</details>
