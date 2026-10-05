<?php

use app\models\CatalogFabricCollection;
use app\models\CatalogPriceCategory;
use app\modules\admin\controllers\FabricCollectionController;
use app\modules\admin\helpers\AdminHtml;

/** @var yii\web\View $this */
/** @var CatalogFabricCollection[] $collections */
/** @var app\modules\admin\models\FabricCollectionSearch $searchModel */
/** @var CatalogPriceCategory[] $priceCategories */
/** @var string $activeTab */

$this->title = 'Ткани';
?>
<?= AdminHtml::pageTabs([
    FabricCollectionController::TAB_COLLECTIONS => [
        'label' => 'Коллекции',
        'url' => ['index', 'tab' => FabricCollectionController::TAB_COLLECTIONS],
    ],
    FabricCollectionController::TAB_CATEGORIES => [
        'label' => 'Категории ткани',
        'url' => ['index', 'tab' => FabricCollectionController::TAB_CATEGORIES],
    ],
], $activeTab, 'Разделы тканей') ?>

<?php if ($activeTab === FabricCollectionController::TAB_CATEGORIES): ?>
    <?= $this->render('_tab_categories', ['priceCategories' => $priceCategories]) ?>
<?php else: ?>
    <?= $this->render('_tab_collections', [
        'collections' => $collections,
        'searchModel' => $searchModel,
    ]) ?>
<?php endif; ?>
