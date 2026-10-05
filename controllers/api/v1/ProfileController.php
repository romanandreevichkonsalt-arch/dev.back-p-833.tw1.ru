<?php

namespace app\controllers\api\v1;

use app\exceptions\ApiValidationException;
use app\models\User;
use app\models\UserProfile;
use app\services\profile\UserSubscriptionService;
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

        $name = null;
        $email = null;
        $avatar = null;
        if ($profile !== null) {
            $name = $profile->display_name
                ?? trim(($profile->first_name ?? '') . ' ' . ($profile->last_name ?? ''))
                ?: null;
            $email = $profile->email;
            $avatar = $profile->avatar_url;
        }

        if ($name === null || $name === '') {
            $name = $identity->username ?? null;
        }

        /** @var User $identity */
        return [
            'id' => (int)$identity->getId(),
            'name' => $name,
            'phone' => $identity->phone ?? null,
            'email' => $email,
            'avatar' => $avatar,
            'subscription' => (bool)$identity->subscription,
        ];
    }

    /**
     * @OA\Put(
     *     path="/api/v1/profile/subscription",
     *     tags={"Профиль"},
     *     summary="Подписка на персональные предложения и новости",
     *     description="Единое поле subscription для розничного пользователя и дилера. true — подписан, false — отписан.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/ProfileSubscriptionRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Текущее состояние подписки",
     *         @OA\JsonContent(ref="#/components/schemas/ProfileSubscriptionResponse")
     *     ),
     *     @OA\Response(response=400, description="Ошибка валидации", @OA\JsonContent(ref="#/components/schemas/ApiValidationError")),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionSubscription(): array
    {
        $identity = Yii::$app->user->identity;
        if ($identity === null) {
            throw new UnauthorizedHttpException('Bearer token is required.');
        }

        $body = Yii::$app->request->getBodyParams();
        if (!array_key_exists('subscription', $body)) {
            throw new ApiValidationException('Укажите subscription: true или false.', [
                'subscription' => ['Поле обязательно.'],
            ]);
        }

        $user = User::findOne((int)$identity->getId());
        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer token is required.');
        }

        return (new UserSubscriptionService())->update($user, $body['subscription']);
    }

    public function verbs(): array
    {
        return [
            'me' => ['GET', 'OPTIONS'],
            'subscription' => ['PUT', 'OPTIONS'],
        ];
    }
}
