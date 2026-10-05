<?php

namespace tests\unit\services;

use app\models\CatalogProduct;
use app\models\Order;
use app\models\OrderItem;
use app\services\order\OrderItemApiEnricher;
use Codeception\Test\Unit;
use Yii;

class OrderItemApiEnricherTest extends Unit
{
    private const ORDER_NUMBER = 'ORD-UNIT-ENRICH-001';

    protected function _before(): void
    {
        parent::_before();
        Yii::$app->db->open();

        $order = Order::findOne(['number' => self::ORDER_NUMBER]);
        if ($order !== null) {
            OrderItem::deleteAll(['order_id' => (int)$order->id]);
            $order->delete();
        }

        CatalogProduct::deleteAll(['like', 'slug', 'order-enrich-test-%', false]);
    }

    public function testEnrichAddsDownloadUrlAndHref(): void
    {
        $slug = 'order-enrich-test-' . substr(md5(uniqid('', true)), 0, 8);
        $product = new CatalogProduct([
            'slug' => $slug,
            'title' => 'Товар enrich',
            'href' => '/product/' . $slug,
            'price_display' => '1 000 ₽',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $product->save(false);

        $order = new Order([
            'number' => self::ORDER_NUMBER,
            'customer_name' => 'Тест',
            'customer_phone' => '+79991112233',
            'status' => Order::STATUS_NEW,
            'total_amount' => 1000,
            'subtotal_amount' => 1000,
        ]);
        $order->save(false);

        $item = new OrderItem([
            'order_id' => (int)$order->id,
            'product_title' => $product->title,
            'product_sku' => $slug,
            'quantity' => 1,
            'unit_price' => 1000,
            'line_total' => 1000,
            'attachment_path' => 'test.bin',
            'attachment_original_name' => 'spec.pdf',
        ]);
        $item->save(false);

        $enricher = new OrderItemApiEnricher();
        $productsBySlug = $enricher->loadProductsForItems([$item]);
        $payload = $enricher->enrich($item, $productsBySlug, self::ORDER_NUMBER);

        $this->assertSame(
            '/api/v1/orders/' . rawurlencode(self::ORDER_NUMBER) . '/items/' . rawurlencode($slug) . '/attachment',
            $payload['attachment']['downloadUrl'],
        );
        $this->assertStringContainsString($slug, (string)$payload['href']);
        $this->assertArrayHasKey('image', $payload);
    }

    public function testBuildSummaryImagesReturnsMiniUrlsAndExtraCount(): void
    {
        $slugs = [];
        for ($i = 0; $i < 5; $i++) {
            $slug = 'order-enrich-test-' . substr(md5((string)$i . uniqid('', true)), 0, 8);
            $slugs[] = $slug;
            $product = new CatalogProduct([
                'slug' => $slug,
                'title' => 'Товар ' . $i,
                'href' => '/product/' . $slug,
                'price_display' => '1 000 ₽',
                'is_active' => true,
                'sort_order' => 0,
            ]);
            $product->save(false);
        }

        $order = new Order([
            'number' => self::ORDER_NUMBER,
            'customer_name' => 'Тест',
            'customer_phone' => '+79991112233',
            'status' => Order::STATUS_NEW,
            'total_amount' => 5000,
            'subtotal_amount' => 5000,
        ]);
        $order->save(false);

        $items = [];
        foreach ($slugs as $slug) {
            $item = new OrderItem([
                'order_id' => (int)$order->id,
                'product_title' => 'Товар',
                'product_sku' => $slug,
                'quantity' => 1,
                'unit_price' => 1000,
                'line_total' => 1000,
            ]);
            $item->save(false);
            $items[] = $item;
        }

        $enricher = new OrderItemApiEnricher();
        $productsBySlug = $enricher->loadProductsForItems($items);
        $payload = $enricher->buildSummaryImages($items, $productsBySlug);

        $this->assertCount(3, $payload['images']);
        $this->assertSame(2, $payload['extraCount']);
        foreach ($payload['images'] as $url) {
            $this->assertIsString($url);
        }
    }
}
