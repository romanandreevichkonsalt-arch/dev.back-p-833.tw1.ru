<?php

namespace app\services\promotion;

use app\exceptions\ApiValidationException;
use app\models\PromotionPopup;
use app\models\PromoCodeTemplate;
use app\modules\admin\models\PromotionPopupForm;
use app\services\dealer\DealerPromoService;
use Yii;

class PromotionPopupAdminService
{
    public function __construct(
        private readonly DealerPromoService $promoService = new DealerPromoService(),
        private readonly PromotionPromoTemplateResolver $promoResolver = new PromotionPromoTemplateResolver(),
    ) {
    }

    public function save(PromotionPopupForm $form, ?PromotionPopup $popup = null, ?int $adminUserId = null): PromotionPopup
    {
        if ($popup === null) {
            $popup = new PromotionPopup();
            $popup->updated_at = date('Y-m-d H:i:s');
        }

        $form->syncPromoModeFromFields();

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $templateId = $this->promoResolver->resolveTemplateId($form, $popup->template_id !== null ? (int)$popup->template_id : null);

            $popup->image_media_id = $form->image_media_id ?: null;
            $popup->headline = trim($form->headline);
            $popup->body_text = trim($form->body_text) !== '' ? trim($form->body_text) : null;
            $popup->price_current = $this->nullableString($form->price_current);
            $popup->price_old = $this->nullableString($form->price_old);
            $popup->cta_label = $this->nullableString($form->cta_label);
            $popup->cta_url = $this->nullableString($form->cta_url);
            $popup->promo_label = $this->nullableString($form->promo_label);
            $popup->template_id = $templateId;
            $popup->valid_from = $form->valid_from ?: null;
            $popup->valid_to = $form->valid_to ?: null;
            $popup->updated_at = date('Y-m-d H:i:s');

            if (!$popup->save()) {
                throw new ApiValidationException('Не удалось сохранить попап.', $popup->getErrors());
            }

            if ($form->grant_to_all_dealers && $popup->template_id !== null) {
                $template = PromoCodeTemplate::findOne((int)$popup->template_id);
                if ($template !== null) {
                    $this->promoService->grantCustomTemplateToAllDealers($template, $adminUserId);
                }
            }

            $transaction->commit();

            return $popup;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    public function grantPromoToAllDealers(PromotionPopup $popup, ?int $adminUserId = null): int
    {
        if ($popup->template_id === null) {
            return 0;
        }

        $template = PromoCodeTemplate::findOne((int)$popup->template_id);
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
