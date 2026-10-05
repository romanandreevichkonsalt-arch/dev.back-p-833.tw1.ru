<?php

namespace app\commands;

use app\services\catalog\CatalogProductListingService;
use app\services\catalog\CatalogService;
use app\services\search\SearchService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Прогрев FileCache API (каталог, поиск) после деплоя или импорта.
 */
class CatalogCacheController extends Controller
{
    /** @var string Запрос для прогрева search vocabulary / match */
    public string $searchQuery = 'диван';

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['searchQuery']);
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), ['q' => 'searchQuery']);
    }

    public function actionWarm(): int
    {
        $catalog = \Yii::$container->get(CatalogService::class);

        $this->stdout("→ catalog/menu\n");
        $catalog->getMenu();

        $this->stdout("→ catalog/searchable-products\n");
        $catalog->getSearchableProducts();

        $listing = \Yii::$container->get(CatalogProductListingService::class);
        $this->stdout("→ catalog/products (guest, p1, scope=all)\n");
        $listing->getProducts([
            'page' => 1,
            'perPage' => 24,
            'sort' => 'default',
        ], null);

        $search = new SearchService();
        $this->stdout("→ search/bootstrap\n");
        $search->getBootstrap();

        $query = trim($this->searchQuery);
        if ($query !== '') {
            $this->stdout("→ search autocomplete (q={$query})\n");
            $search->search($query, 5);
            $this->stdout("→ search/products p1\n");
            $search->searchProducts($query, 1, 24, 'default');
        }

        $this->stdout("Готово.\n");

        return ExitCode::OK;
    }
}
