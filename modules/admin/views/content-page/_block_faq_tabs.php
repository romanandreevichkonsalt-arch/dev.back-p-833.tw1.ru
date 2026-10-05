<?php

use app\modules\admin\helpers\ContentPageFaqHelper;

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$categories = ContentPageFaqHelper::padFaqCategoriesForForm($formData['categories'] ?? []);
?>
<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Четыре вкладки</h3>
    <p class="admin-muted admin-page-block-section__lead">На сайте — горизонтальные вкладки; здесь — содержимое каждой вкладки.</p>

    <div class="admin-faq-tabs">
        <div class="admin-faq-tabs__nav" role="tablist">
            <?php foreach ($categories as $ci => $category): ?>
                <button
                    type="button"
                    class="admin-faq-tabs__tab<?= $ci === 0 ? ' is-active' : '' ?>"
                    role="tab"
                    data-faq-tab="<?= (int)$ci ?>"
                    aria-selected="<?= $ci === 0 ? 'true' : 'false' ?>"
                >
                    <span class="admin-faq-tabs__tab-num"><?= sprintf('%02d', $ci + 1) ?></span>
                    <span class="admin-faq-tabs__tab-label"><?= htmlspecialchars($category['label'] ?? '', ENT_QUOTES) ?></span>
                </button>
            <?php endforeach; ?>
        </div>

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
            <div class="admin-faq-tabs__panel<?= $ci === 0 ? ' is-active' : '' ?>" data-faq-panel="<?= (int)$ci ?>">
                <div class="admin-faq-tabs__panel-head">
                    <h4 class="admin-faq-tabs__panel-title">Вкладка <?= $ci + 1 ?></h4>
                    <p class="admin-muted admin-faq-tabs__panel-meta">
                        ID: <code><?= htmlspecialchars($category['id'] ?? '', ENT_QUOTES) ?></code>
                        · иконка: <code><?= htmlspecialchars($category['icon'] ?? '', ENT_QUOTES) ?></code>
                    </p>
                </div>

                <input type="hidden" name="categories[<?= $ci ?>][id]" value="<?= htmlspecialchars($category['id'] ?? '', ENT_QUOTES) ?>">
                <input type="hidden" name="categories[<?= $ci ?>][icon]" value="<?= htmlspecialchars($category['icon'] ?? '', ENT_QUOTES) ?>">

                <?= $this->render('_block_field', [
                    'name' => "categories[{$ci}][label]",
                    'label' => 'Название вкладки',
                    'value' => $category['label'] ?? '',
                ]) ?>

                <div class="admin-content-nested" data-repeatable data-parent-index="<?= (int)$ci ?>">
                    <div class="admin-content-repeatable__toolbar">
                        <h5 class="admin-content-nested__title">Вопросы и ответы</h5>
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
</div>
