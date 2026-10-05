<?php

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $label */
/** @var string $value */
/** @var int $rows */

$rows = $rows ?? 3;
$fieldId = 'journal-rich-text-' . preg_replace('/[^a-z0-9_-]+/i', '-', $name);

?>
<div class="admin-page-field">
    <div class="admin-faq-answer-field__head">
        <label class="form-label" for="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></label>
        <button
            type="button"
            class="admin-btn admin-btn--ghost admin-btn--small admin-faq-answer-field__link-btn"
            data-faq-answer-link
        >Добавить ссылку</button>
    </div>
    <textarea
        id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>"
        class="form-control admin-journal-text-editor admin-faq-answer-editor"
        name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
        rows="<?= (int)$rows ?>"
    ><?= htmlspecialchars($value, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
    <p class="admin-muted admin-faq-answer-field__hint">Выделите слово и нажмите «Добавить ссылку» или введите вручную: [текст](/url)</p>
</div>
