<?php

namespace app\models;

use yii\db\ActiveRecord;

class DealerCashbackLedger extends ActiveRecord
{
    public const TYPE_ACCRUAL = 'accrual';
    public const TYPE_SPEND = 'spend';
    public const TYPE_EXPIRE = 'expire';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_NOTIFY = 'notify';

    public static function tableName(): string
    {
        return '{{%dealer_cashback_ledger}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'type', 'amount', 'balance_after', 'created_at'], 'required'],
            [['user_id', 'order_id'], 'integer'],
            [['type'], 'string', 'max' => 32],
            [['amount', 'balance_after'], 'number'],
            [['period_year_month'], 'string', 'max' => 7],
            [['comment'], 'string'],
            [['expires_at', 'created_at'], 'safe'],
        ];
    }
}
