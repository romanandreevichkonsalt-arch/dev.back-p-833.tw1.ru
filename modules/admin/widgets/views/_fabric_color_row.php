<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\CatalogFabricColor $link */
/** @var string $rowKey */
/** @var int $productCount */

$productCount = $productCount ?? 0;
$colorLabel = $link->catalogColor?->label ?? '—';
$swatchHtml = $link->getSwatchPreviewUrl() !== null
    ? Html::img($link->getSwatchPreviewUrl(), ['class' => 'admin-fabric-colors__thumb', 'alt' => ''])
    : Html::tag('span', '—', ['class' => 'admin-muted']);
?>
<tr
    data-fabric-color-row
    data-row-key="<?= Html::encode($rowKey) ?>"
    data-design-code="<?= Html::encode($link->design_code) ?>"
    data-color-id="<?= $link->color_id !== null ? (int)$link->color_id : '' ?>"
    data-swatch-media-id="<?= $link->swatch_media_id !== null ? (int)$link->swatch_media_id : '' ?>"
    data-swatch-url="<?= Html::encode((string)$link->getSwatchPreviewUrl()) ?>"
    data-link-id="<?= (int)$link->id ?>"
    data-product-count="<?= (int)$productCount ?>"
>
    <td>
        <strong data-fabric-color-display-code><?= Html::encode($link->design_code) ?></strong>
        <input type="hidden" name="fabric_color_links[<?= Html::encode($rowKey) ?>][id]" value="<?= (int)$link->id ?>">
        <input type="hidden" name="fabric_color_links[<?= Html::encode($rowKey) ?>][design_code]" value="<?= Html::encode($link->design_code) ?>">
        <input type="hidden" name="fabric_color_links[<?= Html::encode($rowKey) ?>][color_id]" value="<?= $link->color_id !== null ? (int)$link->color_id : '' ?>">
        <input type="hidden" name="fabric_color_links[<?= Html::encode($rowKey) ?>][swatch_media_id]" value="<?= $link->swatch_media_id !== null ? (int)$link->swatch_media_id : '' ?>">
        <input type="hidden" name="fabric_color_links[<?= Html::encode($rowKey) ?>][is_active]" value="1">
    </td>
    <td data-fabric-color-display-label><?= Html::encode($colorLabel) ?></td>
    <td data-fabric-color-display-swatch><?= $swatchHtml ?></td>
    <td class="admin-fabric-colors__recommended">
        <input type="hidden" name="fabric_color_links[<?= Html::encode($rowKey) ?>][is_recommended_fabric]" value="0">
        <input
            type="checkbox"
            name="fabric_color_links[<?= Html::encode($rowKey) ?>][is_recommended_fabric]"
            value="1"
            <?= $link->is_recommended_fabric ? 'checked' : '' ?>
            aria-label="Рекомендуемая ткань"
        >
    </td>
    <td class="admin-fabric-colors__position">
        <input
            type="number"
            class="form-control admin-input--narrow admin-fabric-colors__position-input"
            name="fabric_color_links[<?= Html::encode($rowKey) ?>][position_number]"
            value="<?= $link->position_number !== null ? (int)$link->position_number : '' ?>"
            min="0"
            step="1"
            aria-label="№ позиции"
        >
    </td>
    <td class="admin-table-actions">
        <button type="button" class="admin-icon-btn" data-fabric-color-edit title="Редактировать" aria-label="Редактировать">
            <svg class="admin-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>
        </button>
        <button type="button" class="admin-icon-btn" data-fabric-color-remove title="Удалить" aria-label="Удалить">
            <svg class="admin-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path></svg>
        </button>
    </td>
</tr>
