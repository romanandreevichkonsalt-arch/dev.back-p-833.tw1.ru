<?php

namespace app\modules\admin\helpers;

final class RegistryImportPostedOptions
{
    public const MODE_SKIP = 'skip';
    public const MODE_UPDATE = 'update';
    public const MODE_UPDATE_NO_MEDIA = 'update_no_media';

    private const NO_MEDIA_SUFFIX = '_no_media';

    /**
     * @return array{conflictResolution: string, importMedia: bool}
     */
    public static function parseConflictResolution(string $posted, string $skipConst, string $updateConst): array
    {
        $value = trim($posted);
        $importMedia = true;
        if ($value === self::MODE_UPDATE_NO_MEDIA) {
            $importMedia = false;
            $value = self::MODE_UPDATE;
        } elseif (str_ends_with($value, self::NO_MEDIA_SUFFIX)) {
            // «Добавление без фото» не поддерживается — только обновление без медиа.
            $value = substr($value, 0, -strlen(self::NO_MEDIA_SUFFIX));
        }

        $conflictResolution = $value === self::MODE_UPDATE ? $updateConst : $skipConst;

        return [
            'conflictResolution' => $conflictResolution,
            'importMedia' => $importMedia,
        ];
    }
}
