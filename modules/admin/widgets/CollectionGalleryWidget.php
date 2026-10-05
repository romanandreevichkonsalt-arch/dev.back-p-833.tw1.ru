<?php

namespace app\modules\admin\widgets;

use app\models\CatalogCollection;
use app\models\CatalogCollectionImage;
use app\models\MediaFile;
use app\modules\admin\assets\AdminAsset;
use yii\base\Widget;
use yii\helpers\Json;
use yii\helpers\Url;
use Yii;

class CollectionGalleryWidget extends Widget
{
    public ?CatalogCollection $collection = null;

    public function run(): string
    {
        AdminAsset::register($this->view);

        $items = [];
        if ($this->collection !== null && !$this->collection->isNewRecord) {
            $links = CatalogCollectionImage::find()
                ->where(['collection_id' => $this->collection->id])
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

        $collectionId = $this->collection?->id;

        return $this->render('collection-gallery', [
            'pickerId' => $this->getId(),
            'entityId' => $collectionId,
            'galleryTitle' => 'Фото коллекции',
            'itemsJson' => Json::encode($items),
            'uploadUrl' => Url::to(['/admin/media/quick-upload']),
            'libraryListUrl' => Url::to(['/admin/media/library-list', 'kind' => MediaFile::KIND_IMAGE]),
            'galleryAddUrl' => $collectionId
                ? Url::to(['/admin/settings-collection/gallery-add', 'id' => $collectionId])
                : '',
            'galleryDeleteUrl' => $collectionId
                ? Url::to(['/admin/settings-collection/gallery-delete', 'id' => $collectionId])
                : '',
            'galleryReorderUrl' => $collectionId
                ? Url::to(['/admin/settings-collection/gallery-reorder', 'id' => $collectionId])
                : '',
            'csrfParam' => Yii::$app->request->csrfParam,
            'csrfToken' => Yii::$app->request->csrfToken,
        ]);
    }
}
