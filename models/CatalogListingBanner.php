<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class CatalogListingBanner extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%catalog_listing_banners}}';
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
            [['direction_id'], 'required'],
            [['direction_id', 'category_id', 'subcategory_id'], 'integer', 'min' => 0],
            [['seo_data', 'hero_data'], 'safe'],
            [['direction_id'], 'exist', 'targetClass' => CatalogDirection::class, 'targetAttribute' => ['direction_id' => 'id']],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getHeroArray(): array
    {
        return $this->decodeJsonField($this->hero_data);
    }

    /**
     * @param array<string, mixed> $hero
     */
    public function setHeroPayload(array $hero): void
    {
        $this->seo_data = '{}';
        $this->hero_data = json_encode($hero, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
