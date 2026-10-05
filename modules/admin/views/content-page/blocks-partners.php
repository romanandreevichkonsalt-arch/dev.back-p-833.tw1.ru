<?php

use app\models\ContentPage;
use app\modules\admin\helpers\ContentPagePartnersHelper;

/** @var yii\web\View $this */
/** @var ContentPage $page */
/** @var string $activeTab */
/** @var array<string, mixed> $formData */

$tabPartial = match ($activeTab) {
    'hero' => '_block_hero_home',
    'intro' => '_block_partners_intro',
    'formats' => '_block_formats_section',
    'audience' => '_block_audience_section',
    'salonFormats' => '_block_salon_formats_section',
    'presentation' => '_block_presentation_section',
    default => '_block_hero_home',
};

$tabPartialParams = match ($activeTab) {
    'hero' => ['withSeo' => true],
    default => [],
};

echo $this->render('_page_editor_tabs', [
    'page' => $page,
    'activeTab' => $activeTab,
    'tabs' => ContentPagePartnersHelper::tabLabels(),
    'tabLeads' => ContentPagePartnersHelper::tabLeads(),
    'formData' => $formData,
    'tabPartial' => $tabPartial,
    'tabPartialParams' => $tabPartialParams,
]);
