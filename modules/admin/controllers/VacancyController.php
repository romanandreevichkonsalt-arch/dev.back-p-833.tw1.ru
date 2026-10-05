<?php

namespace app\modules\admin\controllers;

use app\models\Vacancy;
use app\models\VacancyDirection;
use app\services\cache\ApiCacheInvalidator;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class VacancyController extends BaseController
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('pages');

        return true;
    }

    public function actionIndex(): string
    {
        $vacancies = Vacancy::find()
            ->with(['direction'])
            ->orderBy(['direction_id' => SORT_ASC, 'sort_order' => SORT_ASC, 'id' => SORT_DESC])
            ->all();

        return $this->render('index', [
            'vacancies' => $vacancies,
        ]);
    }

    public function actionCreate(): Response|string
    {
        $defaultDirectionId = (int)VacancyDirection::find()->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])->select('id')->scalar();
        $requestedDirectionId = (int)Yii::$app->request->get('direction_id', 0);
        if ($requestedDirectionId > 0 && VacancyDirection::find()->where(['id' => $requestedDirectionId])->exists()) {
            $defaultDirectionId = $requestedDirectionId;
        }

        $model = new Vacancy([
            'show_on_about' => false,
            'sort_order' => 0,
            'direction_id' => $defaultDirectionId > 0 ? $defaultDirectionId : null,
            'schedule' => Vacancy::SCHEDULE_FULL_TIME,
            'posted_at' => date('Y-m-d'),
        ]);

        if ($this->loadAndSave($model)) {
            Yii::$app->session->setFlash('success', 'Вакансия создана.');

            return $this->redirect(['update', 'id' => $model->id]);
        }

        return $this->render('form', [
            'model' => $model,
            'title' => 'Новая вакансия',
        ]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);

        if ($this->loadAndSave($model)) {
            Yii::$app->session->setFlash('success', 'Вакансия сохранена.');

            return $this->redirect(['update', 'id' => $model->id]);
        }

        return $this->render('form', [
            'model' => $model,
            'title' => 'Редактирование вакансии',
        ]);
    }

    public function actionDelete(int $id): Response
    {
        $this->findModel($id)->delete();
        ApiCacheInvalidator::touch();
        Yii::$app->session->setFlash('success', 'Вакансия удалена.');

        return $this->redirect(['index']);
    }

    private function loadAndSave(Vacancy $model): bool
    {
        if (!Yii::$app->request->isPost || !$model->load(Yii::$app->request->post())) {
            return false;
        }

        $requirements = array_values(array_filter(array_map(
            'trim',
            (array)Yii::$app->request->post('requirements', [])
        ), static fn (string $value): bool => $value !== ''));
        $conditions = array_values(array_filter(array_map(
            'trim',
            (array)Yii::$app->request->post('conditions', [])
        ), static fn (string $value): bool => $value !== ''));

        $model->setRequirementsArray($requirements);
        $model->setConditionsArray($conditions);

        if ($model->isNewRecord) {
            if ($model->posted_at === null || trim((string)$model->posted_at) === '') {
                $model->posted_at = date('Y-m-d');
            }
            $model->show_on_about = false;
        }

        if (!$model->save()) {
            return false;
        }

        ApiCacheInvalidator::touch();

        return true;
    }

    private function findModel(int $id): Vacancy
    {
        $model = Vacancy::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Вакансия не найдена.');
        }

        return $model;
    }
}
