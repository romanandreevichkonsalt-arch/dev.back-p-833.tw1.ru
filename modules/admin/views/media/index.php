<?php

use app\models\MediaFile;
use app\models\MediaFolder;
use app\modules\admin\helpers\AdminHtml;
use app\services\media\ListingTileConfig;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var string $activeKind */
/** @var int $activeFolderId */
/** @var MediaFolder[] $folders */
/** @var ListingTileConfig $listingTileConfig */
/** @var int $listingTileFloorGuide */

$this->title = 'Медиатека';

$models = $dataProvider->getModels();
$kindLabels = MediaFile::kindLabels();
$kindRoute = $activeKind !== '' ? $activeKind : 'all';

$kindTabs = [];
foreach ($kindLabels as $kind => $label) {
    $kindTabs[$kind] = [
        'label' => $label,
        'url' => ['index', 'kind' => $kind, 'folder_id' => $activeFolderId],
    ];
}
$kindTabs['all'] = [
    'label' => 'Все',
    'url' => ['index', 'kind' => 'all', 'folder_id' => $activeFolderId],
];

$folderTabs = [
    0 => [
        'label' => 'Все папки',
        'url' => ['index', 'kind' => $kindRoute],
    ],
];
foreach ($folders as $folder) {
    $folderTabs[(int)$folder->id] = [
        'label' => $folder->label,
        'url' => ['index', 'kind' => $kindRoute, 'folder_id' => $folder->id],
    ];
}
?>
<div class="admin-toolbar">
    <div>
        <p style="margin:0;color:#6b6862;">Локальное хранилище: <code>web/uploads/media</code></p>
    </div>
    <div class="admin-media-upload-actions">
        <?= Html::a('Загрузить изображение', ['upload', 'kind' => MediaFile::KIND_IMAGE, 'folder_id' => $activeFolderId], ['class' => 'admin-btn admin-btn--secondary']) ?>
        <?= Html::a('Загрузить видео', ['upload', 'kind' => MediaFile::KIND_VIDEO, 'folder_id' => $activeFolderId], ['class' => 'admin-btn admin-btn--secondary']) ?>
        <?= Html::a('Загрузить PDF', ['upload', 'kind' => MediaFile::KIND_DOCUMENT, 'folder_id' => $activeFolderId], ['class' => 'admin-btn']) ?>
    </div>
</div>

<?= $this->render('_listing_tile_floor_guide', [
    'listingTileConfig' => $listingTileConfig,
    'listingTileFloorGuide' => $listingTileFloorGuide,
]) ?>

<?= AdminHtml::pageTabs($kindTabs, $kindRoute, 'Тип файла') ?>

<div class="admin-media-folders admin-media-folders--page">
    <?= AdminHtml::pageTabs($folderTabs, $activeFolderId, 'Папки', true) ?>
</div>

<?php if ($models === []): ?>
    <div class="admin-card">
        <p style="margin:0;color:#6b6862;">Файлов пока нет.</p>
    </div>
<?php else: ?>
    <div class="admin-media-grid">
        <?php /** @var MediaFile $model */ ?>
        <?php foreach ($models as $model): ?>
            <div class="admin-media-card">
                <?php if ($model->isVideo()): ?>
                    <video src="<?= Html::encode($model->getPublicUrl()) ?>" controls preload="metadata"></video>
                <?php elseif ($model->isImage()): ?>
                    <img src="<?= Html::encode($model->getPublicUrl('mini')) ?>" alt="<?= Html::encode($model->alt ?? '') ?>">
                <?php elseif ($model->isDocument()): ?>
                    <div class="admin-media-card__doc-thumb">PDF</div>
                <?php else: ?>
                    <div style="height:140px;display:grid;place-items:center;background:#eee;">Файл</div>
                <?php endif; ?>
                <div class="admin-media-card__body">
                    <strong><?= Html::encode($model->filename) ?></strong>
                    <div class="admin-media-card__meta">
                        <?= Html::encode($model->folder?->label ?? $kindLabels[$model->kind] ?? $model->kind) ?>
                        · <?= Html::encode($model->getFormattedSize()) ?>
                    </div>
                    <div class="admin-media-card__meta"><?= Html::encode($model->getPublicUrl()) ?></div>
                    <div class="admin-actions admin-table-actions" style="margin-top:12px;">
                        <?= AdminHtml::actionIcon(['update', 'id' => $model->id], 'update') ?>
                        <?= AdminHtml::actionIcon(['delete', 'id' => $model->id], 'delete', [
                            'class' => 'admin-icon-btn admin-icon-btn--danger',
                            'data-confirm' => 'Удалить файл?',
                        ]) ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div style="margin-top:20px;">
        <?= \yii\widgets\LinkPager::widget(['pagination' => $dataProvider->pagination]) ?>
    </div>
<?php endif; ?>
