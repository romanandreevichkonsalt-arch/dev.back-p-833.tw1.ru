<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $label */
/** @var string $type */
/** @var mixed $value */
/** @var string|null $hint */
/** @var int|null $rows */
/** @var array<string, mixed> $inputOptions */

$type = $type ?? 'text';
$rows = $rows ?? 3;
$hint = $hint ?? null;
$inputOptions = $inputOptions ?? [];
$inputClass = 'form-control' . (isset($inputOptions['class']) ? ' ' . $inputOptions['class'] : '');
unset($inputOptions['class']);
?>
<div class="admin-page-field">
    <label class="form-label" for="<?= Html::encode($name) ?>"><?= Html::encode($label) ?></label>
    <?php if ($hint !== null && $hint !== ''): ?>
        <p class="admin-page-field__hint admin-muted"><?= Html::encode($hint) ?></p>
    <?php endif; ?>
    <?php if ($type === 'textarea'): ?>
        <?= Html::textarea($name, $value ?? '', array_merge([
            'class' => $inputClass,
            'id' => $name,
            'rows' => $rows,
        ], $inputOptions)) ?>
    <?php else: ?>
        <?= Html::textInput($name, $value ?? '', array_merge([
            'class' => $inputClass,
            'id' => $name,
        ], $inputOptions)) ?>
    <?php endif; ?>
</div>
