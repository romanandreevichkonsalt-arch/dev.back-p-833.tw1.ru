<?php

namespace app\models\search;

use app\models\Category;
use app\models\ProductCategory;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;

class CategorySearch extends Model
{
    public $q;
    public $parent_id;
    public $slug;
    public $is_active;
    public $has_products;
    public $seo_title;
    public $seo_h1;
    public $sort = 'sort_order';
    public $order = 'asc';
    public $page = 1;
    public $per_page = 20;

    public function rules(): array
    {
        return [
            [['q', 'slug', 'seo_title', 'seo_h1', 'sort', 'order'], 'string'],
            [['parent_id', 'page', 'per_page'], 'integer'],
            [['is_active', 'has_products'], 'filter', 'filter' => static function ($value) {
                if ($value === '' || $value === null) {
                    return null;
                }

                return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }],
            [['per_page'], 'integer', 'min' => 1, 'max' => 100],
            [['page'], 'integer', 'min' => 1],
            [['sort'], 'in', 'range' => ['id', 'name', 'slug', 'sort_order', 'created_at']],
            [['order'], 'in', 'range' => ['asc', 'desc']],
        ];
    }

    public function search(array $params): ActiveDataProvider
    {
        $this->load($params, '');

        $query = Category::find();

        if (!$this->validate()) {
            $query->where('0=1');

            return $this->buildProvider($query);
        }

        $this->applyFilters($query);

        return $this->buildProvider($query);
    }

    private function applyFilters(ActiveQuery $query): void
    {
        if ($this->q !== null && $this->q !== '') {
            $query->andWhere([
                'or',
                ['like', 'name', $this->q],
                ['like', 'description', $this->q],
                ['like', 'slug', $this->q],
            ]);
        }

        if ($this->parent_id !== null && $this->parent_id !== '') {
            if ((int) $this->parent_id === 0) {
                $query->andWhere(['parent_id' => null]);
            } else {
                $query->andWhere(['parent_id' => (int) $this->parent_id]);
            }
        }

        if ($this->slug !== null && $this->slug !== '') {
            $query->andWhere(['slug' => $this->slug]);
        }

        if ($this->is_active !== null) {
            $query->andWhere(['is_active' => (bool) $this->is_active]);
        }

        if ($this->seo_title !== null && $this->seo_title !== '') {
            $query->andWhere(['like', 'seo_title', $this->seo_title]);
        }

        if ($this->seo_h1 !== null && $this->seo_h1 !== '') {
            $query->andWhere(['like', 'seo_h1', $this->seo_h1]);
        }

        if ($this->has_products !== null) {
            $subQuery = ProductCategory::find()->select('category_id')->distinct();
            if ($this->has_products) {
                $query->andWhere(['id' => $subQuery]);
            } else {
                $query->andWhere(['not in', 'id', $subQuery]);
            }
        }

        $sortDirection = strtolower((string) $this->order) === 'desc' ? SORT_DESC : SORT_ASC;
        $query->orderBy([$this->sort => $sortDirection, 'id' => SORT_ASC]);
    }

    private function buildProvider(ActiveQuery $query): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => (int) ($this->per_page ?: 20),
                'page' => max(0, (int) ($this->page ?: 1) - 1),
            ],
        ]);
    }
}
