<?php

use app\modules\admin\helpers\HomePageProductsHelper;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$cards = $formData['cards'] ?? [];
while (count($cards) < HomePageProductsHelper::CARD_COUNT) {
    $cards[] = [
        'catalog_product_id' => '',
        'product_search' => '',
        'image_src' => '',
        'image_alt' => '',
    ];
}

$searchUrl = Url::to(['/admin/content-page/search-catalog-models']);
$layoutLabels = [
    0 => 'Главная карточка (featured)',
    1 => 'Карточка 2 (stacked)',
    2 => 'Карточка 3 (stacked)',
];
?>
<div class="admin-page-block-section">
    <p class="admin-muted admin-page-block-section__lead">
        Три товара на главной. Название, коллекция, ткань, цена и ссылка подставляются из каталога при выборе товара.
        Товары генерируются из <?= Html::a('моделей каталога', ['/admin/catalog-model/index'], ['class' => 'admin-link']) ?>.
    </p>

    <div class="admin-home-products">
        <?php foreach ($cards as $i => $card): ?>
            <?php if ($i >= HomePageProductsHelper::CARD_COUNT) {
                break;
            } ?>
            <div
                class="admin-content-row admin-home-product-card<?= $i === 0 ? ' admin-home-product-card--featured' : '' ?>"
                data-home-product-picker
                data-search-url="<?= Html::encode($searchUrl) ?>"
            >
                <span class="admin-home-hero-zone__badge"><?= sprintf('%02d', $i + 1) ?></span>
                <div class="admin-home-product-card__fields">
                    <?= $this->render('_block_row_header', [
                        'title' => $layoutLabels[$i] ?? 'Товар ' . ($i + 1),
                        'removable' => false,
                    ]) ?>

                    <div class="admin-page-field admin-home-product-search">
                        <label class="form-label" for="cards_<?= $i ?>_product_search">Товар</label>
                        <?= Html::hiddenInput("cards[{$i}][catalog_product_id]", $card['catalog_product_id'] ?? '', [
                            'id' => "cards_{$i}_catalog_product_id",
                            'data-product-id-input' => true,
                        ]) ?>
                        <input
                            type="search"
                            class="form-control"
                            id="cards_<?= $i ?>_product_search"
                            name="cards[<?= $i ?>][product_search]"
                            value="<?= Html::encode($card['product_search'] ?? '') ?>"
                            placeholder="Поиск по наименованию товара (от 3 символов)"
                            autocomplete="off"
                            data-product-search-input
                        >
                        <p class="admin-field-hint">Начните ввод и выберите товар из списка — текст в поле без выбора не сохраняется.</p>
                        <div class="admin-home-product-search__results" data-product-search-results hidden></div>
                    </div>

                    <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                        'inputName' => "cards[{$i}][image_src]",
                        'altInputName' => "cards[{$i}][image_alt]",
                        'value' => $card['image_src'] ?? '',
                        'altValue' => $card['image_alt'] ?? '',
                        'label' => 'Баннер карточки',
                    ]) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
