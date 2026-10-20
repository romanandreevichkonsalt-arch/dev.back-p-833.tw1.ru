<?php

namespace app\modules\admin\helpers;

use app\models\ContentBlock;

class ContentPageCatalogHelper
{
    public static function usesUnifiedEditor(string $pageSlug): bool
    {
        return $pageSlug === 'catalog';
    }

    public static function shouldRedirectToPageEditor(ContentBlock $block): bool
    {
        return $block->page !== null && $block->page->slug === 'catalog';
    }
}
