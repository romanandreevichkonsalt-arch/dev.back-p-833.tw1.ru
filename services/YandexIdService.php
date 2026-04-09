<?php

namespace app\services;

use yii\web\UnauthorizedHttpException;

class YandexIdService implements YandexIdServiceInterface
{
    private string $userinfoUrl;

    public function __construct(array $config = [])
    {
        $this->userinfoUrl = $config['userinfoUrl'] ?? 'https://login.yandex.ru/info?format=json';
    }

    public function fetchProfileByAccessToken(string $accessToken): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: OAuth {$accessToken}\r\nAccept: application/json\r\n",
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents($this->userinfoUrl, false, $context);
        if ($raw === false) {
            throw new UnauthorizedHttpException('Не удалось получить профиль Яндекс ID.');
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            throw new UnauthorizedHttpException('Яндекс ID вернул некорректный ответ.');
        }

        $id = (string)($payload['id'] ?? $payload['default_email'] ?? '');
        if ($id === '') {
            throw new UnauthorizedHttpException('Не удалось определить идентификатор пользователя Яндекс ID.');
        }

        return [
            'id' => $id,
            'email' => isset($payload['default_email']) ? (string)$payload['default_email'] : null,
            'display_name' => isset($payload['display_name']) ? (string)$payload['display_name'] : null,
            'first_name' => isset($payload['first_name']) ? (string)$payload['first_name'] : null,
            'last_name' => isset($payload['last_name']) ? (string)$payload['last_name'] : null,
            'login' => isset($payload['login']) ? (string)$payload['login'] : null,
            'avatar_url' => isset($payload['default_avatar_id']) && $payload['default_avatar_id'] !== ''
                ? 'https://avatars.yandex.net/get-yapic/' . (string)$payload['default_avatar_id'] . '/islands-200'
                : null,
            'raw' => $payload,
        ];
    }
}
