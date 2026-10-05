<?php

namespace app\services\import\fabric;

use app\helpers\SlugHelper;
use app\models\CatalogFabricColor;
use app\models\MediaFile;
use app\services\media\LocalMediaStorage;
use Yii;

class FabricMediaRepairService
{
    public function __construct(
        private readonly MediaImportService $mediaImportService = new MediaImportService(),
        private readonly LocalMediaStorage $storage = new LocalMediaStorage(),
    ) {
    }

    /**
     * @return array{repaired:int,failed:int,skipped:int,messages:string[]}
     */
    public function repairMissingSwatches(): array
    {
        $result = [
            'repaired' => 0,
            'failed' => 0,
            'skipped' => 0,
            'messages' => [],
        ];

        $links = CatalogFabricColor::find()
            ->with(['swatchMedia', 'fabricCollection'])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        foreach ($links as $link) {
            $label = ($link->fabricCollection?->name ?? '—') . ' / ' . $link->design_code;

            if ($this->hasValidSwatchFile($link)) {
                $result['skipped']++;
                continue;
            }

            $textureUrl = trim((string)$link->source_photo_url);
            if ($textureUrl === '' || !preg_match('~^https?://~i', $textureUrl)) {
                $result['failed']++;
                $result['messages'][] = $label . ': нет рабочей ссылки для восстановления.';

                continue;
            }

            $downloadUrl = CloudStorageUrlResolver::resolve($textureUrl);
            if ($downloadUrl === null) {
                $result['failed']++;
                $reason = CloudStorageUrlResolver::unsupportedReason($textureUrl) ?? 'ссылка не распознана';
                $result['messages'][] = $label . ': ' . $reason . '.';

                continue;
            }

            try {
                if ($link->swatchMedia !== null) {
                    $tempPath = $this->downloadToTempFile($downloadUrl);
                    try {
                        $this->storage->replaceFromPath($link->swatchMedia, $tempPath);
                    } finally {
                        if (is_file($tempPath)) {
                            @unlink($tempPath);
                        }
                    }
                } else {
                    $collectionSlug = SlugHelper::slugify((string)($link->fabricCollection?->name ?? 'fabric'));
                    $mediaSlug = FabricDesignCodeNormalizer::normalize($link->design_code);
                    $media = $this->mediaImportService->importFabricTexture(
                        $downloadUrl,
                        $collectionSlug,
                        $mediaSlug
                    );
                    if ($media === null) {
                        throw new \RuntimeException('не удалось импортировать файл');
                    }

                    if ($media->kind === MediaFile::KIND_IMAGE) {
                        $link->swatch_media_id = (int)$media->id;
                    } else {
                        $link->pbr_media_id = (int)$media->id;
                    }
                    $link->save(false);
                }

                $result['repaired']++;
                $result['messages'][] = $label . ': фото восстановлено.';
            } catch (\Throwable $exception) {
                $result['failed']++;
                $result['messages'][] = $label . ': ' . $exception->getMessage();
            }
        }

        return $result;
    }

    private function hasValidSwatchFile(CatalogFabricColor $link): bool
    {
        if ($link->swatch_media_id === null || $link->swatchMedia === null) {
            return false;
        }

        return is_file($this->storage->resolveFullPath($link->swatchMedia->path));
    }

    private function downloadToTempFile(string $url): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'fabric_repair_');
        if ($tempPath === false) {
            throw new \RuntimeException('Не удалось создать временный файл.');
        }

        $handle = fopen($tempPath, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Не удалось открыть временный файл.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            fclose($handle);
            throw new \RuntimeException('Не удалось инициализировать загрузку.');
        }

        curl_setopt_array($ch, [
            CURLOPT_FILE => $handle,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_USERAGENT => 'FabricMediaRepair/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $success = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($handle);

        if ($success === false || $httpCode >= 400 || !is_file($tempPath) || filesize($tempPath) === 0) {
            @unlink($tempPath);
            throw new \RuntimeException(
                'Не удалось скачать файл: ' . ($error !== '' ? $error : 'HTTP ' . $httpCode)
            );
        }

        return $tempPath;
    }
}
