<?php

use app\models\CatalogBadge;
use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var CatalogBadge[] $badges */

$this->title = 'Бейджи';
?>
<?= $this->render('../_nav', ['active' => 'badge']) ?>

<div class="admin-toolbar">
    <?= Html::a('Добавить бейдж', ['create'], ['class' => 'admin-btn']) ?>
</div>

<div class="admin-card">
    <table class="admin-table">
        <thead>
        <tr>
            <th></th>
            <th>Текст</th>
            <th>Вариант</th>
            <th>Slug</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($badges as $badge): ?>
            <tr>
                <td style="width:56px;">
                    <?php if ($badge->image): ?>
                        <img src="<?= Html::encode($badge->image->getPublicUrl()) ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:8px;">
                    <?php else: ?>
                        <span class="admin-muted">—</span>
                    <?php endif; ?>
                </td>
                <td><?= Html::encode($badge->label) ?></td>
                <td><?= Html::encode(CatalogBadge::variantLabels()[$badge->variant] ?? $badge->variant) ?></td>
                <td><?= Html::encode($badge->slug) ?></td>
                <td class="admin-table-actions">
                    <?= AdminHtml::actionIcon(['update', 'id' => $badge->id], 'update') ?>
                    <?= AdminHtml::actionIcon(['delete', 'id' => $badge->id], 'delete', [
                        'data-method' => 'post',
                        'data-confirm' => 'Удалить бейдж?',
                    ]) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
