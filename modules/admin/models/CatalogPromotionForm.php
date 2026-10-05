<?php

namespace app\modules\admin\models;

use app\models\CatalogProduct;
use app\models\CatalogPromotion;
use app\modules\admin\helpers\HomePageProductsHelper;
use app\services\dealer\DealerPricingService;
use app\services\promotion\CatalogPromotionPricing;
use yii\base\Model;

class CatalogPromotionForm extends Model
{
    public string $title = '';
    public string $discount_type = CatalogPromotion::DISCOUNT_PERCENT;
    public float $discount_value = 10.0;
    public string $starts_at = '';
    public string $ends_at = '';
    public string $scope_type = CatalogPromotion::SCOPE_MODEL;
    public ?int $catalog_model_id = null;
    public ?int $catalog_product_id = null;
    public ?int $image_media_id = null;
    public string $product_search = '';

    /**
     * @param array<string, mixed> $values
     */
    public function setAttributes($values, $safeOnly = true): void
    {
        if (is_array($values)) {
            foreach (['catalog_model_id', 'catalog_product_id', 'image_media_id'] as $field) {
                if (array_key_exists($field, $values)) {
                    $values[$field] = $this->normalizeNullableInt($values[$field]);
                }
            }
        }

        parent::setAttributes($values, $safeOnly);
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit(trim($value))) {
            return (int)trim($value);
        }

        if (is_numeric($value)) {
            return (int)$value;
        }

        return null;
    }

    public function rules(): array
    {
        return [
            [['title', 'discount_type', 'discount_value', 'starts_at', 'ends_at', 'scope_type', 'catalog_model_id'], 'required'],
            [['title'], 'string', 'max' => 255],
            [['discount_type'], 'in', 'range' => [CatalogPromotion::DISCOUNT_PERCENT, CatalogPromotion::DISCOUNT_FIXED]],
            [['scope_type'], 'in', 'range' => [CatalogPromotion::SCOPE_MODEL, CatalogPromotion::SCOPE_PRODUCT]],
            [['discount_value'], 'number', 'min' => 0],
            [['catalog_model_id', 'catalog_product_id', 'image_media_id'], 'integer'],
            [['starts_at', 'ends_at'], 'safe'],
            ['catalog_product_id', 'required', 'when' => static fn (self $m): bool => $m->scope_type === CatalogPromotion::SCOPE_PRODUCT],
            ['discount_value', 'validateDiscountValue'],
            ['ends_at', 'validatePeriod'],
        ];
    }

    public function validateDiscountValue(): void
    {
        if ($this->discount_type === CatalogPromotion::DISCOUNT_PERCENT) {
            if ((float)$this->discount_value > 100) {
                $this->addError('discount_value', 'Процент скидки не может быть больше 100.');

                return;
            }

            return;
        }

        if ($this->discount_type === CatalogPromotion::DISCOUNT_FIXED && (float)$this->discount_value <= 0) {
            $this->addError('discount_value', 'Сумма скидки должна быть больше 0.');

            return;
        }

        if ($this->discount_type !== CatalogPromotion::DISCOUNT_FIXED || $this->scope_type !== CatalogPromotion::SCOPE_PRODUCT) {
            return;
        }

        $productId = (int)($this->catalog_product_id ?? 0);
        if ($productId <= 0) {
            return;
        }

        $product = CatalogProduct::findOne($productId);
        if ($product === null) {
            return;
        }

        $retail = (new DealerPricingService())->resolveRetailPrice($product);
        if ($retail === null || $retail <= 0) {
            return;
        }

        $discountAmount = (float)$this->discount_value;
        if ($discountAmount >= $retail) {
            $this->addError(
                'discount_value',
                'Сумма должна быть меньше розничной цены (' . number_format($retail, 0, '.', ' ') . ' ₽).',
            );
        }
    }

    public function validatePeriod(): void
    {
        if ($this->starts_at === '' || $this->ends_at === '') {
            return;
        }

        if (strtotime($this->ends_at) <= strtotime($this->starts_at)) {
            $this->addError('ends_at', 'Дата окончания должна быть позже начала.');
        }
    }

    public function attributeLabels(): array
    {
        return (new CatalogPromotion())->attributeLabels();
    }

    public static function fromPromotion(CatalogPromotion $promotion): self
    {
        $form = new self();
        $form->title = (string)$promotion->title;
        $form->discount_type = (string)$promotion->discount_type;
        $form->discount_value = (float)$promotion->discount_value;
        $form->starts_at = date('Y-m-d\TH:i', strtotime((string)$promotion->starts_at));
        $form->ends_at = date('Y-m-d\TH:i', strtotime((string)$promotion->ends_at));
        $form->scope_type = (string)$promotion->scope_type;
        $form->catalog_model_id = (int)$promotion->catalog_model_id;
        $form->catalog_product_id = $promotion->catalog_product_id !== null ? (int)$promotion->catalog_product_id : null;
        $form->image_media_id = $promotion->image_media_id !== null ? (int)$promotion->image_media_id : null;
        $form->product_search = '';
        if ($form->catalog_product_id !== null && $form->catalog_product_id > 0) {
            $item = HomePageProductsHelper::pickerItemByProductId($form->catalog_product_id);
            if ($item !== null) {
                $label = trim((string)($item['productTitle'] ?? ''));
                if ($label === '') {
                    $label = trim((string)($item['title'] ?? ''));
                }
                $form->product_search = $label;
            }
        }
        return $form;
    }

    public function applyTo(CatalogPromotion $promotion): void
    {
        $promotion->title = trim($this->title);
        $promotion->discount_type = $this->discount_type;
        $promotion->discount_value = round((float)$this->discount_value, 2);
        $promotion->starts_at = date('Y-m-d H:i:s', strtotime($this->starts_at));
        $promotion->ends_at = date('Y-m-d H:i:s', strtotime($this->ends_at));
        $promotion->scope_type = $this->scope_type;
        $promotion->catalog_model_id = (int)$this->catalog_model_id;
        $promotion->catalog_product_id = $this->scope_type === CatalogPromotion::SCOPE_PRODUCT
            ? (int)$this->catalog_product_id
            : null;
        $promotion->image_media_id = $this->image_media_id !== null && $this->image_media_id > 0
            ? (int)$this->image_media_id
            : null;
    }
}
