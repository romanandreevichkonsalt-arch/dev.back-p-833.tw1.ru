<?php

namespace app\services\dealer;

use app\exceptions\ApiValidationException;
use app\models\User;

final class DealerPasswordService
{
    private const MIN_LENGTH = 8;

    /**
     * @param array<string, mixed> $payload
     */
    public function change(User $user, array $payload): void
    {
        if (!$user->isDealer()) {
            throw new ApiValidationException('Доступ только для дилеров.');
        }

        $currentPassword = (string)($payload['currentPassword'] ?? $payload['current_password'] ?? '');
        $newPassword = (string)($payload['newPassword'] ?? $payload['new_password'] ?? '');

        if ($currentPassword === '') {
            throw new ApiValidationException('Укажите текущий пароль.', [
                'currentPassword' => ['Обязательное поле.'],
            ]);
        }

        if (!$user->validatePassword($currentPassword)) {
            throw new ApiValidationException('Неверный текущий пароль.', [
                'currentPassword' => ['Пароль не совпадает.'],
            ]);
        }

        if (mb_strlen($newPassword) < self::MIN_LENGTH) {
            throw new ApiValidationException('Новый пароль слишком короткий.', [
                'newPassword' => ['Минимум ' . self::MIN_LENGTH . ' символов.'],
            ]);
        }

        if ($newPassword === $currentPassword) {
            throw new ApiValidationException('Новый пароль должен отличаться от текущего.', [
                'newPassword' => ['Выберите другой пароль.'],
            ]);
        }

        $user->setPassword($newPassword);
        if (!$user->save(false, ['password_hash', 'updated_at'])) {
            throw new ApiValidationException('Не удалось сохранить пароль.', $user->getErrors());
        }
    }
}
