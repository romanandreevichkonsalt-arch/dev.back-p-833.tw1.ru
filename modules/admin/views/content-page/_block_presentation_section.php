<?php

use app\modules\admin\helpers\ContentPagePartnersHelper;

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$items = ContentPagePartnersHelper::padPresentationItemsForForm($formData['items'] ?? []);
?>
<div class="admin-page-block-section">
    <?= $this->render('_block_field', [
        'name' => 'presentation_title',
        'label' => 'Заголовок',
        'type' => 'textarea',
        'rows' => 2,
        'value' => $formData['presentation_title'] ?? '',
    ]) ?>
</div>

<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Список и фото</h3>
    <p class="admin-muted admin-page-block-section__lead">Пункты преимуществ слева, фото справа.</p>
    <div class="admin-partners-presentation-layout__body">
        <div class="admin-partners-presentation-layout__content">
            <div class="admin-partners-presentation-items">
                <?php foreach ($items as $i => $row): ?>
                    <div class="admin-partners-presentation-items__row">
                        <span class="admin-partners-presentation-items__number"><?= htmlspecialchars($row['number'] ?? sprintf('%02d', $i + 1), ENT_QUOTES) ?></span>
                        <?= $this->render('_block_field', [
                            'name' => "items[{$i}][text]",
                            'label' => 'Пункт ' . ($i + 1),
                            'value' => $row['text'] ?? '',
                        ]) ?>
                        <input type="hidden" name="items[<?= $i ?>][number]" value="<?= htmlspecialchars($row['number'] ?? sprintf('%02d', $i + 1), ENT_QUOTES) ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="admin-partners-presentation-layout__photo">
            <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                'inputName' => 'presentation_image_src',
                'altInputName' => 'presentation_image_alt',
                'value' => $formData['presentation_image_src'] ?? '',
                'altValue' => $formData['presentation_image_alt'] ?? '',
                'label' => 'Фото справа',
            ]) ?>
        </div>
    </div>
</div>

<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Файл презентации</h3>
    <p class="admin-muted admin-page-block-section__lead">Текст кнопки слева, PDF из папки «Документы» справа.</p>
    <div class="admin-partners-presentation-layout__body">
        <div class="admin-partners-presentation-layout__content">
            <?= $this->render('_block_field', [
                'name' => 'file_label',
                'label' => 'Текст кнопки',
                'value' => $formData['file_label'] ?? '',
            ]) ?>
            <?= $this->render('_block_field', [
                'name' => 'file_hint',
                'label' => 'Подпись под кнопкой',
                'value' => $formData['file_hint'] ?? '',
                'inputOptions' => ['placeholder' => 'PDF, 12 страниц'],
            ]) ?>
        </div>
        <div class="admin-partners-presentation-layout__photo">
            <?= $this->render('@app/modules/admin/views/shared/_document_picker', [
                'inputName' => 'file_url',
                'value' => $formData['file_url'] ?? '',
                'label' => 'PDF справа',
            ]) ?>
        </div>
    </div>
</div>
