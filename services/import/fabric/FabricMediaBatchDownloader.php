<?php

namespace app\services\import\fabric;

use app\models\MediaFile;
use app\services\media\MediaContentValidator;

/**
 * Параллельная загрузка URL (curl_multi, sliding window).
 */
final class FabricMediaBatchDownloader
{
    /**
     * @param list<array{id: int|string, url: string, kind: string}> $jobs
     * @return array<int|string, FabricMediaDownloadResult>
     */
    public function downloadParallel(array $jobs, int $concurrency = 8): array
    {
        $concurrency = max(1, min(10, $concurrency));
        /** @var array<int|string, FabricMediaDownloadResult> $results */
        $results = [];
        if ($jobs === []) {
            return $results;
        }

        $mh = curl_multi_init();
        if ($mh === false) {
            foreach ($jobs as $job) {
                $results[$job['id']] = FabricMediaDownloadResult::failed('curl_multi_init failed');
            }

            return $results;
        }

        /** @var array<int, array{id: int|string, url: string, kind: string, tempPath: string, curl: \CurlHandle, file: resource, contentDisposition: ?string}> $active */
        $active = [];
        $pending = array_values($jobs);

        $startNext = function () use (&$pending, &$active, $mh, &$results): void {
            if ($pending === []) {
                return;
            }

            $job = array_shift($pending);
            $tempPath = tempnam(sys_get_temp_dir(), 'fabric_media_');
            if ($tempPath === false) {
                $results[$job['id']] = FabricMediaDownloadResult::failed('Не удалось создать временный файл');

                return;
            }

            $fileHandle = fopen($tempPath, 'wb');
            if ($fileHandle === false) {
                @unlink($tempPath);
                $results[$job['id']] = FabricMediaDownloadResult::failed('Не удалось открыть временный файл');

                return;
            }

            $dispositionHolder = new \stdClass();
            $ch = curl_init($job['url']);
            if ($ch === false) {
                fclose($fileHandle);
                @unlink($tempPath);
                $results[$job['id']] = FabricMediaDownloadResult::failed('curl_init failed');

                return;
            }

            FabricMediaCurlFactory::applyDownloadOptions($ch, $fileHandle, $dispositionHolder);

            curl_multi_add_handle($mh, $ch);
            $active[(int)$ch] = [
                'id' => $job['id'],
                'url' => $job['url'],
                'kind' => $job['kind'],
                'tempPath' => $tempPath,
                'curl' => $ch,
                'file' => $fileHandle,
                'dispositionHolder' => $dispositionHolder,
            ];
        };

        while (count($active) < $concurrency && $pending !== []) {
            $startNext();
        }

        do {
            $status = curl_multi_exec($mh, $running);
            if ($running > 0) {
                curl_multi_select($mh, 0.5);
            }

            while ($info = curl_multi_info_read($mh)) {
                $ch = $info['handle'];
                $key = (int)$ch;
                if (!isset($active[$key])) {
                    curl_multi_remove_handle($mh, $ch);
                    curl_close($ch);
                    continue;
                }

                $meta = $active[$key];
                unset($active[$key]);

                $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);

                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                fclose($meta['file']);

                $id = $meta['id'];
                $tempPath = $meta['tempPath'];

                if ($info['result'] !== CURLE_OK || $httpCode >= 400) {
                    @unlink($tempPath);
                    $results[$id] = FabricMediaDownloadResult::failed(
                        $curlError !== '' ? $curlError : 'HTTP ' . $httpCode,
                        $httpCode
                    );
                } else {
                    $contentDisposition = isset($meta['dispositionHolder']->value)
                        ? (string)$meta['dispositionHolder']->value
                        : null;
                    $results[$id] = $this->validateDownloadedFile(
                        $tempPath,
                        $meta['kind'],
                        $contentDisposition,
                        $httpCode
                    );
                }

                while (count($active) < $concurrency && $pending !== []) {
                    $startNext();
                }
            }
        } while ($running > 0 || $active !== [] || $pending !== []);

        foreach ($active as $meta) {
            curl_multi_remove_handle($mh, $meta['curl']);
            curl_close($meta['curl']);
            fclose($meta['file']);
            @unlink($meta['tempPath']);
            $results[$meta['id']] = FabricMediaDownloadResult::failed('Download aborted');
        }

        curl_multi_close($mh);

        return $results;
    }

    private function validateDownloadedFile(
        string $tempPath,
        string $kind,
        ?string $contentDisposition,
        int $httpCode,
    ): FabricMediaDownloadResult {
        if (!is_file($tempPath) || filesize($tempPath) === 0) {
            @unlink($tempPath);

            return FabricMediaDownloadResult::failed('Скачанный файл пуст.', $httpCode);
        }

        if ($kind === MediaFile::KIND_IMAGE && !MediaContentValidator::isImageFile($tempPath)) {
            @unlink($tempPath);

            return FabricMediaDownloadResult::failed(
                'По ссылке получен не файл изображения (HTTP ' . $httpCode . ').',
                $httpCode
            );
        }

        if ($kind === MediaFile::KIND_DOCUMENT && !MediaContentValidator::isDocumentFile($tempPath)) {
            @unlink($tempPath);

            return FabricMediaDownloadResult::failed(
                'По ссылке получен не PDF/ZIP (HTTP ' . $httpCode . ').',
                $httpCode
            );
        }

        return new FabricMediaDownloadResult(
            true,
            $tempPath,
            MediaContentValidator::filenameFromContentDisposition($contentDisposition),
            null,
            $httpCode
        );
    }
}
