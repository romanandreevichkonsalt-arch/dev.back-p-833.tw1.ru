<?php

namespace app\services\api;

use app\models\User;
use Yii;

final class OptionalBearerUserResolver
{
    public function resolve(): ?User
    {
        $header = Yii::$app->request->headers->get('Authorization');
        if ($header === null || !preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return null;
        }

        $identity = User::findIdentityByAccessToken($matches[1]);

        return $identity instanceof User ? $identity : null;
    }

    public function resolveDealer(): ?User
    {
        $user = $this->resolve();

        return ($user !== null && $user->isDealer()) ? $user : null;
    }
}
