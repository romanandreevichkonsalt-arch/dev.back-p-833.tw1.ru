<?php

use app\models\PromoCodeTemplate;
use app\modules\admin\helpers\AdminHtml;
use app\modules\admin\helpers\PromoCodeConditions;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var PromoCodeTemplate[] $templates */
/** @var list<array<string, mixed>> $systemPromos */
?>
<div class="admin-card">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Код</th>
                <th>Название</th>
                <th>Тип</th>
                <th>%</th>
                <th>Условия</th>
                <th>Статус</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($templates as $template): ?>
                <?php $validity = PromoCodeConditions::validityLineForTemplate($template); ?>
                <tr>
                    <td><code><?= Html::encode($template->code) ?></code></td>
                    <td><?= Html::encode($template->title) ?></td>
                    <td><?= Html::encode($template->getTypeLabel()) ?></td>
                    <td><?= Html::encode($template->discount_percent) ?>%</td>
                    <td class="admin-promo-conditions__cell">
                        <?= $validity !== null ? Html::encode($validity) : '—' ?>
                    </td>
                    <td><?= $template->is_active ? 'Активен' : 'Выключен' ?></td>
                    <td class="admin-table-actions">
                        <?= AdminHtml::actionIcon(['update', 'id' => $template->id], 'edit') ?>
                        <?php if ($template->type === PromoCodeTemplate::TYPE_CUSTOM): ?>
                            <?= AdminHtml::actionIcon(['delete', 'id' => $template->id], 'delete', [
                                'class' => 'admin-icon-btn admin-icon-btn--danger',
                                'data' => [
                                    'method' => 'post',
                                    'confirm' => 'Удалить промокод «' . $template->code . '»?',
                                ],
                            ]) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php foreach ($systemPromos as $system): ?>
                <tr class="admin-table-row--muted">
                    <td><code><?= Html::encode($system['code']) ?></code></td>
                    <td><?= Html::encode($system['title']) ?></td>
                    <td><?= Html::encode($system['typeLabel']) ?></td>
                    <td>10%</td>
                    <td class="admin-promo-conditions__cell">
                        <?php
                        $validity = PromoCodeConditions::validityLineForSystemType((string)$system['type']);
                        echo $validity !== null ? Html::encode($validity) : '—';
                        ?>
                    </td>
                    <td><span class="admin-muted">Системный</span></td>
                    <td></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<p class="admin-hint">Промокоды NOVINKA_{коллекция} создаются при сохранении модели с бейджем «Новинка» и отображаются в таблице выше.</p>
