<?php

namespace app\modules\admin\controllers;

use app\services\cache\ApiCacheInvalidator;
use yii\db\ActiveRecord;

abstract class SettingsBaseController extends BaseController
{
    protected function trySaveModel(ActiveRecord $model): bool
    {
        if (!$model->load(\Yii::$app->request->post())) {
            return false;
        }

        if ($model->save()) {
            ApiCacheInvalidator::touch();
            return true;
        }

        \Yii::$app->session->setFlash('error', 'Не удалось сохранить. Исправьте ошибки в форме.');

        return false;
    }

    public function init(): void
    {
        parent::init();
        $this->setViewPath('@app/modules/admin/views/settings');
    }

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('settings');

        return true;
    }
}
