<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * @property string $session_id
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $favorites_merged_at
 * @property string|null $cart_merged_at
 * @property string|null $moodboards_merged_at
 */
class GuestSession extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%guest_sessions}}';
    }

    public static function primaryKey(): array
    {
        return ['session_id'];
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
            [['session_id'], 'required'],
            [['session_id'], 'string', 'max' => 64],
            [['created_at', 'updated_at', 'favorites_merged_at', 'cart_merged_at', 'moodboards_merged_at'], 'safe'],
        ];
    }
}
