<?php

namespace app\commands;

use app\services\media\MediaVariantRegenerator;
use yii\console\Controller;
use yii\console\ExitCode;

class MediaController extends Controller
{
    public bool $dryRun = false;

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        if ($actionID === 'regenerate-variants') {
            $options[] = 'dryRun';
        }

        return $options;
    }

    public function optionAliases(): array
    {
        return [
            'd' => 'dryRun',
        ];
    }

    public function actionRegenerateVariants(): int
    {
        $service = new MediaVariantRegenerator();
        $result = $service->regenerateMissing($this->dryRun);

        $this->stdout(sprintf(
            "Regenerate finished: processed=%d regenerated=%d skipped=%d failed=%d%s\n",
            $result['processed'],
            $result['regenerated'],
            $result['skipped'],
            $result['failed'],
            $this->dryRun ? ' (dry-run)' : ''
        ));

        foreach ($result['messages'] as $message) {
            $this->stdout('  ' . $message . "\n");
        }

        return ($result['failed'] ?? 0) > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }
}
