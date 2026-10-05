<?php

namespace app\services\promotion;

use app\models\CatalogPromotion;
use app\models\PromotionBanner;
use app\models\PromotionPopup;

class PromotionDealerApiService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listVisibleBanners(): array
    {
        $banners = PromotionBanner::find()
            ->with(['imageMedia', 'template'])
            ->orderBy(['id' => SORT_DESC])
            ->all();

        $items = [];
        foreach ($banners as $banner) {
            if (!$banner->isVisibleNow() || trim((string)$banner->headline) === '') {
                continue;
            }
            $items[] = $this->serializeMarketingBlock($banner);
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listVisiblePopups(): array
    {
        $popups = PromotionPopup::find()
            ->with(['imageMedia', 'template'])
            ->orderBy(['id' => SORT_DESC])
            ->all();

        $items = [];
        foreach ($popups as $popup) {
            if (!$popup->isVisibleNow() || trim((string)$popup->headline) === '') {
                continue;
            }
            $items[] = $this->serializeMarketingBlock($popup);
        }

        return $items;
    }

    /**
     * @deprecated Используйте listVisiblePopups(); первый активный попап для совместимости.
     *
     * @return array<string, mixed>|null
     */
    public function getVisiblePopup(): ?array
    {
        $items = $this->listVisiblePopups();

        return $items[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveCatalogPromotions(): array
    {
        $now = date('Y-m-d H:i:s');
        $promotions = CatalogPromotion::find()
            ->with(['catalogModel', 'catalogProduct', 'imageMedia'])
            ->orderBy(['starts_at' => SORT_DESC, 'id' => SORT_DESC])
            ->all();

        $items = [];
        foreach ($promotions as $promotion) {
            if (!$promotion->isActiveAt($now)) {
                continue;
            }
            $items[] = $this->serializeCatalogPromotion($promotion);
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMarketingBlock(PromotionBanner|PromotionPopup $row): array
    {
        $image = null;
        if ($row->imageMedia !== null) {
            $image = $row->imageMedia->toApiImagePayload();
        }

        $template = $row->template ?? null;

        return [
            'id' => (int)$row->id,
            'headline' => (string)$row->headline,
            'bodyText' => $row->body_text !== null && $row->body_text !== '' ? (string)$row->body_text : null,
            'priceCurrent' => $row->price_current !== null && $row->price_current !== '' ? (string)$row->price_current : null,
            'priceOld' => $row->price_old !== null && $row->price_old !== '' ? (string)$row->price_old : null,
            'ctaLabel' => $row->cta_label !== null && $row->cta_label !== '' ? (string)$row->cta_label : null,
            'ctaUrl' => $row->cta_url !== null && $row->cta_url !== '' ? (string)$row->cta_url : null,
            'promoLabel' => $row->promo_label !== null && $row->promo_label !== '' ? (string)$row->promo_label : null,
            'promoCode' => $template !== null ? (string)$template->code : null,
            'image' => $image,
            'validFrom' => $row->valid_from !== null && $row->valid_from !== '' ? (string)$row->valid_from : null,
            'validTo' => $row->valid_to !== null && $row->valid_to !== '' ? (string)$row->valid_to : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCatalogPromotion(CatalogPromotion $promotion): array
    {
        $image = null;
        if ($promotion->imageMedia !== null) {
            $image = $promotion->imageMedia->toApiImagePayload();
        }

        return [
            'id' => (int)$promotion->id,
            'title' => (string)$promotion->title,
            'image' => $image,
            'discountType' => (string)$promotion->discount_type,
            'discountValue' => (float)$promotion->discount_value,
            'startsAt' => (string)$promotion->starts_at,
            'endsAt' => (string)$promotion->ends_at,
            'scopeType' => (string)$promotion->scope_type,
            'catalogModelId' => (int)$promotion->catalog_model_id,
            'catalogModelTitle' => $promotion->catalogModel?->title,
            'catalogProductId' => $promotion->catalog_product_id !== null ? (int)$promotion->catalog_product_id : null,
            'catalogProductSlug' => $promotion->catalogProduct?->slug,
        ];
    }
}
