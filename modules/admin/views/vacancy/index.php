<?php

use app\models\Vacancy;
use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var Vacancy[] $vacancies */

$this->title = 'Вакансии';
$this->params['breadcrumbs'][] = ['label' => 'Страницы', 'url' => ['/admin/content-page/index']];
$this->params['breadcrumbs'][] = $this->title;

$vacanciesPage = \app\models\ContentPage::find()->where(['slug' => 'vacancies'])->one();
$directionsUrl = $vacanciesPage !== null
    ? ['/admin/content-page/blocks', 'id' => $vacanciesPage->id, 'tab' => 'groups']
    : ['/admin/content-page/index'];
$valuesUrl = $vacanciesPage !== null
    ? ['/admin/content-page/blocks', 'id' => $vacanciesPage->id, 'tab' => 'values']
    : ['/admin/content-page/index'];
$jobsTabUrl = $vacanciesPage !== null
    ? ['/admin/content-page/blocks', 'id' => $vacanciesPage->id, 'tab' => 'jobs']
    : ['/admin/content-page/index'];
?>
<div class="vacancy-index">
    <div class="admin-toolbar">
        <?= Html::a('Новая вакансия', ['create'], ['class' => 'admin-btn']) ?>
        <?= Html::a('Редактор страницы', $jobsTabUrl, ['class' => 'admin-btn admin-btn--secondary']) ?>
        <?= Html::a('Направления', $directionsUrl, ['class' => 'admin-btn admin-btn--secondary']) ?>
        <?= Html::a('Вступление', $valuesUrl, ['class' => 'admin-btn admin-btn--secondary']) ?>
    </div>

    <?php if ($vacancies === []): ?>
        <div class="admin-card admin-page-empty">
            <p class="mb-3">Вакансий пока нет.</p>
            <?= Html::a('Создать первую вакансию', ['create'], ['class' => 'admin-btn']) ?>
        </div>
    <?php else: ?>
    <table class="table table-striped">
        <thead>
        <tr>
            <th>Должность</th>
            <th>Направление</th>
            <th>Цех</th>
            <th>Slug</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($vacancies as $vacancy): ?>
            <tr>
                <td><?= Html::encode($vacancy->title) ?></td>
                <td><?= Html::encode($vacancy->getDirectionLabel()) ?></td>
                <td><?= Html::encode($vacancy->department) ?></td>
                <td><code><?= Html::encode($vacancy->slug) ?></code></td>
                <td class="text-end">
                    <?= AdminHtml::actionIcon(['update', 'id' => $vacancy->id], 'update') ?>
                    <?= AdminHtml::actionIcon(['delete', 'id' => $vacancy->id], 'delete', [
                        'data' => [
                            'method' => 'post',
                            'confirm' => 'Удалить вакансию?',
                        ],
                    ]) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
