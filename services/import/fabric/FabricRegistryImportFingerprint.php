<?php

namespace app\services\import\fabric;

use app\models\MediaFile;
use Yii;

final class FabricRegistryImportFingerprint
{
    public static function sha256File(string $fullPath): ?string
    {
        if (!is_file($fullPath) || filesize($fullPath) === 0) {
            return null;
        }

        $hash = @hash_file('sha256', $fullPath);

        return is_string($hash) && $hash !== '' ? $hash : null;
    }

    public static function fromSwatchMediaId(?int $mediaId): ?string
    {
        if ($mediaId === null || $mediaId <= 0) {
            return null;
        }

        $media = MediaFile::findOne($mediaId);
        if ($media === null || !$media->isImage()) {
            return null;
        }

        $relative = ltrim((string)$media->path, '/');
        if ($relative === '') {
            return null;
        }

        return self::sha256File(Yii::getAlias('@webroot/' . $relative));
    }
}
