<?php

namespace app\services\catalog;

use app\models\CatalogCategory;
use app\models\CatalogCollection;
use app\models\CatalogDirection;
use app\models\CatalogSubcategory;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

final class CatalogScope
{
    public ?CatalogDirection $direction = null;
    public ?CatalogCollection $collection = null;
    public ?CatalogCategory $category = null;
    public ?CatalogSubcategory $subcategory = null;

    /** @var 'shortcut'|'chain'|'all' */
    public string $scopeMode = 'shortcut';

    /** @var array<string, string> */
    public array $requestedSlugs = [];

    public function __construct(
        private readonly CatalogUrlSlugResolver $slugResolver = new CatalogUrlSlugResolver(),
    ) {
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function fromParams(array $params, bool $requireScope = true): self
    {
        $resolver = new CatalogUrlSlugResolver();
        $scope = new self($resolver);
        $normalized = $resolver->normalizeRequestParams($params);
        $provided = self::collectProvidedSlugs($normalized);

        if ($provided === []) {
            if ($requireScope) {
                throw new BadRequestHttpException('Укажите хотя бы один scope-параметр: direction, collection, modelLine, category или subcategory.');
            }
            $scope->scopeMode = 'all';

            return $scope;
        }

        $scope->scopeMode = count($provided) >= 2 ? 'chain' : 'shortcut';
        $scope->requestedSlugs = $provided;

        $directionSlug = $provided['direction'] ?? '';
        $modelLineSlug = $provided['modelLine'] ?? '';
        $categorySlug = $provided['category'] ?? '';
        $subcategorySlug = $provided['subcategory'] ?? '';

        if ($directionSlug !== '') {
            $scope->direction = $resolver->findDirectionByCollectionParam($directionSlug);
            if ($scope->direction === null) {
                throw new NotFoundHttpException('Направление не найдено.');
            }
        }

        if ($modelLineSlug !== '') {
            $scope->collection = $resolver->findModelLineBySlug($modelLineSlug);
            if ($scope->collection === null) {
                throw new NotFoundHttpException('Линейка моделей не найдена.');
            }
            if ($scope->direction !== null && (int)$scope->collection->direction_id !== (int)$scope->direction->id) {
                throw new BadRequestHttpException('Линейка моделей не принадлежит указанному направлению.');
            }
            if ($scope->direction === null) {
                $scope->direction = $scope->collection->direction;
            }
        }

        if ($categorySlug !== '') {
            $scope->category = $resolver->findCategoryBySlug($categorySlug);
            if ($scope->category === null) {
                throw new NotFoundHttpException('Категория не найдена.');
            }
        }

        if ($subcategorySlug !== '') {
            $categoryId = $scope->category !== null ? (int)$scope->category->id : null;
            $matches = CatalogSubcategory::find()
                ->where(['is_active' => true])
                ->andWhere(['or', ['slug' => $subcategorySlug], ['url_slug' => $subcategorySlug]])
                ->with('category');

            if ($categoryId !== null) {
                $matches->andWhere(['category_id' => $categoryId]);
            }

            $subcategoryRows = $matches->all();
            if ($subcategoryRows === []) {
                throw new NotFoundHttpException('Подкатегория не найдена.');
            }
            if ($scope->category === null && count($subcategoryRows) > 1) {
                throw new BadRequestHttpException('Slug подкатегории неоднозначен. Укажите category.');
            }

            $scope->subcategory = $subcategoryRows[0];
            if ($scope->category === null) {
                $scope->category = $scope->subcategory->category;
            } elseif ((int)$scope->subcategory->category_id !== (int)$scope->category->id) {
                throw new BadRequestHttpException('Подкатегория не принадлежит указанной категории.');
            }
        }

        return $scope;
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array<string, string>
     */
    private static function collectProvidedSlugs(array $normalized): array
    {
        return array_filter([
            'direction' => trim((string)($normalized['direction'] ?? '')),
            'modelLine' => trim((string)($normalized['modelLine'] ?? '')),
            'category' => trim((string)($normalized['category'] ?? '')),
            'subcategory' => trim((string)($normalized['subcategory'] ?? '')),
        ], static fn (string $value): bool => $value !== '');
    }

    /**
     * @return array<string, string|null>
     */
    public function appliedSlugs(): array
    {
        $direction = $this->direction !== null
            ? $this->slugResolver->getDirectionPublicSlug($this->direction)
            : null;
        $modelLine = $this->collection !== null ? (string)$this->collection->slug : null;
        $category = $this->category !== null
            ? $this->slugResolver->getCategoryPublicSlug($this->category)
            : null;
        $subcategory = $this->subcategory !== null
            ? $this->slugResolver->getSubcategoryPublicSlug($this->subcategory)
            : null;

        return [
            'direction' => $direction,
            'collection' => $direction,
            'modelLine' => $modelLine,
            'category' => $category,
            'subcategory' => $subcategory,
        ];
    }
}
