<?php

use app\models\CatalogCollection;
use app\models\CatalogSurfaceMaterial;
use app\models\MediaFile;
use app\models\MediaFolder;
use app\modules\admin\widgets\MediaPickerWidget;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var CatalogSurfaceMaterial $model */
/** @var string $title */
/** @var CatalogCollection[] $collections */
/** @var int[] $linkedCollectionIds */

$this->title = $title;
$materialsFolder = MediaFolder::SLUG_SURFACE_MATERIALS;
?>
<div class="admin-toolbar">
    <?= Html::a('К списку', ['index'], ['class' => 'admin-btn admin-btn--secondary']) ?>
</div>

<div class="admin-card admin-card--form">
    <?php $form = ActiveForm::begin(['options' => ['class' => 'admin-form']]); ?>

    <div class="admin-form-grid admin-form-grid--2">
        <?= $form->field($model, 'material_type')->dropDownList(CatalogSurfaceMaterial::materialTypeOptions()) ?>
        <?= $form->field($model, 'name') ?>
    </div>

    <?= $form->field($model, 'is_active')->checkbox() ?>
    <?= $form->field($model, 'description')->textarea(['rows' => 4]) ?>

    <div class="admin-form-grid admin-form-grid--2 admin-surface-material-form__media">
        <?= MediaPickerWidget::widget([
            'mode' => MediaPickerWidget::MODE_ID,
            'kind' => MediaFile::KIND_IMAGE,
            'inputName' => Html::getInputName($model, 'photo_media_id'),
            'value' => $model->photo_media_id,
            'label' => $model->getAttributeLabel('photo_media_id'),
            'allowClear' => true,
            'compact' => true,
            'defaultFolder' => $materialsFolder,
        ]) ?>
        <?= MediaPickerWidget::widget([
            'mode' => MediaPickerWidget::MODE_ID,
            'kind' => MediaFile::KIND_IMAGE,
            'inputName' => Html::getInputName($model, 'texture_media_id'),
            'value' => $model->texture_media_id,
            'label' => $model->getAttributeLabel('texture_media_id'),
            'allowClear' => true,
            'compact' => true,
            'defaultFolder' => $materialsFolder,
        ]) ?>
    </div>

    <div class="form-group">
        <label class="form-label">Коллекции каталога</label>
        <div class="admin-checkbox-list admin-checkbox-list--cols-5">
            <?php foreach ($collections as $collection): ?>
                <label class="admin-checkbox-list__item">
                    <input type="checkbox" name="collection_ids[]" value="<?= (int)$collection->id ?>"
                        <?= in_array((int)$collection->id, $linkedCollectionIds, true) ? 'checked' : '' ?>>
                    <?= Html::encode($collection->name) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php if ($collections === []): ?>
            <p class="admin-muted">Коллекции каталога не найдены.</p>
        <?php endif; ?>
    </div>

    <div class="admin-form-actions">
        <?= Html::submitButton('Сохранить', ['class' => 'admin-btn']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
