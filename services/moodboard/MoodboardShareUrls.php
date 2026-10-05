<?php

namespace app\services\moodboard;

use app\models\Moodboard;
use Yii;

final class MoodboardShareUrls
{
    public static function publicApiPath(string $shareCode): string
    {
        return '/api/v1/moodboard/public/' . rawurlencode($shareCode);
    }

    public static function frontendShareUrl(?string $shareCode): ?string
    {
        if ($shareCode === null || $shareCode === '') {
            return null;
        }

        $base = rtrim((string)(Yii::$app->params['frontendUrl'] ?? ''), '/');
        if ($base === '') {
            return null;
        }

        $pathPrefix = (string)(Yii::$app->params['moodboardSharePathPrefix'] ?? '/moodboard/public/');
        if (!str_starts_with($pathPrefix, '/')) {
            $pathPrefix = '/' . $pathPrefix;
        }
        if (!str_ends_with($pathPrefix, '/')) {
            $pathPrefix .= '/';
        }

        return $base . $pathPrefix . rawurlencode($shareCode);
    }

    /**
     * @return array{shareCode: string|null, shareUrl: string|null, publicApiUrl: string|null}
     */
    public static function sharePayload(Moodboard $board): array
    {
        if (!$board->public_share_enabled || $board->share_code === null || $board->share_code === '') {
            return [
                'shareCode' => null,
                'shareUrl' => null,
                'publicApiUrl' => null,
            ];
        }

        $code = (string)$board->share_code;

        return [
            'shareCode' => $code,
            'shareUrl' => self::frontendShareUrl($code),
            'publicApiUrl' => self::publicApiPath($code),
        ];
    }
}
