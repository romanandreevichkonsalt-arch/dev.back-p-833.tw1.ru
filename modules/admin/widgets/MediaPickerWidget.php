<?php

namespace app\modules\admin\widgets;

use app\models\MediaFile;
use app\modules\admin\assets\AdminAsset;
use app\services\media\MediaPathLookup;
use yii\base\Widget;
use yii\helpers\Url;
use Yii;

class MediaPickerWidget extends Widget
{
    public const MODE_ID = 'id';
    public const MODE_URL = 'url';

    public string $mode = self::MODE_ID;
    public string $kind = MediaFile::KIND_IMAGE;
    public string $inputName = '';
    public ?string $altInputName = null;
    public mixed $value = null;
    public ?string $altValue = null;
    public string $label = 'Изображение';
    public bool $allowClear = false;
    public bool $compact = false;
    public ?string $defaultFolder = null;
    public bool $enableListingTile = false;

    public function run(): string
    {
        AdminAsset::register($this->view);

        $this->kind = MediaFile::normalizeKind($this->kind);
        if ($this->label === 'Изображение' && $this->kind === MediaFile::KIND_VIDEO) {
            $this->label = 'Видео';
        }
        if ($this->label === 'Изображение' && $this->kind === MediaFile::KIND_DOCUMENT) {
            $this->label = 'Документ';
        }

        $previewUrl = '';
        $previewAlt = $this->altValue ?? '';
        $previewMime = '';

        if ($this->mode === self::MODE_ID && $this->value) {
            $pickerValue = is_string($this->value) ? trim($this->value) : (string)$this->value;
            if ($pickerValue !== '' && !ctype_digit($pickerValue)) {
                $resolvedId = (new MediaPathLookup())->findIdByPublicPath($pickerValue);
                if ($resolvedId !== null) {
                    $this->value = (string)$resolvedId;
                    $pickerValue = $this->value;
                }
            }

            $media = ctype_digit($pickerValue) ? MediaFile::findOne((int)$pickerValue) : null;
            if ($media !== null) {
                $previewUrl = $media->getPublicUrl('large');
                $previewAlt = $media->alt ?? $previewAlt;
                $previewMime = $media->mime;
            } elseif ($pickerValue !== '' && str_starts_with($pickerValue, '/')) {
                $previewUrl = $pickerValue;
            }
        } elseif ($this->mode === self::MODE_URL && is_string($this->value) && $this->value !== '') {
            $previewUrl = $this->value;
            if ($this->kind === MediaFile::KIND_DOCUMENT) {
                $previewMime = 'application/pdf';
            }
        }

        return $this->render('media-picker', [
            'pickerId' => $this->getId(),
            'mode' => $this->mode,
            'kind' => $this->kind,
            'inputName' => $this->inputName,
            'altInputName' => $this->altInputName,
            'value' => $this->value,
            'altValue' => $this->altValue,
            'label' => $this->label,
            'allowClear' => $this->allowClear,
            'compact' => $this->compact,
            'defaultFolder' => $this->defaultFolder ?? '',
            'previewUrl' => $previewUrl,
            'previewAlt' => $previewAlt,
            'previewMime' => $previewMime,
            'uploadUrl' => Url::to(['/admin/media/quick-upload']),
            'mediaDeleteUrl' => Url::to(['/admin/media/quick-delete']),
            'libraryListUrl' => Url::to(['/admin/media/library-list', 'kind' => $this->kind]),
            'foldersListUrl' => Url::to(['/admin/media/folders-list']),
            'csrfParam' => Yii::$app->request->csrfParam,
            'csrfToken' => Yii::$app->request->csrfToken,
            'enableListingTile' => $this->enableListingTile && $this->kind === MediaFile::KIND_IMAGE,
            'listingTileLoadUrl' => Url::to(['/admin/media/listing-tile-load']),
            'listingTileSaveUrl' => Url::to(['/admin/media/listing-tile-save']),
            'listingTileConfigUrl' => Url::to(['/admin/media/listing-tile-config']),
        ]);
    }
}
