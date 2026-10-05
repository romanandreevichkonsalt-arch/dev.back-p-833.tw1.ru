<?php

namespace app\commands;

use app\services\catalog\FabricLibraryArchiveLauncher;
use yii\console\Controller;
use yii\console\ExitCode;

class FabricLibraryArchiveController extends Controller
{
    public function actionBuild(): int
    {
        $result = FabricLibraryArchiveLauncher::rebuildNow();

        $this->stdout($result['message'] . "\n");
        if (($result['publicUrl'] ?? null) !== null) {
            $this->stdout('URL: ' . $result['publicUrl'] . "\n");
        }
        $this->stdout(sprintf("Files in archive: %d\n", (int)($result['fileCount'] ?? 0)));

        return ($result['success'] ?? false) ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }
}
