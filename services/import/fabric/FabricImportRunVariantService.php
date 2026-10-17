<?php

namespace app\services\import\fabric;

use app\models\CatalogImportRun;
use app\models\MediaFile;
use app\services\media\MediaContentValidator;
use app\services\media\MediaVariantRegenerator;
use Yii;

final class FabricImportRunVariantService
{
    private const WORKER_MAX_SECONDS = 720;

    public function __construct(
        private readonly MediaVariantRegenerator $regenerator = new MediaVariantRegenerator(),
    ) {
    }

    /**
     * @return array{continueInNextWorker: bool, failed: int}
     */
    public function process(CatalogImportRun $run): array
    {
        @set_time_limit(0);

        $payload = $run->getStats();
        $mediaIds = $this->normalizeMediaIds($payload['variant_media_ids'] ?? []);
        if ($mediaIds === []) {
            return ['continueInNextWorker' => false, 'failed' => 0];
        }

        $total = count($mediaIds);
        $post = is_array($payload['post_process'] ?? null) ? $payload['post_process'] : [];
        $startIndex = max(0, (int)($post['variants_processed'] ?? 0));

        $workerStartedAt = microtime(true);
        $failed = (int)($post['variants_failed'] ?? 0);

        for ($index = $startIndex; $index < $total; $index++) {
            $mediaId = $mediaIds[$index];
            $media = MediaFile::findOne(['id' => $mediaId, 'kind' => MediaFile::KIND_IMAGE]);
            if ($media === null) {
                $failed++;
                $this->saveProgress($run, $index + 1, $total, $failed);
                continue;
            }

            try {
                if (
                    !MediaContentValidator::isVariantFileReadable($media, 'mini')
                    || !MediaContentValidator::isVariantFileReadable($media, 'medium')
                    || !MediaContentValidator::isVariantFileReadable($media, 'large')
                ) {
                    $this->regenerator->regenerateForMedia($media);
                }
                if (!MediaContentValidator::isVariantFileReadable($media, 'medium')) {
                    throw new \RuntimeException('Не удалось создать варианты изображения.');
                }
            } catch (\Throwable $exception) {
                $failed++;
                Yii::warning(
                    sprintf('Fabric import variants #%d: %s', $mediaId, $exception->getMessage()),
                    __METHOD__
                );
            }

            $processed = $index + 1;
            if ($processed % 5 === 0 || $processed === $total) {
                $this->saveProgress($run, $processed, $total, $failed);
            }

            if (
                $processed < $total
                && (microtime(true) - $workerStartedAt) >= self::WORKER_MAX_SECONDS
            ) {
                $this->saveProgress($run, $processed, $total, $failed);

                return ['continueInNextWorker' => true, 'failed' => $failed];
            }
        }

        $this->saveProgress($run, $total, $total, $failed);

        return ['continueInNextWorker' => false, 'failed' => $failed];
    }

    private function saveProgress(CatalogImportRun $run, int $processed, int $total, int $failed): void
    {
        $payload = $run->getStats();
        if (!isset($payload['post_process']) || !is_array($payload['post_process'])) {
            $payload['post_process'] = [];
        }
        $payload['post_process']['variants_processed'] = $processed;
        $payload['post_process']['variants_total'] = $total;
        $payload['post_process']['variants_failed'] = $failed;
        $run->setStats($payload);
        $run->save(false, ['stats_json']);

        Yii::info(
            sprintf('[FABRIC_VARIANTS] run #%d %d/%d failed=%d', (int)$run->id, $processed, $total, $failed),
            'fabric-import'
        );
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    private function normalizeMediaIds(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $id) {
            $intId = (int)$id;
            if ($intId > 0) {
                $ids[$intId] = true;
            }
        }

        $sorted = array_keys($ids);
        sort($sorted);

        return $sorted;
    }
}
