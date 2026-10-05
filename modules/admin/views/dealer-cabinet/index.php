<?php

use app\models\DealerPriceList;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var DealerPriceList|null $globalPriceList */

$this->title = 'ЛК дилера — настройки';
?>
<div class="admin-toolbar">
    <?= Html::a('← Пользователи', ['/admin/user/index', 'tab' => 'dealers'], ['class' => 'admin-link']) ?>
    <?= Html::a('Менеджеры', ['/admin/user/index', 'tab' => 'managers'], ['class' => 'admin-link']) ?>
</div>

<div class="admin-card">
    <h2 class="admin-card__title">Общий прайс-лист</h2>
    <p class="admin-muted">Доступен всем дилерам в ЛК. Форматы: PDF, Excel (xlsx, xls), Word (doc, docx), ZIP.</p>

    <?php if ($globalPriceList !== null && $globalPriceList->mediaFile !== null): ?>
        <p>
            Текущий файл:
            <strong><?= Html::encode($globalPriceList->mediaFile->filename) ?></strong>
            (<?= Html::encode($globalPriceList->updated_at) ?>)
        </p>
    <?php else: ?>
        <p class="admin-muted">Файл не загружен — используется legacy-настройка из params/медиатеки, если есть.</p>
    <?php endif; ?>

    <?= Html::beginForm(['upload-global-price-list'], 'post', ['enctype' => 'multipart/form-data', 'class' => 'admin-form']) ?>
    <div class="admin-form-grid">
        <div class="admin-form-field">
            <label class="form-label" for="global-price-label">Название в ЛК</label>
            <input type="text" id="global-price-label" name="label" class="form-control" value="<?= Html::encode($globalPriceList->label ?? 'Общий прайс-лист') ?>">
        </div>
        <div class="admin-form-field">
            <label class="form-label" for="global-price-file">Файл</label>
            <input type="file" id="global-price-file" name="price_list_file" class="form-control" accept=".pdf,.xlsx,.xls,.doc,.docx,.zip" required>
        </div>
    </div>
    <div class="admin-form-actions">
        <?= Html::submitButton('Загрузить / заменить', ['class' => 'admin-btn']) ?>
    </div>
    <?= Html::endForm() ?>
</div>

<p class="admin-muted">Индивидуальный прайс загружается на странице редактирования конкретного дилера.</p>
