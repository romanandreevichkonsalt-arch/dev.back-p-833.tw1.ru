<?php

namespace app\services\catalog;

use Yii;

class FabricLibraryArchiveUrls
{
    public static function relativePath(): string
    {
        $config = Yii::$app->params['fabricLibraryArchive'] ?? [];
        if (!is_array($config)) {
            $config = [];
        }

        $relativePath = trim((string)($config['relativePath'] ?? 'files/library-fabrics.pdf'));

        return $relativePath !== '' ? $relativePath : 'files/library-fabrics.pdf';
    }

    public static function absolutePath(): string
    {
        return Yii::getAlias('@webroot') . '/' . ltrim(self::relativePath(), '/');
    }

    public static function publicUrl(): string
    {
        return '/' . ltrim(self::relativePath(), '/');
    }

    public static function exists(): bool
    {
        $path = self::absolutePath();

        return is_file($path) && filesize($path) > 0;
    }
}
