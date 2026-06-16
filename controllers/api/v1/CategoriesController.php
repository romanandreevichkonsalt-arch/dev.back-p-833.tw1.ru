<?php

namespace app\controllers\api\v1;

use app\components\ApiPagination;
use app\models\Category;
use app\models\search\CategorySearch;
use app\services\CategoryTreeService;
use OpenApi\Annotations as OA;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class CategoriesController extends ApiController
{
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = [
            'index',
            'view',
            'view-by-slug',
            'tree',
            'children',
            'options',
        ];

        return $behaviors;
    }

    public function verbs(): array
    {
        return [
            'index' => ['GET', 'OPTIONS'],
            'view' => ['GET', 'OPTIONS'],
            'view-by-slug' => ['GET', 'OPTIONS'],
            'tree' => ['GET', 'OPTIONS'],
            'children' => ['GET', 'OPTIONS'],
            'create' => ['POST', 'OPTIONS'],
            'update' => ['PUT', 'PATCH', 'OPTIONS'],
            'delete' => ['DELETE', 'OPTIONS'],
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/v1/categories",
     *     tags={"Категории"},
     *     summary="Список категорий с фильтрацией",
     *     @OA\Parameter(name="q", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="parent_id", in="query", description="0 для корневых", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="slug", in="query", description="Фильтр по slug (частичное совпадение)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="has_products", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="seo_title", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="seo_h1", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string", enum={"id","name","slug","sort_order","created_at"})),
     *     @OA\Parameter(name="order", in="query", @OA\Schema(type="string", enum={"asc","desc"})),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Response(response=200, description="Список категорий", @OA\JsonContent(ref="#/components/schemas/CategoryListResponse"))
     * )
     */
    public function actionIndex(): array
    {
        $search = new CategorySearch();
        $provider = $search->search(Yii::$app->request->queryParams);

        return ApiPagination::format($provider, static fn (Category $category): array => $category->toApiArray());
    }

    /**
     * @OA\Get(
     *     path="/api/v1/categories/tree",
     *     tags={"Категории"},
     *     summary="Дерево категорий",
     *     @OA\Parameter(name="root_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="root_slug", in="query", description="Корневая категория по slug", @OA\Schema(type="string")),
     *     @OA\Parameter(name="depth", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Дерево категорий", @OA\JsonContent(ref="#/components/schemas/CategoryTreeResponse"))
     * )
     */
    public function actionTree(): array
    {
        $rootId = Yii::$app->request->get('root_id');
        $rootSlug = Yii::$app->request->get('root_slug');
        $depth = Yii::$app->request->get('depth');

        if (($rootId === null || $rootId === '') && $rootSlug !== null && $rootSlug !== '') {
            $rootCategory = Category::findBySlug((string) $rootSlug);
            if ($rootCategory === null) {
                throw new NotFoundHttpException('Категория не найдена.');
            }
            $rootId = $rootCategory->id;
        }

        /** @var CategoryTreeService $service */
        $service = Yii::createObject(CategoryTreeService::class);
        $roots = $service->buildTree(
            $rootId !== null && $rootId !== '' ? (int) $rootId : null,
            $depth !== null && $depth !== '' ? (int) $depth : null
        );

        return [
            'items' => array_map(static fn (Category $category): array => $category->toTreeNode(), $roots),
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/v1/categories/slug/{slug}",
     *     tags={"Категории"},
     *     summary="Карточка категории по slug",
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string", example="divany-i-kresla")),
     *     @OA\Response(response=200, description="Категория", @OA\JsonContent(ref="#/components/schemas/Category")),
     *     @OA\Response(response=404, description="Не найдена")
     * )
     */
    public function actionViewBySlug(string $slug): array
    {
        return $this->findCategoryBySlug($slug)->toApiArray();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/categories/{id}",
     *     tags={"Категории"},
     *     summary="Карточка категории",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Категория", @OA\JsonContent(ref="#/components/schemas/Category")),
     *     @OA\Response(response=404, description="Не найдена")
     * )
     */
    public function actionView(int $id): array
    {
        return $this->findCategory($id)->toApiArray();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/categories/{id}/children",
     *     tags={"Категории"},
     *     summary="Подкатегории",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Подкатегории", @OA\JsonContent(ref="#/components/schemas/CategoryListResponse")),
     *     @OA\Response(response=404, description="Не найдена")
     * )
     */
    public function actionChildren(int $id): array
    {
        $this->findCategory($id);

        /** @var CategoryTreeService $service */
        $service = Yii::createObject(CategoryTreeService::class);
        $provider = $service->childrenProvider($id);

        return ApiPagination::format($provider, static fn (Category $category): array => $category->toApiArray());
    }

    /**
     * @OA\Post(
     *     path="/api/v1/categories",
     *     tags={"Категории"},
     *     summary="Создание категории",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CategoryCreateRequest")),
     *     @OA\Response(response=201, description="Создана", @OA\JsonContent(ref="#/components/schemas/Category")),
     *     @OA\Response(response=400, description="Ошибка валидации"),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionCreate(): array
    {
        $category = new Category();
        $category->load($this->getJsonBody(), '');

        if (!$category->save()) {
            throw new BadRequestHttpException($this->formatErrors($category->getErrors()));
        }

        Yii::$app->response->statusCode = 201;

        return $category->toApiArray();
    }

    /**
     * @OA\Put(
     *     path="/api/v1/categories/{id}",
     *     tags={"Категории"},
     *     summary="Обновление категории",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CategoryUpdateRequest")),
     *     @OA\Response(response=200, description="Обновлена", @OA\JsonContent(ref="#/components/schemas/Category")),
     *     @OA\Response(response=400, description="Ошибка валидации"),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=404, description="Не найдена")
     * )
     *
     * @OA\Patch(
     *     path="/api/v1/categories/{id}",
     *     tags={"Категории"},
     *     summary="Частичное обновление категории",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CategoryUpdateRequest")),
     *     @OA\Response(response=200, description="Обновлена", @OA\JsonContent(ref="#/components/schemas/Category"))
     * )
     */
    public function actionUpdate(int $id): array
    {
        $category = $this->findCategory($id);
        $category->load($this->getJsonBody(), '');

        if (!$category->save()) {
            throw new BadRequestHttpException($this->formatErrors($category->getErrors()));
        }

        return $category->toApiArray();
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/categories/{id}",
     *     tags={"Категории"},
     *     summary="Удаление категории",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Удалена"),
     *     @OA\Response(response=400, description="Есть зависимости"),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=404, description="Не найдена")
     * )
     */
    public function actionDelete(int $id): void
    {
        $category = $this->findCategory($id);

        if ($category->hasChildren()) {
            throw new BadRequestHttpException('Нельзя удалить категорию с подкатегориями.');
        }

        if ($category->hasProducts()) {
            throw new BadRequestHttpException('Нельзя удалить категорию, к которой привязаны товары.');
        }

        $category->delete();
        Yii::$app->response->statusCode = 204;
    }

    private function findCategory(int $id): Category
    {
        $category = Category::findOne($id);
        if ($category === null) {
            throw new NotFoundHttpException('Категория не найдена.');
        }

        return $category;
    }

    private function findCategoryBySlug(string $slug): Category
    {
        $category = Category::findBySlug($slug);
        if ($category === null) {
            throw new NotFoundHttpException('Категория не найдена.');
        }

        return $category;
    }

    /**
     * @return array<string, mixed>
     */
    private function getJsonBody(): array
    {
        return Yii::$app->request->getBodyParams();
    }

    /**
     * @param array<string, array<int, string>> $errors
     */
    private function formatErrors(array $errors): string
    {
        $messages = [];
        foreach ($errors as $fieldErrors) {
            foreach ($fieldErrors as $message) {
                $messages[] = $message;
            }
        }

        return $messages !== [] ? implode(' ', $messages) : 'Ошибка валидации.';
    }
}
