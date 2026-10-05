<?php

namespace app\models;

use yii\db\ActiveRecord;

class PromotionPopup extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%promotion_popup}}';
    }

    public function rules(): array
    {
        return [
            [['headline'], 'string', 'max' => 512],
            [['body_text'], 'string'],
            [['price_current', 'price_old'], 'string', 'max' => 64],
            [['cta_label'], 'string', 'max' => 128],
            [['cta_url'], 'string', 'max' => 512],
            [['promo_label'], 'string', 'max' => 255],
            [['image_media_id', 'template_id'], 'integer'],
            [['valid_from', 'valid_to', 'updated_at'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'image_media_id' => 'Изображение',
            'headline' => 'Заголовок',
            'body_text' => 'Текст',
            'price_current' => 'Цена акционная',
            'price_old' => 'Старая цена',
            'cta_label' => 'Текст кнопки',
            'cta_url' => 'Ссылка кнопки',
            'promo_label' => 'Подпись к промокоду',
            'template_id' => 'Промокод',
            'valid_from' => 'Показывать с',
            'valid_to' => 'Показывать по',
        ];
    }

    public function getImageMedia()
    {
        return $this->hasOne(MediaFile::class, ['id' => 'image_media_id']);
    }

    public function getTemplate()
    {
        return $this->hasOne(PromoCodeTemplate::class, ['id' => 'template_id']);
    }

    public function isVisibleNow(): bool
    {
        return $this->displayStatusLabel() === 'В эфире';
    }

    public function displayStatusLabel(): string
    {
        $today = date('Y-m-d');
        $from = $this->valid_from !== null && $this->valid_from !== '' ? (string)$this->valid_from : null;
        $to = $this->valid_to !== null && $this->valid_to !== '' ? (string)$this->valid_to : null;

        if ($from !== null && $today < $from) {
            return 'Запланирован';
        }
        if ($to !== null && $today > $to) {
            return 'Завершён';
        }

        return 'В эфире';
    }
}
