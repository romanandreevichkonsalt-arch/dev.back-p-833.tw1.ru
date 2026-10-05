<?php

use app\models\CatalogFabricCollection;
use app\models\CatalogPriceCategory;
use app\models\CatalogProduct;
use app\models\CatalogModel;
use app\models\MediaFile;
use app\models\MediaFolder;
use app\modules\admin\helpers\AdminHtml;
use app\modules\admin\widgets\ModelDimensionGalleryWidget;
use app\modules\admin\widgets\ModelFabricProductsWidget;
use app\modules\admin\widgets\ModelGalleryWidget;
use app\modules\admin\widgets\ModelInteriorGalleryWidget;
use app\modules\admin\widgets\ModelPricesWidget;
use app\modules\admin\widgets\MediaPickerWidget;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var CatalogModel $model */
/** @var string $title */
/** @var array<int, string> $collections */
/** @var array<int, array<string, string>> $collectionOptionAttributes */
/** @var array<int, array<string, string>> $categoryOptionAttributes */
/** @var array<int, string> $categories */
/** @var array<int, string> $subcategories */
/** @var array<int, array<string, string>> $subcategoryOptionAttributes */
/** @var array<int, string> $badges */
/** @var CatalogFabricCollection[] $fabricCollections */
/** @var int[] $linkedFabricIds */
/** @var array<int, string> $priceMap */
/** @var CatalogPriceCategory[] $priceCategories */
/** @var CatalogPriceCategory[] $visiblePriceCategories */
/** @var array<int, CatalogProduct> $productsByColorId */
/** @var list<int> $noveltyBadgeIds */
/** @var bool $hasProducts */
/** @var string $noveltyPromoCodePreview */

$this->title = $title;

$titleAutoHint = Html::tag('span', 'Формируется автоматически: подкатегория + коллекция', [
    'class' => 'admin-form-label-hint',
]);
$slugAutoHint = Html::tag('span', 'Формируется автоматически из названия', [
    'class' => 'admin-form-label-hint',
]);

