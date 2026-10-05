<?php

use app\models\ContentPage;
use app\modules\admin\helpers\ContentPageContactsHelper;

/** @var yii\web\View $this */
/** @var ContentPage $page */
/** @var string $activeTab */
/** @var array<string, mixed> $formData */

$tabPartial = match ($activeTab) {
    'hero' => '_block_hero_home',
    'info' => '_block_info',
    'contact' => '_block_partners_contact',
    'regions' => '_block_regions',
    default => '_block_hero_home',
};

$tabPartialParams = match ($activeTab) {
    'hero' => ['withSeo' => true],
    default => [],
};

echo $this->render('_page_editor_tabs', [
    'page' => $page,
    'activeTab' => $activeTab,
    'tabs' => ContentPageContactsHelper::tabLabels(),
    'tabLeads' => ContentPageContactsHelper::tabLeads(),
    'formData' => $formData,
    'tabPartial' => $tabPartial,
    'tabPartialParams' => $tabPartialParams,
]);
