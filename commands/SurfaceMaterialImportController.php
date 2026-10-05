<?php

namespace app\commands;

use app\services\import\surface\SurfaceMaterialImportRunService;
use yii\console\Controller;
use yii\console\ExitCode;

class SurfaceMaterialImportController extends Controller
{
    public function actionRun(int $id): int
    {
        $service = new SurfaceMaterialImportRunService();
        $service->process($id);

        return ExitCode::OK;
    }
}
