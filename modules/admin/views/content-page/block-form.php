<?php

use app\models\ContentBlock;
use app\models\ContentPage;
use app\modules\admin\helpers\ContentBlockUi;
use app\modules\admin\helpers\ContentPageContactsHelper;
use app\modules\admin\helpers\ContentPageDesignersHelper;
use app\modules\admin\helpers\ContentPageFaqHelper;
use app\modules\admin\helpers\ContentPagePartnersHelper;
use app\modules\admin\helpers\HomePageCollectionsHelper;
use app\services\content\BlockTypeRegistry;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var ContentBlock $block */
/** @var ContentPage $page */
/** @var array<string, mixed> $formData */
/** @var string $typeLabel */
/** @var bool $isHomeHeroEditor */
/** @var bool $isPartnersHeroEditor */

/** @var bool $isPartnersIntroEditor */

/** @var bool $isHomeCollectionsEditor */

$isHomeHeroEditor = $isHomeHeroEditor ?? false;
$isHomeCollectionsEditor = $isHomeCollectionsEditor ?? false;
$isPartnersHeroEditor = $isPartnersHeroEditor ?? false;
$isPartnersIntroEditor = $isPartnersIntroEditor ?? false;
$isPartnersFormatsEditor = $isPartnersFormatsEditor ?? false;
$isPartnersAudienceEditor = $isPartnersAudienceEditor ?? false;
$isPartnersSalonFormatsEditor = $isPartnersSalonFormatsEditor ?? false;
$isPartnersPresentationEditor = $isPartnersPresentationEditor ?? false;
$isContactsContactEditor = $isContactsContactEditor ?? false;
$isContactsHeroEditor = $isContactsHeroEditor ?? false;
$isFaqHeroEditor = $isFaqHeroEditor ?? false;
$isFaqIntroEditor = $isFaqIntroEditor ?? false;
$isFaqCategoriesEditor = $isFaqCategoriesEditor ?? false;
$isDesignersHeroEditor = $isDesignersHeroEditor ?? false;
$isDesignersIntroEditor = $isDesignersIntroEditor ?? false;
$isDesignersMaterialsEditor = $isDesignersMaterialsEditor ?? false;
$isDesignersGalleryEditor = $isDesignersGalleryEditor ?? false;

$blockTitle = match ($page->slug) {
    'partners' => ContentPagePartnersHelper::blockLabel($block->block_key),
    'designers' => ContentPageDesignersHelper::blockLabel($block->block_key),
    'contacts' => ContentPageContactsHelper::blockLabel($block->block_key),
    'faq' => ContentPageFaqHelper::blockLabel($block->block_key),
    default => ContentBlockUi::keyLabel($block->block_key),
};

$this->title = $blockTitle;
?>
<div class="admin-page-header">
    <div>
        <?= Html::a('← Блоки страницы', ['blocks', 'id' => $page->id], ['class' => 'admin-link admin-page-back']) ?>
        <h1 class="admin-page-header__title"><?= Html::encode($blockTitle) ?></h1>
        <p class="admin-muted">
            <?= Html::encode($typeLabel) ?>
            · страница «<?= Html::encode($page->title) ?>»
        </p>
    </div>
</div>

<?php $form = ActiveForm::begin(['options' => ['class' => 'admin-form admin-page-editor-form']]); ?>

