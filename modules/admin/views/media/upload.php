<?php

use app\models\MediaFile;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var string $kind */
/** @var int $folderId */

$isVideo = $kind === MediaFile::KIND_VIDEO;
$isDocument = $kind === MediaFile::KIND_DOCUMENT;
$this->title = $isVideo ? 'Загрузка видео' : ($isDocument ? 'Загрузка PDF' : 'Загрузка изображения');
?>
<div class="admin-card" style="max-width:560px;">
    <form method="post" enctype="multipart/form-data" class="admin-form">
        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
        <?= Html::hiddenInput('kind', $kind) ?>
        <?php if ($folderId > 0): ?>
            <?= Html::hiddenInput('folder_id', $folderId) ?>
        <?php endif; ?>

        <div class="form-group">
            <label for="file"><?= $isVideo ? 'Видеофайл' : ($isDocument ? 'PDF-файл' : 'Изображение') ?></label>
            <input
                class="form-control"
                type="file"
                name="file"
                id="file"
                accept="<?= $isVideo
                    ? 'video/mp4,video/webm,video/quicktime'
                    : ($isDocument ? 'application/pdf,.pdf' : 'image/jpeg,image/png,image/webp,image/gif') ?>"
            >
        </div>

        <?php if (!$isVideo && !$isDocument): ?>
            <div class="form-group">
                <label for="alt">Alt-текст (необязательно)</label>
                <input class="form-control" type="text" name="alt" id="alt" maxlength="512">
            </div>
        <?php endif; ?>

        <div class="admin-actions">
            <?= Html::submitButton('Загрузить', ['class' => 'admin-btn']) ?>
            <?= Html::a('Отмена', ['index', 'kind' => $kind], ['class' => 'admin-btn admin-btn--secondary']) ?>
        </div>
    </form>
</div>
