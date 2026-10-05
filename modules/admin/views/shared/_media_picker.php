<?php

use app\models\MediaFolder;
use app\modules\admin\widgets\MediaPickerWidget;

/** @var yii\web\View $this */
/** @var string $inputName */
/** @var string|null $altInputName */
/** @var string $value */
/** @var string $altValue */
/** @var string $label */
/** @var string|null $defaultFolder */
/** @var string|null $hint */

$defaultFolder = $defaultFolder ?? MediaFolder::SLUG_BANNERS;
$hint = $hint ?? 'Выберите файл в медиатеке или загрузите новый.';
$altInputName = $altInputName ?? null;
$altValue = $altValue ?? '';

echo MediaPickerWidget::widget([
    'mode' => MediaPickerWidget::MODE_ID,
    'inputName' => $inputName,
    'altInputName' => $altInputName,
    'value' => $value,
    'altValue' => $altValue,
    'label' => $label,
    'defaultFolder' => $defaultFolder,
    'allowClear' => true,
    'compact' => true,
]);
