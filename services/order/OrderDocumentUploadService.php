<?php

namespace app\services\order;

use app\exceptions\ApiValidationException;
use app\models\Order;
use app\models\OrderDocument;
use Yii;
use yii\web\UploadedFile;

class OrderDocumentUploadService
{
    private const DEFAULT_MAX_BYTES = 50 * 1024 * 1024;

    public function saveForOrder(Order $order, UploadedFile $file, string $label): OrderDocument
    {
        $label = trim($label);
        if ($label === '') {
            throw new ApiValidationException('Укажите название документа.', [
                'label' => ['Название обязательно.'],
            ]);
        }

        $this->validateFile($file);

        $storagePath = $this->getStoragePath();
        if (!is_dir($storagePath) && !mkdir($storagePath, 0775, true) && !is_dir($storagePath)) {
            throw new ApiValidationException('Не удалось сохранить документ.');
        }

        $extension = $this->resolveSafeExtension($file);
        $safeName = $order->number . '_doc_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $targetPath = $storagePath . DIRECTORY_SEPARATOR . $safeName;

        if (!$file->saveAs($targetPath)) {
            throw new ApiValidationException('Не удалось сохранить документ.');
        }

        $document = new OrderDocument([
            'order_id' => (int)$order->id,
            'label' => $label,
            'stored_path' => $safeName,
            'original_name' => $file->name,
            'sort_order' => $this->nextSortOrder((int)$order->id),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $document->save(false);

        return $document;
    }

    public function resolveAbsolutePath(string $storedPath): string
    {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($storedPath));
        if ($normalized === '' || str_contains($normalized, '..')) {
            throw new ApiValidationException('Некорректный путь к документу.');
        }

        return $this->getStoragePath() . DIRECTORY_SEPARATOR . $normalized;
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

    public function buildDownloadUrl(string $orderNumber, int $documentId): string
    {
        return '/api/v1/orders/' . rawurlencode($orderNumber) . '/documents/' . $documentId;
    }

    private function getStoragePath(): string
    {
        return Yii::getAlias(Yii::$app->params['orderDocumentStoragePath'] ?? '@runtime/order-documents');
    }

    private function nextSortOrder(int $orderId): int
    {
        $max = OrderDocument::find()->where(['order_id' => $orderId])->max('sort_order');

        return $max !== null ? ((int)$max + 1) : 0;
    }

    private function validateFile(UploadedFile $file): void
    {
        if ($file->hasError) {
            throw new ApiValidationException('Не удалось загрузить документ.', [
                'document' => ['Ошибка загрузки файла.'],
            ]);
        }

        $maxBytes = (int)(Yii::$app->params['orderDocumentMaxBytes'] ?? self::DEFAULT_MAX_BYTES);
        if ($file->size <= 0) {
            throw new ApiValidationException('Файл пустой.', [
                'document' => ['Выберите непустой файл.'],
            ]);
        }

        if ($file->size > $maxBytes) {
            $maxMb = max(1, (int)round($maxBytes / 1024 / 1024));
            throw new ApiValidationException('Файл слишком большой.', [
                'document' => ['Максимальный размер файла — ' . $maxMb . ' МБ.'],
            ]);
        }
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
