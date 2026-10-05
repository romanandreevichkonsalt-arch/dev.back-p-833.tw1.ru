<?php

namespace app\services\order;

use app\exceptions\ApiValidationException;
use app\models\Order;
use app\models\OrderItem;
use Yii;
use yii\web\UploadedFile;

class OrderAttachmentUploadService
{
    private const DEFAULT_MAX_BYTES = 50 * 1024 * 1024;

    /**
     * @return array{path: string, originalName: string}
     */
    public function saveForOrder(Order $order, UploadedFile $file): array
    {
        $this->validateFile($file, 'attachment');

        return $this->persistFile($order->number . '_' . bin2hex(random_bytes(8)), $file);
    }

    /**
     * @return array{path: string, originalName: string}
     */
    public function saveForOrderItem(Order $order, OrderItem $item, UploadedFile $file): array
    {
        $this->validateFile($file, 'itemAttachments[' . ($item->product_sku ?: 'item') . ']');

        $prefix = $order->number . '_item' . (int)$item->id . '_' . bin2hex(random_bytes(8));

        return $this->persistFile($prefix, $file);
    }

    public function resolveAbsolutePath(string $storedPath): string
    {
        $storagePath = Yii::getAlias(Yii::$app->params['orderAttachmentStoragePath'] ?? '@runtime/order-attachments');
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($storedPath));
        if ($normalized === '' || str_contains($normalized, '..')) {
            throw new ApiValidationException('Некорректный путь к файлу.');
        }

        return $storagePath . DIRECTORY_SEPARATOR . $normalized;
    }

    public function deleteStoredFile(string $storedPath): void
    {
        if (trim($storedPath) === '') {
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
        $storagePath = Yii::getAlias(Yii::$app->params['orderAttachmentStoragePath'] ?? '@runtime/order-attachments');
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
