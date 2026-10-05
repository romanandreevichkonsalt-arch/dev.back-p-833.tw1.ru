<?php

use app\models\CatalogCategory;
use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var CatalogCategory[] $categories */

$this->title = 'Категории и подкатегории';
?>
<?= $this->render('../_nav', ['active' => 'category']) ?>

<div class="admin-toolbar">
    <?= Html::a('Добавить категорию', ['create'], ['class' => 'admin-btn']) ?>
</div>

<p class="admin-muted" style="margin-bottom:16px;">
    Единый справочник для всех коллекций. Пример: категория <strong>Диван</strong> → подкатегории <strong>Прямой диван</strong>, <strong>Угловой диван</strong>…
</p>

<?php foreach ($categories as $category): ?>
    <div class="admin-card" style="margin-bottom:16px;">
        <div class="admin-toolbar admin-toolbar--card">
            <div class="admin-toolbar__title">
                <strong><?= Html::encode($category->label) ?></strong>
                <span class="admin-muted">(<?= Html::encode($category->slug) ?>)</span>
            </div>
            <div class="admin-card-actions">
                <?= AdminHtml::actionIcon(['update', 'id' => $category->id], 'update') ?>
                <?= AdminHtml::actionIcon(['delete', 'id' => $category->id], 'delete', [
                    'data-method' => 'post',
                    'data-confirm' => 'Удалить категорию?',
                ]) ?>
                <?= AdminHtml::actionIcon(['create-subcategory', 'category_id' => $category->id], 'add') ?>
            </div>
        </div>
        <?php if ($category->subcategories === []): ?>
            <p class="admin-muted">Подкатегорий нет</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                <tr>
                    <th>Название</th>
                    <th>Slug</th>
                    <th>Порядок</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($category->subcategories as $sub): ?>
                    <tr>
                        <td><?= Html::encode($sub->label) ?></td>
                        <td><?= Html::encode($sub->slug) ?></td>
                        <td><?= (int)$sub->sort_order ?></td>
                        <td class="admin-table-actions">
                            <?= AdminHtml::actionIcon(['update-subcategory', 'id' => $sub->id], 'update') ?>
                            <?= AdminHtml::actionIcon(['delete-subcategory', 'id' => $sub->id], 'delete', [
                                'data-method' => 'post',
                                'data-confirm' => 'Удалить подкатегорию?',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<?php if ($categories === []): ?>
    <div class="admin-card">
        <p class="admin-muted">Добавьте категорию (Диван, Кресло, Комбинация) и подкатегории внутри неё.</p>
    </div>
<?php endif; ?>
