<?php

namespace app\services\moodboard;

use app\models\User;
use app\models\UserProfile;
use Yii;
use yii\web\UnauthorizedHttpException;

final class MoodboardAuth
{
    public static function requireUser(): User
    {
        $identity = Yii::$app->user->identity;
        if (!$identity instanceof User) {
            throw new UnauthorizedHttpException('Bearer token is required.');
        }

        return $identity;
    }

    /**
     * @return array{id: int, displayName: string|null}
     */
    public static function authorPayload(User $user): array
    {
        $profile = UserProfile::find()->where(['user_id' => (int)$user->getId()])->one();

        $displayName = null;
        if ($profile !== null) {
            $displayName = $profile->display_name
                ?? trim(($profile->first_name ?? '') . ' ' . ($profile->last_name ?? ''))
                ?: null;
        }

        if ($displayName === null || $displayName === '') {
            $displayName = $user->username ?? null;
        }

        return [
            'id' => (int)$user->getId(),
            'displayName' => $displayName,
        ];
    }
}
