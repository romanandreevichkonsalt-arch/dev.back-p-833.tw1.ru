<?php

namespace app\modules\admin\models;

use app\models\PromotionPopup;
use yii\base\Model;

class PromotionPopupForm extends Model
{
    use PromotionPromoFieldsTrait;

    public ?int $image_media_id = null;
    public string $headline = '';
    public string $body_text = '';
    public string $price_current = '';
    public string $price_old = '';
    public string $cta_label = 'Подробнее';
    public string $cta_url = '';
    public string $promo_label = 'Используйте промокод:';
    public ?string $valid_from = null;
    public ?string $valid_to = null;

    public function load($data, $formName = null): bool
    {
        $formName = $formName ?? $this->formName();
        if (isset($data[$formName]) && is_array($data[$formName])) {
            $this->sanitizeIntegerFormFields($data[$formName], ['template_id', 'image_media_id']);
        }

        if (!parent::load($data, $formName)) {
            return false;
        }
        $this->normalizeStringFields();
        $this->syncPromoModeFromFields();

        return true;
    }

    public function rules(): array
    {
        return array_merge($this->promoFieldRules(), [
            [['headline'], 'required'],
            [['headline', 'promo_label'], 'string', 'max' => 255],
            [['body_text'], 'string'],
            [['price_current', 'price_old'], 'string', 'max' => 64],
            [['cta_label'], 'string', 'max' => 128],
            [['cta_url'], 'string', 'max' => 512],
            [['image_media_id', 'template_id'], 'integer'],
            [['valid_from', 'valid_to'], 'date', 'format' => 'php:Y-m-d'],
        ]);
    }

    public function attributeLabels(): array
    {
        return (new PromotionPopup())->attributeLabels() + $this->promoFieldLabels();
    }

    public static function fromPopup(PromotionPopup $popup): self
    {
        if (!$popup->isRelationPopulated('template') && $popup->template_id !== null) {
            $popup = PromotionPopup::find()->where(['id' => $popup->id])->with('template')->one() ?? $popup;
        }

        $form = new self();
        $stringAttrs = ['headline', 'body_text', 'price_current', 'price_old', 'cta_label', 'cta_url', 'promo_label'];
        foreach ([
            'image_media_id', 'headline', 'body_text', 'price_current', 'price_old',
            'cta_label', 'cta_url', 'promo_label', 'template_id',
            'valid_from', 'valid_to',
        ] as $attr) {
            if (!$popup->hasAttribute($attr)) {
                continue;
            }
            $value = $popup->$attr;
            if (in_array($attr, $stringAttrs, true)) {
                $form->$attr = $value !== null && $value !== '' ? (string)$value : '';
            } else {
                $form->$attr = $value;
            }
        }

        $form->hydratePromoFromTemplate($popup->template_id !== null ? $popup->template : null, (int)$popup->template_id);

        return $form;
    }

    private function normalizeStringFields(): void
    {
        foreach (['headline', 'body_text', 'price_current', 'price_old', 'cta_label', 'cta_url', 'promo_label'] as $attr) {
            if ($this->$attr === null) {
                $this->$attr = '';
            }
        }
    }
}
