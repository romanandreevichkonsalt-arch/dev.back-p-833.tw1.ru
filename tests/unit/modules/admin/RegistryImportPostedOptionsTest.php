<?php

namespace tests\unit\modules\admin;

use app\modules\admin\helpers\RegistryImportPostedOptions;
use app\services\import\catalog\CatalogModelImportOptions;
use Codeception\Test\Unit;

class RegistryImportPostedOptionsTest extends Unit
{
    public function testParsesMediaModes(): void
    {
        $updateNoMedia = RegistryImportPostedOptions::parseConflictResolution(
            RegistryImportPostedOptions::MODE_UPDATE_NO_MEDIA,
            CatalogModelImportOptions::CONFLICT_SKIP,
            CatalogModelImportOptions::CONFLICT_UPDATE
        );
        verify($updateNoMedia['conflictResolution'])->equals(CatalogModelImportOptions::CONFLICT_UPDATE);
        verify($updateNoMedia['importMedia'])->false();

        $skip = RegistryImportPostedOptions::parseConflictResolution(
            RegistryImportPostedOptions::MODE_SKIP,
            CatalogModelImportOptions::CONFLICT_SKIP,
            CatalogModelImportOptions::CONFLICT_UPDATE
        );
        verify($skip['importMedia'])->true();
        verify($skip['conflictResolution'])->equals(CatalogModelImportOptions::CONFLICT_SKIP);

        $legacySkipNoMedia = RegistryImportPostedOptions::parseConflictResolution(
            'skip_no_media',
            CatalogModelImportOptions::CONFLICT_SKIP,
            CatalogModelImportOptions::CONFLICT_UPDATE
        );
        verify($legacySkipNoMedia['importMedia'])->true();
        verify($legacySkipNoMedia['conflictResolution'])->equals(CatalogModelImportOptions::CONFLICT_SKIP);
    }
}
