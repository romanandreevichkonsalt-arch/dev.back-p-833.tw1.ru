<?php

namespace app\services\promotion;

use app\models\CatalogPromotion;
use yii\db\Expression;

/**
 * SQL-выражение эффективной цены дилера с учётом активных catalog_promotions (product > model > персональная скидка).
 */
final class CatalogPromotionDealerListingPrice
{
    public static function buildEffectivePriceExpression(float $dealerDiscountPercent): Expression
    {
        $table = CatalogPromotion::tableName();
        $dealerMultiplier = sprintf('%.10F', 1 - $dealerDiscountPercent / 100);

        $promoCase = "CASE WHEN cp.discount_type = '"
            . CatalogPromotion::DISCOUNT_PERCENT
            . "' THEN ROUND(p.price_amount * (1 - cp.discount_value / 100)) ELSE ROUND(p.price_amount - cp.discount_value) END";

        $productPromo = '(SELECT ' . $promoCase
            . ' FROM ' . $table . ' cp WHERE cp.catalog_product_id = p.id'
            . " AND cp.scope_type = '" . CatalogPromotion::SCOPE_PRODUCT . "'"
            . ' AND NOW() >= cp.starts_at AND NOW() <= cp.ends_at'
            . ' ORDER BY cp.id DESC LIMIT 1)';

        $modelPromo = '(SELECT ' . $promoCase
            . ' FROM ' . $table . ' cp WHERE cp.catalog_model_id = p.model_id'
            . " AND cp.scope_type = '" . CatalogPromotion::SCOPE_MODEL . "'"
            . ' AND NOW() >= cp.starts_at AND NOW() <= cp.ends_at'
            . ' ORDER BY cp.id DESC LIMIT 1)';

        $dealerPrice = 'ROUND(p.price_amount * ' . $dealerMultiplier . ')';

        return new Expression('COALESCE(' . $productPromo . ', ' . $modelPromo . ', ' . $dealerPrice . ')');
    }
}
