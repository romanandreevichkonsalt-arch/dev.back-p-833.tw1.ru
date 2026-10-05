<?php

use app\models\CatalogColor;
use app\models\MediaFile;
use app\models\MediaFolder;
use app\modules\admin\helpers\AdminHtml;
use app\modules\admin\widgets\ColorGalleryWidget;
use app\modules\admin\widgets\MediaPickerWidget;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var CatalogColor $model */
/** @var string $title */

$this->title = $title;
$hex = $model->hex_color ?: '#c8c4bc';
?>
<?= $this->render('../_nav', ['active' => 'color']) ?>

<div class="admin-card admin-card--wide">
    <?php $form = ActiveForm::begin(['options' => ['class' => 'admin-form']]); ?>
    <?= $form->errorSummary($model, ['class' => 'admin-form-errors']) ?>

    <div class="admin-form-grid">
        <?= $form->field($model, 'label')->textInput() ?>
        <?= AdminHtml::slugField($form, $model, 'label') ?>
        <div class="form-group">
            <label class="form-label" for="catalog-color-hex">Цвет (picker)</label>
            <input
                type="color"
                class="form-control form-control-color"
                id="catalog-color-hex"
                name="CatalogColor[hex_color]"
                value="<?= Html::encode($hex) ?>"
            >
        </div>
        <?= $form->field($model, 'sort_order')->input('number') ?>
        <?= $form->field($model, 'is_active')->checkbox() ?>
    </div>

    <?= MediaPickerWidget::widget([
        'kind' => MediaFile::KIND_IMAGE,
        'inputName' => 'CatalogColor[swatch_media_id]',
        'value' => $model->swatch_media_id,
        'label' => 'Фото образца',
        'allowClear' => true,
        'defaultFolder' => MediaFolder::SLUG_FABRICS,
    ]) ?>

    <?php if (!$model->isNewRecord): ?>
        <?= ColorGalleryWidget::widget(['color' => $model]) ?>
    <?php else: ?>
        <p class="admin-muted">Галерею образцов можно добавить после сохранения цвета.</p>
    <?php endif; ?>

    <div class="admin-actions">
        <?= Html::submitButton('Сохранить', ['class' => 'admin-btn']) ?>
        <?= Html::a('Отмена', ['index'], ['class' => 'admin-btn admin-btn--secondary']) ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>
