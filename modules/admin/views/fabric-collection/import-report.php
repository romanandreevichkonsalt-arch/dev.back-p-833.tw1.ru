<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var string $title */
/** @var app\services\import\fabric\FabricRegistryImportResult $result */
/** @var app\models\CatalogImportRun|null $importRun */

$this->title = $title;
$stats = $result->stats;
?>
<div class="admin-toolbar">
    <?= Html::a('К списку тканей', ['index'], ['class' => 'admin-btn admin-btn--secondary']) ?>
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
        <span class="admin-badge">Создано: <?= (int)($stats['links_created'] ?? 0) ?></span>
        <span class="admin-badge">Обновлено: <?= (int)($stats['links_updated'] ?? 0) ?></span>
        <span class="admin-badge">Пропущено: <?= (int)($stats['rows_skipped'] ?? 0) ?></span>
        <span class="admin-badge">Конфликты: <?= (int)($stats['links_conflict'] ?? 0) ?></span>
        <span class="admin-badge<?= ($stats['errors'] ?? 0) > 0 ? ' admin-badge--rejected' : '' ?>">Ошибки: <?= (int)($stats['errors'] ?? 0) ?></span>
    </div>

    <table class="admin-table admin-table--spaced">
        <thead>
        <tr>
            <th>Строка</th>
            <th>Коллекция</th>
            <th>Название</th>
            <th>Цвет</th>
            <th>Действие</th>
            <th>Фото</th>
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
                <td><?= Html::encode($row->collectionName) ?></td>
                <td><code><?= Html::encode($row->designCode) ?></code></td>
                <td><?= Html::encode($row->colorLabel ?? '—') ?></td>
                <td><?= Html::encode($row->action) ?></td>
                <td><?= Html::encode($row->photoImported ? 'ok' : ($row->photoSkipped ? 'skipped' : 'none')) ?></td>
                <td><?= Html::encode(implode('; ', $row->messages)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
