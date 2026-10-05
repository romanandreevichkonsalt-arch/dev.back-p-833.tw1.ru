<?php

use app\models\MediaFile;
use app\models\MediaFolder;
use app\modules\admin\widgets\MediaPickerWidget;

/** @var yii\web\View $this */
/** @var string $inputName */
/** @var string $value */
/** @var string $label */
/** @var string|null $hint */

$hint = $hint ?? 'Выберите PDF в медиатеке или загрузите новый.';

echo MediaPickerWidget::widget([
    'mode' => MediaPickerWidget::MODE_URL,
    'kind' => MediaFile::KIND_DOCUMENT,
    'inputName' => $inputName,
    'value' => $value,
    'label' => $label,
    'defaultFolder' => MediaFolder::SLUG_DOCUMENTS,
    'allowClear' => true,
    'compact' => true,
]);
