<?php

namespace app\services\dealer;

use app\models\User;
use app\models\UserProfile;
use Yii;

class CustomerUserFactory
{
    public function findOrCreateFromOrderData(string $customerName, string $customerPhone, ?string $customerEmail): ?User
    {
        $phone = $this->normalizePhone($customerPhone);
        $user = $this->findRetailCustomerByPhone($phone);
        if ($user === null) {
            $user = new User([
                'type' => User::TYPE_CUSTOMER,
                'phone' => $phone,
                'username' => $this->allocateUniqueUsername(trim($customerName), $phone),
            ]);
            try {
                if (!$user->save(false)) {
                    Yii::warning(
                        'Retail customer not created for phone ' . $phone . ': ' . json_encode($user->getErrors(), JSON_UNESCAPED_UNICODE),
                        __METHOD__,
                    );

                    return null;
                }
            } catch (\Throwable $e) {
                Yii::warning(
                    'Retail customer not created for phone ' . $phone . ': ' . $e->getMessage(),
                    __METHOD__,
                );

                return null;
            }
        }

        $this->syncProfileFromOrderData($user, trim($customerName), $customerEmail);

        return $user;
    }

    private function findRetailCustomerByPhone(string $phone): ?User
    {
        return User::find()
            ->where(['phone' => $phone])
            ->andWhere([
                'or',
                ['type' => User::TYPE_CUSTOMER],
                ['type' => null],
                ['type' => ''],
            ])
            ->one();
    }

    private function syncProfileFromOrderData(User $user, string $customerName, ?string $customerEmail): void
    {
        if ($user->isDealer()) {
            return;
        }

        if ($user->type !== User::TYPE_CUSTOMER) {
            $user->type = User::TYPE_CUSTOMER;
            $user->save(false, ['type', 'updated_at']);
        }

        $email = $this->normalizeEmail($customerEmail);
        if ($email === null && $customerName === '') {
            return;
        }

        try {
            $profile = UserProfile::find()->where(['user_id' => (int)$user->id])->one();
            if ($profile === null) {
                $profile = new UserProfile(['user_id' => (int)$user->id]);
            }

            if ($email !== null) {
                $profile->email = $email;
            }
            if ($customerName !== '' && trim((string)($profile->display_name ?? '')) === '') {
                $profile->display_name = $customerName;
            }

            $profile->save(false);
        } catch (\Throwable $e) {
            Yii::warning(
                'Customer profile not saved for user #' . (int)$user->id . ': ' . $e->getMessage(),
                __METHOD__,
            );
        }
    }

    private function normalizeEmail(?string $customerEmail): ?string
    {
        $email = trim((string)$customerEmail);
        if ($email === '') {
            return null;
        }

        $validator = new \yii\validators\EmailValidator();

        return $validator->validate($email) ? $email : null;
    }

    private function allocateUniqueUsername(string $customerName, string $phone): string
    {
        $preferred = $customerName !== '' ? $customerName : $phone;
        if (!User::find()->where(['username' => $preferred])->exists()) {
            return $preferred;
        }

        $withPhoneSuffix = $customerName !== ''
            ? $customerName . ' · ' . substr($phone, -4)
            : $phone;
        if (!User::find()->where(['username' => $withPhoneSuffix])->exists()) {
            return $withPhoneSuffix;
        }

        return 'customer-' . $phone;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            $digits = '7' . substr($digits, 1);
        }
        if (strlen($digits) === 10) {
            $digits = '7' . $digits;
        }

        if (!preg_match('/^7\d{10}$/', $digits)) {
            $digits = '7' . str_pad(substr($digits, -10), 10, '0', STR_PAD_LEFT);
        }

        return $digits;
    }
}
