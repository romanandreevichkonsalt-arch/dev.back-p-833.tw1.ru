<?php

use app\models\ContentPage;
use app\models\JournalArticle;
use app\modules\admin\helpers\AdminHtml;
use app\modules\admin\widgets\MediaPickerWidget;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var JournalArticle $model */
/** @var array<int, array<string, mixed>> $blocksForm */
/** @var list<array{catalog_product_id?: int|string, product_search?: string}> $recommendedForm */
/** @var string $title */

$this->title = $title;
$journalPageId = ContentPage::find()->select('id')->where(['slug' => 'journal'])->scalar();
?>
<div class="admin-page-header">
    <div>
        <?= Html::a('← Журнал', $journalPageId ? ['/admin/content-page/blocks', 'id' => $journalPageId] : ['/admin/content-page/index'], ['class' => 'admin-link admin-page-back']) ?>
        <h1 class="admin-page-header__title"><?= Html::encode($title) ?></h1>
    </div>
</div>

<div class="admin-card admin-card--full">
    <?php $form = ActiveForm::begin(['options' => ['class' => 'admin-form admin-page-editor-form']]); ?>
    <?= $form->errorSummary($model, ['class' => 'admin-form-errors']) ?>

    <div class="admin-page-combined-editor">
        <section class="admin-page-combined-section">
            <header class="admin-page-combined-section__head">
                <h2 class="admin-page-combined-section__title">Метаданные</h2>
                <p class="admin-muted admin-page-combined-section__lead">Карточка в списке журнала и шапка статьи.</p>
            </header>
            <div class="admin-page-block-panel">
                <div class="row g-4 align-items-start admin-journal-form__meta">
                    <div class="col-12 col-lg-7">
                        <div class="admin-form-grid">
                            <?= $form->field($model, 'category_id')->dropDownList(JournalArticle::categoryLabels()) ?>
                            <?= $form->field($model, 'title')->textInput() ?>
                            <?= AdminHtml::slugField($form, $model, 'title') ?>
                            <?= $form->field($model, 'date')->textInput(['placeholder' => '07.10.2025']) ?>
                            <?= $form->field($model, 'is_active')->checkbox() ?>
                        </div>
                        <?= $form->field($model, 'excerpt')->textarea(['rows' => 2]) ?>
                    </div>

                    <div class="col-12 col-lg-5 admin-journal-form__sidebar">
                        <?= MediaPickerWidget::widget([
                            'inputName' => 'JournalArticle[image_src]',
                            'altInputName' => 'JournalArticle[image_alt]',
                            'value' => $model->image_src,
                            'altValue' => $model->image_alt,
                            'label' => 'Превью карточки',
                            'allowClear' => true,
                            'compact' => true,
                        ]) ?>

                        <div class="admin-journal-form__seo">
                            <h3 class="admin-form-section-title">SEO</h3>
                            <?= $form->field($model, 'seo_title')->textInput() ?>
                            <?= $form->field($model, 'seo_description')->textarea(['rows' => 3]) ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?= $this->render('_recommended_products', ['recommendedForm' => $recommendedForm ?? []]) ?>

        <section class="admin-page-combined-section">
            <header class="admin-page-combined-section__head">
                <h2 class="admin-page-combined-section__title">Контент статьи</h2>
                <p class="admin-muted admin-page-combined-section__lead">Блоки в порядке отображения на странице.</p>
            </header>
            <div class="admin-page-block-panel">
                <?= $this->render('_block_editor', ['blocksForm' => $blocksForm]) ?>
            </div>
        </section>

        <div class="admin-actions admin-page-editor__actions">
            <?= Html::submitButton('Сохранить', ['class' => 'admin-btn']) ?>
            <?= Html::a('Отмена', $journalPageId ? ['/admin/content-page/blocks', 'id' => $journalPageId] : ['index'], ['class' => 'admin-btn admin-btn--secondary']) ?>
        </div>
    </div>

    <?php ActiveForm::end(); ?>
</div>
