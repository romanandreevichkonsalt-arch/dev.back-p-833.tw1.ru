<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var object $model */
/** @var string $formName e.g. PromotionBannerForm */
/** @var bool $promoCreateLocked */
?>
<div class="admin-modal admin-banner-promo-modal" data-banner-promo-modal hidden>
    <div class="admin-modal__backdrop" data-banner-promo-modal-close></div>
    <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="banner-promo-modal-title">
        <div class="admin-modal__header">
            <h3 class="admin-modal__title" id="banner-promo-modal-title">
                <?= $promoCreateLocked ? 'Промокод' : 'Создать промокод' ?>
            </h3>
            <button type="button" class="admin-modal__close" data-banner-promo-modal-close aria-label="Закрыть">&times;</button>
        </div>
        <div class="admin-modal__body">
            <div class="admin-form-grid admin-form-grid--2col" data-banner-promo-modal-fields>
                <?php
                $field = static function (string $attr, string $label, string $inputHtml) use ($formName): string {
                    $id = strtolower($formName) . '-' . str_replace('_', '-', $attr);

                    return '<div class="form-group field-' . Html::encode($id) . '">'
                        . '<label class="form-label" for="' . Html::encode($id) . '">' . Html::encode($label) . '</label>'
                        . str_replace('__ID__', Html::encode($id), $inputHtml)
                        . '</div>';
                };
                ?>
                <?= $field('promo_code', 'Код промокода', '<input type="text" id="__ID__" class="form-control" data-promo-draft="promo_code" value="' . Html::encode((string)$model->promo_code) . '" style="text-transform:uppercase"' . ($promoCreateLocked ? ' readonly' : '') . '>') ?>
                <?= $field('promo_title', 'Название промокода', '<input type="text" id="__ID__" class="form-control" data-promo-draft="promo_title" value="' . Html::encode((string)$model->promo_title) . '">') ?>
                <?= $field('promo_discount_percent', 'Скидка, %', '<input type="number" id="__ID__" class="form-control" data-promo-draft="promo_discount_percent" value="' . Html::encode((string)$model->promo_discount_percent) . '" step="0.1" min="0" max="100">') ?>
                <?= $field('promo_valid_until', 'Действует до', '<input type="date" id="__ID__" class="form-control" data-promo-draft="promo_valid_until" value="' . Html::encode((string)($model->promo_valid_until ?? '')) . '">') ?>
                <div class="form-group">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" data-promo-draft="promo_is_single_use" value="1" <?= $model->promo_is_single_use ? 'checked' : '' ?>>
                            Одноразовый
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" data-promo-draft="promo_is_active" value="1" <?= $model->promo_is_active ? 'checked' : '' ?>>
                            Промокод активен
                        </label>
                    </div>
                </div>
                <div class="form-group admin-form-field--full">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" data-promo-draft="grant_to_all_dealers" value="1" <?= $model->grant_to_all_dealers ? 'checked' : '' ?>>
                            Выдать всем дилерам при сохранении
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="admin-modal__footer">
            <button type="button" class="admin-btn admin-btn--secondary" data-banner-promo-modal-close>Отмена</button>
            <button type="button" class="admin-btn" data-banner-promo-modal-apply>Применить</button>
        </div>
    </div>
</div>
