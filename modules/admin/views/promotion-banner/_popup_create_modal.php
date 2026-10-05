<?php

use app\modules\admin\helpers\PromoSectionTabs;
use app\modules\admin\models\PromotionPopupForm;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var PromotionPopupForm $model */
/** @var array<int, string> $promoTemplates */
/** @var bool $open */

$open = !empty($open);
?>
<div
    class="admin-modal admin-promo-popup-create-modal"
    data-promo-popup-create-modal
    <?= $open ? '' : 'hidden' ?>
>
    <div class="admin-modal__backdrop" data-promo-popup-create-modal-close></div>
    <div class="admin-modal__dialog admin-promo-popup-create-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="promo-popup-create-modal-title">
        <div class="admin-modal__header">
            <div class="admin-modal__header-title-row">
                <span class="admin-promo-type-badge admin-promo-type-badge--popup">Попап</span>
                <h3 class="admin-modal__title" id="promo-popup-create-modal-title">Новый всплывающий баннер</h3>
            </div>
            <button type="button" class="admin-modal__close" data-promo-popup-create-modal-close aria-label="Закрыть">&times;</button>
        </div>
        <?= $this->render('_popup_form_fields', [
            'model' => $model,
            'popup' => null,
            'promoTemplates' => $promoTemplates,
            'formAction' => Url::to(['/admin/promo-code/index', 'tab' => PromoSectionTabs::TAB_BANNERS]),
            'embedInCreateModal' => true,
        ]) ?>
    </div>
</div>
