<?php

namespace app\controllers\api\v1;

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
     *     tags={"Profile"},
     *     summary="Returns current user profile",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Current user profile",
     *         @OA\JsonContent(ref="#/components/schemas/ProfileMeResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function actionMe(): array
    {
        $identity = Yii::$app->user->identity;
        if ($identity === null) {
            throw new UnauthorizedHttpException('Bearer token is required.');
        }

        return [
            'id' => $identity->getId(),
            'username' => $identity->username ?? null,
        ];
    }

    public function verbs(): array
    {
        return [
            'me' => ['GET', 'OPTIONS'],
        ];
    }
}
