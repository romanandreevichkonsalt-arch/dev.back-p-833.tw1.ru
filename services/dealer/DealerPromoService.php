<?php

namespace app\services\dealer;

use app\exceptions\ApiValidationException;
use app\models\CatalogBadge;
use app\models\CatalogCollection;
use app\models\CatalogModel;
use app\models\DealerCartCheckout;
use app\models\DealerPromoGrant;
use app\models\DealerProfile;
use app\models\PromoCodeTemplate;
use app\models\User;
use Yii;

class DealerPromoService
{
    public function grantExhibitionPromo(
        User $user,
        string $source = DealerPromoGrant::SOURCE_ADMIN,
        ?int $adminUserId = null,
        bool $force = false,
    ): ?DealerPromoGrant {
        if (!$user->isDealer()) {
            return null;
        }

        $template = PromoCodeTemplate::findExhibitionTemplate();
        if ($template === null) {
            return null;
        }

        if (!$force) {
            $existing = DealerPromoGrant::find()
                ->where([
                    'user_id' => (int)$user->id,
                    'code' => PromoCodeTemplate::CODE_EXHIBITION,
                    'used_at' => null,
                    'is_active' => true,
                ])
                ->with('template')
                ->one();
            if ($existing !== null && $existing->isUsable()) {
                return $existing;
            }
        }

        $grant = new DealerPromoGrant([
            'user_id' => (int)$user->id,
            'template_id' => (int)$template->id,
            'code' => PromoCodeTemplate::CODE_EXHIBITION,
            'discount_percent' => $template->discount_percent,
            'source' => $source,
            'granted_by_admin_id' => $adminUserId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $grant->save(false);

        return $grant;
    }

    public function grantExhibitionOnFirstLogin(User $user): void
    {
        $profile = $user->dealerProfile;
        if ($profile === null || $profile->dealer_type !== DealerProfile::TYPE_NEW) {
            return;
        }

        $this->grantExhibitionPromo($user, DealerPromoGrant::SOURCE_FIRST_LOGIN);
    }

    public static function buildNoveltyPromoCode(string $collectionSlug): string
    {
        $slug = trim($collectionSlug);
        if ($slug === '') {
            return '';
        }

        return 'NOVINKA_' . mb_strtoupper(str_replace('-', '_', $slug));
    }

    public function resolveNoveltyPromoCodeForModel(int $modelId): ?string
    {
        $model = CatalogModel::find()
            ->where(['id' => $modelId])
            ->with('collection')
            ->one();
        if ($model === null || $model->collection === null) {
            return null;
        }

        $code = self::buildNoveltyPromoCode((string)$model->collection->slug);

        return $code !== '' ? $code : null;
    }

    public function ensureNoveltyPromoTemplate(int $modelId, float $discountPercent = 10.0): ?PromoCodeTemplate
    {
        $model = CatalogModel::find()
            ->where(['id' => $modelId])
            ->with('collection')
            ->one();
        if ($model === null || $model->collection === null) {
            return null;
        }

        $code = self::buildNoveltyPromoCode((string)$model->collection->slug);
        if ($code === '') {
            return null;
        }

        $title = 'Новинка — ' . $model->collection->getDisplayName();
        $template = PromoCodeTemplate::findOne(['code' => $code]);
        if ($template === null) {
            $template = new PromoCodeTemplate([
                'code' => $code,
                'title' => $title,
                'discount_percent' => $discountPercent,
                'type' => PromoCodeTemplate::TYPE_NOVELTY,
                'is_single_use' => true,
                'is_active' => true,
                'default_valid_days' => 30,
            ]);
            if (!$template->save(false)) {
                return null;
            }

            return $template;
        }

        $template->title = $title;
        $template->discount_percent = $discountPercent;
        $template->is_active = true;
        if ($template->default_valid_days === null) {
            $template->default_valid_days = 30;
        }
        $template->save(false, ['title', 'discount_percent', 'is_active', 'default_valid_days', 'updated_at']);

        return $template;
    }

    public function grantNoveltyPromosForModel(int $modelId, float $discountPercent = 10.0): int
    {
        $template = $this->ensureNoveltyPromoTemplate($modelId, $discountPercent);
        if ($template === null) {
            return 0;
        }

        return $this->grantNoveltyTemplateToAllDealers($template, $modelId);
    }

    public function grantCustomTemplateToAllDealers(
        PromoCodeTemplate $template,
        ?int $adminUserId = null,
    ): int {
        if ($template->type !== PromoCodeTemplate::TYPE_CUSTOM || !$template->is_active) {
            return 0;
        }

        $dealers = User::find()
            ->where(['type' => User::TYPE_DEALER, 'is_blocked' => false])
            ->all();

        $count = 0;
        foreach ($dealers as $dealer) {
            if ($this->hasActivePromoGrant((int)$dealer->id, $template->code)) {
                continue;
            }

            try {
                $this->grantFromTemplate($dealer, (int)$template->id, $adminUserId, true);
                $count++;
            } catch (ApiValidationException) {
                continue;
            }
        }

        return $count;
    }

    public function grantNoveltyTemplateToAllDealers(PromoCodeTemplate $template, ?int $catalogModelId = null): int
    {
        if ($template->type !== PromoCodeTemplate::TYPE_NOVELTY) {
            return 0;
        }

        $modelId = $catalogModelId ?? $this->resolveCatalogModelIdForNoveltyTemplate($template);
        $dealers = User::find()
            ->where(['type' => User::TYPE_DEALER, 'is_blocked' => false])
            ->all();

        $count = 0;
        foreach ($dealers as $dealer) {
            if ($this->grantNoveltyPromoToDealer($dealer, $template, $modelId, DealerPromoGrant::SOURCE_NOVELTY) !== null) {
                $count++;
            }
        }

        return $count;
    }

    public function grantNoveltyPromoToDealer(
        User $user,
        PromoCodeTemplate $template,
        ?int $catalogModelId = null,
        string $source = DealerPromoGrant::SOURCE_ADMIN,
        ?int $adminUserId = null,
        bool $force = false,
    ): ?DealerPromoGrant {
        if (!$user->isDealer() || $template->type !== PromoCodeTemplate::TYPE_NOVELTY || !$template->is_active) {
            return null;
        }

        $code = mb_strtoupper(trim($template->code));
        if (!$force && $this->hasActivePromoGrant((int)$user->id, $code)) {
            return null;
        }

        $expiresAt = $this->resolveGrantExpiresAt($template);

        $grant = new DealerPromoGrant([
            'user_id' => (int)$user->id,
            'template_id' => (int)$template->id,
            'code' => $code,
            'discount_percent' => (float)$template->discount_percent,
            'catalog_model_id' => $catalogModelId,
            'expires_at' => $expiresAt,
            'source' => $source,
            'granted_by_admin_id' => $adminUserId,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$grant->save(false)) {
            return null;
        }

        return $grant;
    }

    private function hasActivePromoGrant(int $userId, string $code): bool
    {
        $grants = DealerPromoGrant::find()
            ->where(['user_id' => $userId, 'code' => mb_strtoupper(trim($code)), 'is_active' => true, 'used_at' => null])
            ->with('template')
            ->all();

        foreach ($grants as $grant) {
            if ($grant->isUsable()) {
                return true;
            }
        }

        return false;
    }

    private function resolveCatalogModelIdForNoveltyTemplate(PromoCodeTemplate $template): ?int
    {
        if (!str_starts_with(mb_strtoupper($template->code), 'NOVINKA_')) {
            return null;
        }

        $suffix = mb_substr($template->code, 8);
        if ($suffix === '') {
            return null;
        }

        $collection = CatalogCollection::findOne(['slug' => mb_strtolower(str_replace('_', '-', $suffix))]);
        if ($collection === null) {
            return null;
        }

        $noveltyBadgeIds = CatalogBadge::find()
            ->select(['id'])
            ->where(['is_active' => true, 'variant' => CatalogBadge::VARIANT_NEW])
            ->column();
        if ($noveltyBadgeIds === []) {
            return null;
        }

        $modelId = CatalogModel::find()
            ->select(['id'])
            ->where(['collection_id' => (int)$collection->id, 'badge_id' => $noveltyBadgeIds])
            ->orderBy(['id' => SORT_DESC])
            ->scalar();

        return $modelId !== false ? (int)$modelId : null;
    }

    public function resolveModelScopeForGrant(DealerPromoGrant $grant): ?int
    {
        if ($grant->catalog_model_id !== null) {
            return (int)$grant->catalog_model_id;
        }

        $template = $grant->template;
        if ($template === null && $grant->template_id !== null) {
            $template = PromoCodeTemplate::findOne((int)$grant->template_id);
        }

        if ($template !== null && $template->type === PromoCodeTemplate::TYPE_NOVELTY) {
            return $this->resolveCatalogModelIdForNoveltyTemplate($template);
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveBonuses(int $userId): array
    {
        $grants = DealerPromoGrant::find()
            ->where(['user_id' => $userId, 'is_active' => true, 'used_at' => null])
            ->with('template')
            ->orderBy(['id' => SORT_DESC])
            ->all();

        $items = [];
        foreach ($grants as $grant) {
            if (!$grant->isUsable()) {
                continue;
            }

            $items[] = [
                'id' => (int)$grant->id,
                'code' => $grant->code,
                'title' => $grant->getTitle(),
                'discountPercent' => (float)$grant->discount_percent,
                'expiresAt' => $grant->expires_at,
                'catalogCollectionSlug' => $this->resolveCollectionSlugForGrant($grant),
                'catalogModelId' => $grant->catalog_model_id !== null ? (int)$grant->catalog_model_id : null,
            ];
        }

        return $items;
    }

    public function findUsableGrant(int $userId, string $code): ?DealerPromoGrant
    {
        $code = mb_strtoupper(trim($code));
        $grant = DealerPromoGrant::find()
            ->where(['user_id' => $userId, 'code' => $code, 'is_active' => true])
            ->with('template')
            ->orderBy(['id' => SORT_DESC])
            ->one();

        if ($grant === null || !$grant->isUsable()) {
            return null;
        }

        return $grant;
    }

    public function markUsed(DealerPromoGrant $grant, int $orderId): void
    {
        $grant->used_at = date('Y-m-d H:i:s');
        $grant->used_order_id = $orderId;
        $grant->is_active = false;
        $grant->save(false);
    }

    /**
     * @return DealerPromoGrant[]
     */
    public function listGrantsForDealer(int $userId): array
    {
        return DealerPromoGrant::find()
            ->where(['user_id' => $userId])
            ->with(['template', 'usedOrder'])
            ->orderBy(['id' => SORT_DESC])
            ->all();
    }

    public function revokeGrantFromDealer(User $dealer, int $grantId): void
    {
        if (!$dealer->isDealer()) {
            throw new ApiValidationException('Пользователь не является дилером.');
        }

        $grant = DealerPromoGrant::find()
            ->where(['id' => $grantId, 'user_id' => (int)$dealer->id])
            ->one();
        if ($grant === null) {
            throw new ApiValidationException('Промокод не найден у этого дилера.');
        }

        if ($grant->used_at !== null || $grant->used_order_id !== null) {
            $orderNumber = $grant->usedOrder !== null ? (string)$grant->usedOrder->number : null;
            $message = $orderNumber !== null
                ? 'Промокод уже использован в заказе «' . $orderNumber . '» и не может быть удалён.'
                : 'Промокод уже использован и не может быть удалён.';
            throw new ApiValidationException($message, [
                'grantId' => [$message],
            ]);
        }

        DealerCartCheckout::updateAll(
            ['promo_grant_id' => null],
            ['user_id' => (int)$dealer->id, 'promo_grant_id' => (int)$grant->id],
        );

        if (!$grant->delete()) {
            throw new ApiValidationException('Не удалось удалить промокод.');
        }
    }

    /**
     * @return PromoCodeTemplate[]
     */
    public function listGrantableTemplates(): array
    {
        return PromoCodeTemplate::find()
            ->where([
                'is_active' => true,
                'type' => [PromoCodeTemplate::TYPE_EXHIBITION, PromoCodeTemplate::TYPE_NOVELTY, PromoCodeTemplate::TYPE_CUSTOM],
            ])
            ->orderBy(['code' => SORT_ASC])
            ->all();
    }

    public function grantFromTemplate(
        User $user,
        int $templateId,
        ?int $adminUserId = null,
        bool $force = true,
    ): DealerPromoGrant {
        if (!$user->isDealer()) {
            throw new ApiValidationException('Пользователь не является дилером.');
        }

        $template = PromoCodeTemplate::findOne(['id' => $templateId, 'is_active' => true]);
        if ($template === null) {
            throw new ApiValidationException('Промокод не найден или неактивен.');
        }

        if ($template->code === PromoCodeTemplate::CODE_EXHIBITION) {
            $grant = $this->grantExhibitionPromo($user, DealerPromoGrant::SOURCE_ADMIN, $adminUserId, $force);
            if ($grant === null) {
                throw new ApiValidationException('Не удалось выдать промокод.');
            }

            return $grant;
        }

        if ($template->type === PromoCodeTemplate::TYPE_NOVELTY) {
            if (!$force && $template->is_single_use && $this->hasActivePromoGrant((int)$user->id, $template->code)) {
                throw new ApiValidationException('У дилера уже есть активный промокод «' . $template->code . '».');
            }

            $grant = $this->grantNoveltyPromoToDealer(
                $user,
                $template,
                $this->resolveCatalogModelIdForNoveltyTemplate($template),
                DealerPromoGrant::SOURCE_ADMIN,
                $adminUserId,
                $force,
            );
            if ($grant === null) {
                throw new ApiValidationException('Не удалось выдать промокод.');
            }

            return $grant;
        }

        if (!$force && $template->is_single_use) {
            $existing = DealerPromoGrant::find()
                ->where([
                    'user_id' => (int)$user->id,
                    'code' => $template->code,
                    'used_at' => null,
                    'is_active' => true,
                ])
                ->with('template')
                ->one();
            if ($existing !== null && $existing->isUsable()) {
                throw new ApiValidationException('У дилера уже есть активный промокод «' . $template->code . '».');
            }
        }

        $expiresAt = $this->resolveGrantExpiresAt($template);

        $grant = new DealerPromoGrant([
            'user_id' => (int)$user->id,
            'template_id' => (int)$template->id,
            'code' => $template->code,
            'discount_percent' => $template->discount_percent,
            'source' => DealerPromoGrant::SOURCE_ADMIN,
            'granted_by_admin_id' => $adminUserId,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$grant->save()) {
            throw new ApiValidationException('Не удалось выдать промокод.', $grant->getErrors());
        }

        return $grant;
    }

    public function updateTemplateDiscount(int $templateId, float $discountPercent): PromoCodeTemplate
    {
        $template = PromoCodeTemplate::findOne($templateId);
        if ($template === null) {
            throw new ApiValidationException('Промокод не найден.');
        }

        $template->discount_percent = $discountPercent;
        if (!$template->save()) {
            throw new ApiValidationException('Не удалось сохранить промокод.', $template->getErrors());
        }

        return $template;
    }

    public function createTemplate(
        string $code,
        string $title,
        float $discountPercent,
        bool $isSingleUse = true,
        bool $isActive = true,
        ?string $validUntil = null,
    ): PromoCodeTemplate {
        $code = mb_strtoupper(trim($code));
        if ($code === '' || $title === '') {
            throw new ApiValidationException('Заполните код и название.');
        }
        if (PromoCodeTemplate::find()->where(['code' => $code])->exists()) {
            throw new ApiValidationException('Промокод с таким кодом уже существует.', ['code' => ['Код уже используется.']]);
        }

        $validUntil = ($validUntil === null || $validUntil === '') ? null : trim($validUntil);

        $template = new PromoCodeTemplate([
            'code' => $code,
            'title' => trim($title),
            'discount_percent' => $discountPercent,
            'type' => PromoCodeTemplate::TYPE_CUSTOM,
            'is_single_use' => $isSingleUse,
            'is_active' => $isActive,
            'default_valid_days' => null,
            'valid_until' => $validUntil,
        ]);

        if (!$template->save()) {
            throw new ApiValidationException('Не удалось создать промокод.', $template->getErrors());
        }

        return $template;
    }

    public function updateTemplate(PromoCodeTemplate $template, array $data): PromoCodeTemplate
    {
        $template->discount_percent = (float)($data['discount_percent'] ?? $template->discount_percent);
        $template->is_active = (bool)($data['is_active'] ?? $template->is_active);

        if ($template->type === PromoCodeTemplate::TYPE_CUSTOM) {
            $template->title = trim((string)($data['title'] ?? $template->title));
            $template->is_single_use = (bool)($data['is_single_use'] ?? $template->is_single_use);
            $validUntil = $data['valid_until'] ?? null;
            $template->valid_until = ($validUntil === null || $validUntil === '') ? null : trim((string)$validUntil);
            $template->default_valid_days = null;
        }

        if (!$template->save()) {
            throw new ApiValidationException('Не удалось сохранить промокод.', $template->getErrors());
        }

        return $template;
    }

    private function resolveCollectionSlugForGrant(DealerPromoGrant $grant): ?string
    {
        if (!str_starts_with(mb_strtoupper($grant->code), 'NOVINKA_')) {
            return null;
        }

        if ($grant->catalog_model_id !== null) {
            $model = CatalogModel::find()
                ->where(['id' => (int)$grant->catalog_model_id])
                ->with('collection')
                ->one();
            if ($model?->collection !== null && trim((string)$model->collection->slug) !== '') {
                return (string)$model->collection->slug;
            }
        }

        $suffix = mb_substr($grant->code, 8);

        return $suffix !== '' ? mb_strtolower(str_replace('_', '-', $suffix)) : null;
    }

    private function resolveGrantExpiresAt(PromoCodeTemplate $template): ?string
    {
        $validUntil = $template->valid_until !== null ? trim((string)$template->valid_until) : '';
        if ($validUntil !== '') {
            return $validUntil . ' 23:59:59';
        }

        if ($template->default_valid_days !== null) {
            return date('Y-m-d H:i:s', strtotime('+' . (int)$template->default_valid_days . ' days'));
        }

        return null;
    }
}
