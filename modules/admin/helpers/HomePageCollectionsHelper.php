<?php

namespace app\modules\admin\helpers;

use app\models\CatalogDirection;
use yii\helpers\ArrayHelper;

class HomePageCollectionsHelper
{
    public const CARD_COUNT = 2;
    public const SLIDES_PER_CARD = 3;

    /**
     * @return array<int, string>
     */
    public static function directionOptions(): array
    {
        $directions = CatalogDirection::find()
            ->where(['is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return ArrayHelper::map(
            $directions,
            'id',
            static fn (CatalogDirection $direction): string => $direction->label
        );
    }

    /**
     * @param array<int, array<string, mixed>> $slides
     * @return array<int, array<string, mixed>>
     */
    public static function padSlidesForForm(array $slides): array
    {
        $normalized = [];
        foreach ($slides as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized[] = $row;
        }

        while (count($normalized) < self::SLIDES_PER_CARD) {
            $normalized[] = [
                'label' => '',
                'image_src' => '',
                'image_alt' => '',
            ];
        }

        return array_slice($normalized, 0, self::SLIDES_PER_CARD);
    }

    /**
     * @param array<int, array<string, mixed>> $cards
     * @return array<int, array<string, mixed>>
     */
    public static function padCardsForForm(array $cards): array
    {
        $normalized = [];
        foreach ($cards as $row) {
            if (!is_array($row)) {
                continue;
            }
            $row['slides'] = self::padSlidesForForm($row['slides'] ?? []);
            $normalized[] = $row;
        }

        while (count($normalized) < self::CARD_COUNT) {
            $normalized[] = [
                'catalog_direction_id' => '',
                'title_uppercase' => false,
                'slides' => self::padSlidesForForm([]),
            ];
        }

        return array_slice($normalized, 0, self::CARD_COUNT);
    }
}
