<?php

namespace app\modules\admin\helpers;

use app\services\journal\JournalArticleService;

class HomePageJournalHelper
{
    public const ARTICLE_COUNT = 3;

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function buildLatestArticlesPayload(): array
    {
        $service = new JournalArticleService();

        return $service->buildLatestArticlesForHome(self::ARTICLE_COUNT);
    }
}
