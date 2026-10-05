<?php

namespace app\services\moodboard;

final class MoodboardPagination
{
    public const DEFAULT_LIMIT = 3;
    public const MAX_LIMIT = 100;

    /**
     * @return array{page: int, limit: int, offset: int}
     */
    public static function fromRequest(?int $defaultLimit = self::DEFAULT_LIMIT): array
    {
        $limit = (int)(\Yii::$app->request->get('limit', $defaultLimit ?? self::DEFAULT_LIMIT));
        $page = (int)(\Yii::$app->request->get('page', 1));

        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $page = max(1, $page);

        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => ($page - 1) * $limit,
        ];
    }

    /**
     * @return array{total: int, page: int, limit: int}
     */
    public static function meta(int $total, int $page, int $limit): array
    {
        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }
}
