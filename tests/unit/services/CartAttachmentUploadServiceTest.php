<?php

namespace tests\unit\services;

use app\exceptions\ApiValidationException;
use app\models\CartItem;
use app\models\Order;
use app\models\OrderItem;
use app\services\cart\CartAttachmentUploadService;
use Codeception\Test\Unit;
use Yii;

class CartAttachmentUploadServiceTest extends Unit
{
    private string $cartDir;
    private string $orderDir;

    protected function _before(): void
    {
        parent::_before();
        $this->cartDir = Yii::getAlias('@runtime/cart-attachment-test');
        $this->orderDir = Yii::getAlias('@runtime/order-attachment-test-cart');
        foreach ([$this->cartDir, $this->orderDir] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }
        Yii::$app->params['cartAttachmentStoragePath'] = $this->cartDir;
        Yii::$app->params['orderAttachmentStoragePath'] = $this->orderDir;
        Yii::$app->params['orderAttachmentMaxBytes'] = 50 * 1024 * 1024;
    }

    public function testTransferToOrderItemCopiesAndRemovesCartFile(): void
    {
        $cartItem = new CartItem([
            'user_id' => 1,
            'catalog_product_id' => 10,
            'quantity' => 1,
            'attachment_path' => 'cart_test.bin',
            'attachment_original_name' => 'eskiz.heic',
        ]);

        $source = $this->cartDir . DIRECTORY_SEPARATOR . 'cart_test.bin';
        file_put_contents($source, 'payload');

        $service = new CartAttachmentUploadService();
        $saved = $service->transferToOrderItem(
            $cartItem,
            $this->makeOrder(),
            $this->makeOrderItem(),
        );

        $this->assertSame('eskiz.heic', $saved['originalName']);
        $this->assertFileExists($this->orderDir . DIRECTORY_SEPARATOR . $saved['path']);
        $this->assertFileDoesNotExist($source);
    }

    public function testTransferFailsWhenCartFileMissing(): void
    {
        $cartItem = new CartItem([
            'attachment_path' => 'missing.bin',
            'attachment_original_name' => 'missing.bin',
        ]);

        $this->expectException(ApiValidationException::class);
        $this->expectExceptionMessage('Файл не найден');

        (new CartAttachmentUploadService())->transferToOrderItem(
            $cartItem,
            $this->makeOrder(),
            $this->makeOrderItem(),
        );
    }

    private function makeOrder(): Order
    {
        $order = new Order();
        $order->number = 'ORD-TEST-001';

        return $order;
    }

    private function makeOrderItem(): OrderItem
    {
        $item = new OrderItem();
        $item->id = 99;
        $item->product_sku = 'sku-test';

        return $item;
    }
}
