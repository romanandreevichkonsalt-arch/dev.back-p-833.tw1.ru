<?php

namespace app\services\profile;

use app\exceptions\ApiValidationException;
use app\models\DealerProfile;
use app\models\User;
use app\models\UserProfile;
use yii\db\Expression;

class EmailSubscriptionService
{
    /**
     * @return array{subscription: bool}
     */
    public function applyForEmail(string $emailRaw, bool $subscription): array
    {
        $email = $this->normalizeEmail($emailRaw);
        if ($email === null) {
            throw new ApiValidationException('Укажите корректный email.', [
                'email' => ['Некорректный адрес электронной почты.'],
            ]);
        }

        $user = $this->findUserByEmail($email);
        if ($user === null) {
            if (!$subscription) {
                return ['subscription' => false];
            }

            $user = $this->createCustomerWithEmail($email);
        }

        return (new UserSubscriptionService())->update($user, $subscription);
    }

    private function normalizeEmail(string $emailRaw): ?string
    {
        $email = mb_strtolower(trim($emailRaw));
        if ($email === '') {
            return null;
        }

        $validator = new \yii\validators\EmailValidator();

        return $validator->validate($email) ? $email : null;
    }

    private function findUserByEmail(string $email): ?User
    {
        $profile = UserProfile::find()
            ->where(['=', new Expression('LOWER([[email]])'), $email])
            ->one();
        if ($profile !== null) {
            return User::findOne((int)$profile->user_id);
        }

        $dealerProfile = DealerProfile::find()
            ->where(['=', new Expression('LOWER([[email]])'), $email])
            ->one();
        if ($dealerProfile !== null) {
            return User::findOne((int)$dealerProfile->user_id);
        }

        return null;
    }

    private function createCustomerWithEmail(string $email): User
    {
        $user = new User([
            'type' => User::TYPE_CUSTOMER,
            'username' => $this->allocateUniqueUsername($email),
            'subscription' => false,
        ]);
        if (!$user->save(false)) {
            throw new ApiValidationException('Не удалось создать пользователя для подписки.');
        }

        $profile = new UserProfile([
            'user_id' => (int)$user->id,
            'email' => $email,
        ]);
        if (!$profile->save(false)) {
            throw new ApiValidationException('Не удалось сохранить email для подписки.');
        }

        return $user;
    }

    private function allocateUniqueUsername(string $email): string
    {
        $local = strstr($email, '@', true);
        $preferred = is_string($local) && $local !== '' ? $local : $email;
        if (!User::find()->where(['username' => $preferred])->exists()) {
            return $preferred;
        }

        $withDomain = str_replace('@', '-at-', $email);
        if (!User::find()->where(['username' => $withDomain])->exists()) {
            return $withDomain;
        }

        return 'subscriber-' . substr(hash('sha256', $email), 0, 12);
    }
}
