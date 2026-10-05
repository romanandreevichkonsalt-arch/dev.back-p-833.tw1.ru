<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var string $modalId */
/** @var string $modalTitle */
/** @var bool $isVideo */
/** @var bool $isDocument */
/** @var bool $multiSelect */
/** @var string $accept */
?>
<div class="admin-modal admin-media-library__modal" hidden>
    <div class="admin-modal__backdrop" data-modal-close="1"></div>
    <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="<?= Html::encode($modalId) ?>-title">
        <div class="admin-modal__header">
            <h3 class="admin-modal__title" id="<?= Html::encode($modalId) ?>-title"><?= Html::encode($modalTitle) ?></h3>
            <button type="button" class="admin-modal__close" data-modal-close="1" aria-label="Закрыть">&times;</button>
        </div>
        <div class="admin-modal__body">
            <div class="admin-media-library__folders-row">
                <div class="admin-page-tabs admin-page-tabs--compact admin-media-library__folders"></div>
                <button type="button" class="admin-btn admin-media-library__upload-btn">Загрузить</button>
                <input
                    type="file"
                    class="admin-media-library__file"
                    accept="<?= Html::encode($accept) ?>"
                    <?= $multiSelect ? 'multiple' : '' ?>
                    hidden
                >
            </div>
            <p class="admin-media-library__upload-warning admin-muted" hidden></p>
            <input
                type="search"
                class="form-control admin-media-library__search"
                placeholder="Поиск по имени файла"
                autocomplete="off"
            >
            <div class="admin-media-library__sections">
                <div class="admin-media-library__section admin-media-library__section--linked" hidden>
                    <p class="admin-media-library__section-title">Уже в блоке</p>
                    <div class="admin-product-gallery__modal-grid admin-media-library__grid admin-media-library__grid--linked"></div>
                </div>
                <div class="admin-media-library__section admin-media-library__section--library">
                    <p class="admin-media-library__section-title admin-media-library__section-title--library" hidden>Медиатека</p>
                    <div class="admin-product-gallery__modal-grid admin-media-library__grid admin-media-library__grid--library">
                        <p class="admin-muted admin-product-gallery__modal-empty">Загрузка...</p>
                    </div>
                </div>
            </div>
            <div class="admin-modal admin-media-library__folder-pick" hidden>
                <div class="admin-modal__backdrop" data-folder-pick-close="1"></div>
                <div class="admin-modal__dialog admin-media-library__folder-pick-dialog" role="dialog" aria-modal="true">
                    <div class="admin-modal__header">
                        <h3 class="admin-modal__title">Выберите папку</h3>
                        <button type="button" class="admin-modal__close" data-folder-pick-close="1" aria-label="Закрыть">&times;</button>
                    </div>
                    <div class="admin-modal__body">
                        <label class="admin-media-library__folder-pick-label">Папка для загрузки</label>
                        <select class="form-control admin-media-library__folder-pick-select"></select>
                    </div>
                    <div class="admin-modal__footer">
                        <button type="button" class="admin-btn admin-btn--secondary" data-folder-pick-close="1">Отмена</button>
                        <button type="button" class="admin-btn admin-media-library__folder-pick-confirm">Загрузить</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="admin-modal__footer">
            <button type="button" class="admin-btn admin-btn--secondary" data-modal-close="1">Отмена</button>
            <?php if ($multiSelect): ?>
                <button type="button" class="admin-btn admin-media-library__apply-btn">Добавить выбранные</button>
            <?php endif; ?>
        </div>
    </div>
</div>
