<?php

namespace app\services\import\catalog;

use app\models\CatalogModel;
use app\models\CatalogModelDimensionImage;
use app\models\CatalogModelImage;
use app\models\MediaFile;
use app\models\MediaFolder;
use app\exceptions\ApiValidationException;
use app\services\catalog\CatalogModel3dFileUploadService;
use app\services\import\fabric\CloudStorageUrlResolver;
use app\services\import\fabric\MediaImportService;

class CatalogModelMediaImportService
{
    public function __construct(
        private readonly MediaImportService $mediaImport = new MediaImportService(),
        private readonly CatalogModel3dFileUploadService $file3dUpload = new CatalogModel3dFileUploadService(),
    ) {
    }

    /**
     * @return list<string> предупреждения (не удалось скачать медиа и т.п.)
     */
    public function applyToModel(CatalogModel $model, CatalogModelImportRowDto $row, bool $importMedia = true): array
    {
        $warnings = [];

        $this->applyNonDownloadFields($model, $row, $importMedia);

        if (!$importMedia) {
            return $warnings;
        }

        if ($row->file3dUrl !== null && $row->file3dUrl !== '') {
            $url = trim($row->file3dUrl);
            $warning = $this->persistFile3dFromSourceUrl($model, $url);
            if ($warning !== null) {
                $warnings[] = $warning;
            }
        }

        if ($row->videoUrl !== null && $row->videoUrl !== '') {
            $video = $this->mediaImport->importCatalogMedia(
                $row->videoUrl,
                'model-' . $model->id . '-video',
                MediaFile::KIND_VIDEO,
                MediaFolder::SLUG_VIDEO
            );
            if ($video !== null) {
                $model->video_id = (int)$video->id;
            }
        }

        if ($row->galleryPhotoUrls !== []) {
            $this->replaceGallery($model, CatalogModelImage::PURPOSE_INTERIOR, $row->galleryPhotoUrls, MediaFolder::SLUG_INTERIOR);
        }

        if ($row->dimensionPhotoUrls !== []) {
            $this->replaceDimensionImages($model, $row->dimensionPhotoUrls);
        } elseif ($row->dimensionPhotoFolderUrl !== null && $row->dimensionPhotoFolderUrl !== '') {
            CatalogModelDimensionImage::deleteAll(['model_id' => $model->id]);
        }

        return $warnings;
    }

    private function applyNonDownloadFields(CatalogModel $model, CatalogModelImportRowDto $row, bool $importMedia): void
    {
        if ($row->fittingRoomUrl !== null && $row->fittingRoomUrl !== '') {
            $model->fitting_room_url = $row->fittingRoomUrl;
        }

        if ($row->polygons3d !== null && $row->polygons3d !== '') {
            $model->polygons_3d = $row->polygons3d;
        }

        if ($row->dimensionPhotoFolderUrl !== null && $row->dimensionPhotoFolderUrl !== '') {
            $model->tech_photos_folder_url = $row->dimensionPhotoFolderUrl;
        } elseif ($importMedia && ($row->dimensionPhotoUrls !== [] || $row->galleryPhotoUrls !== [])) {
            $model->tech_photos_folder_url = null;
        }
    }

    /**
     * Скачивает файл в медиатеку и привязывает к модели. Внешний URL в БД не сохраняется.
     */
    private function persistFile3dFromSourceUrl(CatalogModel $model, string $url): ?string
    {
        try {
            $this->file3dUpload->uploadFromUrl($model, $url);
        } catch (ApiValidationException $exception) {
            $detail = CloudStorageUrlResolver::unsupportedReason($url);

            return $detail ?? $exception->getMessage();
        }

        return null;
    }

    /**
     * @param list<string> $urls
     */
    private function replaceGallery(CatalogModel $model, string $purpose, array $urls, string $folderSlug): void
    {
        CatalogModelImage::deleteAll(['model_id' => $model->id, 'purpose' => $purpose]);

        $sortOrder = 0;
        foreach ($urls as $url) {
            $media = $this->mediaImport->importCatalogMedia(
                $url,
                'model-' . $model->id . '-' . $purpose . '-' . ($sortOrder + 1),
                MediaFile::KIND_IMAGE,
                $folderSlug
            );
            if ($media === null) {
                continue;
            }

            $link = new CatalogModelImage([
                'model_id' => $model->id,
                'media_file_id' => (int)$media->id,
                'purpose' => $purpose,
                'sort_order' => $sortOrder,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $link->save(false);
            $sortOrder++;
        }
    }

    /**
     * @param list<string> $urls
     */
    private function replaceDimensionImages(CatalogModel $model, array $urls): void
    {
        CatalogModelDimensionImage::deleteAll(['model_id' => $model->id]);

        $sortOrder = 0;
        foreach ($urls as $url) {
            $media = $this->mediaImport->importCatalogMedia(
                $url,
                'model-' . $model->id . '-tech-' . ($sortOrder + 1),
                MediaFile::KIND_IMAGE,
                MediaFolder::SLUG_TECH
            );
            if ($media === null) {
                continue;
            }

            $link = new CatalogModelDimensionImage([
                'model_id' => $model->id,
                'media_file_id' => (int)$media->id,
                'sort_order' => $sortOrder,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $link->save(false);
            $sortOrder++;
        }
    }
}
