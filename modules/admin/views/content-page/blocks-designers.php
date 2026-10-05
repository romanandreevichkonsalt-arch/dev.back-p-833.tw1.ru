<?php

use app\models\ContentPage;
use app\modules\admin\helpers\ContentPageDesignersHelper;

/** @var yii\web\View $this */
/** @var ContentPage $page */
/** @var string $activeTab */
/** @var array<string, mixed> $formData */

$tabPartial = match ($activeTab) {
    'hero' => '_block_hero_home',
    'intro' => '_block_designers_intro',
    'materials' => '_block_designers_materials_section',
    'gallery' => '_block_designers_gallery',
    default => '_block_hero_home',
};

$tabPartialParams = match ($activeTab) {
    'hero' => ['withSeo' => true],
    default => [],
};

echo $this->render('_page_editor_tabs', [
    'page' => $page,
    'activeTab' => $activeTab,
    'tabs' => ContentPageDesignersHelper::tabLabels(),
    'tabLeads' => ContentPageDesignersHelper::tabLeads(),
    'formData' => $formData,
    'tabPartial' => $tabPartial,
    'tabPartialParams' => $tabPartialParams,
]);
