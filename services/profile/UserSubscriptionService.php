<?php

namespace app\services\profile;

use app\exceptions\ApiValidationException;
use app\models\User;

class UserSubscriptionService
{
    /**
     * @return array{subscription: bool}
     */
    public function update(User $user, mixed $subscriptionRaw): array
    {
        if (!is_bool($subscriptionRaw)) {
            throw new ApiValidationException('Укажите subscription: true или false.', [
                'subscription' => ['Значение должно быть boolean.'],
            ]);
        }

        $user->subscription = $subscriptionRaw;
        if (!$user->save(false, ['subscription', 'updated_at'])) {
            throw new ApiValidationException('Не удалось сохранить подписку.');
        }

        return ['subscription' => (bool)$user->subscription];
    }
}
