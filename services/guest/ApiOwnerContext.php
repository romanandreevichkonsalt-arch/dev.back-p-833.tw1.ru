<?php

namespace app\services\guest;

use app\models\User;
use Yii;
use yii\web\IdentityInterface;
use yii\web\UnauthorizedHttpException;

final class ApiOwnerContext
{
    public function __construct(
        public readonly ?int $userId,
        public readonly ?string $sessionId,
    ) {
    }

    public static function resolve(bool $requireAuth = false): self
    {
        $user = Yii::$app->user->identity;
        $sessionId = (new GuestSessionService())->resolveFromRequest();

        if ($user instanceof IdentityInterface) {
            return new self((int)$user->getId(), $sessionId !== '' ? $sessionId : null);
        }

        if ($requireAuth) {
            throw new UnauthorizedHttpException('Bearer token is required.');
        }

        if ($sessionId === '') {
            throw new UnauthorizedHttpException('Нужна авторизация или заголовок X-Session-ID.');
        }

        return new self(null, $sessionId);
    }

    public function isGuest(): bool
    {
        return $this->userId === null;
    }

    public function identity(): ?User
    {
        $identity = Yii::$app->user->identity;

        return $identity instanceof User ? $identity : null;
    }
}
