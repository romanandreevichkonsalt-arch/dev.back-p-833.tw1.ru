<?php

use yii\helpers\Html;
use app\services\import\catalog\CatalogModelImportResult;

/** @var yii\web\View $this */
/** @var array<string, mixed> $conflict */
/** @var CatalogModelImportResult $result */

$this->title = 'Конфликт при импорте моделей';
?>
<div class="admin-card admin-card--full">
    <h2 class="admin-form-section-title">Импорт остановлен: модель уже существует</h2>

    <p class="admin-form-notice">
        В коллекции <strong><?= Html::encode((string)($conflict['collection_name'] ?? '')) ?></strong>
        уже есть модель <strong><?= Html::encode((string)($conflict['model_label'] ?? '')) ?></strong>
        (строка <?= (int)($conflict['row_number'] ?? 0) ?>).
    </p>

    <p class="admin-muted">Выберите, как обработать конфликтующие строки, и продолжите импорт.</p>

    <?= Html::beginForm(['import-resolve'], 'post', ['class' => 'admin-form']) ?>
    <div class="admin-actions">
        <?= Html::submitButton('Обновить цены и данные', [
            'class' => 'admin-btn',
            'name' => 'conflict_action',
            'value' => 'update',
        ]) ?>
        <?= Html::submitButton('Пропустить конфликты', [
            'class' => 'admin-btn admin-btn--secondary',
            'name' => 'conflict_action',
            'value' => 'skip',
        ]) ?>
        <?= Html::a('Отмена', ['index'], ['class' => 'admin-btn admin-btn--secondary']) ?>
    </div>
    <?= Html::endForm() ?>

    <?php if ($result->rows !== []): ?>
        <h3 class="admin-form-section-title" style="margin-top:24px;">Обработанные строки до остановки</h3>
        <table class="admin-table">
            <thead>
            <tr>
                <th>Строка</th>
                <th>Коллекция</th>
                <th>Модель</th>
                <th>Действие</th>
                <th>Сообщения</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($result->rows as $row): ?>
                <tr>
                    <td><?= (int)$row->rowNumber ?></td>
                    <td><?= Html::encode($row->collectionName) ?></td>
                    <td><?= Html::encode($row->modelLabel) ?></td>
                    <td><?= Html::encode($row->action) ?></td>
                    <td><?= Html::encode(implode('; ', $row->messages)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
