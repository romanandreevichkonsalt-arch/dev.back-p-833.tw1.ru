<?php

namespace app\commands;

use app\services\import\fabric\FabricImportRunService;
use yii\console\Controller;
use yii\console\ExitCode;

class FabricImportController extends Controller
{
    public function actionRun(int $id): int
    {
        $service = new FabricImportRunService();
        $service->process($id);

        return ExitCode::OK;
    }
}
