<?php

use app\models\DealerPromoGrant;
use app\models\PromoCodeTemplate;
use app\models\User;
use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\User $user */
/** @var DealerPromoGrant[] $promoGrants */
/** @var PromoCodeTemplate[] $grantableTemplates */

$templateOptions = [];
foreach ($grantableTemplates as $template) {
    $templateOptions[$template->id] = sprintf(
        '%s — %s (−%s%%)',
        $template->code,
        $template->title,
        rtrim(rtrim(number_format((float)$template->discount_percent, 2, '.', ''), '0'), '.')
    );
}
?>
<div class="admin-card" style="margin-top:16px;">
    <h3 class="admin-card__title">Промокоды</h3>

    <?php if ($promoGrants === []): ?>
        <p style="margin:0 0 16px;color:#6b6862;">Промокоды не выдавались.</p>
    <?php else: ?>
        <table class="admin-table" style="margin-bottom:16px;">
            <thead>
            <tr>
                <th>Код</th>
                <th>Название</th>
                <th>Скидка</th>
                <th>Источник</th>
                <th>Выдан</th>
                <th>Действует до</th>
                <th>Статус</th>
                <th>Заказ</th>
                <th class="admin-table-actions"></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($promoGrants as $grant): ?>
                <?php
                $statusClass = match ($grant->getStatusLabel()) {
                    'Активен' => 'admin-badge--success',
                    'Использован' => 'admin-badge--confirmed',
                    default => 'admin-badge--rejected',
                };
                ?>
                <tr>
                    <td><?= Html::encode($grant->code) ?></td>
                    <td><?= Html::encode($grant->getTitle()) ?></td>
                    <td><?= Html::encode(rtrim(rtrim(number_format((float)$grant->discount_percent, 2, '.', ''), '0'), '.') . '%') ?></td>
                    <td><?= Html::encode($grant->getSourceLabel()) ?></td>
                    <td><?= Html::encode($grant->created_at) ?></td>
                    <td><?= Html::encode($grant->expires_at ?: '—') ?></td>
                    <td><span class="admin-badge <?= $statusClass ?>"><?= Html::encode($grant->getStatusLabel()) ?></span></td>
                    <td>
                        <?php if ($grant->usedOrder !== null): ?>
                            <?= Html::a(
                                Html::encode($grant->usedOrder->number),
                                ['/admin/order/view', 'id' => $grant->usedOrder->id],
                                ['class' => 'admin-link']
                            ) ?>
                            <?php if ($grant->used_at): ?>
                                <div style="margin-top:4px;color:#6b6862;font-size:12px;">
                                    <?= Html::encode($grant->used_at) ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td class="admin-table-actions">
                        <?php if ($grant->used_at === null && $grant->used_order_id === null): ?>
                            <?= AdminHtml::actionIcon(
                                ['revoke-promo', 'id' => $user->id, 'grantId' => (int)$grant->id],
                                'delete',
                                [
                                    'class' => 'admin-icon-btn admin-icon-btn--danger',
                                    'data' => [
                                        'method' => 'post',
                                        'confirm' => 'Снять промокод «' . $grant->code . '» у дилера?',
                                    ],
                                ],
                            ) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ($templateOptions !== []): ?>
        <?= Html::beginForm(['grant-promo', 'id' => $user->id], 'post', ['class' => 'admin-form admin-promo-grant-form']) ?>
        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
        <label class="control-label" for="dealer-promo-template">Выдать промокод</label>
        <div class="admin-promo-grant-form__row">
            <?= Html::dropDownList(
                'template_id',
                null,
                $templateOptions,
                [
                    'id' => 'dealer-promo-template',
                    'class' => 'form-control',
                    'prompt' => 'Выберите промокод…',
                ]
            ) ?>
            <?= Html::submitButton('Выдать', ['class' => 'admin-btn']) ?>
        </div>
        <?= Html::endForm() ?>
    <?php else: ?>
        <p style="margin:0;color:#6b6862;">Нет активных шаблонов промокодов для выдачи.</p>
    <?php endif; ?>
</div>
