<?php

namespace app\modules\admin\helpers;

final class RegistryImportPostedOptions
{
    public const MODE_SKIP = 'skip';
    public const MODE_UPDATE = 'update';
    public const MODE_UPDATE_NO_MEDIA = 'update_no_media';

    /** TEMPORARY: разовый backfill source_url у моделей — удалить после прогона. */
    public const MODE_RECORD_PHOTO_URLS = 'record_photo_urls_temp';

    private const NO_MEDIA_SUFFIX = '_no_media';

    /**
     * @return array{conflictResolution: string, importMedia: bool, backfillPhotoSourceUrls: bool}
     */
    public static function parseConflictResolution(string $posted, string $skipConst, string $updateConst): array
    {
        $value = trim($posted);
        $importMedia = true;
        $backfillPhotoSourceUrls = false;
        if ($value === self::MODE_RECORD_PHOTO_URLS) {
            return [
                'conflictResolution' => $updateConst,
                'importMedia' => false,
                'backfillPhotoSourceUrls' => true,
            ];
        }
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
            'backfillPhotoSourceUrls' => $backfillPhotoSourceUrls,
        ];
    }
}
