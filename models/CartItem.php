<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $session_id
 * @property int $catalog_product_id
 * @property int $quantity
 * @property string|null $comment
 * @property string|null $attachment_path
 * @property string|null $attachment_original_name
 * @property string $created_at
 * @property string $updated_at
 */
class CartItem extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%cart_items}}';
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
            [['catalog_product_id', 'quantity'], 'required'],
            [['user_id', 'catalog_product_id', 'quantity'], 'integer'],
            [['quantity'], 'integer', 'min' => 1],
            [['session_id'], 'string', 'max' => 64],
            [['comment'], 'string', 'max' => 2000],
            [['attachment_path'], 'string', 'max' => 512],
            [['attachment_original_name'], 'string', 'max' => 255],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getProduct()
    {
        return $this->hasOne(CatalogProduct::class, ['id' => 'catalog_product_id']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buildAttachmentPayload(): ?array
    {
        if ($this->attachment_path === null || trim($this->attachment_path) === '') {
            return null;
        }

        return [
            'originalName' => $this->attachment_original_name,
            'hasFile' => true,
        ];
    }
}
