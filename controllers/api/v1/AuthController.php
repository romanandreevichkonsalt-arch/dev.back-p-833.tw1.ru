<?php

namespace app\controllers\api\v1;

use app\models\ApiAccessToken;
use app\models\SmsCode;
use app\models\User;
use app\services\SmsSenderInterface;
use OpenApi\Annotations as OA;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\UnauthorizedHttpException;

class AuthController extends ApiController
{
    private const SMS_CODE_TTL_SECONDS = 300;
    private const TOKEN_TTL_SECONDS = 2592000;

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = ['request-code', 'verify-code', 'options'];

        return $behaviors;
    }

    public function verbs(): array
    {
        return [
            'request-code' => ['POST', 'OPTIONS'],
            'verify-code' => ['POST', 'OPTIONS'],
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/request-code",
     *     tags={"Авторизация"},
     *     summary="Запрашивает SMS-код для входа по номеру телефона",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/AuthRequestCodeInput")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Код отправлен",
     *         @OA\JsonContent(ref="#/components/schemas/AuthRequestCodeOutput")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Некорректные входные данные"
     *     )
     * )
     */
    public function actionRequestCode(): array
    {
        $phone = $this->extractPhoneFromBody();

        $user = User::findByPhone($phone);
        if ($user === null) {
            $user = new User([
                'phone' => $phone,
                'username' => $phone,
            ]);
            if (!$user->save()) {
                throw new BadRequestHttpException('Не удалось создать пользователя.');
            }
        }

        $code = (string) random_int(100000, 999999);
        $expiresAt = date('Y-m-d H:i:s', time() + self::SMS_CODE_TTL_SECONDS);

        $smsCode = new SmsCode([
            'user_id' => (int) $user->id,
            'phone' => $phone,
            'code' => $code,
            'expires_at' => $expiresAt,
        ]);
        if (!$smsCode->save()) {
            throw new BadRequestHttpException('Не удалось сохранить SMS-код.');
        }

        /** @var SmsSenderInterface $smsSender */
        $smsSender = Yii::$container->get(SmsSenderInterface::class);
        $smsSender->sendCode($phone, $code);

        return [
            'phone' => $phone,
            'code' => $code,
            'expires_in' => self::SMS_CODE_TTL_SECONDS,
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/verify-code",
     *     tags={"Авторизация"},
     *     summary="Проверяет SMS-код и возвращает Bearer-токен",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/AuthVerifyCodeInput")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Успешная авторизация",
     *         @OA\JsonContent(ref="#/components/schemas/AuthVerifyCodeOutput")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Неверный или просроченный код"
     *     )
     * )
     */
    public function actionVerifyCode(): array
    {
        $body = Yii::$app->request->getBodyParams();
        $phone = $this->normalizePhone($body['phone'] ?? '');
        $code = trim((string) ($body['code'] ?? ''));

        if (!preg_match('/^\d{6}$/', $code)) {
            throw new BadRequestHttpException('Код должен содержать 6 цифр.');
        }

        $smsCode = SmsCode::find()
            ->where([
                'phone' => $phone,
                'code' => $code,
                'used_at' => null,
            ])
            ->andWhere(['>', 'expires_at', date('Y-m-d H:i:s')])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        if ($smsCode === null) {
            throw new UnauthorizedHttpException('Неверный или просроченный код.');
        }

        $user = User::findByPhone($phone);
        if ($user === null) {
            throw new UnauthorizedHttpException('Пользователь не найден.');
        }

        $smsCode->used_at = date('Y-m-d H:i:s');
        $smsCode->save(false);

        $rawToken = Yii::$app->security->generateRandomString(64);
        $expiresAt = date('Y-m-d H:i:s', time() + self::TOKEN_TTL_SECONDS);

        $token = new ApiAccessToken([
            'user_id' => (int) $user->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => $expiresAt,
        ]);
        if (!$token->save()) {
            throw new BadRequestHttpException('Не удалось выпустить токен.');
        }

        return [
            'token_type' => 'Bearer',
            'access_token' => $rawToken,
            'expires_in' => self::TOKEN_TTL_SECONDS,
            'user' => [
                'id' => (int) $user->id,
                'phone' => $user->phone,
            ],
        ];
    }

    private function extractPhoneFromBody(): string
    {
        $body = Yii::$app->request->getBodyParams();

        return $this->normalizePhone($body['phone'] ?? '');
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        if (!preg_match('/^7\d{10}$/', $phone)) {
            throw new BadRequestHttpException('Телефон должен быть в формате 79998886644.');
        }

        return $phone;
    }
}
