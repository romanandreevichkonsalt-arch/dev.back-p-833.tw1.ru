<?php

use app\models\SearchCategory;
use app\models\SearchFrequentQuery;
use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var SearchFrequentQuery[] $queries */
/** @var SearchCategory[] $categories */
?>
<div class="admin-toolbar">
    <div class="admin-muted">Частые запросы и категории в пустом состоянии поиска</div>
</div>

<div class="admin-card" style="margin-bottom:16px;">
    <div class="admin-toolbar">
        <h2 style="margin:0;font-size:18px;">Частые запросы</h2>
        <?= Html::a('Добавить', ['create-query'], ['class' => 'admin-btn']) ?>
    </div>
    <table class="admin-table">
        <thead>
        <tr>
            <th>Запрос</th>
            <th>Порядок</th>
            <th>Статус</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php if ($queries === []): ?>
            <tr><td colspan="4">Нет данных. Импорт: <code>php yii seed/search</code></td></tr>
        <?php endif; ?>
        <?php foreach ($queries as $query): ?>
            <tr>
                <td><?= Html::encode($query->query) ?></td>
                <td><?= (int)$query->sort_order ?></td>
                <td><?= $query->is_active ? 'Активен' : 'Скрыт' ?></td>
                <td class="admin-table-actions">
                    <?= AdminHtml::actionIcon(['update-query', 'id' => $query->id], 'update') ?>
                    <?= AdminHtml::actionIcon(['delete-query', 'id' => $query->id], 'delete', [
                        'class' => 'admin-icon-btn admin-icon-btn--danger',
                        'data' => [
                            'method' => 'post',
                            'confirm' => 'Удалить запрос «' . $query->query . '»?',
                        ],
                    ]) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="admin-card">
    <div class="admin-toolbar">
        <h2 style="margin:0;font-size:18px;">Категории</h2>
        <?= Html::a('Добавить', ['create-category'], ['class' => 'admin-btn']) ?>
    </div>
    <table class="admin-table">
        <thead>
        <tr>
            <th>Название</th>
            <th>Slug</th>
            <th>Ссылка</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($categories as $category): ?>
            <tr>
                <td><?= Html::encode($category->label) ?></td>
                <td><code><?= Html::encode($category->slug) ?></code></td>
                <td><?= Html::encode($category->href) ?></td>
                <td class="admin-table-actions">
                    <?= AdminHtml::actionIcon(['update-category', 'id' => $category->id], 'update') ?>
                    <?= AdminHtml::actionIcon(['delete-category', 'id' => $category->id], 'delete', [
                        'class' => 'admin-icon-btn admin-icon-btn--danger',
                        'data' => [
                            'method' => 'post',
                            'confirm' => 'Удалить категорию «' . $category->label . '»?',
                        ],
                    ]) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
