<?php

namespace app\services\guest;

use Yii;
use yii\web\BadRequestHttpException;

class GuestSessionService
{
    public function ensure(string $sessionId): void
    {
        $sessionId = $this->normalize($sessionId);
        if ($sessionId === '') {
            return;
        }

        $now = date('Y-m-d H:i:s');
        Yii::$app->db->createCommand(
            'INSERT INTO {{%guest_sessions}} ([[session_id]], [[created_at]], [[updated_at]])'
            . ' VALUES (:sessionId, :createdAt, :updatedAt)'
            . ' ON DUPLICATE KEY UPDATE [[updated_at]] = :touchAt',
            [
                ':sessionId' => $sessionId,
                ':createdAt' => $now,
                ':updatedAt' => $now,
                ':touchAt' => $now,
            ],
        )->execute();
    }

    /**
     * Как resolveFromRequest(), но без исключения при некорректном ID (для необязательного audit).
     */
    public function resolveFromRequestOptional(): ?string
    {
        try {
            $sessionId = $this->resolveFromRequest();

            return $sessionId !== '' ? $sessionId : null;
        } catch (BadRequestHttpException) {
            return null;
        }
    }

    public function resolveFromRequest(): string
    {
        $request = Yii::$app->request;
        $sessionId = trim((string)$request->headers->get('X-Session-ID', ''));
        if ($sessionId === '') {
            $body = $request->bodyParams;
            $sessionId = trim((string)($body['sessionId'] ?? $body['session_id'] ?? ''));
        }
        if ($sessionId === '') {
            $sessionId = trim((string)($request->get('sessionId') ?: $request->get('session_id', '')));
        }

        if ($sessionId === '') {
            return '';
        }

        $sessionId = $this->normalize($sessionId);
        $this->ensure($sessionId);

        return $sessionId;
    }

    public function normalize(string $sessionId): string
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            return '';
        }

        if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $sessionId)) {
            throw new BadRequestHttpException('Некорректный идентификатор сессии.');
        }

        return $sessionId;
    }
}
