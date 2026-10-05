<?php

namespace app\commands;

use app\services\docs\OpenApiGeneratorService;
use yii\console\Controller;

class OpenApiController extends Controller
{
    public function actionGenerate(): int
    {
        $service = new OpenApiGeneratorService();
        $force = (bool)$this->force;
        if (!$force && $service->isRuntimeFresh()) {
            $this->stdout('OpenAPI schema is up to date: ' . $service->getOutputPath() . "\n");

            return self::EXIT_CODE_NORMAL;
        }

        $path = $service->writeToRuntime($force);
        $this->stdout("OpenAPI schema saved to {$path}\n");

        return self::EXIT_CODE_NORMAL;
    }

    /** @var bool Generate even when runtime/openapi.json is newer than sources. */
    public $force = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['force']);
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), ['f' => 'force']);
    }
}
