<?php

namespace app\modules\admin\controllers;

use app\models\SearchCategory;
use app\models\SearchFrequentQuery;
use app\modules\admin\helpers\HomePageProductsHelper;
use app\services\cache\ApiCacheInvalidator;
use app\services\search\SearchCatalogPriorityService;
use app\services\search\SearchRecommendedService;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class SearchController extends BaseController
{
    private SearchRecommendedService $recommendedService;
    private SearchCatalogPriorityService $catalogPriorityService;

    public function init(): void
    {
        parent::init();
        $this->recommendedService = new SearchRecommendedService();
        $this->catalogPriorityService = new SearchCatalogPriorityService();
    }

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('search');

        return true;
    }

    public function actionIndex(string $tab = 'queries'): string
    {
        if (!in_array($tab, ['queries', 'recommended', 'catalog'], true)) {
            $tab = 'queries';
        }

        $directionId = self::nullableInt(\Yii::$app->request->get('direction_id'));

        return $this->render('index', [
            'tab' => $tab,
            'queries' => SearchFrequentQuery::find()->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])->all(),
            'categories' => SearchCategory::find()->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])->all(),
            'recommendedFormData' => $this->recommendedService->buildAdminFormData(),
            'catalogPriorityFormData' => $this->catalogPriorityService->buildAdminFormData($directionId),
        ]);
    }

    public function actionCreateQuery(): Response|string
    {
        $model = new SearchFrequentQuery(['is_active' => true, 'sort_order' => 0]);

        if ($model->load(\Yii::$app->request->post()) && $model->save()) {
            \Yii::$app->session->setFlash('success', 'Запрос добавлен.');
            ApiCacheInvalidator::touch();

            return $this->redirect(['index']);
        }

        return $this->render('query-form', ['model' => $model, 'title' => 'Новый частый запрос']);
    }

    public function actionUpdateQuery(int $id): Response|string
    {
        $model = $this->findQuery($id);

        if ($model->load(\Yii::$app->request->post()) && $model->save()) {
            \Yii::$app->session->setFlash('success', 'Запрос обновлён.');
            ApiCacheInvalidator::touch();

            return $this->redirect(['index']);
        }

        return $this->render('query-form', ['model' => $model, 'title' => 'Редактирование запроса']);
    }

    public function actionDeleteQuery(int $id): Response
    {
        $this->findQuery($id)->delete();
        \Yii::$app->session->setFlash('success', 'Запрос удалён.');
        ApiCacheInvalidator::touch();

        return $this->redirect(['index']);
    }

    public function actionCreateCategory(): Response|string
    {
        $model = new SearchCategory(['is_active' => true, 'sort_order' => 0]);

        if ($model->load(\Yii::$app->request->post()) && $model->save()) {
            \Yii::$app->session->setFlash('success', 'Категория добавлена.');
            ApiCacheInvalidator::touch();

            return $this->redirect(['index']);
        }

        return $this->render('category-form', ['model' => $model, 'title' => 'Новая категория поиска']);
    }

    public function actionUpdateCategory(int $id): Response|string
    {
        $model = $this->findCategory($id);

        if ($model->load(\Yii::$app->request->post()) && $model->save()) {
            \Yii::$app->session->setFlash('success', 'Категория обновлена.');
            ApiCacheInvalidator::touch();

            return $this->redirect(['index']);
        }

        return $this->render('category-form', ['model' => $model, 'title' => 'Редактирование категории']);
    }

    public function actionDeleteCategory(int $id): Response
    {
        $this->findCategory($id)->delete();
        \Yii::$app->session->setFlash('success', 'Категория удалена.');
        ApiCacheInvalidator::touch();

        return $this->redirect(['index']);
    }

    public function actionSaveCatalogPriority(): Response
    {
        $request = \Yii::$app->request;
        if (!$request->isPost) {
            throw new BadRequestHttpException('Метод не поддерживается.');
        }

        $directionId = self::nullableInt($request->post('active_direction_id'));
        $errors = $this->catalogPriorityService->saveFromPost($request->post());
        if ($errors !== []) {
            \Yii::$app->session->setFlash('error', implode("\n", $errors));

            return $this->redirect(['index', 'tab' => 'catalog', 'direction_id' => $directionId]);
        }

        \Yii::$app->session->setFlash('success', 'Приоритет поиска сохранён.');
        ApiCacheInvalidator::touch();

        return $this->redirect(['index', 'tab' => 'catalog', 'direction_id' => $directionId]);
    }

    public function actionSaveRecommended(): Response
    {
        $request = \Yii::$app->request;
        if (!$request->isPost) {
            throw new BadRequestHttpException('Метод не поддерживается.');
        }

        $errors = $this->recommendedService->saveFromPost($request->post());
        if ($errors !== []) {
            \Yii::$app->session->setFlash('error', implode("\n", $errors));

            return $this->redirect(['index', 'tab' => 'recommended']);
        }

        \Yii::$app->session->setFlash('success', 'Рекомендации сохранены.');
        ApiCacheInvalidator::touch();

        return $this->redirect(['index', 'tab' => 'recommended']);
    }

    public function actionSearchProducts(): array
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;

        $request = \Yii::$app->request;
        $id = (int)$request->get('id', 0);
        if ($id > 0) {
            $item = HomePageProductsHelper::pickerItemByProductId($id);

            return $item ?? [];
        }

        return HomePageProductsHelper::searchProducts(
            (string)$request->get('q', ''),
            self::nullableInt($request->get('direction_id')),
            self::nullableInt($request->get('category_id')),
            self::nullableInt($request->get('subcategory_id')),
            true,
        );
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int)$value;

        return $int > 0 ? $int : null;
    }

    private function findQuery(int $id): SearchFrequentQuery
    {
        $model = SearchFrequentQuery::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Запрос не найден.');
        }

        return $model;
    }

    private function findCategory(int $id): SearchCategory
    {
        $model = SearchCategory::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Категория не найдена.');
        }

        return $model;
    }
}
