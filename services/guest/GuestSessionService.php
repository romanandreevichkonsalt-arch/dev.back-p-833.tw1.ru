<?php

namespace app\services\guest;

use app\models\GuestSession;
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

        $model = GuestSession::findOne(['session_id' => $sessionId]);
        if ($model === null) {
            $model = new GuestSession();
            $model->session_id = $sessionId;
            $model->save(false);
            return;
        }

        $model->updated_at = date('Y-m-d H:i:s');
        $model->save(false, ['updated_at']);
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
