<?php

namespace app\modules\admin\controllers;

use app\models\SmsCode;
use app\services\RbacInitializer;
use yii\data\ActiveDataProvider;

class SmsCodeController extends AdminController
{
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['access'] = $this->permissionAccess(RbacInitializer::PERM_VIEW_SMS_CODES);

        return $behaviors;
    }

    public function actionIndex(): string
    {
        $dataProvider = new ActiveDataProvider([
            'query' => SmsCode::find()->orderBy(['id' => SORT_DESC]),
            'pagination' => ['pageSize' => 50],
        ]);

        return $this->render('index', ['dataProvider' => $dataProvider]);
    }
}
