<?php

use app\modules\admin\helpers\DealerManagerWorkHoursHelper;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var string|null $workHours */
/** @var string $fieldPrefix */
/** @var string $idPrefix */

$parts = DealerManagerWorkHoursHelper::parse($workHours ?? null);
$dayOptions = DealerManagerWorkHoursHelper::dayOptions();
$timeOptions = DealerManagerWorkHoursHelper::timeOptions();
?>
<div class="admin-work-hours">
    <span class="admin-work-hours__label form-label">График работы</span>
    <div class="admin-work-hours__row">
        <?= Html::dropDownList("{$fieldPrefix}[dayFrom]", $parts['dayFrom'], $dayOptions, [
            'class' => 'form-control',
            'id' => "{$idPrefix}-day-from",
        ]) ?>
        <span class="admin-work-hours__sep">–</span>
        <?= Html::dropDownList("{$fieldPrefix}[dayTo]", $parts['dayTo'], $dayOptions, [
            'class' => 'form-control',
            'id' => "{$idPrefix}-day-to",
        ]) ?>
        <?= Html::dropDownList("{$fieldPrefix}[timeFrom]", $parts['timeFrom'], $timeOptions, [
            'class' => 'form-control',
            'id' => "{$idPrefix}-time-from",
        ]) ?>
        <span class="admin-work-hours__sep">–</span>
        <?= Html::dropDownList("{$fieldPrefix}[timeTo]", $parts['timeTo'], $timeOptions, [
            'class' => 'form-control',
            'id' => "{$idPrefix}-time-to",
        ]) ?>
    </div>
</div>
