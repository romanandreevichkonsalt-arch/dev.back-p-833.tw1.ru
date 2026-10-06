<?php

use app\models\CatalogFabricCollection;
use app\modules\admin\controllers\FabricCollectionController;
use app\services\catalog\CatalogModelProductSyncService;
use app\modules\admin\models\FabricCollectionSearch;
use app\services\import\fabric\FabricRegistryImportOptions;
use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;
use yii\web\View;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var CatalogFabricCollection[] $collections */
/** @var FabricCollectionSearch $searchModel */
?>
<div class="admin-toolbar">
    <?= Html::a('Добавить коллекцию', ['create'], ['class' => 'admin-btn']) ?>
    <?php $filterForm = ActiveForm::begin([
        'method' => 'get',
        'action' => ['index'],
        'options' => ['class' => 'admin-toolbar-search'],
    ]); ?>
    <?= Html::hiddenInput('tab', FabricCollectionController::TAB_COLLECTIONS) ?>
    <?= $filterForm->field($searchModel, 'q', [
        'options' => ['class' => 'admin-toolbar-search__field'],
    ])->label(false)->textInput([
        'placeholder' => 'Коллекция, фактура…',
        'class' => 'form-control',
    ]) ?>
    <div class="admin-toolbar-search__actions">
        <?= Html::submitButton(AdminHtml::icon('search'), [
            'class' => 'admin-icon-btn admin-icon-btn--accent',
            'title' => 'Найти',
            'aria-label' => 'Найти',
        ]) ?>
        <?= Html::a(AdminHtml::icon('clear'), ['index', 'tab' => FabricCollectionController::TAB_COLLECTIONS], [
            'class' => 'admin-icon-btn',
            'title' => 'Сбросить',
            'aria-label' => 'Сбросить',
        ]) ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>

<div class="admin-card admin-card--full admin-fabric-import" id="fabric-import"
     data-fabric-import
     data-import-start-url="<?= \yii\helpers\Html::encode(\yii\helpers\Url::to(['import-start'])) ?>"
     data-import-status-url="<?= \yii\helpers\Html::encode(\yii\helpers\Url::to(['import-status', 'id' => '__RUN_ID__'])) ?>"
     data-import-resolve-url="<?= \yii\helpers\Html::encode(\yii\helpers\Url::to(['import-resolve'])) ?>">
    <div class="admin-fabric-import__head">
        <h2 class="admin-form-section-title">Импорт</h2>
        <p class="admin-muted">Лист «Ткани и кожа» (реестр v2) или листы «Коллекции» + «Цвета».</p>
    </div>

    <?php $importForm = \yii\widgets\ActiveForm::begin([
        'action' => ['index', 'tab' => FabricCollectionController::TAB_COLLECTIONS],
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
            <label class="form-label" for="import-action">Выбрать действия:</label>
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
        <div class="admin-fabric-import__download">
            <?= Html::a('Скачать архив фото', ['download-textures-archive'], [
                'class' => 'admin-btn admin-btn--secondary',
                'data-fabric-textures-archive-download' => '1',
            ]) ?>
        </div>
    </div>
    <?php \yii\widgets\ActiveForm::end(); ?>

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
                <p><strong>Конфликт:</strong> коллекция <span data-conflict-collection></span>, код <code data-conflict-code></code></p>
                <div class="admin-actions admin-import-conflict__actions">
                    <button type="button" class="admin-btn admin-btn--secondary" data-conflict-action="skip">Пропустить строку</button>
                    <button type="button" class="admin-btn admin-btn--secondary" data-conflict-action="skip_all">Пропустить все существующие</button>
                    <button type="button" class="admin-btn" data-conflict-action="update">Обновить строку</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="admin-card admin-card--full admin-fabric-import" id="fabric-color-descriptions-import">
    <div class="admin-fabric-import__head">
        <h2 class="admin-form-section-title">Импорт описаний цветодизайнов</h2>
        <p class="admin-muted">
            Отдельный файл Excel: колонки «Название ткани», «Нумерация оттенка ткани», «Название цвета и описание».
            Сопоставление по коллекции ткани и коду цвета в каталоге.
        </p>
    </div>

    <?php $descriptionsImportForm = ActiveForm::begin([
        'action' => ['import-color-descriptions', 'tab' => FabricCollectionController::TAB_COLLECTIONS],
        'options' => [
            'enctype' => 'multipart/form-data',
            'class' => 'admin-form admin-fabric-import__form',
        ],
    ]); ?>
    <div class="admin-fabric-import__row">
        <div class="form-group">
            <label class="form-label" for="color-descriptions-file">Файл .xlsx</label>
            <input type="file" id="color-descriptions-file" name="color_descriptions_file" class="form-control" accept=".xlsx,.xls" required>
        </div>
        <div class="form-group">
            <label class="form-label" for="color-descriptions-overwrite">Если описание уже есть</label>
            <select id="color-descriptions-overwrite" name="overwrite_existing" class="form-control">
                <option value="1" selected>Заменять существующие</option>
                <option value="0">Не перезаписывать</option>
            </select>
        </div>
        <div class="admin-fabric-import__actions">
            <?= Html::submitButton('Импортировать описания', ['class' => 'admin-btn']) ?>
        </div>
    </div>
    <?php ActiveForm::end(); ?>
</div>

<div class="admin-card">
    <table class="admin-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Название</th>
            <th>Категория</th>
            <th>Slug</th>
            <th>Фактура</th>
            <th>Статус</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php if ($collections === []): ?>
            <tr>
                <td colspan="7">
                    <?= $searchModel->q !== null && trim($searchModel->q) !== ''
                        ? 'Ничего не найдено.'
                        : 'Коллекций пока нет.' ?>
                </td>
            </tr>
        <?php endif; ?>
        <?php
        $productSync = Yii::$container->get(CatalogModelProductSyncService::class);
        foreach ($collections as $collection):
            $linkedProductCount = $productSync->countProductsForFabricCollectionId((int)$collection->id);
            $deleteConfirm = $linkedProductCount > 0
                ? 'Удалить коллекцию «' . $collection->name . '»? Будут удалены все цвета коллекции и связанные товары (SKU): '
                    . $linkedProductCount . ' шт. Восстановить данные будет нельзя.'
                : 'Удалить коллекцию «' . $collection->name . '»? Все цвета коллекции будут удалены.';
            ?>
            <tr>
                <td><?= (int)$collection->id ?></td>
                <td>
                    <div class="admin-fabric-collection-name">
                        <?= Html::encode($collection->name) ?>
                    </div>
                    <?= AdminHtml::fabricColorSwatchesPreview($collection->colors, catalogColorsOnly: true) ?>
                </td>
                <td><?= Html::encode($collection->material_kind ?? 'Ткань') ?></td>
                <td><?= Html::encode($collection->slug) ?></td>
                <td><?= $collection->texture !== null && $collection->texture !== '' ? Html::encode($collection->texture) : '—' ?></td>
                <td>
                    <span class="admin-badge<?= $collection->is_active ? '' : ' admin-badge--rejected' ?>">
                        <?= $collection->is_active ? 'Активна' : 'Скрыта' ?>
                    </span>
                </td>
                <td class="admin-table-actions">
                    <?= AdminHtml::actionIcon(['update', 'id' => $collection->id], 'update') ?>
                    <?= AdminHtml::actionIcon(['delete', 'id' => $collection->id], 'delete', [
                        'data' => [
                            'method' => 'post',
                            'confirm' => $deleteConfirm,
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
    'depends' => [\app\modules\admin\assets\AdminAsset::class],
    'position' => View::POS_END,
    'appendTimestamp' => true,
]);
?>
