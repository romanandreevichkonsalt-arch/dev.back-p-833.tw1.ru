<?php

use app\models\CatalogCategory;
use app\models\CatalogSubcategory;
use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var CatalogSubcategory $model */
/** @var CatalogCategory $category */
/** @var string $title */

$this->title = $title;
?>
<?= $this->render('../_nav', ['active' => 'category']) ?>

<div class="admin-card admin-card--wide">
    <p class="admin-muted">
        Категория: <?= Html::encode($category->label) ?>
    </p>

    <?php $form = ActiveForm::begin(['options' => ['class' => 'admin-form']]); ?>

    <?= $form->field($model, 'label')->textInput() ?>
    <?= AdminHtml::slugField($form, $model, 'label') ?>
    <?= $form->field($model, 'sort_order')->input('number') ?>
    <?= $form->field($model, 'is_active')->checkbox() ?>

    <div class="admin-actions">
        <?= Html::submitButton('Сохранить', ['class' => 'admin-btn']) ?>
        <?= Html::a('Отмена', ['index'], ['class' => 'admin-btn admin-btn--secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
