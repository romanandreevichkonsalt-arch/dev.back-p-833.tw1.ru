<?php

use app\modules\admin\helpers\HomePageCollectionsHelper;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */
/** @var array<int, string> $catalogDirectionOptions */
/** @var bool $withPhilosophy */

$catalogDirectionOptions = $catalogDirectionOptions ?? HomePageCollectionsHelper::directionOptions();
$withPhilosophy = $withPhilosophy ?? false;
$cards = HomePageCollectionsHelper::padCardsForForm($formData['cards'] ?? []);
?>
<?php if ($withPhilosophy): ?>
<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Философия бренда</h3>
    <p class="admin-muted admin-page-block-section__lead">Белый блок под главным фото с иконкой и абзацем.</p>
    <?= $this->render('_block_field', [
        'name' => 'philosophy_text',
        'label' => 'Текст',
        'type' => 'textarea',
        'rows' => 5,
        'value' => $formData['philosophy_text'] ?? '',
    ]) ?>
</div>
<?php endif; ?>

<div class="admin-page-block-section">
    <p class="admin-muted admin-page-block-section__lead">
        Две карточки на главной. Направления настраиваются в
        <?= Html::a('Настройки → Направления', ['/admin/settings-direction/index'], ['class' => 'admin-link']) ?>.
        В каждой карточке — <?= HomePageCollectionsHelper::SLIDES_PER_CARD ?> фото с заголовком.
    </p>

    <div class="admin-home-hero-zones">
        <?php foreach ($cards as $i => $card): ?>
            <div class="admin-content-row admin-home-hero-zone">
                <span class="admin-home-hero-zone__badge"><?= $i + 1 ?></span>
                <div class="admin-home-hero-zone__fields">
                    <?= $this->render('_block_row_header', [
                        'title' => 'Карточка ' . ($i + 1),
                        'removable' => false,
                    ]) ?>
                    <div class="admin-page-field">
                        <label class="form-label" for="cards_<?= $i ?>_direction">Направление</label>
                        <?= Html::dropDownList(
                            "cards[{$i}][catalog_direction_id]",
                            $card['catalog_direction_id'] ?? '',
                            $catalogDirectionOptions,
                            [
                                'class' => 'form-control',
                                'id' => "cards_{$i}_direction",
                                'prompt' => '— Выберите направление —',
                            ]
                        ) ?>
                        <p class="admin-field-hint">Обязательно выберите направление — иначе фото и заголовки этой карточки не сохранятся.</p>
                        <?= Html::hiddenInput("cards[{$i}][title_uppercase]", !empty($card['title_uppercase']) ? '1' : '') ?>
                    </div>

                    <div class="admin-home-collections-slides">
                        <?php foreach ($card['slides'] as $j => $slide): ?>
                            <div class="admin-home-collections-slides__item">
                                <?= $this->render('_block_row_header', [
                                    'title' => 'Фото ' . ($j + 1),
                                    'removable' => false,
                                ]) ?>
                                <?= $this->render('_block_field', [
                                    'name' => "cards[{$i}][slides][{$j}][label]",
                                    'label' => 'Заголовок',
                                    'value' => $slide['label'] ?? '',
                                ]) ?>
                                <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                                    'inputName' => "cards[{$i}][slides][{$j}][image_src]",
                                    'altInputName' => "cards[{$i}][slides][{$j}][image_alt]",
                                    'value' => $slide['image_src'] ?? '',
                                    'altValue' => $slide['image_alt'] ?? '',
                                    'label' => 'Фото',
                                ]) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
