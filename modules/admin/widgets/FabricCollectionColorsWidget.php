<?php

namespace app\modules\admin\widgets;

use app\models\CatalogColor;
use app\models\CatalogFabricCollection;
use app\models\CatalogFabricColor;
use app\models\CatalogProduct;
use app\modules\admin\assets\AdminAsset;
use yii\base\Widget;
use yii\helpers\Url;

class FabricCollectionColorsWidget extends Widget
{
    public ?CatalogFabricCollection $collection = null;

    public function run(): string
    {
        AdminAsset::register($this->view);

        $links = [];
        if ($this->collection !== null && !$this->collection->isNewRecord) {
            $links = CatalogFabricColor::find()
                ->where(['fabric_collection_id' => $this->collection->id])
                ->with(['catalogColor', 'swatchMedia'])
                ->orderBy(CatalogFabricColor::defaultSortOrder())
                ->all();
        }

        $productCountByColorId = [];
        if ($links !== []) {
            $colorIds = array_map(static fn (CatalogFabricColor $link): int => (int)$link->id, $links);
            $rows = CatalogProduct::find()
                ->select(['fabric_color_id', 'cnt' => 'COUNT(*)'])
                ->where(['fabric_color_id' => $colorIds])
                ->groupBy('fabric_color_id')
                ->asArray()
                ->all();
            foreach ($rows as $row) {
                $productCountByColorId[(int)$row['fabric_color_id']] = (int)$row['cnt'];
            }
        }

        return $this->render('fabric-collection-colors', [
            'collection' => $this->collection,
            'links' => $links,
            'productCountByColorId' => $productCountByColorId,
            'catalogColors' => CatalogColor::findActiveOrdered(),
            'settingsColorsUrl' => Url::to(['/admin/settings-color/index']),
        ]);
    }
}
