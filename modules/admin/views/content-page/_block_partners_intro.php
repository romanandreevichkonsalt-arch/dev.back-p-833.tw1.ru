<?php

use app\modules\admin\helpers\ContentPagePartnersHelper;

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$items = ContentPagePartnersHelper::padMissionItemsForForm($formData['items'] ?? []);
?>
<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Текст и фото</h3>
    <p class="admin-muted admin-page-block-section__lead">Текст над блоком и большое вертикальное фото в левой колонке.</p>
    <div class="admin-page-media-grid">
        <div class="admin-page-media-grid__item">
            <?= $this->render('_block_field', [
                'name' => 'intro_text',
                'label' => 'Основной текст',
                'type' => 'textarea',
                'rows' => 6,
                'value' => $formData['intro_text'] ?? '',
                'hint' => 'Центрированный абзац над фото и слайдером.',
            ]) ?>
        </div>
        <div class="admin-page-media-grid__item">
            <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                'inputName' => 'intro_image_src',
                'altInputName' => 'intro_image_alt',
                'value' => $formData['intro_image_src'] ?? '',
                'altValue' => $formData['intro_image_alt'] ?? '',
                'label' => 'Фото слева',
            ]) ?>
        </div>
    </div>
</div>

<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Слайдер преимуществ</h3>
    <p class="admin-muted admin-page-block-section__lead">
        <?= ContentPagePartnersHelper::MISSION_SLIDE_COUNT ?> слайдов справа: фото, заголовок и подзаголовок.
    </p>

    <div class="admin-home-hero-zones">
        <?php foreach ($items as $i => $row): ?>
            <div class="admin-content-row admin-home-hero-zone">
                <span class="admin-home-hero-zone__badge"><?= sprintf('%02d', $i + 1) ?></span>
                <div class="admin-home-hero-zone__fields">
                    <?= $this->render('_block_row_header', [
                        'title' => 'Слайд ' . ($i + 1),
                        'removable' => false,
                    ]) ?>
                    <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                        'inputName' => "items[{$i}][image_src]",
                        'altInputName' => "items[{$i}][image_alt]",
                        'value' => $row['image_src'] ?? '',
                        'altValue' => $row['image_alt'] ?? '',
                        'label' => 'Фото слайда',
                    ]) ?>
                    <div class="admin-content-row__grid">
                        <?= $this->render('_block_field', [
                            'name' => "items[{$i}][title]",
                            'label' => 'Заголовок',
                            'value' => $row['title'] ?? '',
                        ]) ?>
                        <?= $this->render('_block_field', [
                            'name' => "items[{$i}][text]",
                            'label' => 'Подзаголовок',
                            'value' => $row['text'] ?? '',
                        ]) ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
