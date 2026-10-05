<?php

namespace app\services\catalog;

use app\exceptions\ApiValidationException;
use app\models\CatalogModel;
use app\models\MediaFile;
use app\models\MediaFolder;
use app\services\import\fabric\MediaImportService;
use app\services\media\LocalMediaStorage;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

class CatalogModel3dFileUploadService
{
    public function __construct(
        private readonly MediaImportService $mediaImport = new MediaImportService(),
        private readonly LocalMediaStorage $storage = new LocalMediaStorage(),
    ) {
    }

    /**
     * @return array{modelSlug: string, sourceUrl: string|null, fileUrl: string, mediaId: int, filename: string}
     */
    public function uploadFromUrl(CatalogModel $model, ?string $urlOverride = null): array
    {
        $url = trim((string)($urlOverride !== null && $urlOverride !== '' ? $urlOverride : $model->file_3d_url));
        if ($url === '') {
            throw new ApiValidationException('Не указана ссылка на файл 3D.', [
                'url' => ['Передайте url или сохраните «Ссылка на файл 3D» у модели.'],
            ]);
        }

        if (!preg_match('~^https?://~i', $url)) {
            throw new ApiValidationException('Некорректная ссылка на файл 3D.', [
                'url' => ['Допустимы только http/https URL.'],
            ]);
        }

        $media = $this->mediaImport->importCatalogMedia(
            $url,
            'model-' . (int)$model->id . '-3d',
            MediaFile::KIND_DOCUMENT,
            MediaFolder::SLUG_MODELS_3D
        );

        if ($media === null) {
            throw new ApiValidationException('Не удалось скачать файл 3D по ссылке.', [
                'url' => ['Ссылка недоступна или не является прямой загрузкой файла.'],
            ]);
        }

        return $this->attachMedia($model, $media, $url);
    }

    /**
     * @return array{modelSlug: string, sourceUrl: string|null, fileUrl: string, mediaId: int, filename: string}
     */
    public function uploadFromFile(CatalogModel $model, UploadedFile $file): array
    {
        if ($file->hasError) {
            throw new ApiValidationException('Не удалось загрузить файл.', [
                'file' => ['Ошибка загрузки файла.'],
            ]);
        }

        $media = $this->storage->upload(
            $file,
            'model-' . (int)$model->id . '-3d',
            MediaFile::KIND_DOCUMENT,
            null,
            MediaFolder::SLUG_MODELS_3D
        );

        return $this->attachMedia($model, $media, $model->file_3d_url);
    }

    public function findModelBySlug(string $slug): CatalogModel
    {
        $model = CatalogModel::find()->where(['slug' => $slug])->one();
        if ($model === null) {
            throw new NotFoundHttpException('Модель не найдена.');
        }

        return $model;
    }

    /**
     * @return array{modelSlug: string, sourceUrl: string|null, fileUrl: string, mediaId: int, filename: string}
     */
    private function attachMedia(CatalogModel $model, MediaFile $media, ?string $sourceUrl): array
    {
        $model->file_3d_id = (int)$media->id;
        $model->file_3d_url = null;

        if (!$model->save(false, ['file_3d_id', 'file_3d_url', 'updated_at'])) {
            throw new ApiValidationException('Не удалось сохранить файл 3D у модели.');
        }

        return [
            'modelSlug' => (string)$model->slug,
            'sourceUrl' => $sourceUrl !== null && trim($sourceUrl) !== '' ? trim($sourceUrl) : null,
            'fileUrl' => $media->getPublicUrl(),
            'mediaId' => (int)$media->id,
            'filename' => (string)$media->filename,
        ];
    }
}
