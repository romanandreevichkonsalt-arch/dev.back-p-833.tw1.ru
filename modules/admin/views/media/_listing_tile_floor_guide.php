<?php

use app\services\media\ListingTileConfig;
use yii\helpers\Html;

/** @var ListingTileConfig $listingTileConfig */
/** @var int $listingTileFloorGuide */

$maxGuide = max(0, $listingTileConfig->height - 1);
?>
<div class="admin-card admin-listing-tile-floor-guide">
    <h3 class="admin-card__title" style="margin-top:0;">Кадр каталога</h3>
    <p class="admin-muted" style="margin:0 0 16px;">
        Пунктирная линия в редакторе кадра — общий ориентир для опоры дивана на всех фото каталога.
    </p>
    <p class="admin-listing-tile-floor-guide__current">
        Текущее значение: <strong><?= (int)$listingTileFloorGuide ?></strong> px от низа кадра
    </p>
    <?= Html::beginForm(['save-listing-tile-floor-guide'], 'post', ['class' => 'admin-listing-tile-floor-guide__form']) ?>
        <?= Html::hiddenInput('kind', Yii::$app->request->get('kind', 'image')) ?>
        <div class="admin-listing-tile-floor-guide__field">
            <span class="admin-listing-tile-floor-guide__label">Линия опоры от низа, px</span>
            <div class="admin-listing-tile-floor-guide__row">
                <input
                    type="number"
                    name="floor_guide_from_bottom"
                    class="form-control admin-input--narrow admin-listing-tile-floor-guide__number"
                    min="0"
                    max="<?= (int)$maxGuide ?>"
                    step="1"
                    value="<?= (int)$listingTileFloorGuide ?>"
                    required
                >
                <?= Html::submitButton('Сохранить линию', ['class' => 'admin-btn admin-btn--secondary admin-btn--sm']) ?>
            </div>
        </div>
        <p class="admin-muted admin-listing-tile-floor-guide__meta">
            Допустимо от 0 до <?= (int)$maxGuide ?> · кадр <?= (int)$listingTileConfig->width ?>×<?= (int)$listingTileConfig->height ?> px
        </p>
    <?= Html::endForm() ?>
</div>
