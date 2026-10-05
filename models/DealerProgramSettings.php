<?php

namespace app\models;

use yii\db\ActiveRecord;

class DealerProgramSettings extends ActiveRecord
{
    private const DEFAULT_ID = 1;

    public static function tableName(): string
    {
        return '{{%dealer_program_settings}}';
    }

    public function rules(): array
    {
        return [
            [['cashback_default_expiry_days'], 'required'],
            [['cashback_default_expiry_days'], 'integer', 'min' => 1, 'max' => 3650],
            [['updated_at'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'cashback_default_expiry_days' => 'Срок действия кэшбека, дней',
        ];
    }

    public static function getSingleton(): self
    {
        $settings = self::findOne(self::DEFAULT_ID);
        if ($settings !== null) {
            return $settings;
        }

        $settings = new self([
            'id' => self::DEFAULT_ID,
            'cashback_default_expiry_days' => (int)(\Yii::$app->params['cashback']['expiryDays'] ?? 90),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $settings->save(false);

        return $settings;
    }
}
