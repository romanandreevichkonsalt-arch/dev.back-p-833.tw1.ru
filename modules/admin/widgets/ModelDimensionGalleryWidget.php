<?php

namespace app\modules\admin\widgets;

use app\models\CatalogModel;
use app\models\CatalogModelDimensionImage;
use app\models\MediaFile;
use app\models\MediaFolder;
use app\modules\admin\assets\AdminAsset;
use yii\base\Widget;
use yii\helpers\Json;
use yii\helpers\Url;
use Yii;

class ModelDimensionGalleryWidget extends Widget
{
    public ?CatalogModel $model = null;
    public bool $hideTitle = false;
    public bool $hideHint = false;

    public function run(): string
    {
        AdminAsset::register($this->view);

        $items = [];
        if ($this->model !== null && !$this->model->isNewRecord) {
            $links = CatalogModelDimensionImage::find()
                ->where(['model_id' => $this->model->id])
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
            'galleryTitle' => 'Тех. фото габаритов',
            'galleryHint' => 'Чертежи и фото с размерами. Перетащите карточки, чтобы изменить порядок.',
            'hideTitle' => $this->hideTitle,
            'hideHint' => $this->hideHint,
            'purpose' => '',
            'defaultFolder' => MediaFolder::SLUG_TECH,
            'pendingInputName' => 'dimension_media_ids[]',
            'foldersListUrl' => Url::to(['/admin/media/folders-list']),
            'items' => $items,
            'itemsJson' => Json::encode($items),
            'uploadUrl' => Url::to(['/admin/media/quick-upload']),
            'libraryListUrl' => Url::to(['/admin/media/library-list', 'kind' => MediaFile::KIND_IMAGE]),
            'galleryAddUrl' => $modelId
                ? Url::to(['/admin/catalog-model/dimension-gallery-add', 'id' => $modelId])
                : '',
            'galleryDeleteUrl' => $modelId
                ? Url::to(['/admin/catalog-model/dimension-gallery-delete', 'id' => $modelId])
                : '',
            'galleryReorderUrl' => $modelId
                ? Url::to(['/admin/catalog-model/dimension-gallery-reorder', 'id' => $modelId])
                : '',
            'csrfParam' => Yii::$app->request->csrfParam,
            'csrfToken' => Yii::$app->request->csrfToken,
            'inlineHeader' => true,
        ]);
    }
}
