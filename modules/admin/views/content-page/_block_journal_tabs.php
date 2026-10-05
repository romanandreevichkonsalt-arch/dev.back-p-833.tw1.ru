<?php

use app\modules\admin\helpers\AdminHtml;
use app\modules\admin\helpers\ContentPageJournalHelper;
use app\services\journal\JournalArticleService;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$categories = ContentPageJournalHelper::padJournalCategoriesForForm($formData['categories'] ?? []);
$journalArticleService = new JournalArticleService();
$articlesByCategory = $journalArticleService->articlesByCategoryForAdmin();
?>
<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Четыре вкладки</h3>
    <p class="admin-muted admin-page-block-section__lead">Названия вкладок и статьи по категориям.</p>

    <div class="admin-faq-tabs">
        <div class="admin-faq-tabs__nav" role="tablist">
            <?php foreach ($categories as $ci => $category): ?>
                <button
                    type="button"
                    class="admin-faq-tabs__tab<?= $ci === 0 ? ' is-active' : '' ?>"
                    role="tab"
                    data-faq-tab="<?= (int)$ci ?>"
                    aria-selected="<?= $ci === 0 ? 'true' : 'false' ?>"
                >
                    <span class="admin-faq-tabs__tab-num"><?= sprintf('%02d', $ci + 1) ?></span>
                    <span class="admin-faq-tabs__tab-label"><?= htmlspecialchars($category['label'] ?? '', ENT_QUOTES) ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <?php foreach ($categories as $ci => $category): ?>
            <?php
            $categoryId = (string)($category['id'] ?? '');
            $tabArticles = $articlesByCategory[$categoryId] ?? [];
            $articleCount = count($tabArticles);
            ?>
            <div class="admin-faq-tabs__panel<?= $ci === 0 ? ' is-active' : '' ?>" data-faq-panel="<?= (int)$ci ?>">
                <div class="admin-faq-tabs__panel-head">
                    <h4 class="admin-faq-tabs__panel-title">Вкладка <?= $ci + 1 ?></h4>
                    <p class="admin-muted admin-faq-tabs__panel-meta">
                        ID: <code><?= htmlspecialchars($categoryId, ENT_QUOTES) ?></code>
                        · иконка: <code><?= htmlspecialchars($category['icon'] ?? '', ENT_QUOTES) ?></code>
                        · статей: <?= (int)$articleCount ?>
                    </p>
                </div>

                <input type="hidden" name="categories[<?= $ci ?>][id]" value="<?= htmlspecialchars($categoryId, ENT_QUOTES) ?>">
                <input type="hidden" name="categories[<?= $ci ?>][icon]" value="<?= htmlspecialchars($category['icon'] ?? '', ENT_QUOTES) ?>">

                <?= $this->render('_block_field', [
                    'name' => "categories[{$ci}][label]",
                    'label' => 'Название вкладки',
                    'value' => $category['label'] ?? '',
                ]) ?>

                <div class="admin-journal-tabs__articles">
                    <div class="admin-content-repeatable__toolbar">
                        <h4 class="admin-content-nested__title">Статьи</h4>
                        <?= Html::a(
                            'Добавить статью',
                            Url::to(['/admin/journal-article/create', 'category' => $categoryId]),
                            ['class' => 'admin-btn admin-btn--ghost admin-btn--small']
                        ) ?>
                    </div>

                    <?php if ($tabArticles === []): ?>
                        <p class="admin-muted admin-journal-tabs__empty">Статей во вкладке пока нет.</p>
                    <?php else: ?>
                        <ul class="admin-journal-tabs__article-list">
                            <?php foreach ($tabArticles as $article): ?>
                                <li class="admin-journal-tabs__article-item">
                                    <div class="admin-journal-tabs__article-main">
                                        <span class="admin-journal-tabs__article-title"><?= Html::encode($article->title) ?></span>
                                        <?php if (!$article->is_active): ?>
                                            <span class="admin-badge admin-badge--rejected">Скрыта</span>
                                        <?php endif; ?>
                                    </div>
                                    <?= AdminHtml::actionIcon(['/admin/journal-article/update', 'id' => $article->id], 'update') ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
