<?php

namespace app\modules\admin\widgets;

use app\models\CatalogColor;
use app\models\CatalogColorImage;
use app\models\MediaFile;
use app\modules\admin\assets\AdminAsset;
use yii\base\Widget;
use yii\helpers\Json;
use yii\helpers\Url;
use Yii;

class ColorGalleryWidget extends Widget
{
    public ?CatalogColor $color = null;
    public string $title = 'Галерея образцов';

    public function run(): string
    {
        AdminAsset::register($this->view);

        $items = [];
        if ($this->color !== null && !$this->color->isNewRecord) {
            $links = CatalogColorImage::find()
                ->where(['color_id' => $this->color->id])
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

        $colorId = $this->color?->id;

        return $this->render('collection-gallery', [
            'pickerId' => $this->getId(),
            'entityId' => $colorId,
            'galleryTitle' => $this->title,
            'items' => $items,
            'itemsJson' => Json::encode($items),
            'uploadUrl' => Url::to(['/admin/media/quick-upload']),
            'libraryListUrl' => Url::to(['/admin/media/library-list', 'kind' => MediaFile::KIND_IMAGE]),
            'galleryAddUrl' => $colorId
                ? Url::to(['/admin/settings-color/gallery-add', 'id' => $colorId])
                : '',
            'galleryDeleteUrl' => $colorId
                ? Url::to(['/admin/settings-color/gallery-delete', 'id' => $colorId])
                : '',
            'galleryReorderUrl' => $colorId
                ? Url::to(['/admin/settings-color/gallery-reorder', 'id' => $colorId])
                : '',
            'csrfParam' => Yii::$app->request->csrfParam,
            'csrfToken' => Yii::$app->request->csrfToken,
        ]);
    }
}
