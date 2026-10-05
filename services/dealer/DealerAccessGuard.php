<?php

namespace app\services\dealer;

use app\exceptions\ProfileIncompleteException;
use app\models\User;
use Yii;
use yii\web\UnauthorizedHttpException;

class DealerAccessGuard
{
    public function requireDealer(): User
    {
        $identity = Yii::$app->user->identity;
        if ($identity === null || !($identity instanceof User)) {
            throw new UnauthorizedHttpException('Bearer token is required.');
        }

        if (!$identity->isDealer()) {
            throw new UnauthorizedHttpException('Доступ только для дилеров.');
        }

        if ($identity->is_blocked) {
            throw new UnauthorizedHttpException('Доступ заблокирован.');
        }

        return $identity;
    }

    public function requireCompleteProfile(User $user): void
    {
        if (!$user->isProfileComplete()) {
            throw new ProfileIncompleteException();
        }
    }

    public function ensureDealerProfileComplete(?User $user): void
    {
        if ($user !== null && $user->isDealer()) {
            $this->requireCompleteProfile($user);
        }
    }
}
