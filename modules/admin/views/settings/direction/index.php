<?php

use app\models\CatalogDirection;
use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var CatalogDirection[] $directions */

$this->title = 'Направления каталога';
?>
<?= $this->render('../_nav', ['active' => 'direction']) ?>

<div class="admin-toolbar">
    <?= Html::a('Добавить направление', ['create'], ['class' => 'admin-btn']) ?>
</div>

<p class="admin-muted" style="margin-bottom:16px;">
    Направления каталога: <strong>А+</strong> и <strong>Линия 1</strong>. К каждому направлению привязываются коллекции.
</p>

<div class="admin-card">
    <?php if ($directions === []): ?>
        <p class="admin-muted">Направлений пока нет.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
            <tr>
                <th>Название</th>
                <th>Slug</th>
                <th>Порядок</th>
                <th>Активно</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($directions as $direction): ?>
                <tr>
                    <td><?= Html::encode($direction->label) ?><?= !$direction->is_active ? ' <span class="admin-muted">(неактивно)</span>' : '' ?></td>
                    <td><?= Html::encode($direction->slug) ?></td>
                    <td><?= (int)$direction->sort_order ?></td>
                    <td><?= $direction->is_active ? 'Да' : 'Нет' ?></td>
                    <td class="admin-table-actions">
                        <?= AdminHtml::actionIcon(['update', 'id' => $direction->id], 'update') ?>
                        <?= AdminHtml::actionIcon(['delete', 'id' => $direction->id], 'delete', [
                            'data-method' => 'post',
                            'data-confirm' => 'Удалить направление?',
                        ]) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
