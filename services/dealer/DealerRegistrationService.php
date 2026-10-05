<?php

namespace app\services\dealer;

use app\exceptions\ApiValidationException;
use app\models\DealerManager;
use app\models\DealerProfile;
use app\models\DealerPromoGrant;
use app\models\User;
use Yii;

class DealerRegistrationService
{
    public function __construct(
        private readonly DealerCredentialGenerator $credentialGenerator = new DealerCredentialGenerator(),
        private readonly DealerCredentialsMailer $credentialsMailer = new DealerCredentialsMailer(),
        private readonly DealerAuthService $authService = new DealerAuthService(),
    ) {
    }

    /**
     * @return array{user:User,profile:DealerProfile,password:string,emailSent:bool}
     */
    public function create(
        string $companyName,
        string $inn,
        ?string $managerName,
        ?string $email,
        ?string $phone,
        bool $sendEmail,
        ?int $adminUserId = null,
        string $dealerType = DealerProfile::TYPE_NEW,
        ?int $assignedManagerId = null,
    ): array {
        $companyName = trim($companyName);
        $innDigits = preg_replace('/\D+/', '', $inn) ?? '';
        $inn = $innDigits !== '' ? $innDigits : null;
        $email = $email !== null ? trim($email) : null;
        $phone = $phone !== null ? $this->normalizePhone($phone) : null;

        if ($companyName === '') {
            throw new ApiValidationException('Укажите название или ФИО.', ['companyName' => ['Обязательное поле.']]);
        }
        if ($inn !== null && !preg_match('/^\d{10}(\d{2})?$/', $inn)) {
            throw new ApiValidationException('Некорректный ИНН.', ['inn' => ['ИНН должен содержать 10 или 12 цифр.']]);
        }
        if ($inn !== null && DealerProfile::find()->where(['inn' => $inn])->exists()) {
            throw new ApiValidationException('Дилер с таким ИНН уже зарегистрирован.', ['inn' => ['ИНН уже используется.']]);
        }
        if ($sendEmail && ($email === null || $email === '')) {
            throw new ApiValidationException('Укажите email для отправки доступа.', ['email' => ['Email обязателен для отправки.']]);
        }

        if ($assignedManagerId !== null) {
            $assignedManager = DealerManager::findOne([
                'id' => $assignedManagerId,
                'is_active' => true,
            ]);
            if ($assignedManager === null) {
                throw new ApiValidationException('Менеджер не найден.', [
                    'assignedManagerId' => ['Выберите активного менеджера из списка.'],
                ]);
            }
        }

        $plainPassword = $this->credentialGenerator->generatePassword();
        $username = $this->credentialGenerator->generateUsername($inn);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $user = new User([
                'type' => User::TYPE_DEALER,
                'username' => $username,
                'phone' => $phone,
            ]);
            $user->setPassword($plainPassword);

            if (!$user->save()) {
                throw new ApiValidationException('Не удалось создать пользователя.', $user->getErrors());
            }

            $profile = new DealerProfile([
                'user_id' => (int)$user->id,
                'inn' => $inn,
                'company_name' => $companyName,
                'manager_name' => $managerName !== null ? trim($managerName) : null,
                'email' => $email,
                'dealer_type' => $dealerType,
                'created_by_admin_id' => $adminUserId,
                'assigned_manager_id' => $assignedManagerId,
            ]);

            if (!$profile->save()) {
                throw new ApiValidationException('Не удалось сохранить профиль дилера.', $profile->getErrors());
            }

            $user->markProfileCompleteIfReady();

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        $emailSent = false;
        if ($sendEmail) {
            $emailSent = $this->credentialsMailer->send($user, $profile, $plainPassword, $adminUserId);
        }

        if ($dealerType === DealerProfile::TYPE_NEW) {
            (new DealerPromoService())->grantExhibitionPromo($user, DealerPromoGrant::SOURCE_FIRST_LOGIN);
        }

        return [
            'user' => $user,
            'profile' => $profile,
            'password' => $plainPassword,
            'emailSent' => $emailSent,
        ];
    }

    public function resetPasswordForCopy(User $user): array
    {
        if (!$user->isDealer()) {
            throw new ApiValidationException('Пользователь не является дилером.');
        }

        $plainPassword = $this->credentialGenerator->generatePassword();
        $user->setPassword($plainPassword);
        if (!$user->save(false, ['password_hash', 'updated_at'])) {
            throw new ApiValidationException('Не удалось обновить пароль.');
        }

        $this->authService->revokeAllTokens($user);

        return ['password' => $plainPassword];
    }

    public function resendCredentials(User $user, ?int $adminUserId = null): array
    {
        if (!$user->isDealer()) {
            throw new ApiValidationException('Пользователь не является дилером.');
        }

        $profile = $user->dealerProfile;
        if ($profile === null) {
            throw new ApiValidationException('Профиль дилера не найден.');
        }

        $plainPassword = $this->credentialGenerator->generatePassword();
        $user->setPassword($plainPassword);
        if (!$user->save(false, ['password_hash', 'updated_at'])) {
            throw new ApiValidationException('Не удалось обновить пароль.');
        }

        $this->authService->revokeAllTokens($user);

        $emailSent = false;
        if ($profile->email !== null && trim($profile->email) !== '') {
            $emailSent = $this->credentialsMailer->send($user, $profile, $plainPassword, $adminUserId);
        }

        return [
            'password' => $plainPassword,
            'emailSent' => $emailSent,
        ];
    }

    public function setBlocked(User $user, bool $blocked): void
    {
        if (!$user->isDealer()) {
            throw new ApiValidationException('Пользователь не является дилером.');
        }

        $user->is_blocked = $blocked;
        $user->save(false, ['is_blocked', 'updated_at']);

        if ($blocked) {
            $this->authService->revokeAllTokens($user);
        }
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($phone) === 10 && str_starts_with($phone, '9')) {
            $phone = '7' . $phone;
        }
        if (!preg_match('/^7\d{10}$/', $phone)) {
            throw new ApiValidationException('Телефон должен быть в формате 79998886644.');
        }

        return $phone;
    }
}
