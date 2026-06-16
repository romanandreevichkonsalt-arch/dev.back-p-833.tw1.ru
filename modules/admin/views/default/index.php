<?php

/** @var yii\web\View $this */
/** @var array<string,int> $stats */

use yii\bootstrap5\Html;

$this->title = 'Дашборд';
?>
<h1><?= Html::encode($this->title) ?></h1>
<div class="row g-3 mt-2">
    <?php foreach ($stats as $label => $value): ?>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small"><?= Html::encode($label) ?></div>
                    <div class="h3 mb-0"><?= (int) $value ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
