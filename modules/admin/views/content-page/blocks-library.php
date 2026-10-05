<?php

use app\models\ContentPage;
use app\modules\admin\helpers\ContentPageLibraryHelper;

/** @var yii\web\View $this */
/** @var ContentPage $page */
/** @var string $activeTab */
/** @var array<string, mixed> $formData */

$tabPartial = match ($activeTab) {
    'hero' => '_block_hero_home',
    'implementedModels' => '_block_library_implemented_models',
    'yourIdea' => '_block_library_your_idea',
    'documents' => '_block_library_documents',
    default => '_block_hero_home',
};

$tabPartialParams = match ($activeTab) {
    'hero' => ['withSeo' => true],
    default => [],
};

echo $this->render('_page_editor_tabs', [
    'page' => $page,
    'activeTab' => $activeTab,
    'tabs' => ContentPageLibraryHelper::tabLabels(),
    'tabLeads' => ContentPageLibraryHelper::tabLeads(),
    'formData' => $formData,
    'tabPartial' => $tabPartial,
    'tabPartialParams' => $tabPartialParams,
]);
