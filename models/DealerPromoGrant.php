<?php

namespace app\models;

use yii\db\ActiveRecord;

class DealerPromoGrant extends ActiveRecord
{
    public const SOURCE_FIRST_LOGIN = 'first_login';
    public const SOURCE_ADMIN = 'admin';
    public const SOURCE_NOVELTY = 'novelty';

    public static function tableName(): string
    {
        return '{{%dealer_promo_grants}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'code', 'discount_percent', 'created_at'], 'required'],
            [['user_id', 'template_id', 'catalog_model_id', 'used_order_id', 'granted_by_admin_id'], 'integer'],
            [['code'], 'string', 'max' => 64],
            [['discount_percent'], 'number', 'min' => 0, 'max' => 100],
            [['source'], 'string', 'max' => 32],
            [['is_active'], 'boolean'],
            [['expires_at', 'used_at', 'created_at'], 'safe'],
        ];
    }

    public function getTemplate()
    {
        return $this->hasOne(PromoCodeTemplate::class, ['id' => 'template_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getUsedOrder()
    {
        return $this->hasOne(Order::class, ['id' => 'used_order_id']);
    }

    public function isUsable(): bool
    {
        if (!$this->is_active || $this->used_at !== null) {
            return false;
        }

        $template = $this->template;
        if ($template !== null && $template->type === PromoCodeTemplate::TYPE_CUSTOM) {
            $validUntil = $template->valid_until !== null ? trim((string)$template->valid_until) : '';
            if ($validUntil !== '') {
                return strtotime($validUntil . ' 23:59:59') >= time();
            }

            // Без даты «Действует до» — промокод действует, пока не использован.
            return true;
        }

        if ($this->expires_at !== null && strtotime($this->expires_at) < time()) {
            return false;
        }

        return true;
    }

    public function getTitle(): string
    {
        if ($this->template !== null) {
            return $this->template->title;
        }

        return $this->code;
    }

    public function getStatusLabel(): string
    {
        if ($this->used_at !== null) {
            return 'Использован';
        }
        if (!$this->is_active) {
            return 'Деактивирован';
        }

        $template = $this->template;
        if ($template !== null && $template->type === PromoCodeTemplate::TYPE_CUSTOM) {
            $validUntil = $template->valid_until !== null ? trim((string)$template->valid_until) : '';
            if ($validUntil !== '' && strtotime($validUntil . ' 23:59:59') < time()) {
                return 'Истёк';
            }

            return 'Активен';
        }

        if ($this->expires_at !== null && strtotime($this->expires_at) < time()) {
            return 'Истёк';
        }

        return 'Активен';
    }

    public static function sourceLabels(): array
    {
        return [
            self::SOURCE_FIRST_LOGIN => 'Первый вход',
            self::SOURCE_ADMIN => 'Администратор',
            self::SOURCE_NOVELTY => 'Новинка',
        ];
    }

    public function getSourceLabel(): string
    {
        return self::sourceLabels()[$this->source] ?? $this->source;
    }
}
