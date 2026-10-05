<?php

use app\models\ContentPage;
use app\modules\admin\helpers\ContentPageBuildingHelper;

/** @var yii\web\View $this */
/** @var ContentPage $page */
/** @var string $activeTab */
/** @var array<string, mixed> $formData */

$tabPartial = match ($activeTab) {
    'hero' => '_block_hero_home',
    'intro' => '_block_intro_text',
    'comfort' => '_block_comfort',
    'stack' => '_block_stack',
    'ethics' => '_block_ethics',
    default => '_block_hero_home',
};

$tabPartialParams = match ($activeTab) {
    'hero' => ['withSeo' => true],
    default => [],
};

echo $this->render('_page_editor_tabs', [
    'page' => $page,
    'activeTab' => $activeTab,
    'tabs' => ContentPageBuildingHelper::tabLabels(),
    'tabLeads' => ContentPageBuildingHelper::tabLeads(),
    'formData' => $formData,
    'tabPartial' => $tabPartial,
    'tabPartialParams' => $tabPartialParams,
]);
