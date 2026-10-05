<?php

/** @var yii\web\View $this */
/** @var app\models\ContentPage $page */
/** @var string $activeTab */
/** @var array<string, string> $tabs */
/** @var array<string, string> $tabLeads */
/** @var array<string, mixed> $formData */
/** @var string $tabPartial */
/** @var array<string, mixed> $tabPartialParams */

use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

$tabPartialParams = $tabPartialParams ?? [];
$hideSave = (bool)($tabPartialParams['hideSave'] ?? false);
$pageTabs = [];
foreach ($tabs as $tabKey => $tabLabel) {
    $pageTabs[$tabKey] = [
        'label' => $tabLabel,
        'url' => ['blocks', 'id' => $page->id, 'tab' => $tabKey],
    ];
}
?>
<div class="admin-page-header">
    <div>
        <?= Html::a('← Все страницы', ['index'], ['class' => 'admin-link admin-page-back']) ?>
        <h1 class="admin-page-header__title"><?= Html::encode($page->title) ?></h1>
        <p class="admin-muted"><?= Html::encode(\app\modules\admin\helpers\ContentBlockUi::pageDescription($page->slug)) ?></p>
    </div>
</div>

<?= AdminHtml::pageTabs($pageTabs, $activeTab, 'Блоки страницы') ?>

<?php $form = ActiveForm::begin(['options' => ['class' => 'admin-form admin-page-editor-form']]); ?>

<div class="admin-page-editor admin-page-editor--full">
    <div class="admin-page-editor__main">
        <div class="admin-page-block-panel">
            <p class="admin-page-block-panel__lead admin-muted"><?= Html::encode($tabLeads[$activeTab] ?? '') ?></p>
            <?= $this->render($tabPartial, array_merge(['formData' => $formData], $tabPartialParams)) ?>
            <?php if (!$hideSave): ?>
                <div class="admin-actions admin-page-editor__actions">
                    <?= Html::submitButton('Сохранить', ['class' => 'admin-btn']) ?>
                    <?= Html::a('Отмена', ['index'], ['class' => 'admin-btn admin-btn--secondary']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php ActiveForm::end(); ?>
