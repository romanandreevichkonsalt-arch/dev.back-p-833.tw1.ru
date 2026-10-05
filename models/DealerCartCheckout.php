<?php

namespace app\models;

use yii\db\ActiveRecord;

class DealerCartCheckout extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%dealer_cart_checkout}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'updated_at'], 'required'],
            [['user_id', 'promo_grant_id'], 'integer'],
            [['cashback_amount'], 'number', 'min' => 0],
            [['updated_at'], 'safe'],
        ];
    }

    public function getPromoGrant()
    {
        return $this->hasOne(DealerPromoGrant::class, ['id' => 'promo_grant_id']);
    }
}
