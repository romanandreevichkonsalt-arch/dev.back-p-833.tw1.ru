<?php

namespace app\controllers\api\v1;

use app\models\ApiAccessToken;
use app\models\ExternalIdentity;
use app\models\SmsCode;
use app\models\User;
use app\models\UserProfile;
use app\services\SmsSenderInterface;
use app\services\YandexIdServiceInterface;
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
        $behaviors['authenticator']['except'] = ['request-code', 'verify-code', 'yandex-login', 'options'];

        return $behaviors;
    }

    public function verbs(): array
    {
        return [
            'request-code' => ['POST', 'OPTIONS'],
            'verify-code' => ['POST', 'OPTIONS'],
            'yandex-login' => ['POST', 'OPTIONS'],
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

        return $this->issueBearerToken($user);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/yandex",
     *     tags={"Авторизация"},
     *     summary="Вход через Яндекс ID и выдача Bearer-токена",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/AuthYandexInput")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Успешная авторизация через Яндекс ID",
     *         @OA\JsonContent(ref="#/components/schemas/AuthVerifyCodeOutput")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Некорректные входные данные"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Не удалось подтвердить Яндекс ID"
     *     )
     * )
     */
    public function actionYandexLogin(): array
    {
        $body = Yii::$app->request->getBodyParams();
        $accessToken = trim((string)($body['access_token'] ?? ''));
        if ($accessToken === '') {
            throw new BadRequestHttpException('Поле access_token обязательно.');
        }

        /** @var YandexIdServiceInterface $yandex */
        $yandex = Yii::$container->get(YandexIdServiceInterface::class);
        $profile = $yandex->fetchProfileByAccessToken($accessToken);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $externalIdentity = ExternalIdentity::find()
                ->where([
                    'provider' => 'yandex',
                    'provider_user_id' => (string)$profile['id'],
                ])
                ->one();

            if ($externalIdentity !== null) {
                $user = User::findOne((int)$externalIdentity->user_id);
            } else {
                $user = null;
            }

            if ($user === null) {
                $user = $this->createUserForYandex((string)$profile['id'], $profile['display_name'] ?? null);
            }

            if ($externalIdentity === null) {
                $externalIdentity = new ExternalIdentity([
                    'user_id' => (int)$user->id,
                    'provider' => 'yandex',
                    'provider_user_id' => (string)$profile['id'],
                    'email' => $profile['email'] ?? null,
                    'raw_payload' => json_encode($profile['raw'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
                if (!$externalIdentity->save()) {
                    throw new BadRequestHttpException('Не удалось сохранить связку с Яндекс ID.');
                }
            }

            $this->upsertUserProfileFromYandex($user, $profile);

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $this->issueBearerToken($user);
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

    private function issueBearerToken(User $user): array
    {
        $rawToken = Yii::$app->security->generateRandomString(64);
        $expiresAt = date('Y-m-d H:i:s', time() + self::TOKEN_TTL_SECONDS);

        $token = new ApiAccessToken([
            'user_id' => (int)$user->id,
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
                'id' => (int)$user->id,
                'phone' => $user->phone,
            ],
        ];
    }

    private function createUserForYandex(string $providerUserId, ?string $displayName): User
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $user = new User([
                'phone' => '7' . str_pad((string)random_int(0, 9999999999), 10, '0', STR_PAD_LEFT),
                'username' => $displayName ?? ('yandex_' . $providerUserId),
            ]);
            if ($user->save()) {
                return $user;
            }
        }

        throw new BadRequestHttpException('Не удалось создать пользователя для Яндекс ID.');
    }

    /**
     * @param array{id:string,email:?string,display_name:?string,first_name:?string,last_name:?string,login:?string,avatar_url:?string,raw:array<string,mixed>} $profile
     */
    private function upsertUserProfileFromYandex(User $user, array $profile): void
    {
        $userProfile = UserProfile::find()->where(['user_id' => (int)$user->id])->one();
        if ($userProfile === null) {
            $userProfile = new UserProfile(['user_id' => (int)$user->id]);
        }

        $userProfile->first_name = $profile['first_name'] ?? null;
        $userProfile->last_name = $profile['last_name'] ?? null;
        $userProfile->display_name = $profile['display_name'] ?? null;
        $userProfile->email = $profile['email'] ?? null;
        $userProfile->avatar_url = $profile['avatar_url'] ?? null;
        $userProfile->yandex_login = $profile['login'] ?? null;
        $userProfile->raw_payload = json_encode($profile['raw'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!$userProfile->save()) {
            throw new BadRequestHttpException('Не удалось сохранить профиль пользователя.');
        }
    }
}
