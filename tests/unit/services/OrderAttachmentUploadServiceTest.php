<?php

namespace tests\unit\services;

use app\exceptions\ApiValidationException;
use app\services\order\OrderAttachmentUploadService;
use Codeception\Test\Unit;
use Yii;
use yii\web\UploadedFile;

class OrderAttachmentUploadServiceTest extends Unit
{
    protected function _before(): void
    {
        parent::_before();
        Yii::$app->params['orderAttachmentMaxBytes'] = 50 * 1024 * 1024;
    }

    public function testRejectsFileLargerThanLimit(): void
    {
        $file = $this->makeUploadedFile('big.zip', 50 * 1024 * 1024 + 1);

        $this->expectException(ApiValidationException::class);
        $this->expectExceptionMessage('Файл слишком большой');

        (new OrderAttachmentUploadService())->saveForOrderItem(
            $this->makeOrder(),
            $this->makeOrderItem(),
            $file,
        );
    }

    public function testAcceptsAnyExtensionWithinLimit(): void
    {
        $service = new OrderAttachmentUploadService();
        $file = $this->makeSavableUploadedFile('eskiz.heic', 1024);
        $tempDir = Yii::getAlias('@runtime/order-attachment-test');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }
        Yii::$app->params['orderAttachmentStoragePath'] = $tempDir;

        $saved = $service->saveForOrderItem($this->makeOrder(), $this->makeOrderItem(), $file);

        $this->assertSame('eskiz.heic', $saved['originalName']);
        $this->assertFileExists($tempDir . DIRECTORY_SEPARATOR . $saved['path']);
        $this->assertStringEndsWith('.heic', $saved['path']);

        @unlink($tempDir . DIRECTORY_SEPARATOR . $saved['path']);
    }

    private function makeSavableUploadedFile(string $name, int $size): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upload-test-');
        file_put_contents($path, str_repeat('a', $size));

        return new class([
            'name' => $name,
            'tempName' => $path,
            'type' => 'application/octet-stream',
            'size' => $size,
            'error' => UPLOAD_ERR_OK,
        ]) extends UploadedFile {
            public function saveAs($file, $deleteTempFile = true): bool
            {
                return copy($this->tempName, Yii::getAlias($file));
            }
        };
    }

    private function makeUploadedFile(string $name, int $size): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upload-test-');
        file_put_contents($path, str_repeat('a', $size));

        return new UploadedFile([
            'name' => $name,
            'tempName' => $path,
            'type' => 'application/octet-stream',
            'size' => $size,
            'error' => UPLOAD_ERR_OK,
        ]);
    }

    private function makeOrder(): \app\models\Order
    {
        return new \app\models\Order([
            'id' => 1,
            'number' => 'ORD-TEST-001',
        ]);
    }

    private function makeOrderItem(): \app\models\OrderItem
    {
        return new \app\models\OrderItem([
            'id' => 10,
            'product_sku' => 'test-product',
        ]);
    }
}