<div class="admin-page-editor admin-page-editor--full">
    <div class="admin-page-editor__main">
        <div class="admin-page-block-panel">
            <?php if ($isHomeHeroEditor): ?>
                <p class="admin-page-block-panel__lead admin-muted">SEO, фото баннера и тексты на главной — в одной форме.</p>
            <?php elseif ($isHomeCollectionsEditor): ?>
                <p class="admin-page-block-panel__lead admin-muted">Философия бренда и карточки коллекций с фото — в одной форме.</p>
            <?php elseif ($isPartnersHeroEditor): ?>
                <p class="admin-page-block-panel__lead admin-muted">SEO, фото баннера (компьютер и телефон) и заголовки страницы.</p>
            <?php elseif ($isDesignersHeroEditor): ?>
                <p class="admin-page-block-panel__lead admin-muted">SEO, фото баннера (компьютер и телефон) и заголовки страницы.</p>
            <?php elseif ($isContactsHeroEditor): ?>
                <p class="admin-page-block-panel__lead admin-muted">SEO, фото баннера (компьютер и телефон) и заголовки страницы.</p>
            <?php elseif ($isFaqHeroEditor): ?>
                <p class="admin-page-block-panel__lead admin-muted">SEO, фото баннера (компьютер и телефон) и заголовки страницы.</p>
            <?php elseif ($isFaqIntroEditor): ?>
                <p class="admin-page-block-panel__lead admin-muted">Заголовок «FAQs» и подзаголовок под баннером.</p>
            <?php elseif ($isFaqCategoriesEditor): ?>
                <p class="admin-page-block-panel__lead admin-muted">Четыре вкладки с вопросами и ответами.</p>
            <?php elseif ($isPartnersIntroEditor): ?>
                <p class="admin-page-block-panel__lead admin-muted">Текст, большое фото слева и 5 слайдов преимуществ.</p>
            <?php elseif ($isDesignersIntroEditor): ?>
                <p class="admin-page-block-panel__lead admin-muted">Текст, фото слева и 4 слайдов преимуществ.</p>
            <?php elseif ($isDesignersMaterialsEditor): ?>
                <p class="admin-page-block-panel__lead admin-muted">Фото на фоне, текст, архив и три колонки.</p>
            <?php elseif ($isDesignersGalleryEditor): ?>
                <p class="admin-page-block-panel__lead admin-muted">Текст и стопка фото.</p>
            <?php elseif ($isPartnersFormatsEditor): ?>
                <p class="admin-page-block-panel__lead admin-muted">Заголовки, сетка «Вы получите» и фото справа.</p>
            <?php elseif ($isPartnersAudienceEditor): ?>
                <p class="admin-page-block-panel__lead admin-muted">Заголовок, баннер, 3 карточки и строка показателей.</p>
            <?php elseif ($isPartnersSalonFormatsEditor): ?>
                <p class="admin-page-block-panel__lead admin-muted">Три формата салона и общие условия партнёрства.</p>
            <?php elseif ($isPartnersPresentationEditor): ?>
                <p class="admin-page-block-panel__lead admin-muted">Список преимуществ, кнопка PDF и фото справа.</p>
            <?php elseif ($isContactsContactEditor): ?>
                <p class="admin-page-block-panel__lead admin-muted">Фото слева, заголовки формы, PDF политики конфиденциальности и пользовательского соглашения.</p>
            <?php else: ?>
                <p class="admin-page-block-panel__lead admin-muted"><?= Html::encode(ContentBlockUi::keyHint($block->block_key, $page->slug)) ?></p>
            <?php endif; ?>

            <?php
            $partial = BlockTypeRegistry::formPartial($block->block_type);
            $partialParams = [
                'formData' => $formData,
                'block' => $block,
            ];
            if ($block->block_type === BlockTypeRegistry::TYPE_HERO_HOME) {
                $partialParams['withSeo'] = $isHomeHeroEditor;
            }
            if ($block->block_type === BlockTypeRegistry::TYPE_HERO_MEDIA && ($isPartnersHeroEditor || $isDesignersHeroEditor || $isContactsHeroEditor || $isFaqHeroEditor)) {
                $partialParams['withSeo'] = true;
            }
            if ($block->block_type === BlockTypeRegistry::TYPE_HERO_MEDIA) {
                $partial = '_block_hero_home';
            }
            if ($block->block_type === BlockTypeRegistry::TYPE_COLLECTIONS) {
                $partialParams['catalogDirectionOptions'] = HomePageCollectionsHelper::directionOptions();
                $partialParams['withPhilosophy'] = $isHomeCollectionsEditor;
            }
            if ($isPartnersIntroEditor) {
                echo $this->render('_block_partners_intro', $partialParams);
            } elseif ($isDesignersIntroEditor) {
                echo $this->render('_block_designers_intro', $partialParams);
            } elseif ($isDesignersMaterialsEditor) {
                echo $this->render('_block_designers_materials_section', $partialParams);
            } elseif ($isDesignersGalleryEditor) {
                echo $this->render('_block_designers_gallery', $partialParams);
            } elseif ($isPartnersFormatsEditor) {
                echo $this->render('_block_formats_section', $partialParams);
            } elseif ($isPartnersAudienceEditor) {
                echo $this->render('_block_audience_section', $partialParams);
            } elseif ($isPartnersSalonFormatsEditor) {
                echo $this->render('_block_salon_formats_section', $partialParams);
            } elseif ($isPartnersPresentationEditor) {
                echo $this->render('_block_presentation_section', $partialParams);
            } elseif ($isContactsContactEditor) {
                echo $this->render('_block_partners_contact', $partialParams);
            } elseif ($isFaqIntroEditor) {
                echo $this->render('_block_faq_intro', $partialParams);
            } elseif ($isFaqCategoriesEditor) {
                echo $this->render('_block_faq_tabs', $partialParams);
            } else {
                echo $this->render($partial, $partialParams);
            }
            ?>
            <div class="admin-actions admin-page-editor__actions">
                <?= Html::submitButton('Сохранить', ['class' => 'admin-btn']) ?>
                <?= Html::a('Отмена', ['blocks', 'id' => $page->id], ['class' => 'admin-btn admin-btn--secondary']) ?>
            </div>
        </div>
    </div>
</div>

<?php ActiveForm::end(); ?>
