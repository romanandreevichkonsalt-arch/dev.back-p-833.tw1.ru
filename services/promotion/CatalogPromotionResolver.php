<?php

namespace app\services\promotion;

use app\models\CatalogPromotion;
use Yii;

class CatalogPromotionResolver
{
    /** @var array<int, CatalogPromotion> */
    private array $byProductId = [];

    /** @var array<int, CatalogPromotion> */
    private array $byModelId = [];

    private bool $loaded = false;

    public function findForProduct(int $productId, int $modelId, ?string $at = null): ?CatalogPromotion
    {
        $this->ensureLoaded($at);

        if ($productId > 0 && isset($this->byProductId[$productId])) {
            return $this->byProductId[$productId];
        }

        if ($modelId > 0 && isset($this->byModelId[$modelId])) {
            return $this->byModelId[$modelId];
        }

        return null;
    }

    public function calculateDiscountAmount(
        CatalogPromotion $promotion,
        ?int $retailUnitPrice,
        int $quantity,
    ): float {
        return CatalogPromotionPricing::lineDiscountFromRetail($retailUnitPrice, $promotion, $quantity);
    }

    private function ensureLoaded(?string $at): void
    {
        if ($this->loaded) {
            return;
        }

        $at ??= date('Y-m-d H:i:s');
        if (Yii::$app->db->schema->getTableSchema(CatalogPromotion::tableName(), true) === null) {
            $this->loaded = true;

            return;
        }

        $promotions = CatalogPromotion::find()->orderBy(['id' => SORT_DESC])->all();

        foreach ($promotions as $promotion) {
            if (!$promotion->isActiveAt($at)) {
                continue;
            }

            if ($promotion->scope_type === CatalogPromotion::SCOPE_PRODUCT) {
                $productId = (int)$promotion->catalog_product_id;
                if ($productId > 0 && !isset($this->byProductId[$productId])) {
                    $this->byProductId[$productId] = $promotion;
                }
                continue;
            }

            $modelId = (int)$promotion->catalog_model_id;
            if ($modelId > 0 && !isset($this->byModelId[$modelId])) {
                $this->byModelId[$modelId] = $promotion;
            }
        }

        $this->loaded = true;
    }
}
