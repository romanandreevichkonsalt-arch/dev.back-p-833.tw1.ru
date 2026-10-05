<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$stages = $formData['stages'] ?? [];
if ($stages === []) {
    $stages = [['year' => '', 'title' => '', 'text' => '', 'images' => []]];
}

$renderStageBody = static function (
    yii\web\View $view,
    int|string $stageIndex,
    array $stage,
): void {
    $images = $stage['images'] ?? [];
    if ($images === []) {
        $images = [['image_src' => '', 'image_alt' => '']];
    }
    ?>
    <div class="admin-about-timeline-stage-grid">
        <div class="admin-about-timeline-stage-grid__text">
            <?= $view->render('_block_field', [
                'name' => "stages[{$stageIndex}][year]",
                'label' => 'Год / период',
                'value' => $stage['year'] ?? '',
                'hint' => 'Например: 1995 или 2017–2019',
            ]) ?>
            <?= $view->render('_block_field', [
                'name' => "stages[{$stageIndex}][title]",
                'label' => 'Заголовок',
                'value' => $stage['title'] ?? '',
            ]) ?>
            <?= $view->render('_block_field', [
                'name' => "stages[{$stageIndex}][text]",
                'label' => 'Текст',
                'type' => 'textarea',
                'rows' => 6,
                'value' => $stage['text'] ?? '',
            ]) ?>
        </div>
        <?= $view->render('_block_about_timeline_stage_photos', [
            'stageIndex' => $stageIndex,
            'images' => $images,
        ]) ?>
    </div>
    <?php
};
?>
<div class="admin-page-block-section" data-repeatable>
    <div class="admin-content-repeatable__toolbar">
        <h3 class="admin-page-block-section__title">Этапы истории</h3>
        <button type="button" class="admin-btn admin-btn--secondary admin-btn--small" data-repeatable-add>Добавить этап</button>
    </div>
    <p class="admin-muted admin-page-block-section__lead">Слева — тексты этапа, справа — галерея фото.</p>
    <div data-repeatable-list>
        <?php foreach ($stages as $si => $stage): ?>
            <div class="admin-content-row admin-content-row--nested" data-repeatable-item>
                <?= $this->render('_block_row_header', ['title' => 'Этап ' . ($si + 1)]) ?>
                <?php $renderStageBody($this, $si, $stage) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <template data-repeatable-template>
        <div class="admin-content-row admin-content-row--nested" data-repeatable-item>
            <?= $this->render('_block_row_header', ['title' => 'Новый этап']) ?>
            <?php $renderStageBody($this, '__INDEX__', ['year' => '', 'title' => '', 'text' => '', 'images' => []]) ?>
        </div>
    </template>
</div>

<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Блок под историей</h3>
    <p class="admin-muted admin-page-block-section__lead">Отдельный заголовок и текст перед списком вакансий.</p>
    <?= $this->render('_block_field', [
        'name' => 'jobs_intro_title',
        'label' => 'Заголовок',
        'value' => $formData['jobs_intro_title'] ?? '',
    ]) ?>
    <?= $this->render('_block_field', [
        'name' => 'jobs_intro_text',
        'label' => 'Текст',
        'type' => 'textarea',
        'rows' => 4,
        'value' => $formData['jobs_intro_text'] ?? '',
    ]) ?>
</div>
