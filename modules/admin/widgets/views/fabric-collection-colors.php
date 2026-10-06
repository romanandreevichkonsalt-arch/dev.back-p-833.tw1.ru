<?php

use app\models\CatalogColor;
use app\models\CatalogFabricColor;
use app\modules\admin\widgets\MediaPickerWidget;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\CatalogFabricCollection|null $collection */
/** @var CatalogFabricColor[] $links */
/** @var CatalogColor[] $catalogColors */
/** @var string $settingsColorsUrl */
/** @var array<int, int> $productCountByColorId */
$productCountByColorId = $productCountByColorId ?? [];
?>
<section class="admin-fabric-colors" data-fabric-color-manager>
    <div class="admin-fabric-colors__header">
        <h3 class="admin-form-section-title">Цвета коллекции</h3>
        <p class="admin-muted">
            Для каждого цвета: название в коллекции, цвет из справочника и фото образца.
            <?= Html::a('Справочник цветов', $settingsColorsUrl, ['class' => 'admin-link', 'target' => '_blank']) ?>
        </p>
    </div>

    <div class="admin-fabric-colors__toolbar">
        <button type="button" class="admin-btn admin-btn--secondary" data-fabric-color-open-modal>
            Добавить цвет
        </button>
    </div>

    <table class="admin-table admin-table--spaced admin-fabric-colors__table">
        <thead>
        <tr>
            <th>Название</th>
            <th>Цвет</th>
            <th>Фото</th>
            <th>Реком. ткань</th>
            <th>№ позиции</th>
            <th></th>
        </tr>
        </thead>
        <tbody data-fabric-color-rows>
        <?php foreach ($links as $index => $link): ?>
            <?= $this->render('_fabric_color_row', [
                'link' => $link,
                'rowKey' => 'link_' . (int)$link->id,
                'productCount' => $productCountByColorId[(int)$link->id] ?? 0,
            ]) ?>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p class="admin-muted admin-fabric-colors__empty<?= $links === [] ? '' : ' hidden' ?>" data-fabric-color-empty>
        Цвета ещё не добавлены.
    </p>

    <div class="admin-modal admin-fabric-color-modal" data-fabric-color-modal hidden>
        <div class="admin-modal__backdrop" data-fabric-color-modal-close></div>
        <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="fabric-color-modal-title">
            <div class="admin-modal__header">
                <h3 class="admin-modal__title" id="fabric-color-modal-title" data-fabric-color-modal-title>Добавить цвет</h3>
                <button type="button" class="admin-modal__close" data-fabric-color-modal-close aria-label="Закрыть">&times;</button>
            </div>
            <div class="admin-modal__body">
                <input type="hidden" data-fabric-color-edit-key value="">
                <input type="hidden" data-fabric-color-edit-id value="">

                <div class="admin-form-grid">
                    <div class="form-group">
                        <label class="form-label" for="fabric-color-design-code">Название</label>
                        <input type="text" id="fabric-color-design-code" class="form-control" data-fabric-color-design-code placeholder="Savana Terracotta, 425">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="fabric-color-catalog-color">Цвет</label>
                        <select id="fabric-color-catalog-color" class="form-control" data-fabric-color-catalog-color>
                            <option value="">— без цвета —</option>
                            <?php foreach ($catalogColors as $color): ?>
                                <option value="<?= (int)$color->id ?>"><?= Html::encode($color->label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="fabric-color-description">Описание цветодизайна</label>
                    <textarea
                        id="fabric-color-description"
                        class="form-control"
                        rows="8"
                        data-fabric-color-description
                        placeholder="Текст для карточки товара и каталога"
                    ></textarea>
                </div>

                <div data-fabric-color-modal-picker>
                    <?= MediaPickerWidget::widget([
                        'inputName' => 'fabric_color_modal_swatch',
                        'value' => null,
                        'compact' => true,
                        'allowClear' => true,
                        'defaultFolder' => 'fabrics',
                        'label' => 'Фото образца',
                    ]) ?>
                </div>
            </div>
            <div class="admin-modal__footer">
                <button type="button" class="admin-btn admin-btn--secondary" data-fabric-color-modal-close>Отмена</button>
                <button type="button" class="admin-btn" data-fabric-color-modal-save>Сохранить</button>
            </div>
        </div>
    </div>

    <template id="admin-fabric-color-row-template">
        <tr data-fabric-color-row data-row-key="__ROW_KEY__" data-product-count="0">
            <td>
                <strong data-fabric-color-display-code>__DESIGN_CODE__</strong>
                <input type="hidden" name="fabric_color_links[__ROW_KEY__][id]" value="__LINK_ID__">
                <input type="hidden" name="fabric_color_links[__ROW_KEY__][design_code]" value="__DESIGN_CODE__">
                <input type="hidden" name="fabric_color_links[__ROW_KEY__][color_id]" value="__COLOR_ID__">
                <input type="hidden" name="fabric_color_links[__ROW_KEY__][swatch_media_id]" value="__SWATCH_MEDIA_ID__">
                <textarea name="fabric_color_links[__ROW_KEY__][description]" class="admin-fabric-colors__description-store" aria-hidden="true" tabindex="-1"></textarea>
                <input type="hidden" name="fabric_color_links[__ROW_KEY__][is_active]" value="1">
            </td>
            <td data-fabric-color-display-label>__COLOR_LABEL__</td>
            <td data-fabric-color-display-swatch>__SWATCH_HTML__</td>
            <td class="admin-fabric-colors__recommended">
                <input type="hidden" name="fabric_color_links[__ROW_KEY__][is_recommended_fabric]" value="0">
                <input type="checkbox" name="fabric_color_links[__ROW_KEY__][is_recommended_fabric]" value="1" __IS_RECOMMENDED_CHECKED__ aria-label="Рекомендуемая ткань">
            </td>
            <td class="admin-fabric-colors__position">
                <input type="number" class="form-control admin-fabric-colors__position-input" name="fabric_color_links[__ROW_KEY__][position_number]" value="__POSITION_NUMBER__" min="0" step="1" aria-label="№ позиции">
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
    </template>
</section>
