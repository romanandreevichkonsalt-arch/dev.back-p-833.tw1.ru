<?php

namespace app\modules\admin\actions;

use app\modules\admin\helpers\AdminErrorReport;
use yii\web\ErrorAction;

class AdminErrorAction extends ErrorAction
{
    protected function getViewRenderParams(): array
    {
        $params = parent::getViewRenderParams();
        /** @var \Throwable $exception */
        $exception = $params['exception'];

        $params['statusCode'] = AdminErrorReport::statusCode($exception);
        $params['userMessage'] = AdminErrorReport::userMessage($exception);
        $params['developerReport'] = AdminErrorReport::developerText($exception);

        return $params;
    }
}
