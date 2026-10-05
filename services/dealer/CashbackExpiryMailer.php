<?php

namespace app\services\dealer;

use app\models\DealerProfile;
use app\models\User;
use Yii;

class CashbackExpiryMailer
{
    public function send(User $user, DealerProfile $profile, float $amount, string $expiresAt): bool
    {
        $email = trim((string)$profile->email);
        if ($email === '') {
            return false;
        }

        $cabinetUrl = (string)(Yii::$app->params['dealerCabinetUrl'] ?? '');
        $expiresLabel = date('d.m.Y', strtotime($expiresAt));
        $amountLabel = number_format($amount, 0, '.', ' ');
        $subject = 'Кэшбек скоро сгорит — МФ Анна';

        try {
            return (bool)Yii::$app->mailer->compose(
                ['html' => 'dealer/cashback-expiring-html', 'text' => 'dealer/cashback-expiring-text'],
                [
                    'companyName' => (string)$profile->company_name,
                    'amount' => $amountLabel,
                    'expiresAt' => $expiresLabel,
                    'cabinetUrl' => $cabinetUrl,
                ]
            )
                ->setTo($email)
                ->setFrom([Yii::$app->params['senderEmail'] => Yii::$app->params['senderName']])
                ->setSubject($subject)
                ->send();
        } catch (\Throwable $e) {
            Yii::error('Cashback expiry email failed: ' . $e->getMessage(), __METHOD__);

            return false;
        }
    }
}
