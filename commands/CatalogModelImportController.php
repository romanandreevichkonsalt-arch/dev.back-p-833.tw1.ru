<?php

namespace app\commands;

use app\services\import\catalog\CatalogModelImportRunService;
use yii\console\Controller;
use yii\console\ExitCode;

class CatalogModelImportController extends Controller
{
    public function actionRun(int $id): int
    {
        $service = new CatalogModelImportRunService();
        $service->process($id);

        return ExitCode::OK;
    }
}
