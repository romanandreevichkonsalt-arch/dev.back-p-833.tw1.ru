<?php

namespace app\modules\admin\controllers;

use app\models\Product;
use app\models\ProductImage;
use app\models\search\ProductSearch;
use app\modules\admin\models\ProductForm;
use app\services\ProductImageService;
use app\services\RbacInitializer;
use Yii;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class ProductController extends AdminController
{
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['access'] = $this->permissionAccess(RbacInitializer::PERM_MANAGE_PRODUCTS);

        return $behaviors;
    }

    public function actionIndex(): string
    {
        $searchModel = new ProductSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->query->with(['categories', 'mainImage']);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionView(int $id): string
    {
        $model = $this->findModel($id);

        return $this->render('view', ['model' => $model]);
    }

    public function actionCreate(): Response|string
    {
        $form = new ProductForm();

        if ($form->load(Yii::$app->request->post())) {
            $product = $form->save();
            if ($product !== null) {
                $this->uploadImages($product);
                $this->flashSuccess('Товар создан.');

                return $this->redirect(['view', 'id' => $product->id]);
            }
        }

        return $this->render('create', [
            'form' => $form,
            'product' => null,
        ]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $product = $this->findModel($id);
        $form = ProductForm::fromProduct($product);

        if ($form->load(Yii::$app->request->post())) {
            $saved = $form->save();
            if ($saved !== null) {
                $this->uploadImages($saved);
                $this->flashSuccess('Товар обновлён.');

                return $this->redirect(['view', 'id' => $saved->id]);
            }
        }

        return $this->render('update', [
            'form' => $form,
            'product' => $product,
        ]);
    }

    public function actionDelete(int $id): Response
    {
        $this->findModel($id)->delete();
        $this->flashSuccess('Товар удалён.');

        return $this->redirect(['index']);
    }

    public function actionDeleteImage(int $id): Response
    {
        $image = ProductImage::findOne($id);
        if ($image !== null) {
            /** @var ProductImageService $service */
            $service = Yii::createObject(ProductImageService::class);
            $productId = (int) $image->product_id;
            $service->deleteImage($image);
            $this->flashSuccess('Изображение удалено.');

            return $this->redirect(['update', 'id' => $productId]);
        }

        return $this->redirect(['index']);
    }

    public function actionSetMainImage(int $id): Response
    {
        $image = ProductImage::findOne($id);
        if ($image !== null) {
            /** @var ProductImageService $service */
            $service = Yii::createObject(ProductImageService::class);
            $service->setMain($image);
            $this->flashSuccess('Главное изображение обновлено.');

            return $this->redirect(['update', 'id' => $image->product_id]);
        }

        return $this->redirect(['index']);
    }

    private function uploadImages(Product $product): void
    {
        $files = UploadedFile::getInstancesByName('images');
        if ($files === []) {
            return;
        }

        /** @var ProductImageService $service */
        $service = Yii::createObject(ProductImageService::class);
        $service->uploadForProduct($product, $files);
    }

    protected function findModel(int $id): Product
    {
        $model = Product::find()->with(['categories', 'productAttributes.definition', 'images'])->where(['id' => $id])->one();
        if ($model === null) {
            throw new NotFoundHttpException('Товар не найден.');
        }

        return $model;
    }
}
