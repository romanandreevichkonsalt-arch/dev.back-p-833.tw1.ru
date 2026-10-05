<?php

use app\modules\admin\helpers\PromoSectionTabs;
use app\modules\admin\models\PromotionBannerForm;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var PromotionBannerForm $model */
/** @var array<int, string> $promoTemplates */
/** @var bool $open */

$open = !empty($open);
?>
<div
    class="admin-modal admin-promo-banner-create-modal"
    data-promo-banner-create-modal
    <?= $open ? '' : 'hidden' ?>
>
    <div class="admin-modal__backdrop" data-promo-banner-create-modal-close></div>
    <div class="admin-modal__dialog admin-promo-banner-create-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="promo-banner-create-modal-title">
        <div class="admin-modal__header">
            <div class="admin-modal__header-title-row">
                <span class="admin-promo-type-badge admin-promo-type-badge--feed">Лента</span>
                <h3 class="admin-modal__title" id="promo-banner-create-modal-title">Новый баннер в ленте акций</h3>
            </div>
            <button type="button" class="admin-modal__close" data-promo-banner-create-modal-close aria-label="Закрыть">&times;</button>
        </div>
        <?= $this->render('_form_fields', [
            'model' => $model,
            'banner' => null,
            'promoTemplates' => $promoTemplates,
            'formAction' => Url::to(['/admin/promo-code/index', 'tab' => PromoSectionTabs::TAB_BANNERS]),
            'embedInCreateModal' => true,
        ]) ?>
    </div>
</div>
