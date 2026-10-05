<?php

namespace app\services\dealer;

use app\models\ApiAccessToken;
use app\models\DealerCredentialsLog;
use app\models\DealerProfile;
use app\models\User;
use Yii;

class DealerCredentialsMailer
{
    public function send(User $user, DealerProfile $profile, string $plainPassword, ?int $adminUserId = null): bool
    {
        $email = trim((string)$profile->email);
        if ($email === '') {
            $this->logAttempt($user, $email, false, 'Email не указан.', $adminUserId);

            return false;
        }

        $cabinetUrl = (string)(Yii::$app->params['dealerCabinetUrl'] ?? '');
        $subject = 'Доступ в личный кабинет дилера — МФ Анна';

        try {
            $sent = Yii::$app->mailer->compose(
                ['html' => 'dealer/credentials-html', 'text' => 'dealer/credentials-text'],
                [
                    'companyName' => $profile->company_name,
                    'username' => (string)$user->username,
                    'password' => $plainPassword,
                    'cabinetUrl' => $cabinetUrl,
                ]
            )
                ->setTo($email)
                ->setFrom([Yii::$app->params['senderEmail'] => Yii::$app->params['senderName']])
                ->setSubject($subject)
                ->send();

            if ($sent) {
                $profile->credentials_sent_at = date('Y-m-d H:i:s');
                $profile->save(false, ['credentials_sent_at', 'updated_at']);
            }

            $this->logAttempt(
                $user,
                $email,
                $sent,
                $sent ? null : 'Mailer вернул false.',
                $adminUserId
            );

            return $sent;
        } catch (\Throwable $e) {
            $this->logAttempt($user, $email, false, $e->getMessage(), $adminUserId);
            Yii::error('Dealer credentials email failed: ' . $e->getMessage(), __METHOD__);

            return false;
        }
    }

    private function logAttempt(
        User $user,
        string $email,
        bool $isSuccess,
        ?string $errorMessage,
        ?int $adminUserId
    ): void {
        $log = new DealerCredentialsLog([
            'user_id' => (int)$user->id,
            'admin_user_id' => $adminUserId,
            'email' => $email !== '' ? $email : '—',
            'is_success' => $isSuccess,
            'error_message' => $errorMessage,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $log->save(false);
    }
}
