<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var string $title */
/** @var app\services\import\surface\SurfaceMaterialRegistryImportResult $result */
/** @var app\models\CatalogImportRun|null $importRun */

$this->title = $title;
$stats = $result->stats;
?>
<div class="admin-toolbar">
    <?= Html::a('К списку материалов', ['index'], ['class' => 'admin-btn admin-btn--secondary']) ?>
</div>

<div class="admin-card admin-card--full">
    <h2 class="admin-form-section-title"><?= Html::encode($title) ?></h2>

    <?php if ($importRun !== null): ?>
        <p class="admin-muted">
            Файл: <?= Html::encode($importRun->filename) ?> ·
            <?= Html::encode($importRun->created_at) ?>
        </p>
    <?php endif; ?>

    <div class="admin-import-stats">
        <span class="admin-badge">Создано: <?= (int)($stats['materials_created'] ?? $stats['links_created'] ?? 0) ?></span>
        <span class="admin-badge">Обновлено: <?= (int)($stats['materials_updated'] ?? $stats['links_updated'] ?? 0) ?></span>
        <span class="admin-badge">Пропущено: <?= (int)($stats['rows_skipped'] ?? 0) ?></span>
        <span class="admin-badge">Конфликты: <?= (int)($stats['materials_conflict'] ?? $stats['links_conflict'] ?? 0) ?></span>
        <span class="admin-badge">Фото: <?= (int)($stats['photo_imported'] ?? 0) ?></span>
        <span class="admin-badge">Текстуры: <?= (int)($stats['texture_imported'] ?? 0) ?></span>
        <span class="admin-badge<?= ($stats['errors'] ?? 0) > 0 ? ' admin-badge--rejected' : '' ?>">Ошибки: <?= (int)($stats['errors'] ?? 0) ?></span>
    </div>

    <table class="admin-table admin-table--spaced">
        <thead>
        <tr>
            <th>Строка</th>
            <th>Тип</th>
            <th>Название</th>
            <th>Действие</th>
            <th>Фото</th>
            <th>Текстура</th>
            <th>Примечание</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($result->rows === []): ?>
            <tr><td colspan="7">Нет строк для импорта.</td></tr>
        <?php endif; ?>
        <?php foreach ($result->rows as $row): ?>
            <tr>
                <td><?= (int)$row->rowNumber ?></td>
                <td><?= Html::encode($row->materialType) ?></td>
                <td><?= Html::encode($row->name) ?></td>
                <td><?= Html::encode($row->action) ?></td>
                <td><?= Html::encode($row->photoImported ? 'ok' : ($row->photoSkipped ? 'skipped' : '—')) ?></td>
                <td><?= Html::encode($row->textureImported ? 'ok' : ($row->textureSkipped ? 'skipped' : '—')) ?></td>
                <td><?= Html::encode(implode('; ', array_merge($row->messages, $row->warnings))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
