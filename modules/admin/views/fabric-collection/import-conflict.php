<?php

use app\services\import\fabric\FabricRegistryImportOptions;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array<string, mixed> $conflict */
/** @var app\services\import\fabric\FabricRegistryImportResult $result */

$this->title = 'Конфликт при импорте';
?>
<div class="admin-card admin-card--full">
    <h2 class="admin-form-section-title">Импорт остановлен: конфликт кода в коллекции</h2>

    <p class="admin-form-notice">
        В коллекции <strong><?= Html::encode((string)($conflict['collection_name'] ?? '')) ?></strong>
        уже есть код <code><?= Html::encode((string)($conflict['design_code'] ?? '')) ?></code>.
    </p>

    <?php if (!empty($conflict['existing'])): ?>
        <p><strong>В базе:</strong> <?= Html::encode(json_encode($conflict['existing'], JSON_UNESCAPED_UNICODE)) ?></p>
    <?php endif; ?>
    <?php if (!empty($conflict['incoming'])): ?>
        <p><strong>В файле:</strong> <?= Html::encode(json_encode($conflict['incoming'], JSON_UNESCAPED_UNICODE)) ?></p>
    <?php endif; ?>

    <p class="admin-muted">Загрузите файл снова и выберите действие для конфликтующих строк.</p>

    <div class="admin-actions">
        <?= Html::a('К импорту', ['index', '#' => 'fabric-import'], ['class' => 'admin-btn']) ?>
    </div>

    <h3 class="admin-form-section-title">Обработанные строки до остановки</h3>
    <?= $this->render('import-report', [
        'title' => 'Частичный отчёт',
        'result' => $result,
        'importRun' => null,
    ]) ?>
</div>
