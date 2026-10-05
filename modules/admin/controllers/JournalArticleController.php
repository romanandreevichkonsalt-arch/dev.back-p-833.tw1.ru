<?php

namespace app\modules\admin\controllers;

use app\models\ContentPage;
use app\models\JournalArticle;
use app\modules\admin\assets\JournalArticleAsset;
use app\services\journal\JournalArticleBlockBuilder;
use app\services\journal\JournalArticleRecommendedService;
use app\services\cache\ApiCacheInvalidator;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class JournalArticleController extends BaseController
{
    private JournalArticleRecommendedService $recommendedProducts;

    public function init(): void
    {
        parent::init();
        $this->recommendedProducts = \Yii::$container->get(JournalArticleRecommendedService::class);
    }

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('pages');

        return true;
    }

    public function actionIndex(): Response
    {
        $pageId = ContentPage::find()->select('id')->where(['slug' => 'journal'])->scalar();

        if ($pageId !== false && $pageId !== null) {
            return $this->redirect(['/admin/content-page/blocks', 'id' => (int)$pageId]);
        }

        return $this->redirect(['/admin/content-page/index']);
    }

    public function actionCreate(): Response|string
    {
        $model = new JournalArticle([
            'is_active' => true,
            'sort_order' => 0,
            'category_id' => JournalArticle::CATEGORY_PROCESS,
        ]);

        $category = trim((string)Yii::$app->request->get('category', ''));
        if ($category !== '' && array_key_exists($category, JournalArticle::categoryLabels())) {
            $model->category_id = $category;
        }

        if ($this->loadAndSave($model)) {
            Yii::$app->session->setFlash('success', 'Статья создана.');

            return $this->redirect(['update', 'id' => $model->id]);
        }

        JournalArticleAsset::register($this->view);

        return $this->render('form', [
            'model' => $model,
            'blocksForm' => $this->blocksFormForView($model),
            'recommendedForm' => $this->recommendedFormForView($model),
            'title' => 'Новая статья',
        ]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);

        if ($this->loadAndSave($model)) {
            Yii::$app->session->setFlash('success', 'Статья сохранена.');

            return $this->redirect(['update', 'id' => $model->id]);
        }

        JournalArticleAsset::register($this->view);

        return $this->render('form', [
            'model' => $model,
            'blocksForm' => $this->blocksFormForView($model),
            'recommendedForm' => $this->recommendedFormForView($model),
            'title' => 'Редактирование статьи',
        ]);
    }

    private function loadAndSave(JournalArticle $model): bool
    {
        if (!Yii::$app->request->isPost) {
            return false;
        }

        $post = Yii::$app->request->post();
        if (!$model->load($post)) {
            return false;
        }

        $model->setBlocksArray(JournalArticleBlockBuilder::blocksFromPost($post));

        if (!$model->validate()) {
            return false;
        }

        if ($model->save(false)) {
            $this->recommendedProducts->syncForArticle(
                (int)$model->id,
                $this->recommendedProducts->parseProductIdsFromPost($post)
            );
            ApiCacheInvalidator::touch();

            return true;
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function blocksFormForView(JournalArticle $model): array
    {
        if (Yii::$app->request->isPost) {
            return JournalArticleBlockBuilder::blocksToForm(
                JournalArticleBlockBuilder::blocksFromPost(Yii::$app->request->post())
            );
        }

        if ($model->isNewRecord) {
            return JournalArticleBlockBuilder::blocksToForm([]);
        }

        return JournalArticleBlockBuilder::blocksToForm($model->getBlocksArray());
    }

    /**
     * @return list<array{catalog_product_id: int|string, product_search: string}>
     */
    private function recommendedFormForView(JournalArticle $model): array
    {
        if (Yii::$app->request->isPost) {
            $rows = Yii::$app->request->post('recommended_products', []);
            if (!is_array($rows)) {
                return [];
            }

            $formRows = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $productId = (int)($row['catalog_product_id'] ?? 0);
                if ($productId <= 0) {
                    continue;
                }
                $formRows[] = [
                    'catalog_product_id' => $productId,
                    'product_search' => trim((string)($row['product_search'] ?? '')),
                ];
            }

            return $formRows;
        }

        return $this->recommendedProducts->buildAdminRows($model);
    }

    private function findModel(int $id): JournalArticle
    {
        $model = JournalArticle::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Статья не найдена.');
        }

        return $model;
    }
}
