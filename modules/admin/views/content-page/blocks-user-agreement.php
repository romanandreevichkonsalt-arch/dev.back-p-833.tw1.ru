<?php

use app\models\ContentPage;
use app\modules\admin\assets\JournalArticleAsset;
use app\modules\admin\helpers\ContentPageUserAgreementHelper;
use app\services\journal\JournalArticleBlockBuilder;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var ContentPage $page */
/** @var string $activeTab */
/** @var array<string, mixed> $formData */

JournalArticleAsset::register($this);

$blocksForm = $formData['blocksForm'] ?? JournalArticleBlockBuilder::blocksToForm([]);
?>
<div class="admin-page-header">
    <div>
        <?= Html::a('← Все страницы', ['index'], ['class' => 'admin-link admin-page-back']) ?>
        <h1 class="admin-page-header__title"><?= Html::encode($page->title) ?></h1>
        <p class="admin-muted"><?= Html::encode(\app\modules\admin\helpers\ContentBlockUi::pageDescription($page->slug)) ?></p>
    </div>
</div>

<div class="admin-card admin-card--full">
    <?php $form = ActiveForm::begin(['options' => ['class' => 'admin-form admin-page-editor-form']]); ?>

    <div class="admin-page-combined-editor">
        <section class="admin-page-combined-section">
            <header class="admin-page-combined-section__head">
                <h2 class="admin-page-combined-section__title">Контент страницы</h2>
                <p class="admin-muted admin-page-combined-section__lead">Блоки в порядке отображения на странице.</p>
            </header>
            <div class="admin-page-block-panel">
                <?= $this->render('@app/modules/admin/views/journal-article/_block_editor', [
                    'blocksForm' => $blocksForm,
                    'allowedTypes' => ContentPageUserAgreementHelper::allowedBlockTypes(),
                ]) ?>
            </div>
        </section>

        <div class="admin-actions admin-page-editor__actions">
            <?= Html::submitButton('Сохранить', ['class' => 'admin-btn']) ?>
            <?= Html::a('Отмена', ['index'], ['class' => 'admin-btn admin-btn--secondary']) ?>
        </div>
    </div>

    <?php ActiveForm::end(); ?>
</div>
