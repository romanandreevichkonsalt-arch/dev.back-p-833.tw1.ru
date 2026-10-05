<?php

use app\modules\admin\helpers\PromoSectionTabs;
use app\modules\admin\models\PromotionBannerForm;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var PromotionBannerForm $model */
/** @var app\models\PromotionBanner|null $banner */
/** @var array<int, string> $promoTemplates */

$this->title = $banner === null ? 'Новый баннер акции' : 'Баннер: ' . $banner->headline;
$this->registerJsFile('@web/js/admin-promo-marketing.js', ['depends' => [\app\modules\admin\assets\AdminAsset::class]]);
?>
<div class="admin-toolbar">
    <?= Html::a('← Баннеры акций', ['/admin/promo-code/index', 'tab' => PromoSectionTabs::TAB_BANNERS], ['class' => 'admin-link']) ?>
    <?php if ($banner !== null && $banner->template_id !== null): ?>
        <?= Html::a(
            'Перевыдать промокод всем',
            ['grant-promo', 'id' => $banner->id],
            [
                'class' => 'admin-btn admin-btn--secondary',
                'style' => 'margin-left:12px;',
                'data' => ['method' => 'post', 'confirm' => 'Выдать промокод всем дилерам, у кого его ещё нет?'],
            ]
        ) ?>
    <?php endif; ?>
</div>

<div class="admin-card">
    <?= $this->render('_form_fields', [
        'model' => $model,
        'banner' => $banner,
        'promoTemplates' => $promoTemplates,
        'form' => null,
    ]) ?>
</div>

<p class="admin-hint">Цены на баннере не связаны с каталогом. Промокод работает через «Мои бонусы» и корзину как обычный custom-код.</p>
