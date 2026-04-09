<?php

namespace app\controllers\api\v1;

use app\models\UserProfile;
use OpenApi\Annotations as OA;
use Yii;
use yii\web\UnauthorizedHttpException;

class ProfileController extends ApiController
{
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        unset($behaviors['authenticator']['except']);

        return $behaviors;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/profile/me",
     *     tags={"Профиль"},
     *     summary="Возвращает профиль текущего пользователя",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Профиль текущего пользователя",
     *         @OA\JsonContent(ref="#/components/schemas/ProfileMeResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Не авторизован"
     *     )
     * )
     */
    public function actionMe(): array
    {
        $identity = Yii::$app->user->identity;
        if ($identity === null) {
            throw new UnauthorizedHttpException('Bearer token is required.');
        }

        $profile = UserProfile::find()->where(['user_id' => (int)$identity->getId()])->one();

        return [
            'id' => $identity->getId(),
            'username' => $identity->username ?? null,
            'phone' => $identity->phone ?? null,
            'profile' => $profile ? [
                'first_name' => $profile->first_name,
                'last_name' => $profile->last_name,
                'display_name' => $profile->display_name,
                'email' => $profile->email,
                'avatar_url' => $profile->avatar_url,
                'yandex_login' => $profile->yandex_login,
            ] : null,
        ];
    }

    public function verbs(): array
    {
        return [
            'me' => ['GET', 'OPTIONS'],
        ];
    }
}
