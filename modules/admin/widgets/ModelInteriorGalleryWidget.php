<?php

namespace app\modules\admin\widgets;

use app\models\CatalogModelImage;
use app\models\MediaFolder;

class ModelInteriorGalleryWidget extends ModelGalleryWidget
{
    public string $purpose = CatalogModelImage::PURPOSE_INTERIOR;
    public ?string $defaultFolder = MediaFolder::SLUG_INTERIOR;
    public string $galleryTitle = 'Фото для мудборда';
    public string $galleryHint = 'Перетащите карточки, чтобы изменить порядок.';
}
