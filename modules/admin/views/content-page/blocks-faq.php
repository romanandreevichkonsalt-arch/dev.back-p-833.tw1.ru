<?php

use app\models\ContentPage;
use app\modules\admin\helpers\ContentPageFaqHelper;

/** @var yii\web\View $this */
/** @var ContentPage $page */
/** @var string $activeTab */
/** @var array<string, mixed> $formData */

$tabPartial = match ($activeTab) {
    'hero' => '_block_hero_home',
    'intro' => '_block_faq_intro',
    'categories' => '_block_faq_tabs',
    default => '_block_hero_home',
};

$tabPartialParams = match ($activeTab) {
    'hero' => ['withSeo' => true],
    default => [],
};

echo $this->render('_page_editor_tabs', [
    'page' => $page,
    'activeTab' => $activeTab,
    'tabs' => ContentPageFaqHelper::tabLabels(),
    'tabLeads' => ContentPageFaqHelper::tabLeads(),
    'formData' => $formData,
    'tabPartial' => $tabPartial,
    'tabPartialParams' => $tabPartialParams,
]);
