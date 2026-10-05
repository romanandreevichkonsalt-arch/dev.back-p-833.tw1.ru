<?php

namespace app\modules\admin\widgets;

use app\models\CatalogPriceCategory;
use app\modules\admin\assets\AdminAsset;
use yii\base\Widget;
use yii\helpers\Json;
use yii\helpers\Url;
use Yii;

class ModelPricesWidget extends Widget
{
    /** @var CatalogPriceCategory[] */
    public array $visiblePriceCategories = [];

    /** @var CatalogPriceCategory[] */
    public array $allPriceCategories = [];

    /** @var array<int, string> */
    public array $priceMap = [];

    public ?int $modelId = null;

    public function run(): string
    {
        AdminAsset::register($this->view);
        $this->view->registerJsFile('@web/js/admin-model-prices.js', ['depends' => [AdminAsset::class]]);

        $allCategories = [];
        foreach ($this->allPriceCategories as $category) {
            $allCategories[] = [
                'id' => (int)$category->id,
                'label' => $category->getDisplayLabel(),
                'number' => (int)$category->number,
            ];
        }

        $maxVisibleNumber = 0;
        foreach ($this->visiblePriceCategories as $category) {
            $maxVisibleNumber = max($maxVisibleNumber, (int)$category->number);
        }

        return $this->render('model-prices', [
            'visiblePriceCategories' => $this->visiblePriceCategories,
            'allPriceCategories' => $this->allPriceCategories,
            'priceMap' => $this->priceMap,
            'allCategoriesJson' => Json::encode($allCategories),
            'createUrl' => Url::to(['/admin/catalog-model/price-category-create']),
            'nextCategoryLabel' => 'Категория ' . ($maxVisibleNumber + 1),
            'modelId' => $this->modelId,
            'csrfParam' => Yii::$app->request->csrfParam,
            'csrfToken' => Yii::$app->request->csrfToken,
        ]);
    }
}
