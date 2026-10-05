<?php

use app\models\MediaFile;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var string $pickerId */
/** @var string $mode */
/** @var string $kind */
/** @var string $inputName */
/** @var string|null $altInputName */
/** @var mixed $value */
/** @var string|null $altValue */
/** @var string $label */
/** @var bool $allowClear */
/** @var bool $compact */
/** @var string $previewUrl */
/** @var string $previewAlt */
/** @var string $previewMime */
/** @var string $defaultFolder */
/** @var bool $enableListingTile */
/** @var string $listingTileLoadUrl */
/** @var string $listingTileSaveUrl */
/** @var string $listingTileConfigUrl */
$enableListingTile = $enableListingTile ?? false;

$isVideo = $kind === MediaFile::KIND_VIDEO;
$isDocument = $kind === MediaFile::KIND_DOCUMENT;
$emptyLabel = $isVideo ? 'Видео не выбрано' : ($isDocument ? 'PDF не выбран' : 'Фото не выбрано');
$accept = $isVideo
    ? 'video/mp4,video/webm,video/quicktime'
    : ($isDocument ? 'application/pdf,.pdf,application/zip,.zip' : 'image/*');

$previewFilename = '';
if ($isDocument && $previewUrl !== '') {
    $path = parse_url($previewUrl, PHP_URL_PATH);
    $previewFilename = $path !== null && $path !== '' ? basename($path) : $previewUrl;
}
?>
<div
    class="admin-media-picker admin-media-library-host<?= $isVideo ? ' admin-media-picker--video' : '' ?><?= $isDocument ? ' admin-media-picker--document' : '' ?><?= $compact ? ' admin-media-picker--compact' : '' ?>"
    id="<?= Html::encode($pickerId) ?>"
    data-mode="<?= Html::encode($mode) ?>"
    data-kind="<?= Html::encode($kind) ?>"
    data-upload-url="<?= Html::encode($uploadUrl) ?>"
    data-media-delete-url="<?= Html::encode($mediaDeleteUrl) ?>"
    data-default-folder="<?= Html::encode($defaultFolder ?? '') ?>"
    data-folders-list-url="<?= Html::encode($foldersListUrl ?? '') ?>"
    data-library-list-url="<?= Html::encode($libraryListUrl) ?>"
    data-csrf-param="<?= Html::encode($csrfParam) ?>"
    data-csrf-token="<?= Html::encode($csrfToken) ?>"
    data-listing-tile-enabled="<?= $enableListingTile ? '1' : '0' ?>"
    data-listing-tile-load-url="<?= Html::encode($listingTileLoadUrl ?? '') ?>"
    data-listing-tile-save-url="<?= Html::encode($listingTileSaveUrl ?? '') ?>"
    data-listing-tile-config-url="<?= Html::encode($listingTileConfigUrl ?? '') ?>"
>
    <div class="form-group">
        <label><?= Html::encode($label) ?></label>

        <div class="admin-media-picker__preview<?= $previewUrl === '' ? ' is-empty' : '' ?>">
            <?php if ($previewUrl !== '' && $isVideo): ?>
                <video src="<?= Html::encode($previewUrl) ?>" controls preload="metadata"></video>
            <?php elseif ($previewUrl !== '' && $isDocument): ?>
                <a class="admin-media-picker__document" href="<?= Html::encode($previewUrl) ?>" target="_blank" rel="noopener noreferrer">
                    <span class="admin-media-picker__document-icon">PDF</span>
                    <span class="admin-media-picker__document-name"><?= Html::encode($previewFilename) ?></span>
                </a>
            <?php elseif ($previewUrl !== ''): ?>
                <img src="<?= Html::encode($previewUrl) ?>" alt="<?= Html::encode($previewAlt) ?>">
            <?php else: ?>
                <span class="admin-media-picker__placeholder"><?= Html::encode($emptyLabel) ?></span>
            <?php endif; ?>
            <?php if ($enableListingTile): ?>
                <button
                    type="button"
                    class="admin-icon-btn admin-media-picker__listing-tile-btn"
                    title="Кадр каталога"
                    aria-label="Кадр каталога"
                    hidden
                >
                    <svg class="admin-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M6 2v4H2"></path>
                        <path d="M18 22v-4h4"></path>
                        <path d="M22 6h-4V2"></path>
                        <path d="M2 18h4v4"></path>
                        <rect x="7" y="7" width="10" height="10" rx="1"></rect>
                    </svg>
                </button>
            <?php endif; ?>
        </div>

        <input
            type="hidden"
            class="admin-media-picker__value"
            name="<?= Html::encode($inputName) ?>"
            value="<?= Html::encode((string)($value ?? '')) ?>"
        >

        <?php if ($altInputName !== null): ?>
            <div class="admin-media-picker__alt" style="margin-top:12px;">
                <label>Alt</label>
                <input
                    type="text"
                    class="form-control admin-media-picker__alt-input"
                    name="<?= Html::encode($altInputName) ?>"
                    value="<?= Html::encode((string)($altValue ?? '')) ?>"
                >
            </div>
        <?php endif; ?>
    </div>

    <div class="admin-media-picker__toolbar">
        <div class="admin-media-upload-actions">
            <button type="button" class="admin-btn admin-btn--secondary admin-media-library__open-btn">Медиатека</button>
            <?php if ($allowClear): ?>
                <button type="button" class="admin-btn admin-btn--secondary admin-media-picker__clear-btn">Убрать</button>
            <?php endif; ?>
        </div>
        <p class="admin-media-picker__error admin-muted" hidden></p>
    </div>

    <?= $this->render('_media_library_modal', [
        'modalId' => $pickerId . '-modal',
        'modalTitle' => $isVideo ? 'Видеотека' : ($isDocument ? 'Документы' : 'Медиатека'),
        'isVideo' => $isVideo,
        'isDocument' => $isDocument,
        'multiSelect' => false,
        'accept' => $accept,
    ]) ?>
</div>
