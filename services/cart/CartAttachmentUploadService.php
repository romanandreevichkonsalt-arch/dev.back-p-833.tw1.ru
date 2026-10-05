<?php

namespace app\services\cart;

use app\exceptions\ApiValidationException;
use app\models\CartItem;
use app\models\Order;
use app\models\OrderItem;
use Yii;
use yii\web\UploadedFile;

class CartAttachmentUploadService
{
    private const DEFAULT_MAX_BYTES = 50 * 1024 * 1024;

    /**
     * @return array{path: string, originalName: string}
     */
    public function saveForCartItem(CartItem $item, string $productSlug, UploadedFile $file): array
    {
        $this->validateFile($file, 'attachment');

        $ownerKey = $item->user_id !== null
            ? 'u' . (int)$item->user_id
            : 's' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$item->session_id);
        $prefix = 'cart_' . $ownerKey . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $productSlug) . '_' . bin2hex(random_bytes(8));

        return $this->persistFile($prefix, $file);
    }

    /**
     * @return array{path: string, originalName: string}
     */
    public function transferToOrderItem(CartItem $cartItem, Order $order, OrderItem $orderItem): array
    {
        $storedPath = trim((string)$cartItem->attachment_path);
        if ($storedPath === '') {
            throw new ApiValidationException('Файл не найден.');
        }

        $sourcePath = $this->resolveAbsolutePath($storedPath);
        if (!is_file($sourcePath)) {
            throw new ApiValidationException('Файл не найден.');
        }

        $originalName = trim((string)$cartItem->attachment_original_name);
        if ($originalName === '') {
            $originalName = basename($storedPath);
        }

        $extension = strtolower(preg_replace('/[^a-z0-9]+/', '', pathinfo($originalName, PATHINFO_EXTENSION)) ?? '');
        if ($extension === '' || strlen($extension) > 16) {
            $extension = 'bin';
        }

        $orderStoragePath = Yii::getAlias(Yii::$app->params['orderAttachmentStoragePath'] ?? '@runtime/order-attachments');
        if (!is_dir($orderStoragePath) && !mkdir($orderStoragePath, 0775, true) && !is_dir($orderStoragePath)) {
            throw new ApiValidationException('Не удалось сохранить файл.');
        }

        $safeName = $order->number . '_item' . (int)$orderItem->id . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $targetPath = $orderStoragePath . DIRECTORY_SEPARATOR . $safeName;

        if (!copy($sourcePath, $targetPath)) {
            throw new ApiValidationException('Не удалось сохранить файл.');
        }

        $this->deleteStoredFile($storedPath);

        return [
            'path' => $safeName,
            'originalName' => $originalName,
        ];
    }

    public function resolveAbsolutePath(string $storedPath): string
    {
        $storagePath = Yii::getAlias(Yii::$app->params['cartAttachmentStoragePath'] ?? '@runtime/cart-attachments');
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($storedPath));
        if ($normalized === '' || str_contains($normalized, '..')) {
            throw new ApiValidationException('Некорректный путь к файлу.');
        }

        return $storagePath . DIRECTORY_SEPARATOR . $normalized;
    }

    public function deleteStoredFile(?string $storedPath): void
    {
        if ($storedPath === null || trim($storedPath) === '') {
            return;
        }

        try {
            $path = $this->resolveAbsolutePath($storedPath);
        } catch (ApiValidationException) {
            return;
        }

        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function buildDownloadUrl(string $productSlug): string
    {
        return '/api/v1/cart/items/' . rawurlencode($productSlug) . '/attachment';
    }

    public function getMaxBytes(): int
    {
        return (int)(Yii::$app->params['orderAttachmentMaxBytes'] ?? self::DEFAULT_MAX_BYTES);
    }

    private function validateFile(UploadedFile $file, string $fieldKey): void
    {
        if ($file->hasError) {
            throw new ApiValidationException('Не удалось загрузить файл.', [
                $fieldKey => ['Ошибка загрузки файла.'],
            ]);
        }

        $maxBytes = $this->getMaxBytes();
        if ($file->size <= 0) {
            throw new ApiValidationException('Файл пустой.', [
                $fieldKey => ['Выберите непустой файл.'],
            ]);
        }

        if ($file->size > $maxBytes) {
            $maxMb = max(1, (int)round($maxBytes / 1024 / 1024));
            throw new ApiValidationException('Файл слишком большой.', [
                $fieldKey => ['Максимальный размер файла — ' . $maxMb . ' МБ.'],
            ]);
        }
    }

    /**
     * @return array{path: string, originalName: string}
     */
    private function persistFile(string $namePrefix, UploadedFile $file): array
    {
        $extension = $this->resolveSafeExtension($file);
        $storagePath = Yii::getAlias(Yii::$app->params['cartAttachmentStoragePath'] ?? '@runtime/cart-attachments');
        if (!is_dir($storagePath) && !mkdir($storagePath, 0775, true) && !is_dir($storagePath)) {
            throw new ApiValidationException('Не удалось сохранить файл.');
        }

        $safeName = $namePrefix . '.' . $extension;
        $targetPath = $storagePath . DIRECTORY_SEPARATOR . $safeName;

        if (!$file->saveAs($targetPath)) {
            throw new ApiValidationException('Не удалось сохранить файл.');
        }

        return [
            'path' => $safeName,
            'originalName' => $file->name,
        ];
    }

    private function resolveSafeExtension(UploadedFile $file): string
    {
        $extension = strtolower(preg_replace('/[^a-z0-9]+/', '', (string)$file->extension) ?? '');

        if ($extension === '' || strlen($extension) > 16) {
            return 'bin';
        }

        return $extension;
    }
}
