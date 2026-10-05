<?php

use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\PromotionBanner[] $banners */
/** @var app\models\PromotionPopup[] $popups */
/** @var array<int, string> $promoTemplates */

$formatPeriodDate = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '—';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return $value;
    }

    return date('d.m.y', $ts);
};
?>
<p class="admin-hint" style="margin-bottom:20px;">
    Два типа промо в личном кабинете дилера: <strong>баннеры в ленте</strong> и <strong>всплывающие попапы</strong>.
    Создавайте и редактируйте их в соответствующих блоках ниже.
</p>

<section class="admin-promo-marketing-section admin-promo-marketing-section--feed" aria-labelledby="promo-feed-heading">
    <div class="admin-card">
        <div class="admin-promo-marketing-section__head" id="promo-feed-heading">
            <div class="admin-promo-marketing-section__head-main">
                <span class="admin-promo-type-badge admin-promo-type-badge--feed">Лента</span>
                <h2 class="admin-form-section-title">Баннеры в ленте акций</h2>
            </div>
            <button type="button" class="admin-btn admin-btn--secondary admin-promo-marketing-section__create-btn" data-promo-banner-create-modal-open>
                Создать баннер
            </button>
        </div>
        <p class="admin-muted">Горизонтальные карточки в разделе акций. Можно несколько — показываются все активные по датам.</p>

        <?php if ($banners === []): ?>
            <p class="admin-muted">Баннеров пока нет.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Заголовок</th>
                        <th>Статус</th>
                        <th>Промокод</th>
                        <th>Период</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($banners as $banner): ?>
                        <tr>
                            <td><?= Html::encode($banner->headline) ?></td>
                            <td><span class="admin-promo-status"><?= Html::encode($banner->displayStatusLabel()) ?></span></td>
                            <td>
                                <?php if ($banner->template !== null): ?>
                                    <code><?= Html::encode($banner->template->code) ?></code>
                                <?php else: ?>
                                    <span class="admin-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= Html::encode(
                                    $formatPeriodDate($banner->valid_from)
                                    . ' … '
                                    . $formatPeriodDate($banner->valid_to)
                                ) ?>
                            </td>
                            <td class="admin-table-actions">
                                <?= AdminHtml::actionIcon(['/admin/promotion-banner/update', 'id' => $banner->id], 'edit') ?>
                                <?= AdminHtml::actionIcon(['/admin/promotion-banner/delete', 'id' => $banner->id], 'delete', [
                                    'class' => 'admin-icon-btn admin-icon-btn--danger',
                                    'data' => [
                                        'method' => 'post',
                                        'confirm' => 'Удалить баннер «' . $banner->headline . '»?',
                                    ],
                                ]) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>

<section class="admin-promo-marketing-section admin-promo-marketing-section--popup" aria-labelledby="promo-popup-heading" style="margin-top:32px;">
    <div class="admin-card">
        <div class="admin-promo-marketing-section__head" id="promo-popup-heading">
            <div class="admin-promo-marketing-section__head-main">
                <span class="admin-promo-type-badge admin-promo-type-badge--popup">Попап</span>
                <h2 class="admin-form-section-title">Всплывающие баннеры</h2>
            </div>
            <button type="button" class="admin-btn admin-btn--secondary admin-promo-marketing-section__create-btn" data-promo-popup-create-modal-open>
                Создать попап
            </button>
        </div>
        <p class="admin-muted">Модальные окна при работе в ЛКД. Можно несколько — в API отдаются все активные по датам.</p>

        <?php if ($popups === []): ?>
            <p class="admin-muted">Попапов пока нет.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Заголовок</th>
                        <th>Статус</th>
                        <th>Промокод</th>
                        <th>Период</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($popups as $popup): ?>
                        <tr>
                            <td><?= Html::encode($popup->headline !== '' ? $popup->headline : ('Попап #' . $popup->id)) ?></td>
                            <td><span class="admin-promo-status"><?= Html::encode($popup->displayStatusLabel()) ?></span></td>
                            <td>
                                <?php if ($popup->template !== null): ?>
                                    <code><?= Html::encode($popup->template->code) ?></code>
                                <?php else: ?>
                                    <span class="admin-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= Html::encode(
                                    $formatPeriodDate($popup->valid_from)
                                    . ' … '
                                    . $formatPeriodDate($popup->valid_to)
                                ) ?>
                            </td>
                            <td class="admin-table-actions">
                                <?= AdminHtml::actionIcon(['/admin/promotion-popup/update', 'id' => $popup->id], 'edit') ?>
                                <?= AdminHtml::actionIcon(['/admin/promotion-popup/delete', 'id' => $popup->id], 'delete', [
                                    'class' => 'admin-icon-btn admin-icon-btn--danger',
                                    'data' => [
                                        'method' => 'post',
                                        'confirm' => 'Удалить попап «' . ($popup->headline !== '' ? $popup->headline : ('#' . $popup->id)) . '»?',
                                    ],
                                ]) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>