$cascadeUrl = \yii\helpers\Url::to(['/admin/catalog-model/cascade-options']);
$this->registerJsFile('@web/js/admin-catalog-cascade.js', ['depends' => [\app\modules\admin\assets\AdminAsset::class]]);
$this->registerJsFile('@web/js/admin-model-filter-fields.js', ['depends' => [\app\modules\admin\assets\AdminAsset::class]]);
$this->registerJsFile('@web/js/admin-catalog-model-novelty.js', ['depends' => [\app\modules\admin\assets\AdminAsset::class]]);
?>
<div class="admin-card admin-card--full admin-catalog-model-form" data-catalog-cascade data-cascade-url="<?= \yii\helpers\Html::encode($cascadeUrl) ?>">
    <?php $form = ActiveForm::begin([
        'id' => 'catalog-model-form',
        'options' => [
            'class' => 'admin-form',
            'data-catalog-model-form' => true,
            'data-initial-badge-id' => (string)(int)$model->badge_id,
            'data-novelty-badge-ids' => json_encode(array_map('intval', $noveltyBadgeIds), JSON_UNESCAPED_UNICODE),
            'data-has-products' => $hasProducts ? '1' : '0',
        ],
    ]); ?>
    <div class="admin-form-toolbar admin-form-toolbar--sticky">
        <div class="admin-form-toolbar__lead">
            <?= Html::a('← К списку', ['index'], ['class' => 'admin-link admin-form-toolbar__back']) ?>
        </div>
        <div class="admin-form-toolbar__actions">
            <?= Html::submitButton('Сохранить', ['class' => 'admin-btn']) ?>
            <?= Html::a('Отмена', ['index'], ['class' => 'admin-btn admin-btn--secondary']) ?>
        </div>
    </div>
    <?= Html::hiddenInput('novelty_promo_action', '', ['id' => 'catalog-model-novelty-promo-action']) ?>
    <?= $form->errorSummary($model, ['class' => 'admin-form-errors']) ?>

    <div class="row g-4 align-items-start">
        <div class="col-12 col-lg-7">
            <div class="admin-form-grid">
                <?= $form->field($model, 'collection_id')->dropDownList($collections, [
                    'data-catalog-collection' => true,
                    'options' => $collectionOptionAttributes,
                ]) ?>
                <?= $form->field($model, 'category_id')->dropDownList($categories, [
                    'data-catalog-category' => true,
                    'options' => $categoryOptionAttributes,
                ]) ?>
                <?= $form->field($model, 'subcategory_id')->dropDownList($subcategories, [
                    'data-catalog-subcategory' => true,
                    'options' => $subcategoryOptionAttributes,
                ]) ?>
                <?= $form->field($model, 'title')
                    ->label($model->getAttributeLabel('title') . ' ' . $titleAutoHint, ['encode' => false])
                    ->textInput(['data-catalog-model-title' => true]) ?>
                <?= AdminHtml::slugField($form, $model, 'title', [], [
                    'readonly' => true,
                    'placeholder' => 'Заполнится из названия',
                ])->label($model->getAttributeLabel('slug') . ' ' . $slugAutoHint, ['encode' => false]) ?>
                <?= $form->field($model, 'polygons_3d')->textInput([
                    'placeholder' => 'Например, 245000',
                ]) ?>
                <?= $form->field($model, 'file_3d_url')->textInput([
                    'placeholder' => 'Google Drive / URL — один раз скачать в медиатеку',
                ])->hint('Внешняя ссылка в каталог не попадает: после загрузки остаётся только файл в медиатеке (справа).') ?>
                <?= $form->field($model, 'subtitle')->textInput() ?>
                <?= $form->field($model, 'badge_id')->dropDownList($badges) ?>
                <?= $form->field($model, 'is_active', ['options' => ['class' => 'form-group admin-form-grid__checkbox-center']])->checkbox() ?>
            </div>
            <?= $form->field($model, 'description')->textarea(['rows' => 4]) ?>
            <?= $form->field($model, 'fitting_room_url')->textInput([
                'placeholder' => 'https://…',
            ]) ?>
        </div>

        <div class="col-12 col-lg-5 admin-model-form__media">
            <?= ModelGalleryWidget::widget(['model' => $model]) ?>
            <?= ModelInteriorGalleryWidget::widget(['model' => $model]) ?>
            <?= MediaPickerWidget::widget([
                'kind' => MediaFile::KIND_VIDEO,
                'inputName' => 'CatalogModel[video_id]',
                'value' => $model->video_id,
                'label' => 'Видео',
                'allowClear' => true,
                'compact' => true,
                'defaultFolder' => MediaFolder::SLUG_VIDEO,
            ]) ?>
            <?= MediaPickerWidget::widget([
                'kind' => MediaFile::KIND_DOCUMENT,
                'inputName' => 'CatalogModel[file_3d_id]',
                'value' => $model->file_3d_id,
                'label' => 'Файл 3D',
                'allowClear' => true,
                'compact' => true,
                'defaultFolder' => MediaFolder::SLUG_MODELS_3D,
            ]) ?>
        </div>
    </div>

    <?= ModelPricesWidget::widget([
        'modelId' => $model->isNewRecord ? null : (int)$model->id,
        'visiblePriceCategories' => $visiblePriceCategories,
        'allPriceCategories' => $priceCategories,
        'priceMap' => $priceMap,
    ]) ?>

    <hr class="admin-form-divider">

    <div class="admin-model-form__dimensions">
        <div class="admin-model-form__dimensions-head">
            <h3 class="admin-form-section-title">Габариты</h3>
        </div>
        <div class="admin-model-form__dimensions-body">
            <div class="admin-model-form__dimensions-fields">
                <div class="admin-form-grid admin-form-grid--dimensions-main">
                    <?= $form->field($model, 'width_mm')->input('number', ['min' => 0, 'step' => 1]) ?>
                    <?= $form->field($model, 'height_mm')->input('number', ['min' => 0, 'step' => 1]) ?>
                    <?= $form->field($model, 'depth_mm')->input('number', ['min' => 0, 'step' => 1]) ?>
                    <?= $form->field($model, 'corner_depth_mm')->input('number', ['min' => 0, 'step' => 1]) ?>
                </div>
                <div class="admin-form-grid admin-form-grid--dimensions-extra">
                    <?= $form->field($model, 'seat_depth')->textInput() ?>
                    <?= $form->field($model, 'seat_height')->textInput() ?>
                    <?= $form->field($model, 'armrest_width')->textInput() ?>
                    <?= $form->field($model, 'leg_height')->textInput() ?>
                </div>
                <?= $form->field($model, 'tech_photos_folder_url', [
                    'options' => ['class' => 'form-group admin-model-form__dimensions-folder-url'],
                ])->textInput([
                    'placeholder' => 'https://drive.google.com/drive/folders/…',
                ]) ?>
            </div>
            <div class="admin-model-form__dimensions-media">
                <div class="form-group admin-model-form__dimensions-gallery-field">
                    <label class="form-label">Тех. фото габаритов</label>
                    <?= ModelDimensionGalleryWidget::widget([
                        'model' => $model,
                        'hideTitle' => true,
                        'hideHint' => true,
                    ]) ?>
                </div>
            </div>
        </div>
    </div>

    <h3 class="admin-form-section-title admin-form-section-title--materials">Технические характеристики</h3>
    <div class="admin-form-grid admin-form-grid--materials" data-model-filter-fields>
        <div class="admin-model-filter-slot">
            <div class="admin-model-filter-group" data-filter-group="sofa" hidden>
                <?= $form->field($model, 'has_sleeping_place', ['options' => ['class' => 'form-group admin-form-grid__checkbox-center']])->checkbox() ?>
            </div>
            <div class="admin-model-filter-group" data-filter-group="armchair" hidden>
                <?= $form->field($model, 'is_foldable', ['options' => ['class' => 'form-group admin-form-grid__checkbox-center']])->checkbox() ?>
            </div>
            <div class="admin-model-filter-size" data-filter-size-fields hidden>
                <div class="admin-model-filter-size__title">Спальное место</div>
                <div class="admin-model-filter-size__fields">
                    <?= $form->field($model, 'sleeping_place_width_mm')->input('number', ['min' => 0, 'step' => 1]) ?>
                    <?= $form->field($model, 'sleeping_place_depth_mm')->input('number', ['min' => 0, 'step' => 1]) ?>
                </div>
            </div>
        </div>
        <?= $form->field($model, 'frame_spec')->textarea(['rows' => 3]) ?>
        <?= $form->field($model, 'mechanism')->textarea(['rows' => 3]) ?>
        <?= $form->field($model, 'filling_spec')->textarea(['rows' => 3]) ?>
        <?= $form->field($model, 'additional')->textarea(['rows' => 3]) ?>
    </div>

    <h3 class="admin-form-section-title admin-form-section-title--materials">Материалы для карточки</h3>
    <div class="admin-form-grid admin-form-grid--materials">
        <?= $form->field($model, 'frame')->textarea(['rows' => 3]) ?>
        <?= $form->field($model, 'foundation')->textarea(['rows' => 3]) ?>
        <?= $form->field($model, 'filling')->textarea(['rows' => 3]) ?>
        <?= $form->field($model, 'upholstery')->textarea(['rows' => 3]) ?>
        <?= $form->field($model, 'supports')->textarea(['rows' => 3]) ?>
    </div>

    <?= ModelFabricProductsWidget::widget([
        'catalogModel' => $model,
        'fabricCollections' => $fabricCollections,
        'linkedFabricIds' => $linkedFabricIds,
        'productsByColorId' => $productsByColorId,
        'searchPriority' => (new \app\services\search\SearchCatalogPriorityService())->getStateForModel($model),
    ]) ?>

    <?php ActiveForm::end(); ?>
