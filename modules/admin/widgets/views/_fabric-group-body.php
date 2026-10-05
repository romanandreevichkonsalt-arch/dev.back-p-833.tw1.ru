<?php

use app\models\CatalogFabricCollection;
use app\models\CatalogProduct;
use app\models\MediaFile;
use app\models\MediaFolder;
use app\modules\admin\widgets\MediaPickerWidget;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var CatalogFabricCollection $fabricCollection */
/** @var array<int, CatalogProduct> $productsByColorId */
/** @var array<string, mixed> $searchPriority */
$searchPriority = $searchPriority ?? ['enabled' => false];

$colors = $fabricCollection->activeColors;
?>
<?php if ($colors === []): ?>
    <p class="admin-muted admin-model-fabrics__empty-colors">В коллекции нет активных цветов.</p>
<?php else: ?>
    <div class="admin-model-fabrics__variants">
        <?php foreach ($colors as $color): ?>
            <?php
            $fabricColorId = (int)$color->id;
            $product = $productsByColorId[$fabricColorId] ?? null;
            $prefix = 'product_variants[' . $fabricColorId . ']';
            $catalogColorName = trim((string)($color->catalogColor?->label ?? ''));
            $designCode = trim((string)$color->design_code);
            ?>
            <article
                class="admin-model-fabrics__variant"
                data-fabric-color-row
                data-color-id="<?= $fabricColorId ?>"
                data-color-slug="<?= Html::encode($color->slug) ?>"
                data-color-label="<?= Html::encode($color->getProductColorLabel()) ?>"
                data-fabric-design-code="<?= Html::encode($designCode) ?>"
            >
                <div class="admin-model-fabrics__variant-head">
                    <div class="admin-model-fabrics__color">
                        <span class="admin-fabric-swatches__dot" style="<?= Html::encode($color->getCatalogColorCircleStyle()) ?>"></span>
                        <div class="admin-model-fabrics__color-names">
                            <?php if ($catalogColorName !== ''): ?>
                                <span class="admin-model-fabrics__color-catalog"><?= Html::encode($catalogColorName) ?></span>
                                <?php if ($designCode !== '' && $designCode !== $catalogColorName): ?>
                                    <span class="admin-model-fabrics__color-design"><?= Html::encode($designCode) ?></span>
                                <?php endif; ?>
                            <?php elseif ($designCode !== ''): ?>
                                <span class="admin-model-fabrics__color-catalog"><?= Html::encode($designCode) ?></span>
                            <?php else: ?>
                                <span class="admin-model-fabrics__color-catalog">—</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (($searchPriority['enabled'] ?? false) && $product !== null): ?>
                        <?php
                        $productId = (int)$product->id;
                        $isPrioritySku = $productId === (int)($searchPriority['sampleCatalogProductId'] ?? 0);
                        $prioritySortOrder = (int)($searchPriority['sortOrder'] ?? 0);
                        ?>
                        <div class="admin-model-fabrics__variant-priority" data-search-priority-variant data-product-id="<?= $productId ?>">
                            <label class="admin-model-fabrics__priority-check" title="Приоритет в поиске">
                                <input
                                    type="checkbox"
                                    value="<?= $productId ?>"
                                    data-search-priority-check
                                    <?= $isPrioritySku ? 'checked' : '' ?>
                                >
                                <span class="admin-model-fabrics__priority-check-label">Приоритет в поиске</span>
                            </label>
                            <label class="admin-model-fabrics__priority-sort-wrap">
                                <span class="visually-hidden">№ сортировки</span>
                                <input
                                    type="number"
                                    class="form-control admin-model-fabrics__priority-sort"
                                    min="1"
                                    step="1"
                                    value="<?= $isPrioritySku && $prioritySortOrder > 0 ? $prioritySortOrder : '' ?>"
                                    placeholder="№"
                                    data-search-priority-sort
                                    <?= $isPrioritySku ? '' : 'disabled' ?>
                                >
                            </label>
                        </div>
                    <?php endif; ?>
                </div>
                <div
                    class="admin-model-fabrics__sku"
                    <?= $product === null ? ' data-sku-preview' : ' data-sku-saved="1"' ?>
                >
                    <div class="admin-model-fabrics__sku-title">
                        <?= $product !== null ? Html::encode($product->title) : '—' ?>
                    </div>
                    <div class="admin-model-fabrics__sku-slug">
                        <?= $product !== null ? Html::encode($product->slug) : '—' ?>
                    </div>
                </div>
                <div class="admin-model-fabrics__photo">
                    <?= MediaPickerWidget::widget([
                        'kind' => MediaFile::KIND_IMAGE,
                        'inputName' => $prefix . '[image_id]',
                        'value' => $product?->image_id,
                        'label' => 'Фото',
                        'allowClear' => true,
                        'compact' => true,
                        'defaultFolder' => MediaFolder::SLUG_FABRICS,
                        'enableListingTile' => true,
                    ]) ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
