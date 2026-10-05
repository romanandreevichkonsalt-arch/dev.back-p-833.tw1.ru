<?php

namespace app\controllers\api\v1;

use app\services\dealer\DealerAccessGuard;
use app\services\dealer\DealerAuthService;
use OpenApi\Annotations as OA;
use Yii;
use yii\web\UnauthorizedHttpException;

class DealerAuthController extends ApiController
{
    public function __construct(
        $id,
        $module,
        private readonly DealerAuthService $authService = new DealerAuthService(),
        private readonly DealerAccessGuard $accessGuard = new DealerAccessGuard(),
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = ['login', 'options'];

        return $behaviors;
    }

    public function verbs(): array
    {
        return [
            'login' => ['POST', 'OPTIONS'],
            'logout' => ['POST', 'OPTIONS'],
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/v1/dealer/auth/login",
     *     tags={"ЛКД — авторизация"},
     *     summary="Вход дилера по логину и паролю",
     *     description="При sessionId (X-Session-ID или тело) в ответе guestSync — автоматический merge гостевого избранного и корзины.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/DealerAuthLoginRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bearer-токен",
     *         @OA\JsonContent(ref="#/components/schemas/DealerAuthLoginResponse")
     *     ),
     *     @OA\Response(response=401, description="Неверные учётные данные", @OA\JsonContent(ref="#/components/schemas/ApiValidationError"))
     * )
     */
    public function actionLogin(): array
    {
        $body = Yii::$app->request->getBodyParams();

        $username = trim((string)($body['username'] ?? $body['login'] ?? ''));

        return $this->authService->login(
            $username,
            (string)($body['password'] ?? '')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/dealer/auth/logout",
     *     tags={"ЛКД — авторизация"},
     *     summary="Выход дилера",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Выход выполнен",
     *         @OA\JsonContent(ref="#/components/schemas/DealerAuthLogoutResponse")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionLogout(): array
    {
        $user = $this->accessGuard->requireDealer();
        $rawToken = $this->extractBearerToken();

        $this->authService->logout($user, $rawToken);

        return ['ok' => true];
    }

    private function extractBearerToken(): ?string
    {
        $header = Yii::$app->request->headers->get('Authorization');
        if ($header === null || !preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
