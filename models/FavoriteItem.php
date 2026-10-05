<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $session_id
 * @property int $catalog_product_id
 * @property string $created_at
 * @property-read CatalogProduct|null $product
 * @property-read User|null $user
 */
class FavoriteItem extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%favorite_items}}';
    }

    public function behaviors(): array
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => false,
                'value' => static fn (): string => date('Y-m-d H:i:s'),
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['catalog_product_id'], 'required'],
            [['user_id', 'catalog_product_id'], 'integer'],
            [['session_id'], 'string', 'max' => 64],
            [['created_at'], 'safe'],
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
}
