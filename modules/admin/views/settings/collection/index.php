<?php

use app\models\CatalogCollection;
use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var CatalogCollection[] $collections */

$this->title = 'Коллекции';
?>
<?= $this->render('../_nav', ['active' => 'collection']) ?>

<div class="admin-toolbar">
    <?= Html::a('Добавить коллекцию', ['create'], ['class' => 'admin-btn']) ?>
</div>

<p class="admin-muted" style="margin-bottom:16px;">
    Коллекция привязана к направлению (<strong>А+</strong> или <strong>Линия 1</strong>) и имеет своё название (Артемида, Адриано…).
</p>

<div class="admin-card">
    <table class="admin-table">
        <thead>
        <tr>
            <th></th>
            <th>Направление</th>
            <th>Название</th>
            <th>Slug</th>
            <th>Заголовок</th>
            <th>Порядок</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php if ($collections === []): ?>
            <tr><td colspan="7">Коллекций пока нет</td></tr>
        <?php endif; ?>
        <?php foreach ($collections as $collection): ?>
            <tr>
                <td>
                    <?php
                    $firstImage = null;
                    $imageCount = count($collection->collectionImages);
                    if ($imageCount > 0 && $collection->collectionImages[0]->media !== null) {
                        $firstImage = $collection->collectionImages[0]->media;
                    } elseif ($collection->image !== null) {
                        $firstImage = $collection->image;
                    }
                    if ($firstImage): ?>
                        <img
                            class="admin-table-thumb"
                            src="<?= Html::encode($firstImage->getPublicUrl()) ?>"
                            alt="<?= Html::encode($collection->name) ?>"
                        >
                        <?php if ($imageCount > 1): ?>
                            <span class="admin-muted">+<?= $imageCount - 1 ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="admin-table-thumb admin-table-thumb--empty">—</span>
                    <?php endif; ?>
                </td>
                <td><?= Html::encode($collection->direction->label ?? '—') ?></td>
                <td><?= Html::encode($collection->name) ?></td>
                <td><?= Html::encode($collection->slug) ?></td>
                <td><?= Html::encode($collection->title) ?></td>
                <td><?= (int)$collection->sort_order ?></td>
                <td class="admin-table-actions">
                    <?= AdminHtml::actionIcon(['update', 'id' => $collection->id], 'update') ?>
                    <?= AdminHtml::actionIcon(['delete', 'id' => $collection->id], 'delete', [
                        'data-method' => 'post',
                        'data-confirm' => 'Удалить коллекцию?',
                    ]) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
