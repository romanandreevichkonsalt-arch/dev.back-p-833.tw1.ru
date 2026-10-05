<?php

use app\models\CatalogModel;
use app\modules\admin\helpers\AdminHtml;
use app\modules\admin\models\CatalogModelSearch;
use app\services\import\catalog\CatalogModelImportOptions;
use yii\helpers\Html;
use yii\web\View;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var CatalogModelSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$models = $dataProvider->getModels();
$collectionFilterDirectionId = ($searchModel->direction_id !== null && $searchModel->direction_id !== '')
    ? (int)$searchModel->direction_id
    : null;

$this->title = 'Модели каталога';
?>
<div class="admin-toolbar">
    <?= Html::a('Добавить модель', ['create'], ['class' => 'admin-btn']) ?>
</div>

<div class="admin-card admin-card--full admin-fabric-import" id="model-import"
     data-model-import
     data-import-start-url="<?= Html::encode(\yii\helpers\Url::to(['import-start'])) ?>"
     data-import-status-url="<?= Html::encode(\yii\helpers\Url::to(['import-status', 'id' => '__RUN_ID__'])) ?>"
     data-import-resolve-url="<?= Html::encode(\yii\helpers\Url::to(['import-resolve'])) ?>">
    <div class="admin-fabric-import__head">
        <h2 class="admin-form-section-title">Импорт</h2>
        <p class="admin-muted">Лист «Модели»</p>
    </div>

    <?php $importForm = \yii\widgets\ActiveForm::begin([
        'action' => ['index'],
        'options' => [
            'enctype' => 'multipart/form-data',
            'class' => 'admin-form admin-fabric-import__form',
            'data-model-import-form' => '1',
            'onsubmit' => 'return false;',
        ],
    ]); ?>
    <div class="admin-fabric-import__row">
        <div class="form-group">
            <label class="form-label" for="price-list-file">Файл .xlsx</label>
            <input type="file" id="price-list-file" name="price_list_file" class="form-control" accept=".xlsx,.xls" required>
        </div>
        <div class="form-group">
            <label class="form-label" for="import-action">Выбрать действия:</label>
            <select id="import-action" name="conflict_resolution" class="form-control">
                <option value="<?= Html::encode(\app\modules\admin\helpers\RegistryImportPostedOptions::MODE_SKIP) ?>" selected>Только добавление</option>
                <option value="<?= Html::encode(\app\modules\admin\helpers\RegistryImportPostedOptions::MODE_UPDATE) ?>">Обновлять существующие</option>
                <option value="<?= Html::encode(\app\modules\admin\helpers\RegistryImportPostedOptions::MODE_UPDATE_NO_MEDIA) ?>">Обновления без фото</option>
            </select>
        </div>
        <div class="admin-fabric-import__checks">
            <label><input type="checkbox" name="skip_if_no_fabric" value="1" checked> Пропускать создание, если нет ткани</label>
        </div>
        <div class="admin-fabric-import__actions">
            <?= Html::button('Импортировать', [
                'class' => 'admin-btn',
                'type' => 'button',
                'data-model-import-submit' => true,
            ]) ?>
        </div>
    </div>
    <?php \yii\widgets\ActiveForm::end(); ?>

    <div class="admin-import-loader admin-import-loader--corner" data-model-import-loader hidden role="status" aria-live="polite" aria-label="Идёт импорт">
        <div class="admin-import-loader__panel">
            <span class="admin-import-loader__spinner" data-model-import-spinner aria-hidden="true"></span>
            <span class="admin-import-loader__success" data-model-import-success hidden aria-hidden="true"></span>
            <p class="admin-import-loader__text" data-model-import-progress-text>Идёт импорт…</p>
            <div class="admin-import-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                <div class="admin-import-progress__bar" data-model-import-progress-bar style="width: 0%"></div>
            </div>
            <p class="admin-import-loader__hint admin-muted" data-model-import-progress-stats></p>
            <p class="admin-import-loader__hint admin-muted" data-model-import-progress-hint>Оставьте вкладку открытой — прогресс обновляется автоматически.</p>
            <div class="admin-import-conflict" data-model-import-conflict hidden>
                <p><strong>Конфликт:</strong> коллекция <span data-conflict-collection></span>, модель <code data-conflict-model></code></p>
                <div class="admin-actions admin-import-conflict__actions">
                    <button type="button" class="admin-btn admin-btn--secondary" data-conflict-action="skip">Пропустить строку</button>
                    <button type="button" class="admin-btn admin-btn--secondary" data-conflict-action="skip_all">Пропустить все существующие</button>
                    <button type="button" class="admin-btn" data-conflict-action="update">Обновить строку</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$filterSelectOptions = [
    'class' => 'form-control admin-table-header-filter',
    'data-catalog-model-filter' => '1',
];
?>
<div class="admin-card">
    <?php $filterForm = ActiveForm::begin([
        'method' => 'get',
        'action' => ['index'],
        'options' => ['class' => 'admin-catalog-model-index-filter'],
    ]); ?>
    <table class="admin-table admin-table--header-filters">
        <thead>
        <tr>
            <th>Название</th>
            <th>
                <?= Html::activeDropDownList(
                    $searchModel,
                    'direction_id',
                    ['' => 'Все направления'] + CatalogModelSearch::directionOptions(),
                    $filterSelectOptions
                ) ?>
            </th>
            <th>
                <?= Html::activeDropDownList(
                    $searchModel,
                    'collection_id',
                    ['' => 'Все коллекции'] + CatalogModelSearch::collectionOptions($collectionFilterDirectionId),
                    $filterSelectOptions
                ) ?>
            </th>
            <th>
                <?= Html::activeDropDownList(
                    $searchModel,
                    'subcategory_id',
                    ['' => 'Все подкатегории'] + CatalogModelSearch::subcategoryOptions(),
                    $filterSelectOptions
                ) ?>
            </th>
            <th>
                <?= Html::activeDropDownList(
                    $searchModel,
                    'color_id',
                    ['' => 'Все цвета'] + CatalogModelSearch::colorOptions(),
                    $filterSelectOptions
                ) ?>
            </th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php if ($models === []): ?>
            <tr>
                <td colspan="6">Моделей пока нет.</td>
            </tr>
        <?php endif; ?>
        <?php foreach ($models as $model): ?>
            <tr>
                <td>
                    <div class="admin-fabric-collection-name">
                        <?= Html::encode($model->title) ?>
                    </div>
                </td>
                <td><?= Html::encode($model->collection?->direction?->label ?? '—') ?></td>
                <td><?= Html::encode($model->collection?->getDisplayName() ?? '—') ?></td>
                <td><?= Html::encode($model->subcategory?->label ?? '—') ?></td>
                <td><?= AdminHtml::fabricColorSwatchesPreview($model->getLinkedActiveFabricColors(), catalogColorsOnly: true) ?></td>
                <td class="admin-table-actions">
                    <?= AdminHtml::actionIcon(['update', 'id' => $model->id], 'update') ?>
                    <?= AdminHtml::actionIcon(['delete', 'id' => $model->id], 'delete', [
                        'data' => [
                            'method' => 'post',
                            'confirm' => 'Удалить модель и связанные товары?',
                        ],
                    ]) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php ActiveForm::end(); ?>
</div>
<?php
$this->registerJsFile('@web/js/admin-catalog-model-import.js', [
    'depends' => [\app\modules\admin\assets\AdminAsset::class],
    'position' => View::POS_END,
    'appendTimestamp' => true,
]);
$this->registerJs(<<<'JS'
document.querySelectorAll('[data-catalog-model-filter]').forEach(function (select) {
    select.addEventListener('change', function () {
        var form = select.closest('form');
        if (form) {
            form.submit();
        }
    });
});
JS, View::POS_READY);
?>
