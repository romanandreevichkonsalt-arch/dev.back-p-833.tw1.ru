<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */
/** @var int|null $pageId */

$directions = $formData['directions'] ?? [];
$jobsTabUrl = $pageId !== null && $pageId > 0
    ? ['/admin/content-page/blocks', 'id' => $pageId, 'tab' => 'jobs']
    : ['/admin/vacancy/index'];
if ($directions === []) {
    $directions = [[
        'id' => '',
        'slug' => '',
        'number' => '',
        'title' => '',
        'description' => '',
        'empty_title' => 'Сейчас у нас нет открытых вакансий',
        'empty_description' => 'Следите за обновлениями — новые позиции появятся здесь',
    ]];
}
?>
<div class="admin-page-block-section">
    <p class="admin-muted admin-page-block-section__lead">
        Направления группируют вакансии на странице. Список и создание — на вкладке
        <?= Html::a('«Вакансии»', $jobsTabUrl) ?>
        или <?= Html::a('новая вакансия', ['/admin/vacancy/create']) ?>.
    </p>
</div>

<div class="admin-page-block-section" data-repeatable>
    <div class="admin-content-repeatable__toolbar">
        <h3 class="admin-page-block-section__title">Направления</h3>
        <button type="button" class="admin-btn admin-btn--secondary admin-btn--small" data-repeatable-add>Добавить направление</button>
    </div>
    <div data-repeatable-list>
        <?php foreach ($directions as $i => $direction): ?>
            <div class="admin-page-block-section admin-vacancy-group" data-repeatable-item>
                <?= Html::hiddenInput("directions[{$i}][id]", $direction['id'] ?? '') ?>
                <div class="admin-content-row__head">
                    <?= $this->render('_block_row_header', ['title' => 'Направление ' . ($i + 1)]) ?>
                </div>
                <div class="admin-vacancy-group__head">
                    <?= $this->render('_block_field', [
                        'name' => "directions[{$i}][number]",
                        'label' => 'Номер',
                        'value' => $direction['number'] ?? '',
                    ]) ?>
                    <?= $this->render('_block_field', [
                        'name' => "directions[{$i}][slug]",
                        'label' => 'Slug (API)',
                        'value' => $direction['slug'] ?? '',
                    ]) ?>
                    <?= $this->render('_block_field', [
                        'name' => "directions[{$i}][title]",
                        'label' => 'Название',
                        'value' => $direction['title'] ?? '',
                    ]) ?>
                </div>
                <?= $this->render('_block_field', [
                    'name' => "directions[{$i}][description]",
                    'label' => 'Описание',
                    'type' => 'textarea',
                    'rows' => 2,
                    'value' => $direction['description'] ?? '',
                ]) ?>
                <?= $this->render('_block_field', [
                    'name' => "directions[{$i}][empty_title]",
                    'label' => 'Заголовок пустого блока',
                    'value' => $direction['empty_title'] ?? '',
                ]) ?>
                <?= $this->render('_block_field', [
                    'name' => "directions[{$i}][empty_description]",
                    'label' => 'Текст пустого блока',
                    'type' => 'textarea',
                    'rows' => 2,
                    'value' => $direction['empty_description'] ?? '',
                ]) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <template data-repeatable-template>
        <div class="admin-page-block-section admin-vacancy-group" data-repeatable-item>
            <?= Html::hiddenInput('directions[__INDEX__][id]', '') ?>
            <div class="admin-content-row__head">
                <?= $this->render('_block_row_header', ['title' => 'Новое направление']) ?>
            </div>
            <div class="admin-vacancy-group__head">
                <?= $this->render('_block_field', [
                    'name' => 'directions[__INDEX__][number]',
                    'label' => 'Номер',
                    'value' => '',
                ]) ?>
                <?= $this->render('_block_field', [
                    'name' => 'directions[__INDEX__][slug]',
                    'label' => 'Slug (API)',
                    'value' => '',
                ]) ?>
                <?= $this->render('_block_field', [
                    'name' => 'directions[__INDEX__][title]',
                    'label' => 'Название',
                    'value' => '',
                ]) ?>
            </div>
            <?= $this->render('_block_field', [
                'name' => 'directions[__INDEX__][description]',
                'label' => 'Описание',
                'type' => 'textarea',
                'rows' => 2,
                'value' => '',
            ]) ?>
            <?= $this->render('_block_field', [
                'name' => 'directions[__INDEX__][empty_title]',
                'label' => 'Заголовок пустого блока',
                'value' => 'Сейчас у нас нет открытых вакансий',
            ]) ?>
            <?= $this->render('_block_field', [
                'name' => 'directions[__INDEX__][empty_description]',
                'label' => 'Текст пустого блока',
                'type' => 'textarea',
                'rows' => 2,
                'value' => 'Следите за обновлениями — новые позиции появятся здесь',
            ]) ?>
        </div>
    </template>
</div>
