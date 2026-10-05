<?php

namespace app\services\catalog;

use app\services\import\ImportRunWorkerLauncher;
use Yii;

class FabricLibraryArchiveLauncher
{
    public static function dispatchRebuild(): void
    {
        ImportRunWorkerLauncher::dispatchAction(
            'fabric-library-archive/build',
            'fabric-library-archive'
        );
    }

    public static function rebuildNow(): array
    {
        return (new FabricLibraryArchiveBuilder())->build();
    }
}
