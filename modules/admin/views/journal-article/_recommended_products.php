<?php

use app\models\JournalArticleRecommendedProduct;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var list<array{catalog_product_id?: int|string, product_search?: string}> $recommendedForm */

$recommendedForm = $recommendedForm ?? [];
$searchUrl = Url::to(['/admin/content-page/search-catalog-models']);
$maxCount = JournalArticleRecommendedProduct::MAX_PER_ARTICLE;
?>
<section class="admin-page-combined-section">
    <header class="admin-page-combined-section__head">
        <h2 class="admin-page-combined-section__title">Рекомендуемые товары</h2>
        <p class="admin-muted admin-page-combined-section__lead">
            Карточки в API — в формате поиска (`CatalogSearchProduct`). До <?= (int)$maxCount ?> SKU, порядок слева направо, сверху вниз.
        </p>
    </header>
    <div class="admin-page-block-panel">
        <div class="admin-journal-recommended" data-journal-recommended data-max-count="<?= (int)$maxCount ?>">
            <div
                class="admin-page-field admin-home-product-search admin-journal-recommended__search"
                data-journal-recommended-search
                data-search-url="<?= Html::encode($searchUrl) ?>"
            >
                <label class="form-label" for="journal-recommended-product-search">Добавить товар</label>
                <input
                    type="text"
                    id="journal-recommended-product-search"
                    class="form-control"
                    placeholder="Поиск по наименованию (от 3 символов)"
                    autocomplete="off"
                    data-journal-recommended-search-input
                >
                <p class="admin-field-hint">Выберите позицию из списка — она появится в сетке ниже.</p>
                <div class="admin-home-product-search__results" data-journal-recommended-search-results hidden></div>
            </div>

            <div class="admin-journal-recommended__grid" data-journal-recommended-list>
                <?php foreach ($recommendedForm as $index => $row): ?>
                    <?php if ((int)($row['catalog_product_id'] ?? 0) <= 0) {
                        continue;
                    } ?>
                    <?= $this->render('_recommended_product_card', [
                        'index' => $index,
                        'row' => $row,
                    ]) ?>
                <?php endforeach; ?>
            </div>

            <template data-journal-recommended-template>
                <?= $this->render('_recommended_product_card', [
                    'index' => '__INDEX__',
                    'row' => ['catalog_product_id' => '', 'product_search' => ''],
                ]) ?>
            </template>
        </div>
    </div>
</section>
