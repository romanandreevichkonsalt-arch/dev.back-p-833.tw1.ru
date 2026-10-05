<?php

namespace app\services\content;

use Yii;
use yii\web\NotFoundHttpException;

class JsonContentService
{
    public function getPage(string $name): array
    {
        return $this->loadJson("pages/{$name}.json");
    }

    public function getCatalogMenu(): array
    {
        return $this->loadJson('catalog/menu.json');
    }

    public function getCatalogProducts(string $subcategory): array
    {
        $path = Yii::getAlias("@app/data/content/catalog/products/{$subcategory}.json");
        if (!is_file($path)) {
            return ['items' => []];
        }

        return $this->decodeJson($path);
    }

    public function getSearchBootstrap(): array
    {
        return $this->loadJson('search/bootstrap.json');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSearchableProducts(): array
    {
        $data = $this->loadJson('search/products-index.json');

        return $data['items'] ?? [];
    }

    private function loadJson(string $relativePath): array
    {
        $path = Yii::getAlias('@app/data/content/' . $relativePath);
        if (!is_file($path)) {
            throw new NotFoundHttpException('Контент не найден.');
        }

        return $this->decodeJson($path);
    }

    private function decodeJson(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new NotFoundHttpException('Не удалось прочитать контент.');
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new NotFoundHttpException('Некорректный формат контента.');
        }

        return $data;
    }
}
