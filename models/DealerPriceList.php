<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class DealerPriceList extends ActiveRecord
{
    public const SCOPE_GLOBAL = 'global';
    public const SCOPE_DEALER = 'dealer';
    public const SCOPE_ORDER_FORM = 'order_form';

    public static function tableName(): string
    {
        return '{{%dealer_price_lists}}';
    }

    public function behaviors(): array
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'value' => static fn (): string => date('Y-m-d H:i:s'),
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['scope', 'media_file_id', 'label'], 'required'],
            [['dealer_user_id', 'media_file_id', 'uploaded_by_admin_id'], 'integer'],
            [['scope'], 'in', 'range' => [self::SCOPE_GLOBAL, self::SCOPE_DEALER, self::SCOPE_ORDER_FORM]],
            [['label'], 'string', 'max' => 255],
            [['is_active'], 'boolean'],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    public function getMediaFile()
    {
        return $this->hasOne(MediaFile::class, ['id' => 'media_file_id']);
    }

    public function getDealerUser()
    {
        return $this->hasOne(User::class, ['id' => 'dealer_user_id']);
    }

    /**
     * @return array{url: string, label: string, filename: string, mimeType: string|null, updatedAt: string|null}|null
     */
    public function toApiPayload(): ?array
    {
        $file = $this->mediaFile;
        if ($file === null) {
            return null;
        }

        return [
            'url' => $file->getPublicUrl(),
            'label' => (string)$this->label,
            'filename' => (string)$file->filename,
            'mimeType' => $file->mime,
            'updatedAt' => $this->updated_at,
        ];
    }
}
