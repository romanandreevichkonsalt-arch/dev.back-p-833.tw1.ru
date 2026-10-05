<?php

namespace app\modules\admin\models;

use app\models\CatalogFabricCollection;
use yii\base\Model;
use yii\data\ActiveDataProvider;

class FabricCollectionSearch extends Model
{
    public ?string $q = null;

    public function rules(): array
    {
        return [
            [['q'], 'string'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'q' => 'Поиск',
        ];
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = CatalogFabricCollection::find()
            ->with(['colors' => static function ($colorQuery) {
                $colorQuery->andWhere(['is_active' => true])
                    ->with(['catalogColor'])
                    ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
            }])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);

        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => false,
        ]);

        if (!$this->load($params) || !$this->validate()) {
            return $provider;
        }

        if ($this->q !== null && trim($this->q) !== '') {
            $query->andWhere([
                'or',
                ['like', 'name', $this->q],
                ['like', 'texture', $this->q],
            ]);
        }

        return $provider;
    }
}
