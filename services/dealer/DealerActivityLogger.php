<?php

namespace app\services\dealer;

use app\models\DealerActivityLog;
use app\models\Order;
use app\models\User;
use Yii;

class DealerActivityLogger
{
    /**
     * @param array<string, mixed> $context
     */
    public function log(User $user, string $action, array $context = []): void
    {
        if (!$user->isDealer()) {
            return;
        }

        $request = Yii::$app->request;
        $entry = new DealerActivityLog([
            'user_id' => (int)$user->id,
            'action' => $action,
            'context' => $context === [] ? null : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip' => $request->userIP,
            'user_agent' => $request->userAgent !== null ? mb_substr((string)$request->userAgent, 0, 512) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $entry->save(false);
    }

    public function promoteDealerTypeIfNeeded(User $user): void
    {
        if (!$user->isDealer()) {
            return;
        }

        $profile = $user->dealerProfile;
        if ($profile === null || $profile->dealer_type !== \app\models\DealerProfile::TYPE_NEW) {
            return;
        }

        $hasOrders = Order::find()->where(['user_id' => (int)$user->id])->exists();
        if (!$hasOrders) {
            return;
        }

        $profile->dealer_type = \app\models\DealerProfile::TYPE_ACTIVE;
        $profile->save(false, ['dealer_type', 'updated_at']);
    }
}
