<?php

namespace app\services\dealer;

use app\models\DealerProfile;
use app\models\DealerProgramSettings;

class DealerCashbackExpiryResolver
{
    public function defaultExpiryDays(): int
    {
        return (int)DealerProgramSettings::getSingleton()->cashback_default_expiry_days;
    }

    public function expiryDaysForDealer(int $userId): int
    {
        $profile = DealerProfile::findOne(['user_id' => $userId]);
        if ($profile !== null && $profile->cashback_expiry_days !== null && (int)$profile->cashback_expiry_days > 0) {
            return (int)$profile->cashback_expiry_days;
        }

        return $this->defaultExpiryDays();
    }

    public function saveDefaultExpiryDays(int $days): void
    {
        $settings = DealerProgramSettings::getSingleton();
        $settings->cashback_default_expiry_days = $days;
        $settings->updated_at = date('Y-m-d H:i:s');
        $settings->save(false);
    }
}
