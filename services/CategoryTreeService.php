<?php

namespace app\services;

use app\models\Category;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;

class CategoryTreeService
{
    /**
     * @return Category[]
     */
    public function buildTree(?int $rootId = null, ?int $depth = null): array
    {
        $categories = Category::find()
            ->orderBy(['sort_order' => SORT_ASC, 'name' => SORT_ASC])
            ->all();

        $childrenMap = [];
        foreach ($categories as $category) {
            $parentKey = $category->parent_id ?? 0;
            $childrenMap[$parentKey][] = $category;
        }

        $attachChildren = function (array $nodes, int $currentDepth) use (&$attachChildren, $childrenMap, $depth): array {
            if ($depth !== null && $currentDepth > $depth) {
                foreach ($nodes as $node) {
                    $node->populateRelation('children', []);
                }

                return $nodes;
            }

            foreach ($nodes as $node) {
                $childNodes = $childrenMap[(int) $node->id] ?? [];
                $node->populateRelation('children', $attachChildren($childNodes, $currentDepth + 1));
            }

            return $nodes;
        };

        if ($rootId !== null) {
            $root = Category::findOne($rootId);
            if ($root === null) {
                return [];
            }

            $childNodes = $childrenMap[$rootId] ?? [];
            $root->populateRelation('children', $attachChildren($childNodes, 1));

            return [$root];
        }

        $roots = $childrenMap[0] ?? [];

        return $attachChildren($roots, 1);
    }

    public function childrenProvider(int $categoryId): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => $this->childrenQuery($categoryId),
            'pagination' => false,
        ]);
    }

    public function childrenQuery(int $categoryId): ActiveQuery
    {
        return Category::find()
            ->where(['parent_id' => $categoryId])
            ->orderBy(['sort_order' => SORT_ASC, 'name' => SORT_ASC]);
    }
}