</div>

<div class="admin-modal admin-catalog-model-novelty-modal" data-catalog-model-novelty-modal hidden>
    <div class="admin-modal__backdrop" data-catalog-model-novelty-close></div>
    <div class="admin-modal__dialog admin-catalog-model-novelty-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="catalog-model-novelty-modal-title">
        <div class="admin-modal__header">
            <h3 class="admin-modal__title" id="catalog-model-novelty-modal-title">Создать промокод для новинки?</h3>
            <button type="button" class="admin-modal__close" data-catalog-model-novelty-close aria-label="Закрыть">&times;</button>
        </div>
        <div class="admin-modal__body">
            <p class="admin-muted" style="margin:0;">
                Будет создан промокод <strong><?= Html::encode($noveltyPromoCodePreview) ?></strong>. Выберите, нужно ли сразу выдать его всем дилерам.
            </p>
        </div>
        <div class="admin-modal__footer">
            <button type="button" class="admin-btn admin-btn--secondary" data-catalog-model-novelty-close>Отмена</button>
            <button type="button" class="admin-btn admin-btn--secondary" data-catalog-model-novelty-action="create_only">
                Создать без добавления
            </button>
            <button type="button" class="admin-btn" data-catalog-model-novelty-action="grant_all">
                Создать и добавить всем дилерам
            </button>
        </div>
    </div>
</div>
