<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$categories = $formData['categories'] ?? [];
if ($categories === []) {
    $categories = [['id' => '', 'label' => '', 'icon' => '', 'items' => []]];
}
?>
<div class="admin-page-block-section" data-repeatable>
    <div class="admin-content-repeatable__toolbar">
        <h3 class="admin-page-block-section__title">Категории FAQ</h3>
        <button type="button" class="admin-btn admin-btn--secondary admin-btn--small" data-repeatable-add>Добавить категорию</button>
    </div>
    <div data-repeatable-list>
        <?php foreach ($categories as $ci => $category): ?>
            <?php
            $items = $category['items'] ?? [];
            if ($items === []) {
                $items = [['question' => '', 'answer' => '']];
            }
            foreach ($items as $ii => $item) {
                if (!isset($item['answer'])) {
                    $items[$ii]['answer'] = \app\services\content\BlockFormBuilders::faqAnswerToForm(
                        is_array($item['paragraphs'] ?? null) ? $item['paragraphs'] : []
                    );
                }
            }
            ?>
            <div class="admin-content-row admin-content-row--nested" data-repeatable-item>
                <?= $this->render('_block_row_header', ['title' => 'Категория ' . ($ci + 1)]) ?>
                <div class="admin-content-row__grid">
                    <?= $this->render('_block_field', [
                        'name' => "categories[{$ci}][id]",
                        'label' => 'ID категории',
                        'value' => $category['id'] ?? '',
                    ]) ?>
                    <?= $this->render('_block_field', [
                        'name' => "categories[{$ci}][icon]",
                        'label' => 'Иконка (ключ)',
                        'value' => $category['icon'] ?? '',
                        'hint' => 'Например: faq-order',
                    ]) ?>
                </div>
                <?= $this->render('_block_field', [
                    'name' => "categories[{$ci}][label]",
                    'label' => 'Название',
                    'value' => $category['label'] ?? '',
                ]) ?>

                <div class="admin-content-nested" data-repeatable data-parent-index="<?= (int)$ci ?>">
                    <div class="admin-content-repeatable__toolbar">
                        <h4 class="admin-content-nested__title">Вопросы</h4>
                        <button type="button" class="admin-btn admin-btn--ghost admin-btn--small" data-repeatable-add>Добавить вопрос</button>
                    </div>
                    <div data-repeatable-list>
                        <?php foreach ($items as $ii => $item): ?>
                            <?= $this->render('_block_faq_item', [
                                'ci' => $ci,
                                'ii' => $ii,
                                'item' => $item,
                            ]) ?>
                        <?php endforeach; ?>
                    </div>
                    <template data-repeatable-template>
                        <?= $this->render('_block_faq_item', [
                            'ci' => '__PARENT_INDEX__',
                            'ii' => '__INDEX__',
                            'item' => ['question' => '', 'answer' => ''],
                        ]) ?>
                    </template>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <template data-repeatable-template>
        <div class="admin-content-row admin-content-row--nested" data-repeatable-item>
            <?= $this->render('_block_row_header', ['title' => 'Новая категория']) ?>
            <div class="admin-content-row__grid">
                <?= $this->render('_block_field', ['name' => 'categories[__INDEX__][id]', 'label' => 'ID категории', 'value' => '']) ?>
                <?= $this->render('_block_field', ['name' => 'categories[__INDEX__][icon]', 'label' => 'Иконка (ключ)', 'value' => '']) ?>
            </div>
            <?= $this->render('_block_field', ['name' => 'categories[__INDEX__][label]', 'label' => 'Название', 'value' => '']) ?>
            <div class="admin-content-nested" data-repeatable data-parent-index="__INDEX__">
                <div class="admin-content-repeatable__toolbar">
                    <h4 class="admin-content-nested__title">Вопросы</h4>
                    <button type="button" class="admin-btn admin-btn--ghost admin-btn--small" data-repeatable-add>Добавить вопрос</button>
                </div>
                <div data-repeatable-list>
                    <?= $this->render('_block_faq_item', [
                        'ci' => '__INDEX__',
                        'ii' => 0,
                        'item' => ['question' => '', 'answer' => ''],
                    ]) ?>
                </div>
                <template data-repeatable-template>
                    <?= $this->render('_block_faq_item', [
                        'ci' => '__PARENT_INDEX__',
                        'ii' => '__INDEX__',
                        'item' => ['question' => '', 'answer' => ''],
                    ]) ?>
                </template>
            </div>
        </div>
    </template>
</div>
