<?php

use app\models\CatalogColor;
use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var CatalogColor[] $colors */

$this->title = 'Цвета';
?>
<?= $this->render('../_nav', ['active' => 'color']) ?>

<div class="admin-toolbar">
    <?= Html::a('Добавить цвет', ['create'], ['class' => 'admin-btn']) ?>
</div>

<div class="admin-card">
    <p class="admin-muted">Общий справочник цветов. Один цвет можно добавить в несколько фактур — данные останутся одинаковыми.</p>
    <?php if ($colors === []): ?>
        <p class="admin-muted">Цветов пока нет.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
            <tr>
                <th></th>
                <th>Название</th>
                <th>Slug</th>
                <th>Порядок</th>
                <th>Активен</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($colors as $color): ?>
                <tr>
                    <td>
                        <span class="admin-fabric-swatches__dot" style="<?= Html::encode($color->getSwatchCircleStyle()) ?>"></span>
                    </td>
                    <td><?= Html::encode($color->label) ?></td>
                    <td><?= Html::encode($color->slug) ?></td>
                    <td><?= (int)$color->sort_order ?></td>
                    <td><?= $color->is_active ? 'Да' : 'Нет' ?></td>
                    <td class="admin-table-actions">
                        <?= AdminHtml::actionIcon(['update', 'id' => $color->id], 'update') ?>
                        <?= AdminHtml::actionIcon(['delete', 'id' => $color->id], 'delete', [
                            'data-method' => 'post',
                            'data-confirm' => 'Удалить цвет из справочника?',
                        ]) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
