<?php

namespace app\services\import\fabric;

/**
 * Общие настройки curl для загрузки медиа (single + multi).
 */
final class FabricMediaCurlFactory
{
    /**
     * @param resource $fileHandle
     */
    public static function applyDownloadOptions($ch, $fileHandle, ?object $dispositionHolder = null): void
    {
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fileHandle,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FAILONERROR => false,
            CURLOPT_USERAGENT => 'FabricRegistryImporter/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use ($dispositionHolder): int {
                if ($dispositionHolder !== null && stripos($headerLine, 'Content-Disposition:') === 0) {
                    $dispositionHolder->value = trim(substr($headerLine, strlen('Content-Disposition:')));
                }

                return strlen($headerLine);
            },
        ]);
    }
}
