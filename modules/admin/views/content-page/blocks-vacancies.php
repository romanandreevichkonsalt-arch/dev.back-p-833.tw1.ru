<?php

use app\models\ContentPage;
use app\modules\admin\helpers\ContentPageVacanciesHelper;

/** @var yii\web\View $this */
/** @var ContentPage $page */
/** @var string $activeTab */
/** @var array<string, mixed> $formData */

$tabPartial = match ($activeTab) {
    'hero' => '_block_vacancies_hero',
    'values' => '_block_vacancies_intro',
    'gallery' => '_block_vacancies_gallery',
    'groups' => '_block_vacancies_groups',
    'jobs' => '_block_vacancies_jobs',
    default => '_block_vacancies_hero',
};

$tabPartialParams = match ($activeTab) {
    'hero' => ['withSeo' => true],
    'groups' => ['pageId' => (int)$page->id],
    'jobs' => ['hideSave' => true],
    default => [],
};

echo $this->render('_page_editor_tabs', [
    'page' => $page,
    'activeTab' => $activeTab,
    'tabs' => ContentPageVacanciesHelper::tabLabels(),
    'tabLeads' => ContentPageVacanciesHelper::tabLeads(),
    'formData' => $formData,
    'tabPartial' => $tabPartial,
    'tabPartialParams' => $tabPartialParams,
]);
