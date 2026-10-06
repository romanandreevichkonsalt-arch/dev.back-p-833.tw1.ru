<?php

namespace app\services\catalog;

use Yii;
use yii\helpers\Url;

class FabricLibraryArchiveUrls
{
    public static function relativePath(): string
    {
        $config = Yii::$app->params['fabricLibraryArchive'] ?? [];
        if (!is_array($config)) {
            $config = [];
        }

        $relativePath = trim((string)($config['relativePath'] ?? 'files/library-fabrics.zip'));

        return $relativePath !== '' ? $relativePath : 'files/library-fabrics.zip';
    }

    public static function absolutePath(): string
    {
        return Yii::getAlias('@webroot') . '/' . ltrim(self::relativePath(), '/');
    }

    public static function publicUrl(): string
    {
        return '/' . ltrim(self::relativePath(), '/');
    }

    /** URL архива для API (абсолютный, если известен host запроса). */
    public static function apiArchiveUrl(): ?string
    {
        if (!self::exists()) {
            return null;
        }

        if (Yii::$app->has('request') && Yii::$app->request instanceof \yii\web\Request) {
            return Url::to(self::publicUrl(), true);
        }

        return self::publicUrl();
    }

    public static function exists(): bool
    {
        $path = self::absolutePath();

        return is_file($path) && filesize($path) > 0;
    }

    public static function mimeType(): string
    {
        $extension = strtolower(pathinfo(self::relativePath(), PATHINFO_EXTENSION));

        return match ($extension) {
            'zip' => 'application/zip',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
