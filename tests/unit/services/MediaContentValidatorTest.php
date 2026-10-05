<?php

namespace tests\unit\services;

use app\services\media\MediaContentValidator;
use Codeception\Test\Unit;

class MediaContentValidatorTest extends Unit
{
    public function testFilenameFromContentDispositionParsesQuotedAndUtf8(): void
    {
        verify(MediaContentValidator::filenameFromContentDisposition('attachment; filename="Sofa.max"'))
            ->equals('Sofa.max');
        verify(MediaContentValidator::filenameFromContentDisposition("attachment; filename*=UTF-8''Model%2Emax"))
            ->equals('Model.max');
    }

    public function testGuessStorageBasenameUsesDownloadedExtensionFor3d(): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'media_validator_');
        $this->assertNotFalse($tempPath);
        file_put_contents($tempPath, str_repeat('x', 64));

        try {
            verify(MediaContentValidator::guessStorageBasename($tempPath, 'model-42-3d', 'Armchair.max'))
                ->equals('model-42-3d.max');
        } finally {
            @unlink($tempPath);
        }
    }
}
