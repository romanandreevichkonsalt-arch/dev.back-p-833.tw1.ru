<?php

use app\models\CatalogPromotion;
use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var CatalogPromotion[] $promotions */
?>
<div class="admin-card">
    <?php if ($promotions === []): ?>
        <p class="admin-muted">Акций пока нет. Задайте скидку (% или сумма), период и модель или конкретный товар.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Скидка</th>
                    <th>Объект</th>
                    <th>Период</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($promotions as $promotion): ?>
                    <?php
                    $discountLabel = $promotion->discount_type === CatalogPromotion::DISCOUNT_PERCENT
                        ? $promotion->discount_value . '%'
                        : number_format((float)$promotion->discount_value, 0, '.', ' ') . ' ₽';
                    $scopeLabel = $promotion->scope_type === CatalogPromotion::SCOPE_PRODUCT
                        ? ($promotion->catalogProduct?->slug ?? 'товар #' . $promotion->catalog_product_id)
                        : ($promotion->catalogModel?->title ?? 'модель #' . $promotion->catalog_model_id);
                    ?>
                    <tr>
                        <td><?= Html::encode($promotion->title) ?></td>
                        <td><?= Html::encode($discountLabel) ?></td>
                        <td><?= Html::encode($scopeLabel) ?></td>
                        <td>
                            <?= Html::encode(date('d.m.Y H:i', strtotime((string)$promotion->starts_at))) ?>
                            —
                            <?= Html::encode(date('d.m.Y H:i', strtotime((string)$promotion->ends_at))) ?>
                        </td>
                        <td class="admin-table-actions">
                            <?= AdminHtml::actionIcon(['/admin/catalog-promotion/update', 'id' => $promotion->id], 'edit') ?>
                            <?= AdminHtml::actionIcon(['/admin/catalog-promotion/delete', 'id' => $promotion->id], 'delete', [
                                'class' => 'admin-icon-btn admin-icon-btn--danger',
                                'data' => [
                                    'method' => 'post',
                                    'confirm' => 'Удалить акцию «' . $promotion->title . '»?',
                                ],
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
