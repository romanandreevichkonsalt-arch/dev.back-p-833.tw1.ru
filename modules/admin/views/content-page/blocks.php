<?php

use app\models\ContentBlock;
use app\models\ContentPage;
use app\modules\admin\helpers\ContentBlockUi;
use app\services\content\BlockTypeRegistry;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var ContentPage $page */
/** @var ContentBlock[] $blocks */
/** @var array<string, string> $typeLabels */

$this->title = $page->title;
?>
<div class="admin-page-header">
    <div>
        <?= Html::a('← Все страницы', ['index'], ['class' => 'admin-link admin-page-back']) ?>
        <h1 class="admin-page-header__title"><?= Html::encode($page->title) ?></h1>
        <p class="admin-muted"><?= Html::encode(ContentBlockUi::pageDescription($page->slug)) ?></p>
    </div>
</div>

<?php if ($blocks === []): ?>
    <div class="admin-card admin-page-empty">
        <p>На странице нет блоков. Импортируйте контент: <code>php yii seed/pages</code></p>
    </div>
<?php else: ?>
    <div class="admin-page-blocks">
        <?php foreach ($blocks as $block): ?>
            <article class="admin-page-block-card">
                <header class="admin-page-block-card__head">
                    <div>
                        <h2 class="admin-page-block-card__title"><?= Html::encode(
                            match ($page->slug) {
                                'partners' => \app\modules\admin\helpers\ContentPagePartnersHelper::blockLabel($block->block_key),
                                'designers' => \app\modules\admin\helpers\ContentPageDesignersHelper::blockLabel($block->block_key),
                                'contacts' => \app\modules\admin\helpers\ContentPageContactsHelper::blockLabel($block->block_key),
                                'faq' => \app\modules\admin\helpers\ContentPageFaqHelper::blockLabel($block->block_key),
                                'journal' => \app\modules\admin\helpers\ContentPageJournalHelper::blockLabel($block->block_key),
                                default => ContentBlockUi::keyLabel($block->block_key),
                            }
                        ) ?></h2>
                        <p class="admin-page-block-card__type"><?= Html::encode($typeLabels[$block->block_type] ?? $block->block_type) ?></p>
                    </div>
                </header>
                <p class="admin-page-block-card__hint admin-muted"><?= Html::encode(ContentBlockUi::keyHint($block->block_key, $page->slug)) ?></p>
                <footer class="admin-page-block-card__actions">
                    <?= Html::a('Редактировать', ['update-block', 'id' => $block->id], ['class' => 'admin-btn admin-btn--secondary']) ?>
                </footer>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
