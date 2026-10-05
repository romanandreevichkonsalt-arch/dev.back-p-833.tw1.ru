<?php

namespace app\modules\admin\widgets;

use app\models\CatalogModel;
use app\models\CatalogModelImage;
use app\models\MediaFile;
use app\models\MediaFolder;
use app\modules\admin\assets\AdminAsset;
use yii\base\Widget;
use yii\helpers\Json;
use yii\helpers\Url;
use Yii;

class ModelGalleryWidget extends Widget
{
    public ?CatalogModel $model = null;
    public string $purpose = CatalogModelImage::PURPOSE_ANGLE;
    public ?string $defaultFolder = MediaFolder::SLUG_ANGLES;
    public string $galleryTitle = 'Фото модели (ракурсы)';
    public string $galleryHint = 'Первое фото — основное в каталоге. Настройте кадр плитки у основного фото. Перетащите карточки, чтобы изменить порядок.';

    public function run(): string
    {
        AdminAsset::register($this->view);

        $items = [];
        if ($this->model !== null && !$this->model->isNewRecord) {
            $links = CatalogModelImage::find()
                ->where(['model_id' => $this->model->id, 'purpose' => $this->purpose])
                ->with('media')
                ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
                ->all();

            foreach ($links as $link) {
                if ($link->media === null) {
                    continue;
                }
                $items[] = [
                    'linkId' => (int)$link->id,
                    'mediaId' => (int)$link->media_file_id,
                    'url' => $link->media->getPublicUrl(),
                    'alt' => $link->media->alt ?? $link->media->filename,
                    'filename' => $link->media->filename,
                ];
            }
        }

        $modelId = $this->model?->id;

        return $this->render('collection-gallery', [
            'pickerId' => $this->getId(),
            'entityId' => $modelId,
            'galleryTitle' => $this->galleryTitle,
            'galleryHint' => $this->galleryHint,
            'purpose' => $this->purpose,
            'defaultFolder' => $this->defaultFolder ?? '',
            'pendingInputName' => $this->purpose === CatalogModelImage::PURPOSE_INTERIOR
                ? 'gallery_media_ids[interior][]'
                : 'gallery_media_ids[angle][]',
            'items' => $items,
            'itemsJson' => Json::encode($items),
            'uploadUrl' => Url::to(['/admin/media/quick-upload']),
            'libraryListUrl' => Url::to(['/admin/media/library-list', 'kind' => MediaFile::KIND_IMAGE]),
            'foldersListUrl' => Url::to(['/admin/media/folders-list']),
            'galleryAddUrl' => $modelId
                ? Url::to(['/admin/catalog-model/gallery-add', 'id' => $modelId])
                : '',
            'galleryDeleteUrl' => $modelId
                ? Url::to(['/admin/catalog-model/gallery-delete', 'id' => $modelId])
                : '',
            'galleryReorderUrl' => $modelId
                ? Url::to(['/admin/catalog-model/gallery-reorder', 'id' => $modelId])
                : '',
            'csrfParam' => Yii::$app->request->csrfParam,
            'csrfToken' => Yii::$app->request->csrfToken,
            'inlineHeader' => true,
            'listingTileEnabled' => $this->purpose === CatalogModelImage::PURPOSE_ANGLE,
            'listingTileConfigUrl' => Url::to(['/admin/media/listing-tile-config']),
            'listingTileLoadUrl' => Url::to(['/admin/media/listing-tile-load']),
            'listingTileSaveUrl' => Url::to(['/admin/media/listing-tile-save']),
        ]);
    }
}
