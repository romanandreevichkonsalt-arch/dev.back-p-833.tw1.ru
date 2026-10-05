<?php

use app\modules\admin\helpers\AdminHtml;
use app\modules\admin\helpers\PromoSectionTabs;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var string $activeTab */
/** @var app\models\PromoCodeTemplate[] $templates */
/** @var list<array<string, mixed>> $systemPromos */
/** @var app\models\PromotionBanner[] $banners */
/** @var app\models\CatalogPromotion[] $promotions */
/** @var app\modules\admin\models\PromotionBannerForm $bannerForm */
/** @var app\models\PromotionPopup[] $popups */
/** @var app\modules\admin\models\PromotionPopupForm $popupForm */
/** @var array<int, string> $promoTemplates */
/** @var app\modules\admin\models\PromoCodeForm $promoCreateForm */
/** @var list<string> $promoCreateConditionLines */
/** @var bool $openPromoCreateModal */
/** @var bool $openBannerCreateModal */
/** @var bool $openPopupCreateModal */

$this->title = 'Промо и акции';
$this->registerJsFile('@web/js/admin-promo-marketing.js', ['depends' => [\app\modules\admin\assets\AdminAsset::class]]);
?>
<div class="admin-toolbar">
    <?php if ($activeTab === PromoSectionTabs::TAB_PROMO_CODES): ?>
        <button type="button" class="admin-btn" data-promo-code-create-modal-open>Создать промокод</button>
    <?php elseif ($activeTab === PromoSectionTabs::TAB_SALES): ?>
        <?= Html::a('Создать акцию', ['/admin/catalog-promotion/create'], ['class' => 'admin-btn']) ?>
    <?php endif; ?>
</div>

<?= AdminHtml::pageTabs(PromoSectionTabs::definitions(), $activeTab, 'Раздел промо и акций') ?>

<?php if ($activeTab === PromoSectionTabs::TAB_PROMO_CODES): ?>
    <?= $this->render('_tab_promo_codes', [
        'templates' => $templates,
        'systemPromos' => $systemPromos,
    ]) ?>
<?php elseif ($activeTab === PromoSectionTabs::TAB_BANNERS): ?>
    <?= $this->render('_tab_banners', [
        'banners' => $banners,
        'popups' => $popups,
        'bannerForm' => $bannerForm,
        'popupForm' => $popupForm,
        'promoTemplates' => $promoTemplates,
    ]) ?>
<?php else: ?>
    <?= $this->render('_tab_sales', ['promotions' => $promotions]) ?>
<?php endif; ?>

<?= $this->render('_create_modal', [
    'model' => $promoCreateForm,
    'conditionLines' => $promoCreateConditionLines,
    'open' => $openPromoCreateModal,
]) ?>

<?php if ($activeTab === PromoSectionTabs::TAB_BANNERS): ?>
    <?= $this->render('@app/modules/admin/views/promotion-banner/_banner_create_modal', [
        'model' => $bannerForm,
        'promoTemplates' => $promoTemplates,
        'open' => !empty($openBannerCreateModal),
    ]) ?>
    <?= $this->render('@app/modules/admin/views/promotion-banner/_popup_create_modal', [
        'model' => $popupForm,
        'promoTemplates' => $promoTemplates,
        'open' => !empty($openPopupCreateModal),
    ]) ?>
<?php endif; ?>
