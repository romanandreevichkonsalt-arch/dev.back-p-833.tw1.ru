<?php

namespace app\modules\admin\models;

use app\models\CatalogCollection;
use app\models\CatalogColor;
use app\models\CatalogDirection;
use app\models\CatalogModel;
use app\models\CatalogSubcategory;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\Query;
use yii\helpers\ArrayHelper;

class CatalogModelSearch extends Model
{
    public ?string $direction_id = null;
    public ?string $collection_id = null;
    public ?string $subcategory_id = null;
    public ?string $color_id = null;

    public function rules(): array
    {
        return [
            [['direction_id', 'collection_id', 'subcategory_id', 'color_id'], 'integer'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'direction_id' => 'Направление',
            'collection_id' => 'Коллекция',
            'subcategory_id' => 'Подкатегория',
            'color_id' => 'Цвет',
        ];
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = CatalogModel::find()
            ->alias('m')
            ->with([
                'collection.direction',
                'category',
                'subcategory',
                'fabricCollections' => static function ($fabricQuery): void {
                    $fabricQuery->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
                },
                'fabricCollections.activeColors.catalogColor',
            ])
            ->orderBy(['m.sort_order' => SORT_ASC, 'm.id' => SORT_DESC]);

        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => false,
        ]);

        if (!$this->load($params) || !$this->validate()) {
            return $provider;
        }

        $this->normalizeFilterIds();

        $query->andFilterWhere(['m.collection_id' => $this->collection_id]);
        $query->andFilterWhere(['m.subcategory_id' => $this->subcategory_id]);

        if ($this->direction_id !== null && $this->direction_id !== '') {
            $query->andWhere([
                'exists',
                (new Query())
                    ->from(['cc' => '{{%catalog_collections}}'])
                    ->where('cc.id = m.collection_id')
                    ->andWhere(['cc.direction_id' => (int)$this->direction_id]),
            ]);
        }

        if ($this->color_id !== null && $this->color_id !== '') {
            $colorId = (int)$this->color_id;
            $query->andWhere([
                'exists',
                (new Query())
                    ->from(['mf' => '{{%catalog_model_fabric_collections}}'])
                    ->innerJoin(
                        ['fc' => '{{%catalog_fabric_collection_colors}}'],
                        'fc.fabric_collection_id = mf.fabric_collection_id'
                    )
                    ->where('mf.model_id = m.id')
                    ->andWhere(['fc.color_id' => $colorId, 'fc.is_active' => true]),
            ]);
        }

        return $provider;
    }

    private function normalizeFilterIds(): void
    {
        $directionId = ($this->direction_id !== null && $this->direction_id !== '')
            ? (int)$this->direction_id
            : null;

        if ($directionId !== null && !in_array($directionId, self::modelDirectionIds(), true)) {
            $this->direction_id = null;
            $directionId = null;
        }

        if ($this->collection_id !== null && $this->collection_id !== '') {
            $collectionId = (int)$this->collection_id;
            if (!in_array($collectionId, self::modelCollectionIds($directionId), true)) {
                $this->collection_id = null;
            }
        }

        if ($this->subcategory_id !== null && $this->subcategory_id !== '') {
            $subcategoryId = (int)$this->subcategory_id;
            if (!in_array($subcategoryId, self::modelSubcategoryIds(), true)) {
                $this->subcategory_id = null;
            }
        }

        if ($this->color_id !== null && $this->color_id !== '') {
            $colorId = (int)$this->color_id;
            if (!in_array($colorId, self::modelColorIds(), true)) {
                $this->color_id = null;
            }
        }
    }

    /**
     * @return list<int>
     */
    private static function modelCollectionIds(?int $directionId = null): array
    {
        $query = (new Query())
            ->select(['m.collection_id'])
            ->distinct()
            ->from(['m' => '{{%catalog_models}}'])
            ->innerJoin(['c' => '{{%catalog_collections}}'], 'c.id = m.collection_id')
            ->andWhere(['>', 'm.collection_id', 0]);

        if ($directionId !== null && $directionId > 0) {
            $query->andWhere(['c.direction_id' => $directionId]);
        }

        return array_map('intval', $query->column());
    }

    /**
     * @return list<int>
     */
    private static function modelSubcategoryIds(): array
    {
        return array_map(
            'intval',
            (new Query())
                ->select(['subcategory_id'])
                ->distinct()
                ->from('{{%catalog_models}}')
                ->andWhere(['>', 'subcategory_id', 0])
                ->column()
        );
    }

    /**
     * @return list<int>
     */
    private static function modelDirectionIds(): array
    {
        return array_map(
            'intval',
            (new Query())
                ->select(['c.direction_id'])
                ->distinct()
                ->from(['m' => '{{%catalog_models}}'])
                ->innerJoin(['c' => '{{%catalog_collections}}'], 'c.id = m.collection_id')
                ->andWhere(['>', 'c.direction_id', 0])
                ->column()
        );
    }

    /**
     * @return list<int>
     */
    private static function modelColorIds(): array
    {
        return array_map(
            'intval',
            (new Query())
                ->select(['fc.color_id'])
                ->distinct()
                ->from(['m' => '{{%catalog_models}}'])
                ->innerJoin(['mf' => '{{%catalog_model_fabric_collections}}'], 'mf.model_id = m.id')
                ->innerJoin(['fc' => '{{%catalog_fabric_collection_colors}}'], 'fc.fabric_collection_id = mf.fabric_collection_id')
                ->andWhere(['>', 'fc.color_id', 0])
                ->andWhere(['fc.is_active' => true])
                ->column()
        );
    }

    /**
     * @return array<int, string>
     */
    public static function directionOptions(): array
    {
        $ids = self::modelDirectionIds();
        if ($ids === []) {
            return [];
        }

        return ArrayHelper::map(
            CatalogDirection::find()
                ->where(['id' => $ids])
                ->orderBy(['sort_order' => SORT_ASC, 'label' => SORT_ASC, 'id' => SORT_ASC])
                ->all(),
            'id',
            'label'
        );
    }

    /**
     * @return array<int, string>
     */
    public static function collectionOptions(?int $directionId = null): array
    {
        $ids = self::modelCollectionIds($directionId);
        if ($ids === []) {
            return [];
        }

        $options = [];
        $collections = CatalogCollection::find()
            ->where(['id' => $ids])
            ->with('direction')
            ->orderBy(['sort_order' => SORT_ASC, 'name' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        foreach ($collections as $collection) {
            $direction = $collection->direction?->label ?? '—';
            $options[(int)$collection->id] = $collection->getDisplayName() . ' (' . $direction . ')';
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public static function subcategoryOptions(): array
    {
        $ids = self::modelSubcategoryIds();
        if ($ids === []) {
            return [];
        }

        return ArrayHelper::map(
            CatalogSubcategory::find()
                ->where(['id' => $ids])
                ->orderBy(['sort_order' => SORT_ASC, 'label' => SORT_ASC, 'id' => SORT_ASC])
                ->all(),
            'id',
            'label'
        );
    }

    /**
     * @return array<int, string>
     */
    public static function colorOptions(): array
    {
        $ids = self::modelColorIds();
        if ($ids === []) {
            return [];
        }

        return ArrayHelper::map(
            CatalogColor::find()
                ->where(['id' => $ids, 'is_active' => true])
                ->orderBy(['sort_order' => SORT_ASC, 'label' => SORT_ASC, 'id' => SORT_ASC])
                ->all(),
            'id',
            'label'
        );
    }
}
