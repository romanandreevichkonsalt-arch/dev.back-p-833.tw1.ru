<?php

namespace app\models;

use yii\db\ActiveRecord;

class CatalogListingTileSettings extends ActiveRecord
{
    private const DEFAULT_ID = 1;

    public static function tableName(): string
    {
        return '{{%catalog_listing_tile_settings}}';
    }

    public function rules(): array
    {
        return [
            [['floor_guide_from_bottom'], 'required'],
            [['floor_guide_from_bottom'], 'integer', 'min' => 0, 'max' => 2000],
            [['updated_at'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'floor_guide_from_bottom' => 'Линия опоры от низа кадра, px',
        ];
    }

    public static function getSingleton(): self
    {
        $settings = self::findOne(self::DEFAULT_ID);
        if ($settings !== null) {
            return $settings;
        }

        $defaultGuide = (int)(\Yii::$app->params['catalogListingTile']['floorGuideFromBottom'] ?? 75);
        $settings = new self([
            'id' => self::DEFAULT_ID,
            'floor_guide_from_bottom' => $defaultGuide,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $settings->save(false);

        return $settings;
    }

    public static function clampFloorGuide(int $value, int $tileHeight): int
    {
        $max = max(0, $tileHeight - 1);

        return max(0, min($max, $value));
    }

    public function saveFloorGuideFromBottom(int $value, int $tileHeight): bool
    {
        $this->floor_guide_from_bottom = self::clampFloorGuide($value, $tileHeight);
        $this->updated_at = date('Y-m-d H:i:s');

        return $this->save();
    }
}
