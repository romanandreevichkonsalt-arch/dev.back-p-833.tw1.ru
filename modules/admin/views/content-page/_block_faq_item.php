<?php

/** @var yii\web\View $this */
/** @var int|string $ci Category index */
/** @var int|string $ii Item index */
/** @var array<string, mixed> $item */

$itemTitle = is_numeric($ii) ? 'Вопрос ' . ((int)$ii + 1) : 'Вопрос';
?>
<div class="admin-faq-item" data-repeatable-item>
    <?= $this->render('_block_row_header', [
        'title' => $itemTitle,
        'faqItemTitle' => true,
    ]) ?>
    <?= $this->render('_block_field', [
        'name' => "categories[{$ci}][items][{$ii}][question]",
        'label' => 'Вопрос',
        'value' => $item['question'] ?? '',
    ]) ?>
    <div class="admin-page-field">
        <div class="admin-faq-answer-field__head">
            <label class="form-label" for="faq-answer-<?= (int)$ci ?>-<?= (int)$ii ?>">Ответ</label>
            <button
                type="button"
                class="admin-btn admin-btn--ghost admin-btn--small admin-faq-answer-field__link-btn"
                data-faq-answer-link
            >Добавить ссылку</button>
        </div>
        <textarea
            id="faq-answer-<?= is_numeric($ci) && is_numeric($ii) ? ((int)$ci . '-' . (int)$ii) : 'new' ?>"
            class="form-control admin-faq-answer-editor"
            name="categories[<?= $ci ?>][items][<?= $ii ?>][answer]"
            rows="1"
        ><?= htmlspecialchars((string)($item['answer'] ?? ''), ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
        <p class="admin-muted admin-faq-answer-field__hint">Выделите слово и нажмите «Добавить ссылку» или введите вручную: [текст](/url)</p>
    </div>
</div>
