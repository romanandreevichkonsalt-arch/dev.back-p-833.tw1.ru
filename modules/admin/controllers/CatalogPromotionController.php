<?php

namespace app\modules\admin\controllers;

use app\exceptions\ApiValidationException;
use app\models\CatalogModel;
use app\models\CatalogPromotion;
use app\modules\admin\helpers\HomePageProductsHelper;
use app\modules\admin\models\CatalogPromotionForm;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class CatalogPromotionController extends BaseController
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('users');

        return true;
    }

    public function actionCreate(): Response|string
    {
        $form = new CatalogPromotionForm();
        $form->starts_at = date('Y-m-d\T00:00');
        $form->ends_at = date('Y-m-d\T23:59', strtotime('+1 month'));

        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            $promotion = new CatalogPromotion();
            $form->applyTo($promotion);
            if ($promotion->save()) {
                Yii::$app->session->setFlash('success', 'Акция создана.');
                return $this->redirect(['/admin/promo-code/index', 'tab' => 'sales']);
            }
            Yii::$app->session->setFlash('error', 'Не удалось сохранить акцию.');
        }

        return $this->render('form', [
            'model' => $form,
            'promotion' => null,
            'models' => $this->modelOptions(),
        ]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $promotion = $this->findPromotion($id);
        $form = CatalogPromotionForm::fromPromotion($promotion);

        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            $form->applyTo($promotion);
            if ($promotion->save()) {
                Yii::$app->session->setFlash('success', 'Акция сохранена.');
                return $this->redirect(['/admin/promo-code/index', 'tab' => 'sales']);
            }
            Yii::$app->session->setFlash('error', 'Не удалось сохранить акцию.');
        }

        return $this->render('form', [
            'model' => $form,
            'promotion' => $promotion,
            'models' => $this->modelOptions(),
        ]);
    }

    public function actionSearchProducts(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $request = Yii::$app->request;
        $id = (int)$request->get('id', 0);
        if ($id > 0) {
            $item = HomePageProductsHelper::pickerItemByProductId($id);

            return $item ?? [];
        }

        $modelId = (int)$request->get('model_id', 0);
        if ($modelId <= 0) {
            return [];
        }

        return HomePageProductsHelper::searchProducts(
            (string)$request->get('q', ''),
            catalogOnly: true,
            modelId: $modelId,
        );
    }

    public function actionDelete(int $id): Response
    {
        $promotion = $this->findPromotion($id);
        $title = $promotion->title;
        $promotion->delete();
        Yii::$app->session->setFlash('success', 'Акция «' . $title . '» удалена.');

        return $this->redirect(['/admin/promo-code/index', 'tab' => 'sales']);
    }

    private function findPromotion(int $id): CatalogPromotion
    {
        $promotion = CatalogPromotion::findOne($id);
        if ($promotion === null) {
            throw new NotFoundHttpException('Акция не найдена.');
        }

        return $promotion;
    }

    /**
     * @return array<int, string>
     */
    private function modelOptions(): array
    {
        $models = CatalogModel::find()->orderBy(['title' => SORT_ASC])->all();
        $options = [];
        foreach ($models as $model) {
            $options[(int)$model->id] = $model->title;
        }

        return $options;
    }

}
