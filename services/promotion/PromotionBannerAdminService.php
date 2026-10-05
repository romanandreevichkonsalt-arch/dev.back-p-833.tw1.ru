<?php

namespace app\services\promotion;

use app\exceptions\ApiValidationException;
use app\models\PromotionBanner;
use app\models\PromoCodeTemplate;
use app\modules\admin\models\PromotionBannerForm;
use app\services\dealer\DealerPromoService;
use Yii;

class PromotionBannerAdminService
{
    public function __construct(
        private readonly DealerPromoService $promoService = new DealerPromoService(),
        private readonly PromotionPromoTemplateResolver $promoResolver = new PromotionPromoTemplateResolver(),
    ) {
    }

    public function save(PromotionBannerForm $form, ?PromotionBanner $banner = null, ?int $adminUserId = null): PromotionBanner
    {
        if ($banner === null) {
            $banner = new PromotionBanner();
        }

        $form->syncPromoModeFromFields();

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $templateId = $this->promoResolver->resolveTemplateId(
                $form,
                $banner->template_id !== null ? (int)$banner->template_id : null,
            );

            $banner->image_media_id = $form->image_media_id ?: null;
            $banner->headline = trim($form->headline);
            $banner->body_text = trim($form->body_text) !== '' ? trim($form->body_text) : null;
            $banner->price_current = $this->nullableString($form->price_current);
            $banner->price_old = $this->nullableString($form->price_old);
            $banner->cta_label = $this->nullableString($form->cta_label);
            $banner->cta_url = $this->nullableString($form->cta_url);
            $banner->promo_label = $this->nullableString($form->promo_label);
            $banner->template_id = $templateId;
            $banner->valid_from = $form->valid_from ?: null;
            $banner->valid_to = $form->valid_to ?: null;

            if (!$banner->save()) {
                throw new ApiValidationException('Не удалось сохранить баннер.', $banner->getErrors());
            }

            if ($form->grant_to_all_dealers && $banner->template_id !== null) {
                $template = PromoCodeTemplate::findOne((int)$banner->template_id);
                if ($template !== null) {
                    $this->promoService->grantCustomTemplateToAllDealers($template, $adminUserId);
                }
            }

            $transaction->commit();

            return $banner;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    public function grantPromoToAllDealers(PromotionBanner $banner, ?int $adminUserId = null): int
    {
        if ($banner->template_id === null) {
            return 0;
        }

        $template = PromoCodeTemplate::findOne((int)$banner->template_id);
        if ($template === null) {
            return 0;
        }

        return $this->promoService->grantCustomTemplateToAllDealers($template, $adminUserId);
    }

    private function nullableString(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
