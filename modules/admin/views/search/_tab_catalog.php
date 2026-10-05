<?php

use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$directions = $formData['directions'] ?? [];
$activeDirectionId = (int)($formData['activeDirectionId'] ?? 0);
$searchUrl = Url::to(['/admin/search/search-products']);

if ($directions === []) {
    ?>
    <div class="admin-card">
        <p class="admin-muted">Нет активных направлений каталога. Добавьте направления в настройках.</p>
    </div>
    <?php
    return;
}

$directionTabs = [];
foreach ($directions as $direction) {
    $directionTabs[(string)$direction['id']] = [
        'label' => $direction['label'],
        'url' => ['index', 'tab' => 'catalog', 'direction_id' => (int)$direction['id']],
    ];
}
?>
<?php $form = ActiveForm::begin([
    'action' => ['save-catalog-priority'],
    'options' => ['class' => 'admin-form admin-page-editor-form', 'data-catalog-priority-form' => true],
]); ?>

<?= Html::hiddenInput('active_direction_id', $activeDirectionId) ?>

<div class="admin-page-editor admin-page-editor--full">
    <div class="admin-page-editor__main">
        <div class="admin-card">
            <p class="admin-muted admin-page-block-section__lead">
                Приоритетные SKU для каталога (sort=default и popular, page 1 при scope направления) и для поиска.
                Тот же список, что отметка «Приоритет в поиске» у SKU в карточке модели. Сохраняются все вкладки направлений сразу.
            </p>

            <?= AdminHtml::pageTabs($directionTabs, (string)$activeDirectionId, 'Направления каталога', true) ?>

            <?php foreach ($directions as $direction): ?>
                <?= $this->render('_tab_catalog_direction_panel', [
                    'direction' => $direction,
                    'searchUrl' => $searchUrl,
                    'isActive' => (int)$direction['id'] === $activeDirectionId,
                ]) ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="admin-form-toolbar admin-form-toolbar--sticky">
    <?= Html::submitButton('Сохранить приоритет поиска', ['class' => 'admin-btn']) ?>
</div>

<?php ActiveForm::end(); ?>
