<?php

use app\models\ContentPage;
use app\modules\admin\helpers\ContentPageAboutHelper;

/** @var yii\web\View $this */
/** @var ContentPage $page */
/** @var string $activeTab */
/** @var array<string, mixed> $formData */

$tabPartial = match ($activeTab) {
    'hero' => '_block_about_hero',
    'intro' => '_block_about_intro',
    'community' => '_block_about_gallery',
    'timeline' => '_block_about_timeline',
    default => '_block_about_hero',
};

$tabPartialParams = match ($activeTab) {
    'hero' => ['withSeo' => true],
    default => [],
};

echo $this->render('_page_editor_tabs', [
    'page' => $page,
    'activeTab' => $activeTab,
    'tabs' => ContentPageAboutHelper::tabLabels(),
    'tabLeads' => ContentPageAboutHelper::tabLeads(),
    'formData' => $formData,
    'tabPartial' => $tabPartial,
    'tabPartialParams' => $tabPartialParams,
]);
