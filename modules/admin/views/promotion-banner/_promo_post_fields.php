<?php

use yii\helpers\Html;

/** @var object $model */
/** @var yii\base\Model $model */
$formName = $model->formName();
?>
<div class="admin-promo-post-fields" data-banner-promo-post-fields hidden aria-hidden="true">
    <?= Html::activeHiddenInput($model, 'promo_code') ?>
    <?= Html::activeHiddenInput($model, 'promo_title') ?>
    <?= Html::activeHiddenInput($model, 'promo_discount_percent') ?>
    <?= Html::activeHiddenInput($model, 'promo_valid_until') ?>
    <input type="hidden" name="<?= Html::encode($formName) ?>[promo_is_single_use]" value="<?= $model->promo_is_single_use ? '1' : '0' ?>">
    <input type="hidden" name="<?= Html::encode($formName) ?>[promo_is_active]" value="<?= $model->promo_is_active ? '1' : '0' ?>">
    <input type="hidden" name="<?= Html::encode($formName) ?>[grant_to_all_dealers]" value="<?= $model->grant_to_all_dealers ? '1' : '0' ?>">
</div>
