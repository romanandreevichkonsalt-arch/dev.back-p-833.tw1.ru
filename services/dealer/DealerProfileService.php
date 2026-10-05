<?php

namespace app\services\dealer;

use app\exceptions\ApiValidationException;
use app\models\DealerProfile;
use app\models\User;
use app\services\promotion\PromotionDealerApiService;
use Yii;

class DealerProfileService
{
    public function __construct(
        private readonly DealerAssignedManagerService $assignedManagerService = new DealerAssignedManagerService(),
        private readonly DealerPricingService $pricingService = new DealerPricingService(),
        private readonly DealerPriceListService $priceListService = new DealerPriceListService(),
        private readonly CashbackService $cashbackService = new CashbackService(),
        private readonly PromotionDealerApiService $promotionDealerApiService = new PromotionDealerApiService(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(User $user): array
    {
        $profile = $user->dealerProfile;

        return [
            'id' => (int)$user->id,
            'subscription' => (bool)$user->subscription,
            'username' => (string)$user->username,
            'inn' => $profile?->inn,
            'companyName' => $profile?->company_name,
            'managerName' => $profile?->manager_name,
            'email' => $profile?->email,
            'phone' => $user->phone,
            'dealerType' => $profile?->dealer_type,
            'dealerTypeLabel' => $profile?->getTypeLabel(),
            'profileComplete' => $user->isProfileComplete(),
            'isBlocked' => (bool)$user->is_blocked,
            'credentialsSentAt' => $profile?->credentials_sent_at,
            'firstLoginAt' => $profile?->first_login_at,
            'assignedManager' => $this->assignedManagerService->resolveForUser($user),
            'personalDiscountPercent' => $profile?->personal_discount_percent !== null
                ? (float)$profile->personal_discount_percent
                : null,
            'effectiveDiscountPercent' => $user->isDealer()
                ? $this->pricingService->getEffectiveDiscountPercent($user)
                : null,
            'priceList' => $user->isDealer()
                ? $this->priceListService->resolveEffectiveForDealer($user)
                : null,
            'cashback' => $user->isDealer()
                ? $this->cashbackService->getWidget((int)$user->id)
                : null,
            'promotionBanners' => $user->isDealer()
                ? $this->promotionDealerApiService->listVisibleBanners()
                : [],
            'catalogPromotions' => $user->isDealer()
                ? $this->promotionDealerApiService->listActiveCatalogPromotions()
                : [],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function update(User $user, array $payload): array
    {
        if (!$user->isDealer()) {
            throw new ApiValidationException('Пользователь не является дилером.');
        }

        $profile = $user->dealerProfile;
        if ($profile === null) {
            throw new ApiValidationException('Профиль дилера не найден.');
        }

        $inn = preg_replace('/\D+/', '', trim((string)($payload['inn'] ?? $profile->inn))) ?? '';
        $managerName = trim((string)($payload['managerName'] ?? $profile->manager_name ?? ''));
        $email = trim((string)($payload['email'] ?? $profile->email ?? ''));
        $phone = trim((string)($payload['phone'] ?? $user->phone ?? ''));

        if ($inn === '') {
            throw new ApiValidationException('ИНН обязателен.', ['inn' => ['Обязательное поле.']]);
        }
        if (!preg_match('/^\d{10}(\d{2})?$/', $inn)) {
            throw new ApiValidationException('Некорректный ИНН.', ['inn' => ['ИНН должен содержать 10 или 12 цифр.']]);
        }
        if ($managerName === '') {
            throw new ApiValidationException('ФИО менеджера обязательно.', ['managerName' => ['Обязательное поле.']]);
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiValidationException('Укажите корректный email.', ['email' => ['Некорректный email.']]);
        }

        $phone = $this->normalizePhone($phone);

        $existingInn = DealerProfile::find()
            ->where(['inn' => $inn])
            ->andWhere(['<>', 'user_id', (int)$user->id])
            ->exists();
        if ($existingInn) {
            throw new ApiValidationException('ИНН уже используется другим дилером.', ['inn' => ['ИНН уже используется.']]);
        }

        $existingPhone = User::find()
            ->where(['phone' => $phone])
            ->andWhere(['<>', 'id', (int)$user->id])
            ->exists();
        if ($existingPhone) {
            throw new ApiValidationException('Телефон уже используется.', ['phone' => ['Телефон уже используется.']]);
        }

        $profile->inn = $inn;
        $profile->manager_name = $managerName;
        $profile->email = $email;
        $user->phone = $phone;

        if (!$profile->save()) {
            throw new ApiValidationException('Не удалось сохранить профиль.', $profile->getErrors());
        }
        if (!$user->save(false, ['phone', 'updated_at'])) {
            throw new ApiValidationException('Не удалось сохранить телефон.', $user->getErrors());
        }

        $user->markProfileCompleteIfReady();

        return $this->toPayload($user);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($phone) === 10 && str_starts_with($phone, '9')) {
            $phone = '7' . $phone;
        }
        if (!preg_match('/^7\d{10}$/', $phone)) {
            throw new ApiValidationException('Телефон должен быть в формате 79998886644.', ['phone' => ['Некорректный телефон.']]);
        }

        return $phone;
    }
}
