<?php

use app\models\MediaFile;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var string $bodyMarkdown */

$fieldId = 'journal-article-body-markdown';
$mediaHostId = 'journal-article-markdown-media';
?>
<div class="admin-journal-markdown" data-journal-markdown-editor>
    <textarea
        id="<?= Html::encode($fieldId) ?>"
        class="form-control admin-journal-markdown__textarea"
        name="JournalArticle[body_markdown]"
        rows="16"
    ><?= Html::encode($bodyMarkdown) ?></textarea>

    <p class="admin-muted admin-journal-markdown__hint">
        Заголовки: <code>##</code> / <code>###</code>, цитата: строки с <code>&gt;</code>, разделитель: <code>---</code>,
        галерея: <code>::: gallery columns=2</code> … <code>:::</code>, подпись к фото — строка <code>*курсив*</code> под картинкой.
        Размер фото: при вставке через «Фото» или вручную <code>ID#large</code> / <code>ID#original</code> (по умолчанию — medium).
    </p>

    <div
        class="admin-journal-markdown__media-host admin-media-library-host"
        id="<?= Html::encode($mediaHostId) ?>"
        data-kind="<?= Html::encode(MediaFile::KIND_IMAGE) ?>"
        data-upload-url="<?= Html::encode(Url::to(['/admin/media/quick-upload'])) ?>"
        data-media-delete-url="<?= Html::encode(Url::to(['/admin/media/quick-delete'])) ?>"
        data-library-list-url="<?= Html::encode(Url::to(['/admin/media/library-list', 'kind' => MediaFile::KIND_IMAGE])) ?>"
        data-folders-list-url="<?= Html::encode(Url::to(['/admin/media/folders-list'])) ?>"
        data-csrf-param="<?= Html::encode(Yii::$app->request->csrfParam) ?>"
        data-csrf-token="<?= Html::encode(Yii::$app->request->csrfToken) ?>"
    >
        <button type="button" class="admin-media-library__open-btn" data-journal-media-open hidden>Медиатека</button>
        <?= $this->render('@app/modules/admin/widgets/views/_media_library_modal', [
            'modalId' => $mediaHostId . '-modal',
            'modalTitle' => 'Медиатека',
            'isVideo' => false,
            'isDocument' => false,
            'multiSelect' => true,
            'accept' => 'image/*',
        ]) ?>
    </div>
</div>
