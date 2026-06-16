<?php

namespace app\models\search;

use app\models\AttributeDefinition;
use app\models\Product;
use app\models\ProductAttribute;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;

class ProductSearch extends Model
{
    public $q;
    public $slug;
    public $sku;
    public $is_active;
    public $in_stock;
    public $price_from;
    public $price_to;
    public $category_id;
    public $category_ids;
    public $category_slug;
    public $seo_title;
    public $seo_h1;
    /** @var array<string,mixed> */
    public $attrs = [];
    public $sort = 'created_at';
    public $order = 'desc';
    public $page = 1;
    public $per_page = 20;

    public function rules(): array
    {
        return [
            [['q', 'slug', 'sku', 'category_slug', 'seo_title', 'seo_h1', 'sort', 'order'], 'string'],
            [['category_id', 'page', 'per_page'], 'integer'],
            [['price_from', 'price_to'], 'number'],
            [['category_ids', 'attrs'], 'safe'],
            [['is_active', 'in_stock'], 'filter', 'filter' => static function ($value) {
                if ($value === '' || $value === null) {
                    return null;
                }

                return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }],
            [['per_page'], 'integer', 'min' => 1, 'max' => 100],
            [['page'], 'integer', 'min' => 1],
            [['sort'], 'in', 'range' => ['id', 'name', 'price', 'created_at', 'stock_qty']],
            [['order'], 'in', 'range' => ['asc', 'desc']],
        ];
    }

    public function search(array $params): ActiveDataProvider
    {
        $this->load($params, '');

        if (isset($params['category_ids']) && !is_array($params['category_ids'])) {
            $this->category_ids = array_filter(array_map('intval', explode(',', (string) $params['category_ids'])));
        }

        if (isset($params['attrs']) && is_array($params['attrs'])) {
            $this->attrs = $params['attrs'];
        }

        $query = Product::find()->distinct();

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
                ['like', 'sku', $this->q],
                ['like', 'slug', $this->q],
            ]);
        }

        if ($this->slug !== null && $this->slug !== '') {
            $query->andWhere(['slug' => $this->slug]);
        }

        if ($this->sku !== null && $this->sku !== '') {
            $query->andWhere(['sku' => $this->sku]);
        }

        if ($this->is_active !== null) {
            $query->andWhere(['is_active' => (bool) $this->is_active]);
        }

        if ($this->in_stock === true) {
            $query->andWhere(['>', 'stock_qty', 0]);
        } elseif ($this->in_stock === false) {
            $query->andWhere(['<=', 'stock_qty', 0]);
        }

        if ($this->price_from !== null && $this->price_from !== '') {
            $query->andWhere(['>=', 'price', (float) $this->price_from]);
        }

        if ($this->price_to !== null && $this->price_to !== '') {
            $query->andWhere(['<=', 'price', (float) $this->price_to]);
        }

        if ($this->seo_title !== null && $this->seo_title !== '') {
            $query->andWhere(['like', 'seo_title', $this->seo_title]);
        }

        if ($this->seo_h1 !== null && $this->seo_h1 !== '') {
            $query->andWhere(['like', 'seo_h1', $this->seo_h1]);
        }

        if ($this->category_id !== null && $this->category_id !== '') {
            $query->innerJoin('{{%product_categories}} pc_filter', 'pc_filter.product_id = {{%products}}.id')
                ->andWhere(['pc_filter.category_id' => (int) $this->category_id]);
        }

        if (!empty($this->category_ids)) {
            $query->innerJoin('{{%product_categories}} pc_ids', 'pc_ids.product_id = {{%products}}.id')
                ->andWhere(['pc_ids.category_id' => array_map('intval', (array) $this->category_ids)]);
        }

        if ($this->category_slug !== null && $this->category_slug !== '') {
            $query->innerJoin('{{%product_categories}} pc_slug', 'pc_slug.product_id = {{%products}}.id')
                ->innerJoin('{{%categories}} c_slug', 'c_slug.id = pc_slug.category_id')
                ->andWhere(['c_slug.slug' => $this->category_slug]);
        }

        $this->applyAttributeFilters($query);

        $sortDirection = strtolower((string) $this->order) === 'desc' ? SORT_DESC : SORT_ASC;
        $query->orderBy([$this->sort => $sortDirection, 'id' => SORT_ASC]);
    }

    private function applyAttributeFilters(ActiveQuery $query): void
    {
        if (empty($this->attrs)) {
            return;
        }

        $definitions = AttributeDefinition::mapByCode();
        $index = 0;

        foreach ($this->attrs as $code => $value) {
            if (!is_string($code) || $code === '') {
                continue;
            }

            if (substr($code, -5) === '_from' || substr($code, -3) === '_to') {
                continue;
            }

            $definition = $definitions[$code] ?? null;
            $alias = 'pa_' . $index++;
            $query->innerJoin(
                '{{%product_attributes}} ' . $alias,
                $alias . '.product_id = {{%products}}.id AND ' . $alias . '.attribute_code = :code_' . $alias,
                [':code_' . $alias => $code]
            );

            if ($definition === null) {
                $query->andWhere([
                    'or',
                    [$alias . '.value_string' => (string) $value],
                    [$alias . '.value_number' => is_numeric($value) ? (float) $value : null],
                    [$alias . '.value_bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)],
                ]);
                continue;
            }

            if ($definition->type === AttributeDefinition::TYPE_NUMBER) {
                $from = $this->attrs[$code . '_from'] ?? null;
                $to = $this->attrs[$code . '_to'] ?? null;

                if ($from !== null && $from !== '') {
                    $query->andWhere(['>=', $alias . '.value_number', (float) $from]);
                }
                if ($to !== null && $to !== '') {
                    $query->andWhere(['<=', $alias . '.value_number', (float) $to]);
                }
                if (($from === null || $from === '') && ($to === null || $to === '') && $value !== null && $value !== '') {
                    $query->andWhere([$alias . '.value_number' => (float) $value]);
                }
                continue;
            }

            if ($definition->type === AttributeDefinition::TYPE_BOOL) {
                $query->andWhere([
                    $alias . '.value_bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                ]);
                continue;
            }

            $query->andWhere([$alias . '.value_string' => (string) $value]);
        }
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
