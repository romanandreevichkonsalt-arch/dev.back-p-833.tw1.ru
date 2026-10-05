<?php

use app\modules\admin\helpers\ContentPagePartnersHelper;

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$items = ContentPagePartnersHelper::padSalonFormatsForForm($formData['items'] ?? []);
$conditions = ContentPagePartnersHelper::padSalonConditionsForForm($formData['conditions'] ?? []);
?>
<div class="admin-page-block-section">
    <div class="admin-content-row__grid">
        <?= $this->render('_block_field', [
            'name' => 'salon_formats_title',
            'label' => 'Заголовок',
            'value' => $formData['salon_formats_title'] ?? '',
        ]) ?>
        <?= $this->render('_block_field', [
            'name' => 'salon_formats_subtitle',
            'label' => 'Подзаголовок',
            'value' => $formData['salon_formats_subtitle'] ?? '',
        ]) ?>
    </div>
</div>

<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Форматы салона</h3>
    <p class="admin-muted admin-page-block-section__lead">Три тарифа: Старт, Комфорт, ВИП.</p>
    <div class="admin-partners-salon-formats">
        <?php foreach ($items as $i => $row): ?>
            <div class="admin-partners-salon-formats__item">
                <?= $this->render('_block_row_header', [
                    'title' => $row['title'] ?? 'Формат ' . ($i + 1),
                    'removable' => false,
                ]) ?>
                <input type="hidden" name="items[<?= $i ?>][id]" value="<?= htmlspecialchars($row['id'] ?? '', ENT_QUOTES) ?>">
                <?= $this->render('_block_field', [
                    'name' => "items[{$i}][title]",
                    'label' => 'Название',
                    'value' => $row['title'] ?? '',
                ]) ?>
                <?= $this->render('_block_field', [
                    'name' => "items[{$i}][text]",
                    'label' => 'Описание',
                    'type' => 'textarea',
                    'rows' => 2,
                    'value' => $row['text'] ?? '',
                ]) ?>
                <div class="admin-content-row__grid">
                    <?= $this->render('_block_field', [
                        'name' => "items[{$i}][area]",
                        'label' => 'Площадь',
                        'value' => $row['area'] ?? '',
                    ]) ?>
                    <?= $this->render('_block_field', [
                        'name' => "items[{$i}][assortment]",
                        'label' => 'Ассортимент',
                        'value' => $row['assortment'] ?? '',
                    ]) ?>
                </div>
                <div class="admin-content-row__grid">
                    <?= $this->render('_block_field', [
                        'name' => "items[{$i}][profit]",
                        'label' => 'Прибыль',
                        'value' => $row['profit'] ?? '',
                    ]) ?>
                    <?= $this->render('_block_field', [
                        'name' => "items[{$i}][employees]",
                        'label' => 'Сотрудников',
                        'value' => $row['employees'] ?? '',
                    ]) ?>
                </div>
                <?= $this->render('_block_field', [
                    'name' => "items[{$i}][investment]",
                    'label' => 'Инвестиции',
                    'value' => $row['investment'] ?? '',
                ]) ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="admin-page-block-section">
    <?= $this->render('_block_field', [
        'name' => 'salon_conditions_title',
        'label' => 'Заголовок общих условий',
        'value' => $formData['salon_conditions_title'] ?? 'Общие условия партнёрства:',
    ]) ?>
    <div class="admin-content-row__grid admin-partners-salon-conditions">
        <?php foreach ($conditions as $i => $condition): ?>
            <div class="admin-partners-salon-conditions__item">
                <?= $this->render('_block_field', [
                    'name' => "conditions[{$i}][label]",
                    'label' => 'Показатель ' . ($i + 1),
                    'value' => $condition['label'] ?? '',
                ]) ?>
                <?= $this->render('_block_field', [
                    'name' => "conditions[{$i}][value]",
                    'label' => 'Значение',
                    'value' => $condition['value'] ?? '',
                ]) ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
