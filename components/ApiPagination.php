<?php

namespace app\components;

use yii\data\ActiveDataProvider;
use yii\data\Pagination;

class ApiPagination
{
    /**
     * @param callable $mapper
     * @return array{items: array<int, mixed>, meta: array<string, int>}
     */
    public static function format(ActiveDataProvider $provider, callable $mapper): array
    {
        /** @var Pagination $pagination */
        $pagination = $provider->getPagination();
        $models = $provider->getModels();

        return [
            'items' => array_map($mapper, $models),
            'meta' => [
                'total' => (int) $provider->getTotalCount(),
                'page' => $pagination->getPage() + 1,
                'per_page' => $pagination->getPageSize(),
                'page_count' => $pagination->getPageCount(),
            ],
        ];
    }
}
