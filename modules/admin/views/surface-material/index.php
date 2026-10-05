<?php

use app\models\CatalogSurfaceMaterial;
use app\modules\admin\helpers\AdminHtml;
use app\services\import\surface\SurfaceMaterialRegistryImportOptions;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\View;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var CatalogSurfaceMaterial[] $materials */
/** @var app\modules\admin\models\SurfaceMaterialSearch $searchModel */

$this->title = 'Дерево и металл';

$filterFormId = 'surface-material-index-filter';
$filterSelectOptions = [
    'class' => 'form-control admin-table-header-filter',
    'data-surface-material-filter' => '1',
    'form' => $filterFormId,
];
?>
<div class="admin-toolbar">
    <?= Html::a('Добавить материал', ['create'], ['class' => 'admin-btn']) ?>
</div>

<div class="admin-card admin-card--full admin-fabric-import" id="surface-import"
     data-fabric-import
     data-import-start-url="<?= Html::encode(Url::to(['import-start'])) ?>"
     data-import-status-url="<?= Html::encode(Url::to(['import-status', 'id' => '__RUN_ID__'])) ?>"
     data-import-resolve-url="<?= Html::encode(Url::to(['import-resolve'])) ?>">
    <div class="admin-fabric-import__head">
        <h2 class="admin-form-section-title">Импорт</h2>
        <p class="admin-muted">Лист «Дерево и металл» (реестр v2).</p>
    </div>

    <?php $importForm = ActiveForm::begin([
        'action' => ['index'],
        'options' => [
            'enctype' => 'multipart/form-data',
            'class' => 'admin-form admin-fabric-import__form',
            'data-fabric-import-form' => '1',
            'onsubmit' => 'return false;',
        ],
    ]); ?>
    <div class="admin-fabric-import__row">
        <div class="form-group">
            <label class="form-label" for="registry-file">Файл .xlsx</label>
            <input type="file" id="registry-file" name="registry_file" class="form-control" accept=".xlsx,.xls" required>
        </div>
        <div class="form-group">
            <label class="form-label" for="import-action">Действие при дубликатах</label>
            <select id="import-action" name="conflict_resolution" class="form-control">
                <option value="<?= Html::encode(\app\modules\admin\helpers\RegistryImportPostedOptions::MODE_SKIP) ?>" selected>Только добавление</option>
                <option value="<?= Html::encode(\app\modules\admin\helpers\RegistryImportPostedOptions::MODE_UPDATE) ?>">Обновлять существующие</option>
                <option value="<?= Html::encode(\app\modules\admin\helpers\RegistryImportPostedOptions::MODE_UPDATE_NO_MEDIA) ?>">Обновления без фото</option>
            </select>
        </div>
        <div class="admin-fabric-import__actions">
            <?= Html::button('Импортировать', [
                'class' => 'admin-btn',
                'type' => 'button',
                'data-fabric-import-submit' => true,
            ]) ?>
        </div>
    </div>
    <?php ActiveForm::end(); ?>

    <div class="admin-import-loader admin-import-loader--corner" data-fabric-import-loader hidden role="status" aria-live="polite" aria-label="Идёт импорт">
        <div class="admin-import-loader__panel">
            <span class="admin-import-loader__spinner" data-fabric-import-spinner aria-hidden="true"></span>
            <span class="admin-import-loader__success" data-fabric-import-success hidden aria-hidden="true"></span>
            <p class="admin-import-loader__text" data-fabric-import-progress-text>Идёт импорт…</p>
            <div class="admin-import-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                <div class="admin-import-progress__bar" data-fabric-import-progress-bar style="width: 0%"></div>
            </div>
            <p class="admin-import-loader__hint admin-muted" data-fabric-import-progress-stats></p>
            <p class="admin-import-loader__hint admin-muted" data-fabric-import-progress-hint>Оставьте вкладку открытой — прогресс обновляется автоматически.</p>
            <div class="admin-import-conflict" data-fabric-import-conflict hidden>
                <p><strong>Конфликт:</strong> тип <span data-conflict-collection></span>, название <code data-conflict-code></code></p>
                <div class="admin-actions admin-import-conflict__actions">
                    <button type="button" class="admin-btn admin-btn--secondary" data-conflict-action="skip">Пропустить строку</button>
                    <button type="button" class="admin-btn admin-btn--secondary" data-conflict-action="skip_all">Пропустить все существующие</button>
                    <button type="button" class="admin-btn" data-conflict-action="update">Обновить строку</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="admin-card">
    <?php $filterForm = ActiveForm::begin([
        'method' => 'get',
        'action' => ['index'],
        'options' => [
            'id' => $filterFormId,
            'class' => 'admin-surface-material-index-filter',
        ],
    ]); ?>
    <?php ActiveForm::end(); ?>
    <table class="admin-table admin-table--header-filters">
        <thead>
        <tr>
            <th>Фото</th>
            <th>
                <?= Html::activeDropDownList(
                    $searchModel,
                    'material_type',
                    ['' => 'Все типы'] + CatalogSurfaceMaterial::materialTypeOptions(),
                    $filterSelectOptions
                ) ?>
            </th>
            <th>Название</th>
            <th>Коллекции</th>
            <th>Статус</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php if ($materials === []): ?>
            <tr>
                <td colspan="6">Материалов пока нет.</td>
            </tr>
        <?php endif; ?>
        <?php foreach ($materials as $material): ?>
            <?php
            $collectionNames = array_map(
                static fn ($c) => $c->name,
                $material->catalogCollections
            );
            ?>
            <tr>
                <td>
                    <?php if ($material->photoMedia !== null): ?>
                        <img src="<?= Html::encode($material->photoMedia->getPublicUrl('mini')) ?>" alt="" width="40" height="40" style="object-fit:cover;border-radius:4px">
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td><?= Html::encode($material->material_type) ?></td>
                <td><?= Html::encode($material->name) ?></td>
                <td><?= Html::encode($collectionNames !== [] ? implode(', ', $collectionNames) : '—') ?></td>
                <td><?= $material->is_active ? 'Активен' : 'Скрыт' ?></td>
                <td class="admin-table-actions">
                    <?= AdminHtml::actionIcon(['update', 'id' => $material->id], 'update') ?>
                    <?= AdminHtml::actionIcon(['delete', 'id' => $material->id], 'delete', [
                        'data' => [
                            'method' => 'post',
                            'confirm' => 'Удалить материал?',
                        ],
                    ]) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
$this->registerJsFile('@web/js/admin-fabric-import.js', [
    'depends' => [\yii\web\JqueryAsset::class],
]);
$this->registerJs(<<<'JS'
document.querySelectorAll('[data-surface-material-filter]').forEach(function (select) {
    select.addEventListener('change', function () {
        if (select.form) {
            select.form.submit();
        }
    });
});
JS, View::POS_READY);
?>
