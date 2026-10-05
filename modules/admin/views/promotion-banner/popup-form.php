<?php

use app\modules\admin\helpers\PromoSectionTabs;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\modules\admin\models\PromotionPopupForm $model */
/** @var app\models\PromotionPopup $popup */
/** @var array<int, string> $promoTemplates */

$this->title = 'Попап: ' . ($popup->headline !== '' ? $popup->headline : ('#' . $popup->id));
$this->registerJsFile('@web/js/admin-promo-marketing.js', ['depends' => [\app\modules\admin\assets\AdminAsset::class]]);
?>
<div class="admin-toolbar">
    <?= Html::a('← Баннеры и попапы', ['/admin/promo-code/index', 'tab' => PromoSectionTabs::TAB_BANNERS], ['class' => 'admin-link']) ?>
    <?php if ($popup->template_id !== null): ?>
        <?= Html::a(
            'Перевыдать промокод всем',
            ['grant-promo', 'id' => $popup->id],
            [
                'class' => 'admin-btn admin-btn--secondary',
                'style' => 'margin-left:12px;',
                'data' => ['method' => 'post', 'confirm' => 'Выдать промокод всем дилерам, у кого его ещё нет?'],
            ]
        ) ?>
    <?php endif; ?>
</div>

<div class="admin-card admin-promo-marketing-section--popup">
    <?= $this->render('_popup_form_fields', [
        'model' => $model,
        'popup' => $popup,
        'promoTemplates' => $promoTemplates,
        'formAction' => null,
    ]) ?>
</div>

<p class="admin-hint">Попап показывается во всплывающем окне ЛКД. Не путать с баннерами в ленте акций на той же вкладке.</p>
