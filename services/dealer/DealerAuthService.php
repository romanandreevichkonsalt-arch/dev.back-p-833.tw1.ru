<?php

namespace app\services\dealer;

use app\models\ApiAccessToken;
use app\models\DealerProfile;
use app\models\User;
use app\services\guest\GuestDataSyncService;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\UnauthorizedHttpException;

class DealerAuthService
{
    private const TOKEN_TTL_SECONDS = 2592000;

    public function __construct(
        private readonly DealerActivityLogger $activityLogger = new DealerActivityLogger(),
    ) {
    }

    public function login(string $username, string $password): array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            throw new BadRequestHttpException('Логин и пароль обязательны.');
        }

        $user = User::findDealerByUsername($username);
        if ($user === null || $user->is_blocked || !$user->validatePassword($password)) {
            throw new UnauthorizedHttpException('Неверный логин или пароль.');
        }

        if ($user->dealerProfile?->first_login_at === null) {
            $profile = $user->dealerProfile;
            if ($profile !== null) {
                $profile->first_login_at = date('Y-m-d H:i:s');
                $profile->save(false, ['first_login_at', 'updated_at']);
            }

            (new DealerPromoService())->grantExhibitionOnFirstLogin($user);
        }

        $tokenPayload = $this->issueBearerToken($user);
        $this->activityLogger->log($user, 'auth.login');

        return array_merge($tokenPayload, [
            'profileComplete' => $user->isProfileComplete(),
            'guestSync' => (new GuestDataSyncService())->syncForCurrentRequest($user),
        ]);
    }

    public function logout(User $user, ?string $rawToken = null): void
    {
        if ($rawToken !== null && $rawToken !== '') {
            $tokenHash = hash('sha256', $rawToken);
            ApiAccessToken::updateAll(
                ['revoked_at' => date('Y-m-d H:i:s')],
                ['user_id' => (int)$user->id, 'token_hash' => $tokenHash, 'revoked_at' => null]
            );
        }

        $this->activityLogger->log($user, 'auth.logout');
    }

    public function revokeAllTokens(User $user): void
    {
        ApiAccessToken::updateAll(
            ['revoked_at' => date('Y-m-d H:i:s')],
            ['user_id' => (int)$user->id, 'revoked_at' => null]
        );
    }

    /**
     * @return array{access_token:string,token_type:string,expires_in:int}
     */
    public function issueBearerToken(User $user): array
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
            'access_token' => $rawToken,
            'token_type' => 'Bearer',
            'expires_in' => self::TOKEN_TTL_SECONDS,
        ];
    }
}
