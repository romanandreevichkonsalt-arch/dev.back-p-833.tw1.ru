<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var string $galleryTitle */
/** @var string $pickerId */
/** @var int|null $entityId */
/** @var string $itemsJson */
/** @var string $uploadUrl */
/** @var string $libraryListUrl */
/** @var string $galleryDeleteUrl */
/** @var string $galleryAddUrl */
/** @var string $galleryReorderUrl */
/** @var string $csrfParam */
/** @var string $csrfToken */
/** @var string $galleryHint */
/** @var string $purpose */
/** @var string $defaultFolder */
/** @var string $foldersListUrl */
/** @var bool $hideTitle */
/** @var bool $hideHint */
/** @var bool $inlineHeader */
/** @var string $pendingInputName */
/** @var bool $listingTileEnabled */
/** @var string $listingTileConfigUrl */
/** @var string $listingTileLoadUrl */
/** @var string $listingTileSaveUrl */
$hideTitle = $hideTitle ?? false;
$listingTileEnabled = $listingTileEnabled ?? false;
$listingTileConfigUrl = $listingTileConfigUrl ?? '';
$listingTileLoadUrl = $listingTileLoadUrl ?? '';
$listingTileSaveUrl = $listingTileSaveUrl ?? '';
$hideHint = $hideHint ?? false;
$inlineHeader = $inlineHeader ?? false;
$pendingInputName = $pendingInputName ?? 'gallery_media_ids[]';
?>
<section
    class="admin-product-gallery admin-media-library-host<?= $inlineHeader ? ' admin-product-gallery--inline-header' : '' ?>"
    id="<?= Html::encode($pickerId) ?>"
    data-product-id="<?= (int)($entityId ?? 0) ?>"
    data-purpose="<?= Html::encode($purpose ?? '') ?>"
    data-default-folder="<?= Html::encode($defaultFolder ?? '') ?>"
    data-items="<?= Html::encode($itemsJson) ?>"
    data-upload-url="<?= Html::encode($uploadUrl) ?>"
    data-media-delete-url="<?= Html::encode(Url::to(['/admin/media/quick-delete'])) ?>"
    data-library-list-url="<?= Html::encode($libraryListUrl) ?>"
    data-folders-list-url="<?= Html::encode($foldersListUrl ?? '') ?>"
    data-gallery-add-url="<?= Html::encode($galleryAddUrl) ?>"
    data-gallery-delete-url="<?= Html::encode($galleryDeleteUrl) ?>"
    data-gallery-reorder-url="<?= Html::encode($galleryReorderUrl) ?>"
    data-pending-input-name="<?= Html::encode($pendingInputName) ?>"
    data-listing-tile-enabled="<?= $listingTileEnabled ? '1' : '0' ?>"
    data-listing-tile-config-url="<?= Html::encode($listingTileConfigUrl) ?>"
    data-listing-tile-load-url="<?= Html::encode($listingTileLoadUrl) ?>"
    data-listing-tile-save-url="<?= Html::encode($listingTileSaveUrl) ?>"
    data-csrf-param="<?= Html::encode($csrfParam) ?>"
    data-csrf-token="<?= Html::encode($csrfToken) ?>"
>
    <?php if (!$hideTitle || !$hideHint): ?>
    <div class="admin-product-gallery__header">
        <?php if ($inlineHeader && !$hideTitle): ?>
            <div class="admin-product-gallery__headline form-label">
                <span class="admin-product-gallery__title"><?= Html::encode($galleryTitle ?? 'Фото коллекции') ?></span>
                <?php if (!$hideHint): ?>
                    <span class="admin-product-gallery__hint"><?= Html::encode($galleryHint ?? 'Первое фото — основное в каталоге. Перетащите карточки, чтобы изменить порядок.') ?></span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php if (!$hideTitle): ?>
                <h2 class="admin-product-gallery__title"><?= Html::encode($galleryTitle ?? 'Фото коллекции') ?></h2>
            <?php endif; ?>
            <?php if (!$hideHint): ?>
                <p class="admin-muted"><?= Html::encode($galleryHint ?? 'Первое фото — основное в каталоге. Перетащите карточки, чтобы изменить порядок.') ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="admin-product-gallery__grid"></div>

    <div class="admin-product-gallery__toolbar">
        <div class="admin-media-upload-actions admin-product-gallery__actions">
            <button type="button" class="admin-btn admin-btn--secondary admin-media-library__open-btn">Медиатека</button>
        </div>
        <p class="admin-product-gallery__error admin-muted" hidden></p>
    </div>

    <?= $this->render('_media_library_modal', [
        'modalId' => $pickerId . '-modal',
        'modalTitle' => 'Медиатека',
        'isVideo' => false,
        'isDocument' => false,
        'multiSelect' => true,
        'accept' => 'image/*',
    ]) ?>

    <div class="admin-product-gallery__pending"></div>
</section>
