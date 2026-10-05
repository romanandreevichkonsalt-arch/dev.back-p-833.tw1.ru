<?php

namespace app\modules\admin\widgets;

use app\models\CatalogFabricCollection;
use app\models\CatalogModel;
use app\models\CatalogProduct;
use app\modules\admin\assets\AdminAsset;
use yii\base\Widget;
use yii\helpers\Url;

class ModelFabricProductsWidget extends Widget
{
    public ?CatalogModel $catalogModel = null;
    /** @var CatalogFabricCollection[] */
    public array $fabricCollections = [];
    /** @var int[] */
    public array $linkedFabricIds = [];
    /** @var array<int, CatalogProduct> */
    public array $productsByColorId = [];
    /** @var array<string, mixed> */
    public array $searchPriority = [];

    public function run(): string
    {
        AdminAsset::register($this->view);

        $lazyLoadBodies = $this->catalogModel !== null && !$this->catalogModel->isNewRecord;

        return $this->render('model-fabric-products', [
            'catalogModel' => $this->catalogModel,
            'fabricCollections' => $this->fabricCollections,
            'linkedFabricIds' => $this->linkedFabricIds,
            'productsByColorId' => $lazyLoadBodies ? [] : $this->productsByColorId,
            'searchPriority' => $this->searchPriority,
            'lazyLoadBodies' => $lazyLoadBodies,
            'fabricGroupBodyUrl' => Url::to(['/admin/catalog-model/fabric-group-body']),
        ]);
    }
}
