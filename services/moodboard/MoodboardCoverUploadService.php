<?php

namespace app\services\moodboard;

use app\exceptions\ApiValidationException;
use app\models\MediaFile;
use app\models\MediaFolder;
use app\services\media\LocalMediaStorage;
use yii\web\UploadedFile;

class MoodboardCoverUploadService
{
    public function __construct(
        private readonly LocalMediaStorage $storage = new LocalMediaStorage(),
    ) {
    }

    /**
     * @return array{mediaId: int, image: array<string, mixed>}
     */
    public function upload(UploadedFile $file): array
    {
        if ($file->hasError) {
            throw new ApiValidationException('Не удалось загрузить файл.', [
                'file' => ['Ошибка загрузки файла.'],
            ]);
        }

        $media = $this->storage->upload(
            $file,
            'moodboard-cover',
            MediaFile::KIND_IMAGE,
            null,
            MediaFolder::SLUG_MOODBOARDS
        );

        return [
            'mediaId' => (int)$media->id,
            'image' => $media->toApiImagePayload('moodboard-cover'),
        ];
    }
}
