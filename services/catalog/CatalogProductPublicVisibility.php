<?php

namespace app\services\catalog;

use yii\db\ActiveQuery;
use yii\db\Expression;

/**
 * Единые правила видимости SKU в каталоге и поиске.
 */
class CatalogProductPublicVisibility
{
    public static function apply(ActiveQuery $query, string $productAlias = 'p'): void
    {
        $fabricColorJoin = 'fc_pub';
        $fabricCollectionJoin = 'fcol_pub';
        $modelJoin = 'cm_pub';

        $query->leftJoin(
            ['fc_pub' => '{{%catalog_fabric_collection_colors}}'],
            new Expression("[[{$fabricColorJoin}]].[[id]] = [[{$productAlias}]].[[fabric_color_id]]")
        );
        $query->leftJoin(
            ['fcol_pub' => '{{%catalog_fabric_collections}}'],
            new Expression("[[{$fabricCollectionJoin}]].[[id]] = [[{$fabricColorJoin}]].[[fabric_collection_id]]")
        );
        $query->leftJoin(
            ['cm_pub' => '{{%catalog_models}}'],
            new Expression("[[{$modelJoin}]].[[id]] = [[{$productAlias}]].[[model_id]]")
        );

        $query->andWhere([
            'or',
            ["{$productAlias}.fabric_color_id" => null],
            [
                "{$fabricColorJoin}.is_active" => true,
                "{$fabricCollectionJoin}.is_active" => true,
            ],
        ]);

        $query->andWhere([
            'or',
            ["{$productAlias}.model_id" => null],
            ["{$modelJoin}.is_active" => true],
        ]);
    }
}
