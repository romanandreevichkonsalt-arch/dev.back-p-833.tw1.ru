<?php

use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var int $directionId */
/** @var int $index */
/** @var array<string, mixed> $item */

$inputPrefix = "catalog_priority[{$directionId}][{$index}]";
$modelIdRaw = $item['catalog_model_id'] ?? 0;
$modelId = is_numeric($modelIdRaw) ? (int)$modelIdRaw : 0;
?>
<tr class="admin-catalog-priority-row" data-catalog-priority-row data-model-id="<?= is_numeric($modelIdRaw) ? $modelId : Html::encode($modelIdRaw) ?>">
    <td>
        <div class="admin-catalog-priority-row__model">
        <span
            class="admin-home-product-search__swatch<?= ($item['swatch_style'] ?? '') === '' ? ' admin-home-product-search__swatch--empty' : '' ?>"
            <?php if (($item['swatch_style'] ?? '') !== ''): ?>style="<?= Html::encode($item['swatch_style']) ?>"<?php endif; ?>
        ></span>
        <div class="admin-catalog-priority-row__body">
            <span class="admin-catalog-priority-row__title"><?= Html::encode($item['model_title'] ?? '') ?></span>
            <?php if (($item['product_search'] ?? '') !== ''): ?>
                <span class="admin-catalog-priority-row__meta"><?= Html::encode($item['product_search']) ?></span>
            <?php endif; ?>
            <?php if (($item['collection_label'] ?? '') !== '' || ($item['subcategory_label'] ?? '') !== ''): ?>
                <span class="admin-catalog-priority-row__meta">
                    <?= Html::encode(trim(($item['collection_label'] ?? '') . ($item['subcategory_label'] ?? '' ? ' · ' . $item['subcategory_label'] : ''))) ?>
                </span>
            <?php endif; ?>
        </div>
        <?php
        $productIdRaw = $item['sample_catalog_product_id'] ?? '';
        ?>
        <?= Html::hiddenInput($inputPrefix . '[catalog_model_id]', $modelIdRaw, ['data-catalog-model-id-input' => true]) ?>
        <?= Html::hiddenInput($inputPrefix . '[sample_catalog_product_id]', $productIdRaw, ['data-catalog-product-id-input' => true]) ?>
        </div>
    </td>
    <td class="admin-catalog-priority-row__sort">
        <?php
        $sortOrderRaw = $item['sort_order'] ?? 0;
        $sortOrder = is_numeric($sortOrderRaw) ? (int)$sortOrderRaw : 0;
        ?>
        <?= Html::input('number', $inputPrefix . '[sort_order]', is_numeric($sortOrderRaw) ? $sortOrder : $sortOrderRaw, [
            'class' => 'form-control admin-catalog-priority-row__sort-input',
            'data-catalog-sort-input' => true,
            'min' => 1,
            'step' => 1,
        ]) ?>
    </td>
    <td class="admin-table-actions">
        <?= Html::a(
            AdminHtml::icon('view'),
            $modelId > 0
                ? Url::to(['/admin/catalog-model/update', 'id' => $modelId])
                : '#',
            [
                'class' => 'admin-icon-btn',
                'data-catalog-model-view' => true,
                'title' => 'Открыть модель',
                'aria-label' => 'Открыть модель',
                'target' => '_blank',
                'rel' => 'noopener',
            ]
        ) ?>
        <?= AdminHtml::repeatableRemoveButton(['data-catalog-priority-remove' => true]) ?>
    </td>
</tr>
