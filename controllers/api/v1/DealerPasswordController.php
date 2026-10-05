<?php

namespace app\controllers\api\v1;

use app\services\dealer\DealerAccessGuard;
use app\services\dealer\DealerPasswordService;
use OpenApi\Annotations as OA;
use Yii;

class DealerPasswordController extends ApiController
{
    public function __construct(
        $id,
        $module,
        private readonly DealerAccessGuard $accessGuard = new DealerAccessGuard(),
        private readonly DealerPasswordService $passwordService = new DealerPasswordService(),
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function verbs(): array
    {
        return [
            'update' => ['POST', 'OPTIONS'],
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/v1/dealer/password",
     *     tags={"ЛКД — профиль"},
     *     summary="Смена пароля дилера",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/DealerPasswordChangeRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Пароль изменён",
     *         @OA\JsonContent(ref="#/components/schemas/DealerPasswordChangeResponse")
     *     ),
     *     @OA\Response(response=400, description="Ошибка валидации", @OA\JsonContent(ref="#/components/schemas/ApiValidationError")),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Профиль не заполнен")
     * )
     */
    public function actionUpdate(): array
    {
        $user = $this->accessGuard->requireDealer();
        $this->accessGuard->requireCompleteProfile($user);

        $payload = Yii::$app->request->getBodyParams();
        $this->passwordService->change($user, is_array($payload) ? $payload : []);

        return ['ok' => true];
    }
}
