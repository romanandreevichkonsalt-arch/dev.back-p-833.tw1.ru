<?php

namespace tests\unit\services\lead;

use app\exceptions\ApiValidationException;
use app\models\Lead;
use app\services\lead\LeadAttachmentUploadService;
use Codeception\Test\Unit;
use yii\web\UploadedFile;

class LeadAttachmentUploadServiceTest extends Unit
{
    public function testRejectsUnsupportedExtension(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'lead');
        file_put_contents($tmp, 'test');
        $file = new UploadedFile([
            'name' => 'virus.exe',
            'tempName' => $tmp,
            'type' => 'application/octet-stream',
            'size' => 4,
            'error' => UPLOAD_ERR_OK,
        ]);

        $service = new LeadAttachmentUploadService();
        $this->expectException(ApiValidationException::class);
        $service->saveForLead(new Lead(), $file);
        @unlink($tmp);
    }
}
