<?php

namespace app\services\lead;

use app\exceptions\ApiValidationException;
use app\models\Lead;
use Yii;
use yii\web\UploadedFile;

class LeadAttachmentUploadService
{
    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];

    private const DEFAULT_MAX_BYTES = 20 * 1024 * 1024;

    /**
     * @return array{path: string, originalName: string}
     */
    public function saveForLead(Lead $lead, UploadedFile $file): array
    {
        $this->validateFile($file, 'attachment');

        $prefix = 'lead_' . ($lead->id ?: 'new') . '_' . bin2hex(random_bytes(8));

        return $this->persistFile($prefix, $file);
    }

    public function resolveAbsolutePath(string $storedPath): string
    {
        $storagePath = Yii::getAlias($this->getStorageAlias());
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

    public function getMaxBytes(): int
    {
        return (int)(Yii::$app->params['leadAttachmentMaxBytes'] ?? self::DEFAULT_MAX_BYTES);
    }

    private function getStorageAlias(): string
    {
        return (string)(Yii::$app->params['leadAttachmentStoragePath'] ?? '@runtime/lead-attachments');
    }

    private function validateFile(UploadedFile $file, string $fieldKey): void
    {
        if ($file->hasError) {
            throw new ApiValidationException('Не удалось загрузить файл.', [
                $fieldKey => ['Ошибка загрузки файла.'],
            ]);
        }

        $extension = strtolower(preg_replace('/[^a-z0-9]+/', '', (string)$file->extension) ?? '');
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new ApiValidationException('Недопустимый тип файла.', [
                $fieldKey => ['Допустимы: PDF, Word (doc, docx), Excel (xls, xlsx).'],
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
        $extension = strtolower(preg_replace('/[^a-z0-9]+/', '', (string)$file->extension) ?? '');
        $storagePath = Yii::getAlias($this->getStorageAlias());
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
}
