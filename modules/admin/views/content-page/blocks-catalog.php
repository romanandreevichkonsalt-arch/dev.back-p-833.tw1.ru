<?php

use app\models\CatalogDirection;
use app\models\ContentPage;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var ContentPage $page */
/** @var CatalogDirection[] $directions */
/** @var CatalogDirection|null $activeDirection */
/** @var array<string, mixed> $formData */
/** @var list<array<string, mixed>> $scopeNavItems */
/** @var int $activeCategoryId */
/** @var int $activeSubcategoryId */
/** @var string $scopeLead */
/** @var string $scopeLabel */
/** @var string $activeTopTabKey */
/** @var array<string, string> $seoVariableHints */

$this->title = $page->title;

$topTabs = [];
foreach ($directions as $direction) {
    $topTabs[$direction->slug] = [
        'label' => $direction->label,
        'url' => [
            'blocks',
            'id' => $page->id,
            'direction' => $direction->slug,
            'categoryId' => 0,
            'subcategoryId' => 0,
        ],
    ];
}
?>
<div class="admin-page-header">
    <div>
        <?= Html::a('← Все страницы', ['index'], ['class' => 'admin-link admin-page-back']) ?>
        <h1 class="admin-page-header__title"><?= Html::encode($page->title) ?></h1>
        <p class="admin-muted"><?= Html::encode(\app\modules\admin\helpers\ContentBlockUi::pageDescription($page->slug)) ?></p>
    </div>
</div>

<?= \app\modules\admin\helpers\AdminHtml::pageTabs($topTabs, $activeTopTabKey, 'Направление') ?>

<?= $this->render('_block_catalog_seo_variable_hints', ['variableHints' => $seoVariableHints]) ?>

<?php $form = ActiveForm::begin(['options' => ['class' => 'admin-form admin-page-editor-form']]); ?>
<?= Html::hiddenInput('catalog_editor', 'scope') ?>
<?= Html::hiddenInput('direction_id', (string)($activeDirection?->id ?? 0)) ?>
<?= Html::hiddenInput('category_id', (string)$activeCategoryId) ?>
<?= Html::hiddenInput('subcategory_id', (string)$activeSubcategoryId) ?>

<div class="admin-page-editor admin-catalog-banners">
    <aside class="admin-catalog-banners__nav">
        <p class="admin-catalog-banners__nav-title admin-muted">Разделы каталога</p>
        <ul class="admin-catalog-banners__list">
            <?php foreach ($scopeNavItems as $item): ?>
                <?php
                $isActive = $activeDirection !== null
                    && (int)$item['directionId'] === (int)$activeDirection->id
                    && (int)$item['categoryId'] === $activeCategoryId
                    && (int)$item['subcategoryId'] === $activeSubcategoryId;
                $kind = (string)$item['kind'];
                $itemClass = 'admin-catalog-banners__item admin-catalog-banners__item--' . $kind;
                if ($isActive) {
                    $itemClass .= ' is-active';
                }
                ?>
                <li class="<?= Html::encode($itemClass) ?>">
                    <?= Html::a(
                        Html::encode((string)$item['label']),
                        [
                            'blocks',
                            'id' => $page->id,
                            'direction' => $activeDirection?->slug,
                            'categoryId' => (int)$item['categoryId'],
                            'subcategoryId' => (int)$item['subcategoryId'],
                        ],
                        ['class' => 'admin-catalog-banners__link']
                    ) ?>
                    <?php if (!empty($item['configured'])): ?>
                        <span class="admin-catalog-banners__badge" title="SEO или баннер заполнены">●</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </aside>

    <div class="admin-page-editor__main">
        <div class="admin-page-block-panel">
            <h2 class="admin-page-block-panel__heading"><?= Html::encode($scopeLabel) ?></h2>
            <p class="admin-page-block-panel__lead admin-muted"><?= Html::encode($scopeLead) ?></p>
            <?= $this->render('_block_catalog_listing_seo', ['formData' => $formData]) ?>
            <?= $this->render('_block_catalog_listing_banner', ['formData' => $formData]) ?>
            <div class="admin-actions admin-page-editor__actions">
                <?= Html::submitButton('Сохранить', ['class' => 'admin-btn']) ?>
                <?= Html::a('Отмена', ['index'], ['class' => 'admin-btn admin-btn--secondary']) ?>
            </div>
        </div>
    </div>
</div>

<?php ActiveForm::end(); ?>
