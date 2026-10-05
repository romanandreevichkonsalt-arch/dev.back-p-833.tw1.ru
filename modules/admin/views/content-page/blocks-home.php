<?php

use app\models\ContentPage;
use app\modules\admin\helpers\ContentPageHomeHelper;
use app\modules\admin\helpers\HomePageCollectionsHelper;

/** @var yii\web\View $this */
/** @var ContentPage $page */
/** @var string $activeTab */
/** @var array<string, mixed> $formData */

$tabPartial = match ($activeTab) {
    'hero' => '_block_hero_home',
    'collections' => '_block_collections',
    'products' => '_block_home_products',
    'partners' => '_block_home_partners',
    default => '_block_hero_home',
};

$tabPartialParams = match ($activeTab) {
    'hero' => ['withSeo' => true],
    'collections' => [
        'catalogDirectionOptions' => HomePageCollectionsHelper::directionOptions(),
        'withPhilosophy' => true,
    ],
    default => [],
};

echo $this->render('_page_editor_tabs', [
    'page' => $page,
    'activeTab' => $activeTab,
    'tabs' => ContentPageHomeHelper::tabLabels(),
    'tabLeads' => ContentPageHomeHelper::tabLeads(),
    'formData' => $formData,
    'tabPartial' => $tabPartial,
    'tabPartialParams' => $tabPartialParams,
]);
