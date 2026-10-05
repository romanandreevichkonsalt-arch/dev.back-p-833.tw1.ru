<?php

use app\models\CatalogFabricCollection;
use app\models\CatalogProduct;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var CatalogFabricCollection $fabricCollection */
/** @var bool $isLinked */
/** @var array<int, CatalogProduct> $productsByColorId */
/** @var array<string, mixed> $searchPriority */
/** @var bool $lazyLoadBody */
$searchPriority = $searchPriority ?? ['enabled' => false];
$lazyLoadBody = $lazyLoadBody ?? false;
$productsByColorId = $productsByColorId ?? [];

$texture = trim((string)($fabricCollection->texture ?? ''));
$colorCount = $lazyLoadBody
    ? (int)($fabricCollection->activeColorsCount ?? 0)
    : count($fabricCollection->activeColors);
$useLazyShell = $lazyLoadBody;
$groupClasses = ['admin-model-fabrics__group'];
if ($isLinked) {
    $groupClasses[] = 'is-active';
}
if ($useLazyShell) {
    $groupClasses[] = 'is-collapsed';
}
?>
<div
    class="<?= Html::encode(implode(' ', $groupClasses)) ?>"
    data-fabric-group
    data-fabric-id="<?= (int)$fabricCollection->id ?>"
    data-fabric-name="<?= Html::encode($fabricCollection->name) ?>"
    data-fabric-texture="<?= Html::encode($texture) ?>"
    <?= $useLazyShell ? ' data-fabric-lazy="1" data-fabric-body-loaded="0"' : '' ?>
>
    <div class="admin-model-fabrics__head">
        <button
            type="button"
            class="admin-model-fabrics__head-toggle"
            data-fabric-toggle
            aria-expanded="<?= $useLazyShell ? 'false' : 'true' ?>"
            <?= $colorCount === 0 ? ' disabled' : '' ?>
        >
            <span class="admin-model-fabrics__chevron" aria-hidden="true"></span>
            <span class="admin-model-fabrics__head-info">
                <span class="admin-model-fabrics__name"><?= Html::encode($fabricCollection->name) ?></span>
                <?php if ($texture !== ''): ?>
                    <span class="admin-model-fabrics__texture"><?= Html::encode($texture) ?></span>
                <?php endif; ?>
                <?php if ($colorCount > 0): ?>
                    <span class="admin-model-fabrics__toggle-meta"><?= $colorCount ?> цветов</span>
                <?php endif; ?>
            </span>
        </button>
        <?php if ($isLinked): ?>
            <input
                type="hidden"
                class="admin-model-fabrics__link-input"
                name="fabric_collection_ids[]"
                value="<?= (int)$fabricCollection->id ?>"
                data-fabric-link-input
            >
            <button type="button" class="admin-btn admin-btn--secondary admin-btn--sm" data-fabric-remove>
                Убрать
            </button>
        <?php endif; ?>
    </div>

    <?php if ($useLazyShell): ?>
        <?php if ($colorCount > 0): ?>
            <div class="admin-model-fabrics__body" hidden data-fabric-body></div>
        <?php else: ?>
            <p class="admin-muted admin-model-fabrics__empty-colors">В коллекции нет активных цветов.</p>
        <?php endif; ?>
    <?php elseif ($colorCount > 0): ?>
        <div class="admin-model-fabrics__body" data-fabric-body>
            <?= $this->render('_fabric-group-body', [
                'fabricCollection' => $fabricCollection,
                'productsByColorId' => $productsByColorId,
                'searchPriority' => $searchPriority,
            ]) ?>
        </div>
    <?php else: ?>
        <p class="admin-muted admin-model-fabrics__empty-colors">В коллекции нет активных цветов.</p>
    <?php endif; ?>
</div>
