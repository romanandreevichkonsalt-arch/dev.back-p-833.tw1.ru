<?php

namespace app\modules\admin\models;

use app\models\PromoCodeTemplate;

trait PromotionPromoFieldsTrait
{
    public const PROMO_NONE = 'none';
    public const PROMO_CREATE = 'create';
    public const PROMO_EXISTING = 'existing';

    public string $promo_mode = self::PROMO_NONE;
    public ?int $template_id = null;
    public string $promo_code = '';
    public string $promo_title = '';
    public float $promo_discount_percent = 10.0;
    public bool $promo_is_single_use = true;
    public bool $promo_is_active = true;
    public ?string $promo_valid_until = null;
    public bool $grant_to_all_dealers = true;

    /**
     * @return array<int, mixed>
     */
    protected function promoFieldRules(): array
    {
        return [
            [['promo_label', 'promo_code', 'promo_title'], 'string', 'max' => 255],
            [
                ['grant_to_all_dealers', 'promo_is_single_use', 'promo_is_active'],
                'boolean',
            ],
            [['template_id'], 'integer'],
            [['promo_mode'], 'in', 'range' => [self::PROMO_NONE, self::PROMO_CREATE, self::PROMO_EXISTING]],
            [['promo_discount_percent'], 'number', 'min' => 0, 'max' => 100],
            [['promo_valid_until'], 'date', 'format' => 'php:Y-m-d'],
            [['promo_code', 'promo_title'], 'required', 'when' => static fn (self $m): bool => $m->promo_mode === self::PROMO_CREATE],
            [['template_id'], 'required', 'when' => static fn (self $m): bool => $m->promo_mode === self::PROMO_EXISTING],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function promoFieldLabels(): array
    {
        return [
            'promo_mode' => 'Промокод',
            'promo_code' => 'Код промокода',
            'promo_title' => 'Название промокода',
            'promo_discount_percent' => 'Скидка промокода, %',
            'promo_is_single_use' => 'Промокод одноразовый',
            'promo_is_active' => 'Промокод активен',
            'promo_valid_until' => 'Промокод действует до',
            'grant_to_all_dealers' => 'Выдать всем дилерам',
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $keys
     */
    protected function sanitizeIntegerFormFields(array &$row, array $keys): void
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            if ($row[$key] === '' || $row[$key] === null) {
                $row[$key] = null;
            }
        }
    }

    public function syncPromoModeFromFields(): void
    {
        if ($this->template_id !== null && (int)$this->template_id > 0) {
            $this->promo_mode = self::PROMO_EXISTING;

            return;
        }

        if (trim($this->promo_code) !== '' || trim($this->promo_title) !== '') {
            $this->promo_mode = self::PROMO_CREATE;
        }
    }

    protected function hydratePromoFromTemplate(?PromoCodeTemplate $template, ?int $templateId): void
    {
        $this->promo_mode = $templateId !== null && $templateId > 0 ? self::PROMO_CREATE : self::PROMO_NONE;
        if ($template === null) {
            return;
        }

        $this->promo_code = (string)$template->code;
        $this->promo_title = (string)$template->title;
        $this->promo_discount_percent = (float)$template->discount_percent;
        $this->promo_is_single_use = (bool)$template->is_single_use;
        $this->promo_is_active = (bool)$template->is_active;
        $this->promo_valid_until = $template->valid_until;
    }
}
