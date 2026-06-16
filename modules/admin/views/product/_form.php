<?php

/** @var yii\web\View $this */
/** @var app\modules\admin\models\ProductForm $form */
/** @var app\models\Product|null $product */

use app\models\AttributeDefinition;
use app\models\Category;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$categories = Category::find()->orderBy(['name' => SORT_ASC])->all();
$categoryItems = [];
foreach ($categories as $category) {
    $categoryItems[$category->id] = $category->getDisplayLabel();
}
$definitions = AttributeDefinition::find()->orderBy(['name' => SORT_ASC])->all();
?>
<?php $activeForm = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>
<div class="row">
    <div class="col-md-6">
        <?= $activeForm->field($form, 'name')->textInput() ?>
        <?= $activeForm->field($form, 'slug')->textInput() ?>
        <?= $activeForm->field($form, 'sku')->textInput() ?>
        <?= $activeForm->field($form, 'description')->textarea(['rows' => 5]) ?>
        <?= $activeForm->field($form, 'category_ids')->checkboxList($categoryItems) ?>
        <?= $activeForm->field($form, 'price')->input('number', ['step' => '0.01']) ?>
        <?= $activeForm->field($form, 'old_price')->input('number', ['step' => '0.01']) ?>
        <?= $activeForm->field($form, 'stock_qty')->input('number') ?>
        <?= $activeForm->field($form, 'is_active')->checkbox() ?>
    </div>
    <div class="col-md-6">
        <?= $activeForm->field($form, 'seo_title')->textInput() ?>
        <?= $activeForm->field($form, 'seo_description')->textInput() ?>
        <?= $activeForm->field($form, 'seo_h1')->textInput() ?>

        <h5 class="mt-3">Атрибуты</h5>
        <?php foreach ($definitions as $definition): ?>
            <?php
            $fieldName = "ProductForm[productAttributeValues][{$definition->code}]";
            $value = $form->productAttributeValues[$definition->code] ?? '';
            ?>
            <div class="mb-3">
                <label class="form-label"><?= Html::encode($definition->name) ?> (<?= Html::encode($definition->code) ?>)</label>
                <?php if ($definition->type === AttributeDefinition::TYPE_BOOL): ?>
                    <?= Html::hiddenInput($fieldName, '0') ?>
                    <?= Html::checkbox($fieldName, (bool) $value, ['class' => 'form-check-input', 'value' => '1']) ?>
                <?php elseif ($definition->type === AttributeDefinition::TYPE_NUMBER): ?>
                    <?= Html::input('number', $fieldName, $value, ['class' => 'form-control', 'step' => 'any']) ?>
                <?php else: ?>
                    <?= Html::textInput($fieldName, $value, ['class' => 'form-control']) ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <h5 class="mt-3">Изображения</h5>
        <input type="file" name="images[]" class="form-control" multiple accept="image/*">

        <?php if ($product && $product->images): ?>
            <div class="row g-2 mt-2">
                <?php foreach ($product->images as $image): ?>
                    <div class="col-md-4">
                        <div class="border rounded p-2">
                            <img src="<?= Html::encode($image->getUrl()) ?>" class="img-fluid" alt="">
                            <?php if ($image->is_main): ?><div class="badge bg-primary">Главное</div><?php endif; ?>
                            <div class="mt-2 d-flex gap-1">
                                <?= Html::a('Главное', ['set-main-image', 'id' => $image->id], ['class' => 'btn btn-sm btn-outline-primary']) ?>
                                <?= Html::a('Удалить', ['delete-image', 'id' => $image->id], [
                                    'class' => 'btn btn-sm btn-outline-danger',
                                    'data' => ['method' => 'post', 'confirm' => 'Удалить изображение?'],
                                ]) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<div class="form-group mt-3">
    <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
</div>
<?php ActiveForm::end(); ?>
