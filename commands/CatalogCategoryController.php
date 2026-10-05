<?php

namespace app\commands;

use app\services\catalog\CatalogCategoryDuplicateMergeService;
use yii\console\Controller;
use yii\console\ExitCode;

class CatalogCategoryController extends Controller
{
    public bool $dryRun = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['dryRun']);
    }

    public function optionAliases(): array
    {
        return [
            'd' => 'dryRun',
        ];
    }

    public function actionMergeDuplicates(): int
    {
        $service = new CatalogCategoryDuplicateMergeService();

        try {
            $service->merge($this->dryRun);
        } catch (\Throwable $exception) {
            $this->stderr('Ошибка: ' . $exception->getMessage() . "\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }

        foreach ($service->getLog() as $line) {
            $this->stdout($line . "\n");
        }

        if ($this->dryRun) {
            $this->stdout("Запустите без --dryRun для применения изменений.\n");
        } else {
            $this->stdout("Готово.\n");
        }

        return ExitCode::OK;
    }
}
