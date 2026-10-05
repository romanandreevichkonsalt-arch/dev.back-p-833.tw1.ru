<?php

namespace app\services\dealer;

use app\models\DealerManager;
use app\models\DealerProfile;
use app\models\User;
use Yii;

final class DealerAssignedManagerService
{
    /**
     * @return array<string, mixed>|null
     */
    public function resolveForUser(User $user): ?array
    {
        if (!$user->isDealer()) {
            return null;
        }

        $profile = $user->dealerProfile;
        if ($profile === null) {
            return $this->resolveFromParams();
        }

        if ($profile->assigned_manager_id !== null) {
            $manager = DealerManager::findOne([
                'id' => (int)$profile->assigned_manager_id,
                'is_active' => true,
            ]);
            if ($manager !== null) {
                return $manager->toAssignedManagerPayload();
            }
        }

        return $this->resolveFromParams();
    }

    public function resolveNotificationEmailForUser(User $user): ?string
    {
        $manager = $this->resolveForUser($user);
        if ($manager !== null) {
            $email = trim((string)($manager['email'] ?? ''));
            if ($email !== '') {
                return $email;
            }
        }

        $notifyEmail = trim((string)(Yii::$app->params['leadsNotifyEmail'] ?? ''));

        return $notifyEmail !== '' ? $notifyEmail : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveFromParams(): ?array
    {
        $defaults = Yii::$app->params['dealerDefaultAssignedManager'] ?? null;
        if (!is_array($defaults) || trim((string)($defaults['name'] ?? '')) === '') {
            return null;
        }

        $avatar = $defaults['avatar'] ?? null;

        return [
            'name' => (string)$defaults['name'],
            'role' => (string)($defaults['role'] ?? 'Менеджер заказов'),
            'phone' => $this->nullableString($defaults['phone'] ?? null),
            'email' => $this->nullableString($defaults['email'] ?? null),
            'hours' => $this->nullableString($defaults['hours'] ?? null),
            'avatar' => is_array($avatar) ? $avatar : null,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }
}
