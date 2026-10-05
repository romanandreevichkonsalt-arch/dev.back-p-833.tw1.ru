<?php

use app\modules\admin\helpers\HomePagePartnersHelper;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$cards = $formData['cards'] ?? [];
while (count($cards) < HomePagePartnersHelper::CARD_COUNT) {
    $cards[] = [
        'title' => '',
        'href' => '',
        'text' => '',
        'image_src' => '',
        'image_alt' => '',
    ];
}

$cardLabels = [
    0 => 'Карточка 1',
    1 => 'Карточка 2',
];
?>
<div class="admin-page-block-section">
    <?= $this->render('_block_field', [
        'name' => 'intro',
        'label' => 'Текст',
        'type' => 'textarea',
        'rows' => 4,
        'value' => $formData['intro'] ?? '',
    ]) ?>
</div>

<div class="admin-page-block-section">
    <p class="admin-muted admin-page-block-section__lead">
        Две карточки под текстом: заголовок, подзаголовок, ссылка и фото.
    </p>

    <div class="admin-home-hero-zones">
        <?php foreach ($cards as $i => $card): ?>
            <?php if ($i >= HomePagePartnersHelper::CARD_COUNT) {
                break;
            } ?>
            <div class="admin-content-row admin-home-hero-zone">
                <span class="admin-home-hero-zone__badge"><?= $i + 1 ?></span>
                <div class="admin-home-hero-zone__fields">
                    <?= $this->render('_block_row_header', [
                        'title' => $cardLabels[$i] ?? 'Карточка ' . ($i + 1),
                        'removable' => false,
                    ]) ?>
                    <?= $this->render('_block_field', [
                        'name' => "cards[{$i}][title]",
                        'label' => 'Заголовок',
                        'value' => $card['title'] ?? '',
                    ]) ?>
                    <?= $this->render('_block_field', [
                        'name' => "cards[{$i}][text]",
                        'label' => 'Подзаголовок',
                        'type' => 'textarea',
                        'rows' => 3,
                        'value' => $card['text'] ?? '',
                    ]) ?>
                    <?= $this->render('_block_field', [
                        'name' => "cards[{$i}][href]",
                        'label' => 'Ссылка',
                        'value' => $card['href'] ?? '',
                        'hint' => 'Например: /partners или /designers',
                    ]) ?>
                    <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                        'inputName' => "cards[{$i}][image_src]",
                        'altInputName' => "cards[{$i}][image_alt]",
                        'value' => $card['image_src'] ?? '',
                        'altValue' => $card['image_alt'] ?? '',
                        'label' => 'Фото',
                    ]) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
