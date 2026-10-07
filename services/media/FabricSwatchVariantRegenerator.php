<?php

namespace app\services\media;

use app\models\CatalogColor;
use app\models\CatalogColorImage;
use app\models\CatalogFabricColor;
use app\models\MediaFile;
use app\models\MediaFolder;

final class FabricSwatchVariantRegenerator
{
    public function __construct(
        private readonly MediaVariantRegenerator $regenerator = new MediaVariantRegenerator(),
    ) {
    }

    /**
     * @return array{processed:int,regenerated:int,skipped:int,failed:int,messages:string[]}
     */
    public function regenerateMissing(bool $dryRun = false): array
    {
        $result = [
            'processed' => 0,
            'regenerated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'messages' => [],
        ];

        $mediaIds = $this->collectSwatchMediaIds();
        if ($mediaIds === []) {
            return $result;
        }

        $query = MediaFile::find()
            ->where(['id' => $mediaIds, 'kind' => MediaFile::KIND_IMAGE])
            ->orderBy(['id' => SORT_ASC]);

        foreach ($query->each() as $media) {
            /** @var MediaFile $media */
            $result['processed']++;

            if (!MediaContentValidator::isReadable($media, 'original')) {
                $result['failed']++;
                $result['messages'][] = sprintf('#%d %s: оригинал недоступен.', $media->id, $media->filename);
                continue;
            }

            $needsLarge = !MediaContentValidator::isVariantFileReadable($media, 'large');
            $needsMini = !MediaContentValidator::isVariantFileReadable($media, 'mini');
            $needsMedium = !MediaContentValidator::isVariantFileReadable($media, 'medium');
            if (!$needsLarge && !$needsMini && !$needsMedium) {
                $result['skipped']++;
                continue;
            }

            if ($dryRun) {
                $result['regenerated']++;
                $result['messages'][] = sprintf('#%d %s: нужны варианты mini/medium/large.', $media->id, $media->filename);
                continue;
            }

            try {
                if ($media->isListingFrameLocked()) {
                    $this->regenerator->regenerateForMedia($media);
                } else {
                    $this->regenerator->regenerateStandardVariants($media);
                }
                $result['regenerated']++;
                $result['messages'][] = sprintf('#%d %s: варианты пересобраны.', $media->id, $media->filename);
            } catch (\Throwable $exception) {
                $result['failed']++;
                $result['messages'][] = sprintf('#%d %s: %s', $media->id, $media->filename, $exception->getMessage());
            }
        }

        return $result;
    }

    /**
     * @return list<int>
     */
    private function collectSwatchMediaIds(): array
    {
        $ids = [];

        $fabricSwatchIds = CatalogFabricColor::find()
            ->select('swatch_media_id')
            ->where(['not', ['swatch_media_id' => null]])
            ->column();
        foreach ($fabricSwatchIds as $id) {
            $ids[(int)$id] = true;
        }

        $colorSwatchIds = CatalogColor::find()
            ->select('swatch_media_id')
            ->where(['not', ['swatch_media_id' => null]])
            ->column();
        foreach ($colorSwatchIds as $id) {
            $ids[(int)$id] = true;
        }

        $colorImageIds = CatalogColorImage::find()
            ->select('media_file_id')
            ->column();
        foreach ($colorImageIds as $id) {
            $ids[(int)$id] = true;
        }

        $fabricsFolderId = MediaFolder::findBySlug(MediaFolder::SLUG_FABRICS)?->id;
        if ($fabricsFolderId !== null) {
            $folderMediaIds = MediaFile::find()
                ->select('id')
                ->where([
                    'kind' => MediaFile::KIND_IMAGE,
                    'folder_id' => $fabricsFolderId,
                ])
                ->column();
            foreach ($folderMediaIds as $id) {
                $ids[(int)$id] = true;
            }
        }

        $sorted = array_keys($ids);
        sort($sorted);

        return $sorted;
    }
}
