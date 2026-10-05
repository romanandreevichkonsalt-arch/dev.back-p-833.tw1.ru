<?php

namespace app\services\promotion;

use app\exceptions\ApiValidationException;
use app\models\PromoCodeTemplate;
use app\modules\admin\models\PromotionBannerForm;
use app\services\dealer\DealerPromoService;
use yii\base\Model;

class PromotionPromoTemplateResolver
{
    public function __construct(
        private readonly DealerPromoService $promoService = new DealerPromoService(),
    ) {
    }

    /**
     * @param Model&PromotionBannerForm $form
     */
    public function resolveTemplateId(Model $form, ?int $currentTemplateId): ?int
    {
        if ($form->promo_mode === PromotionBannerForm::PROMO_NONE) {
            return null;
        }

        if ($form->promo_mode === PromotionBannerForm::PROMO_EXISTING) {
            $template = PromoCodeTemplate::findOne(['id' => (int)$form->template_id, 'is_active' => true]);
            if ($template === null) {
                throw new ApiValidationException('Выберите активный промокод.');
            }

            return (int)$template->id;
        }

        if ($currentTemplateId !== null && $form->promo_mode === PromotionBannerForm::PROMO_CREATE) {
            $existing = PromoCodeTemplate::findOne((int)$currentTemplateId);
            if ($existing !== null && $existing->type === PromoCodeTemplate::TYPE_CUSTOM) {
                $this->promoService->updateTemplate($existing, [
                    'title' => $form->promo_title,
                    'discount_percent' => $form->promo_discount_percent,
                    'is_single_use' => $form->promo_is_single_use,
                    'is_active' => $form->promo_is_active,
                    'valid_until' => $form->promo_valid_until,
                ]);

                return (int)$existing->id;
            }
        }

        $template = $this->promoService->createTemplate(
            $form->promo_code,
            $form->promo_title,
            (float)$form->promo_discount_percent,
            $form->promo_is_single_use,
            $form->promo_is_active,
            $form->promo_valid_until,
        );

        return (int)$template->id;
    }
}
