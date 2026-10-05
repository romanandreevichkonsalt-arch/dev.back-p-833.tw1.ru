<?php

use app\modules\admin\helpers\ContentPagePartnersHelper;

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$items = ContentPagePartnersHelper::padFormatsItemsForForm($formData['items'] ?? []);
?>
<div class="admin-page-block-section">
    <div class="admin-content-row__grid">
        <?= $this->render('_block_field', [
            'name' => 'formats_title',
            'label' => 'Заголовок',
            'value' => $formData['formats_title'] ?? '',
        ]) ?>
        <?= $this->render('_block_field', [
            'name' => 'formats_subtitle',
            'label' => 'Подзаголовок',
            'value' => $formData['formats_subtitle'] ?? '',
        ]) ?>
    </div>
</div>

<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Пункты и фото</h3>
    <p class="admin-muted admin-page-block-section__lead">Сетка «Вы получите» слева и фото справа.</p>
    <div class="admin-partners-formats-layout">
        <div class="admin-partners-formats-layout__body">
            <div class="admin-partners-formats-grid">
                <?php foreach ($items as $i => $row): ?>
                    <div class="admin-partners-formats-grid__item">
                        <?= $this->render('_block_row_header', [
                            'title' => $row['number'] ?? sprintf('%02d', $i + 1),
                            'removable' => false,
                        ]) ?>
                        <?= $this->render('_block_field', [
                            'name' => "items[{$i}][text]",
                            'label' => 'Текст',
                            'type' => 'textarea',
                            'rows' => 3,
                            'value' => $row['text'] ?? '',
                        ]) ?>
                        <input type="hidden" name="items[<?= $i ?>][number]" value="<?= htmlspecialchars($row['number'] ?? sprintf('%02d', $i + 1), ENT_QUOTES) ?>">
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="admin-partners-formats-layout__photo">
                <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                    'inputName' => 'formats_image_src',
                    'altInputName' => 'formats_image_alt',
                    'value' => $formData['formats_image_src'] ?? '',
                    'altValue' => $formData['formats_image_alt'] ?? '',
                    'label' => 'Фото справа',
                ]) ?>
            </div>
        </div>
    </div>
</div>
