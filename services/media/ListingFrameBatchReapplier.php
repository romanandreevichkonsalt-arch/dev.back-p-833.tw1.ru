<?php

namespace app\services\media;

use app\models\MediaFile;

final class ListingFrameBatchReapplier
{
    public function __construct(
        private readonly ListingTileService $listingTileService = new ListingTileService(),
    ) {
    }

    /**
     * @return array{processed:int,reapplied:int,skipped:int,failed:int,messages:string[]}
     */
    public function reapplyAllLocked(bool $dryRun = false): array
    {
        $result = [
            'processed' => 0,
            'reapplied' => 0,
            'skipped' => 0,
            'failed' => 0,
            'messages' => [],
        ];

        $query = MediaFile::find()
            ->where(['kind' => MediaFile::KIND_IMAGE, 'listing_frame_locked' => 1])
            ->orderBy(['id' => SORT_ASC]);

        foreach ($query->each() as $media) {
            /** @var MediaFile $media */
            $result['processed']++;

            if (!$media->isListingFrameLocked()) {
                $result['skipped']++;
                continue;
            }

            if ($dryRun) {
                $result['reapplied']++;
                $result['messages'][] = sprintf('#%d %s: будет пересобран кадр каталога.', $media->id, $media->filename);
                continue;
            }

            try {
                $this->listingTileService->reapplyLockedFrame($media);
                $result['reapplied']++;
                $result['messages'][] = sprintf('#%d %s: кадр каталога пересобран.', $media->id, $media->filename);
            } catch (\Throwable $exception) {
                $result['failed']++;
                $result['messages'][] = sprintf('#%d %s: %s', $media->id, $media->filename, $exception->getMessage());
            }
        }

        return $result;
    }
}
