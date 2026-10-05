<?php

namespace app\controllers\api\v1;

use app\services\dealer\DealerAccessGuard;
use app\services\dealer\DealerProfileService;
use OpenApi\Annotations as OA;
use Yii;

class DealerProfileController extends ApiController
{
    public function __construct(
        $id,
        $module,
        private readonly DealerAccessGuard $accessGuard = new DealerAccessGuard(),
        private readonly DealerProfileService $profileService = new DealerProfileService(),
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function verbs(): array
    {
        return [
            'index' => ['GET', 'OPTIONS'],
            'update' => ['PUT', 'OPTIONS'],
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dealer/profile",
     *     tags={"ЛКД — профиль"},
     *     summary="Профиль дилера (менеджер, скидка, прайс, баннеры и акции каталога)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Профиль",
     *         @OA\JsonContent(ref="#/components/schemas/DealerProfileResponse")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionIndex(): array
    {
        $user = $this->accessGuard->requireDealer();

        return $this->profileService->toPayload($user);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/dealer/profile",
     *     tags={"ЛКД — профиль"},
     *     summary="Заполнение/обновление профиля дилера",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/DealerProfileUpdateRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Обновлённый профиль",
     *         @OA\JsonContent(ref="#/components/schemas/DealerProfileResponse")
     *     ),
     *     @OA\Response(response=400, description="Ошибка валидации", @OA\JsonContent(ref="#/components/schemas/ApiValidationError")),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionUpdate(): array
    {
        $user = $this->accessGuard->requireDealer();
        $payload = Yii::$app->request->getBodyParams();

        return $this->profileService->update($user, $payload);
    }
}
