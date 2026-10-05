<?php

namespace app\services\dealer;

use app\models\CatalogProduct;
use app\models\User;
use app\services\catalog\CatalogListingValueParser;
use app\services\promotion\CatalogPromotionPricing;
use app\services\promotion\CatalogPromotionResolver;
use Yii;
use yii\db\Expression;

final class DealerPricingService
{
    public function getEffectiveDiscountPercent(User $dealer): float
    {
        if (!$dealer->isDealer()) {
            return 0.0;
        }

        $profile = $dealer->dealerProfile;
        if ($profile !== null && $profile->personal_discount_percent !== null && $profile->personal_discount_percent !== '') {
            return (float)$profile->personal_discount_percent;
        }

        return (float)(Yii::$app->params['dealerDefaultDiscountPercent'] ?? 0.0);
    }

    public function resolveRetailPrice(CatalogProduct $product): ?int
    {
        if ($product->price_amount !== null && (int)$product->price_amount > 0) {
            return (int)$product->price_amount;
        }

        $fromDisplay = CatalogListingValueParser::parsePriceAmount($product->price_display);
        if ($fromDisplay !== null && $fromDisplay > 0) {
            return $fromDisplay;
        }

        if (!$product->isGeneratedFromModel()) {
            return null;
        }

        $model = $product->catalogModel;
        if ($model === null) {
            return null;
        }

        $fabricCollection = $product->fabricColor?->fabricCollection;
        $priceCategoryId = $fabricCollection !== null
            ? $fabricCollection->getPriceCategoryForProducts()
            : null;
        if ($priceCategoryId === null) {
            return null;
        }

        $modelPriceDisplay = $model->getPriceForPriceCategoryId($priceCategoryId);

        return CatalogListingValueParser::parsePriceAmount($modelPriceDisplay);
    }

    public function applyDiscount(?int $retailPrice, float $discountPercent): ?int
    {
        if ($retailPrice === null || $retailPrice <= 0) {
            return null;
        }

        return (int)round($retailPrice * (1 - $discountPercent / 100));
    }

    /**
     * @return array{
     *     retailPrice: ?int,
     *     dealerPrice?: int,
     *     dealerDiscountPercent?: int,
     *     catalogPromotion?: array<string, mixed>
     * }
     */
    public function buildProductPrices(
        CatalogProduct $product,
        ?User $dealer,
        ?CatalogPromotionResolver $promotionResolver = null,
    ): array {
        $retailPrice = $this->resolveRetailPrice($product);
        $payload = ['retailPrice' => $retailPrice];

        if ($dealer === null || !$dealer->isDealer()) {
            return $payload;
        }

        $modelId = $product->model_id !== null ? (int)$product->model_id : 0;
        $promotionResolver ??= new CatalogPromotionResolver();
        $promotion = $promotionResolver->findForProduct((int)$product->id, $modelId);
        if ($promotion !== null) {
            $promotionUnitPrice = CatalogPromotionPricing::resolveUnitPrice($retailPrice, $promotion);
            if ($promotionUnitPrice !== null) {
                $payload['dealerPrice'] = $promotionUnitPrice;
                $payload['catalogPromotion'] = CatalogPromotionPricing::toApiPayload($promotion);

                return $payload;
            }
        }

        $discountPercent = $this->getEffectiveDiscountPercent($dealer);
        $dealerPrice = $this->applyDiscount($retailPrice, $discountPercent);
        if ($dealerPrice === null) {
            return $payload;
        }

        $payload['dealerPrice'] = $dealerPrice;
        $payload['dealerDiscountPercent'] = (int)round($discountPercent);

        return $payload;
    }

    public function resolveUnitPrice(CatalogProduct $product, ?User $dealer): ?float
    {
        $prices = $this->buildProductPrices($product, $dealer);
        if ($dealer !== null && $dealer->isDealer() && isset($prices['dealerPrice'])) {
            return (float)$prices['dealerPrice'];
        }

        $retail = $prices['retailPrice'] ?? null;

        return $retail !== null ? (float)$retail : null;
    }

    public function formatPriceDisplay(?int $amount): ?string
    {
        if ($amount === null) {
            return null;
        }

        return number_format($amount, 0, '.', ' ') . ' ₽';
    }

    public function buildSqlDealerPriceExpression(float $discountPercent): Expression
    {
        $multiplier = 1 - $discountPercent / 100;

        return new Expression('ROUND(p.price_amount * ' . sprintf('%.10F', $multiplier) . ')');
    }

    public function applyDiscountToPriceAmount(?int $priceAmount, float $discountPercent): ?int
    {
        if ($priceAmount === null) {
            return null;
        }

        return $this->applyDiscount($priceAmount, $discountPercent);
    }
}
