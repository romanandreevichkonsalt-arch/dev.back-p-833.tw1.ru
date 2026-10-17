<?php

namespace app\commands;

use app\services\import\fabric\FabricImportRunService;
use yii\console\Controller;
use yii\console\ExitCode;

class FabricImportController extends Controller
{
    public int $limit = 40;
    public int $concurrency = 8;

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        if ($actionID === 'process-media-queue') {
            $options[] = 'limit';
            $options[] = 'concurrency';
        }

        return $options;
    }

    public function actionRun(int $id): int
    {
        $service = new FabricImportRunService();
        $service->process($id);

        return ExitCode::OK;
    }

    public function actionProcessMediaQueue(int $id): int
    {
        $service = new FabricImportRunService();
        $service->processMediaQueue($id, $this->limit, $this->concurrency);

        return ExitCode::OK;
    }

    public function actionProcessVariants(int $id): int
    {
        $service = new FabricImportRunService();
        $service->processVariants($id);

        return ExitCode::OK;
    }
}
