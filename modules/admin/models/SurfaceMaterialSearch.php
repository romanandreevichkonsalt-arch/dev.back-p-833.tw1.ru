<?php

namespace app\modules\admin\models;

use app\models\CatalogSurfaceMaterial;
use yii\base\Model;
use yii\data\ActiveDataProvider;

class SurfaceMaterialSearch extends Model
{
    public ?string $q = null;
    public ?string $material_type = null;

    public function rules(): array
    {
        return [
            [['q', 'material_type'], 'string'],
        ];
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = CatalogSurfaceMaterial::find()
            ->with(['photoMedia', 'catalogCollections'])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);

        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => false,
        ]);

        if (!$this->load($params) || !$this->validate()) {
            return $provider;
        }

        if ($this->material_type !== null && trim($this->material_type) !== '') {
            $query->andWhere(['material_type' => $this->material_type]);
        }

        if ($this->q !== null && trim($this->q) !== '') {
            $query->andWhere([
                'or',
                ['like', 'name', $this->q],
                ['like', 'applied_models_text', $this->q],
                ['like', 'description', $this->q],
            ]);
        }

        return $provider;
    }
}
