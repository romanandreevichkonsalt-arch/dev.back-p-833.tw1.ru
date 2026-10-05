<?php

use app\modules\admin\helpers\ContentPagePartnersHelper;

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$items = ContentPagePartnersHelper::padAudienceCardsForForm($formData['items'] ?? []);
$stats = ContentPagePartnersHelper::padAudienceStatsForForm($formData['stats'] ?? []);
?>
<div class="admin-page-block-section">
    <div class="admin-page-media-grid">
        <div class="admin-page-media-grid__item">
            <?= $this->render('_block_field', [
                'name' => 'audience_title',
                'label' => 'Заголовок блока',
                'value' => $formData['audience_title'] ?? '',
            ]) ?>
        </div>
        <div class="admin-page-media-grid__item">
            <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                'inputName' => 'audience_image_src',
                'altInputName' => 'audience_image_alt',
                'value' => $formData['audience_image_src'] ?? '',
                'altValue' => $formData['audience_image_alt'] ?? '',
                'label' => 'Баннер',
            ]) ?>
        </div>
    </div>
</div>

<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Карточки</h3>
    <p class="admin-muted admin-page-block-section__lead">Три карточки в ряд: заголовок и текст (списки — с новой строки).</p>
    <div class="admin-partners-audience-cards">
        <?php foreach ($items as $i => $row): ?>
            <div class="admin-partners-audience-cards__item">
                <?= $this->render('_block_row_header', [
                    'title' => $row['number'] ?? sprintf('%02d', $i + 1),
                    'removable' => false,
                ]) ?>
                <?= $this->render('_block_field', [
                    'name' => "items[{$i}][title]",
                    'label' => 'Заголовок',
                    'value' => $row['title'] ?? '',
                ]) ?>
                <?= $this->render('_block_field', [
                    'name' => "items[{$i}][text]",
                    'label' => 'Текст',
                    'type' => 'textarea',
                    'rows' => 4,
                    'value' => $row['text'] ?? '',
                    'hint' => 'Несколько пунктов — каждый с новой строки.',
                ]) ?>
                <input type="hidden" name="items[<?= $i ?>][number]" value="<?= htmlspecialchars($row['number'] ?? sprintf('%02d', $i + 1), ENT_QUOTES) ?>">
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Показатели</h3>
    <p class="admin-muted admin-page-block-section__lead">Три колонки под карточками.</p>
    <div class="admin-content-row__grid admin-partners-audience-stats">
        <?php foreach ($stats as $i => $stat): ?>
            <?= $this->render('_block_field', [
                'name' => "stats[{$i}][text]",
                'label' => 'Показатель ' . ($i + 1),
                'value' => $stat['text'] ?? '',
            ]) ?>
        <?php endforeach; ?>
    </div>
</div>
