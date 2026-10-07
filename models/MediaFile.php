<?php

namespace app\models;

use app\services\media\MediaContentValidator;
use yii\db\ActiveRecord;

class MediaFile extends ActiveRecord
{
    public const KIND_IMAGE = 'image';
    public const KIND_VIDEO = 'video';
    public const KIND_DOCUMENT = 'document';

    public static function tableName(): string
    {
        return '{{%media_files}}';
    }

    public static function normalizeKind(string $kind): string
    {
        if ($kind === self::KIND_VIDEO) {
            return self::KIND_VIDEO;
        }
        if ($kind === self::KIND_DOCUMENT) {
            return self::KIND_DOCUMENT;
        }

        return self::KIND_IMAGE;
    }

    public static function kindLabels(): array
    {
        return [
            self::KIND_IMAGE => 'Изображения',
            self::KIND_VIDEO => 'Видео',
            self::KIND_DOCUMENT => 'Документы',
        ];
    }

    public function rules(): array
    {
        return [
            [['filename', 'path', 'mime', 'size', 'created_at', 'kind'], 'required'],
            [['filename'], 'string', 'max' => 255],
            [[
                'path',
                'path_large',
                'path_medium',
                'path_mini',
                'path_listing_medium',
                'path_listing_mini',
            ], 'string', 'max' => 512],
            [['path'], 'unique'],
            [['kind'], 'string', 'max' => 16],
            [['kind'], 'in', 'range' => array_keys(self::kindLabels())],
            [['mime'], 'string', 'max' => 128],
            [['alt'], 'string', 'max' => 512],
            [['size', 'width', 'height', 'folder_id'], 'integer'],
            [['listing_frame_json'], 'string'],
            [['listing_frame_locked'], 'boolean'],
            [['created_at'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'filename' => 'Имя файла',
            'path' => 'Путь',
            'kind' => 'Тип файла',
            'mime' => 'MIME',
            'size' => 'Размер',
            'width' => 'Ширина',
            'height' => 'Высота',
            'alt' => 'Alt-текст',
            'folder_id' => 'Папка',
            'created_at' => 'Загружен',
            'listing_frame_json' => 'Кадр каталога (JSON)',
            'listing_frame_locked' => 'Кадр каталога зафиксирован',
        ];
    }

    public function isListingFrameLocked(): bool
    {
        return (bool)$this->listing_frame_locked;
    }

    public function getFolder()
    {
        return $this->hasOne(MediaFolder::class, ['id' => 'folder_id']);
    }

    public function getPublicUrl(string $variant = 'original'): string
    {
        $resolvedVariant = MediaContentValidator::resolveReadableVariant($this, $variant);
        $path = MediaContentValidator::pathForVariant(
            $this,
            $resolvedVariant ?? $variant
        );

        return '/' . ltrim($path, '/');
    }

    /**
     * @return array{original:string,large:string,medium:string,mini:string}
     */
    public function getPublicUrls(): array
    {
        return [
            'original' => $this->getPublicUrl('original'),
            'large' => $this->getPublicUrl('large'),
            'medium' => $this->getPublicUrl('medium'),
            'mini' => $this->getPublicUrl('mini'),
        ];
    }

    public function hasListingTileVariants(): bool
    {
        return $this->isListingFrameLocked()
            && trim((string)$this->path_listing_medium) !== '';
    }

    /**
     * URL для листинга/поиска: кадр редактора, если есть; иначе авто-варианты.
     *
     * @return array{original:string,large:string,medium:string,mini:string}
     */
    public function getListingPublicUrls(): array
    {
        $urls = $this->getPublicUrls();
        if (!$this->hasListingTileVariants()) {
            return $urls;
        }

        $listingMedium = trim((string)$this->path_listing_medium);
        if ($listingMedium !== '') {
            $urls['medium'] = '/' . ltrim($listingMedium, '/');
        }
        $listingMini = trim((string)$this->path_listing_mini);
        if ($listingMini !== '') {
            $urls['mini'] = '/' . ltrim($listingMini, '/');
        }

        return $urls;
    }

    /**
     * Карточка товара: auto mini/medium/large (path_*), без кадра редактора и без original в API.
     *
     * @return array{original:string,large:string,medium:string,mini:string}
     */
    public function getProductDetailPublicUrls(): array
    {
        return $this->getPublicUrls();
    }

    public function getListingPreviewUrl(): string
    {
        if ($this->hasListingTileVariants()) {
            return '/' . ltrim((string)$this->path_listing_medium, '/');
        }

        return $this->getPublicUrl('medium');
    }

    /**
     * @return array{src:string,alt:string,srcSet?:array{mini:string,medium:string,large:string,original:string},width?:int,height?:int}
     */
    public function toApiImagePayload(?string $alt = null, string $defaultSrc = 'medium'): array
    {
        return self::buildApiImagePayload(
            $this->isImage(),
            $this->getPublicUrls(),
            $alt ?? $this->alt ?? $this->filename,
            $this->width,
            $this->height,
            $this->getPublicUrl(),
            $defaultSrc,
            false
        );
    }

    public function toListingApiImagePayload(?string $alt = null, string $defaultSrc = 'medium'): array
    {
        return self::buildApiImagePayload(
            $this->isImage(),
            $this->getListingPublicUrls(),
            $alt ?? $this->alt ?? $this->filename,
            $this->width,
            $this->height,
            $this->getPublicUrl(),
            $defaultSrc,
            true
        );
    }

    /**
     * Карточка товара / галерея модели: src и srcSet.medium — auto _m (без кадра редактора).
     *
     * @return array{src:string,alt:string,srcSet?:array{mini:string,medium:string,large?:string},width?:int,height?:int}
     */
    public function toProductDetailApiImagePayload(?string $alt = null, string $defaultSrc = 'medium'): array
    {
        return self::buildApiImagePayload(
            $this->isImage(),
            $this->getProductDetailPublicUrls(),
            $alt ?? $this->alt ?? $this->filename,
            $this->width,
            $this->height,
            $this->getPublicUrl(),
            $defaultSrc,
            false,
            true
        );
    }

    /**
     * Ткани / материалы: mini, medium, large из медиатеки (те же лимиты, что у товара), без original в srcSet.
     *
     * @return array{src:string,alt:string,srcSet?:array{mini:string,medium:string,large?:string},width?:int,height?:int}
     */
    public function toMaterialApiImagePayload(?string $alt = null, string $defaultSrc = 'medium'): array
    {
        return self::buildApiImagePayload(
            $this->isImage(),
            $this->getPublicUrls(),
            $alt ?? $this->alt ?? $this->filename,
            $this->width,
            $this->height,
            $this->getPublicUrl(),
            $defaultSrc,
            false,
            false,
            true
        );
    }

    /**
     * @param array{original:string,large?:string,medium:string,mini:string} $urls
     * @return array{src:string,alt:string,srcSet?:array{mini:string,medium:string,large?:string,original?:string},width?:int,height?:int}
     */
    public static function buildApiImagePayload(
        bool $isImage,
        array $urls,
        string $alt,
        ?int $width = null,
        ?int $height = null,
        ?string $nonImageSrc = null,
        string $defaultSrc = 'medium',
        bool $forListing = false,
        bool $forProductDetail = false,
        bool $forMaterial = false
    ): array {
        if (!$isImage) {
            return [
                'src' => $nonImageSrc ?? $urls['original'],
                'alt' => $alt,
            ];
        }

        if ($forListing) {
            $srcSet = [
                'mini' => $urls['mini'],
                'medium' => $urls['medium'],
            ];
        } elseif ($forProductDetail || $forMaterial) {
            $srcSet = [
                'mini' => $urls['mini'],
                'medium' => $urls['medium'],
            ];
            if (($urls['large'] ?? '') !== '') {
                $srcSet['large'] = $urls['large'];
            }
        } else {
            $srcSet = [
                'mini' => $urls['mini'],
                'medium' => $urls['medium'],
                'original' => $urls['original'],
            ];
            if (($urls['large'] ?? '') !== '') {
                $srcSet['large'] = $urls['large'];
            }
        }

        $payload = [
            'src' => self::resolveDefaultSrc($urls, $defaultSrc),
            'srcSet' => $srcSet,
            'alt' => $alt,
        ];

        if ($width !== null && $width > 0) {
            $payload['width'] = $width;
        }
        if ($height !== null && $height > 0) {
            $payload['height'] = $height;
        }

        return $payload;
    }

    /**
     * @param array{original:string,large?:string,medium:string,mini:string} $urls
     */
    public static function resolveDefaultSrc(array $urls, string $preferred = 'medium'): string
    {
        $cascade = match ($preferred) {
            'large' => ['large', 'original', 'medium', 'mini'],
            'mini' => ['mini', 'medium', 'large', 'original'],
            'original' => ['original', 'large', 'medium', 'mini'],
            default => ['medium', 'large', 'original', 'mini'],
        };

        foreach ($cascade as $variant) {
            $url = trim((string)($urls[$variant] ?? ''));
            if ($url !== '') {
                return $url;
            }
        }

        return (string)($urls['original'] ?? '');
    }

    /**
     * @return array{src:string,alt:string}
     */
    public static function emptyImagePayload(string $alt = ''): array
    {
        return [
            'src' => null,
            'alt' => $alt,
        ];
    }

    public function getFormattedSize(): string
    {
        if ($this->size < 1024) {
            return $this->size . ' Б';
        }
        if ($this->size < 1048576) {
            return round($this->size / 1024, 1) . ' КБ';
        }

        return round($this->size / 1048576, 1) . ' МБ';
    }

    public function isImage(): bool
    {
        return $this->kind === self::KIND_IMAGE || strpos($this->mime, 'image/') === 0;
    }

    public function isVideo(): bool
    {
        return $this->kind === self::KIND_VIDEO || strpos($this->mime, 'video/') === 0;
    }

    public function isDocument(): bool
    {
        return $this->kind === self::KIND_DOCUMENT
            || $this->mime === 'application/pdf'
            || str_ends_with(strtolower($this->filename), '.pdf');
    }
}
