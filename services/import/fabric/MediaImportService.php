<?php

namespace app\services\import\fabric;

use app\models\CatalogImportMediaQueue;
use app\models\MediaFile;
use app\models\MediaFolder;
use app\services\import\surface\SurfaceMaterialMediaNaming;
use app\services\media\LocalMediaStorage;
use app\services\media\MediaContentValidator;

class MediaImportService
{
    public function __construct(
        private readonly LocalMediaStorage $storage = new LocalMediaStorage(),
    ) {
    }

    public function importFabricTexture(
        string $url,
        string $collectionSlug,
        string $designCode,
        ?int $importRunId = null,
        int $rowNumber = 0
    ): ?MediaFile {
        $downloadUrl = CloudStorageUrlResolver::resolve($url) ?? $url;
        $extension = FabricMediaNaming::extensionFromUrl($downloadUrl);
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        $kind = in_array($extension, $imageExtensions, true)
            ? MediaFile::KIND_IMAGE
            : MediaFile::KIND_DOCUMENT;

        return $this->importDirectFile(
            $downloadUrl,
            FabricMediaNaming::swatch($collectionSlug, $designCode, $extension),
            $kind,
            $importRunId,
            $rowNumber,
            'fabric',
            MediaFolder::SLUG_FABRICS
        );
    }

    public function importSwatch(
        string $url,
        string $collectionSlug,
        string $designCode,
        ?int $importRunId = null,
        int $rowNumber = 0
    ): ?MediaFile {
        return $this->importDirectFile(
            $url,
            FabricMediaNaming::swatch(
                $collectionSlug,
                $designCode,
                FabricMediaNaming::extensionFromUrl($url)
            ),
            MediaFile::KIND_IMAGE,
            $importRunId,
            $rowNumber,
            'swatch'
        );
    }

    public function importPbr(
        string $url,
        string $collectionSlug,
        string $designCode,
        ?int $importRunId = null,
        int $rowNumber = 0
    ): ?MediaFile {
        return $this->importDirectFile(
            $url,
            FabricMediaNaming::pbr(
                $collectionSlug,
                $designCode,
                FabricMediaNaming::extensionFromUrl($url)
            ),
            MediaFile::KIND_DOCUMENT,
            $importRunId,
            $rowNumber,
            'pbr'
        );
    }

    public function importSurfacePhoto(
        string $url,
        string $typeSlug,
        string $materialSlug,
        ?int $importRunId = null,
        int $rowNumber = 0
    ): ?MediaFile {
        $downloadUrl = CloudStorageUrlResolver::resolve($url) ?? $url;

        return $this->importDirectFile(
            $downloadUrl,
            SurfaceMaterialMediaNaming::photo(
                $typeSlug,
                $materialSlug,
                SurfaceMaterialMediaNaming::extensionFromUrl($downloadUrl)
            ),
            MediaFile::KIND_IMAGE,
            $importRunId,
            $rowNumber,
            'surface_photo',
            MediaFolder::SLUG_SURFACE_MATERIALS
        );
    }

    public function importSurfaceTexture(
        string $url,
        string $typeSlug,
        string $materialSlug,
        ?int $importRunId = null,
        int $rowNumber = 0
    ): ?MediaFile {
        $downloadUrl = CloudStorageUrlResolver::resolve($url) ?? $url;

        return $this->importDirectFile(
            $downloadUrl,
            SurfaceMaterialMediaNaming::texture(
                $typeSlug,
                $materialSlug,
                SurfaceMaterialMediaNaming::extensionFromUrl($downloadUrl)
            ),
            MediaFile::KIND_IMAGE,
            $importRunId,
            $rowNumber,
            'surface_texture',
            MediaFolder::SLUG_SURFACE_MATERIALS
        );
    }

    public function importCatalogMedia(
        string $url,
        string $preferredBasename,
        string $kind,
        string $folderSlug
    ): ?MediaFile {
        $downloadUrl = CloudStorageUrlResolver::resolve($url) ?? $url;
        $preferredBasename = $this->ensurePreferredBasenameExtension($preferredBasename, $url);

        return $this->importDirectFile(
            $downloadUrl,
            $preferredBasename,
            $kind,
            null,
            0,
            'catalog_model',
            $folderSlug
        );
    }

    private function importDirectFile(
        string $url,
        string $preferredBasename,
        string $kind,
        ?int $importRunId,
        int $rowNumber,
        string $entityType,
        string $folderSlug = MediaFolder::SLUG_FABRICS
    ): ?MediaFile {
        $classification = MediaUrlClassifier::classify($url);
        if ($classification !== MediaUrlClassifier::TYPE_DIRECT && !$this->isResolvedDownloadUrl($url)) {
            $this->enqueueSkippedUrl($importRunId, $rowNumber, $url, $classification, $entityType);

            return null;
        }

        [$tempPath, $downloadedFilename] = $this->downloadToTempFile($url, $kind, true);
        try {
            $storageBasename = MediaContentValidator::guessStorageBasename(
                $tempPath,
                $preferredBasename,
                $downloadedFilename
            );
            $originalFilename = $downloadedFilename !== null && trim($downloadedFilename) !== ''
                ? MediaContentValidator::normalizeFilename(trim($downloadedFilename))
                : $storageBasename;

            return $this->storage->importFromPath(
                $tempPath,
                $storageBasename,
                $kind,
                $folderSlug,
                $originalFilename
            );
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function downloadToTempFile(string $url, string $kind, bool $allowGoogleDriveConfirmRetry = false): array
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'fabric_media_');
        if ($tempPath === false) {
            throw new \RuntimeException('Не удалось создать временный файл для загрузки медиа.');
        }

        $handle = fopen($tempPath, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Не удалось открыть временный файл для записи.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            fclose($handle);
            throw new \RuntimeException('Не удалось инициализировать загрузку URL.');
        }

        $contentDisposition = null;
        curl_setopt_array($ch, [
            CURLOPT_FILE => $handle,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_USERAGENT => 'FabricRegistryImporter/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$contentDisposition): int {
                if (stripos($headerLine, 'Content-Disposition:') === 0) {
                    $contentDisposition = trim(substr($headerLine, strlen('Content-Disposition:')));
                }

                return strlen($headerLine);
            },
        ]);

        $success = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($handle);

        if ($success === false || $httpCode >= 400) {
            @unlink($tempPath);
            throw new \RuntimeException(
                'Не удалось скачать файл: ' . ($error !== '' ? $error : 'HTTP ' . $httpCode)
            );
        }

        if (!is_file($tempPath) || filesize($tempPath) === 0) {
            @unlink($tempPath);
            throw new \RuntimeException('Скачанный файл пуст.');
        }

        if ($kind === MediaFile::KIND_IMAGE && !MediaContentValidator::isImageFile($tempPath)) {
            @unlink($tempPath);
            throw new \RuntimeException('По ссылке получен не файл изображения (возможно, страница Яндекс.Диска или Google Drive).');
        }

        if ($kind === MediaFile::KIND_DOCUMENT && !MediaContentValidator::isDocumentFile($tempPath)) {
            if ($allowGoogleDriveConfirmRetry) {
                $confirmUrl = $this->buildGoogleDriveConfirmDownloadUrl($url, $tempPath);
                if ($confirmUrl !== null) {
                    @unlink($tempPath);

                    return $this->downloadToTempFile($confirmUrl, $kind, false);
                }
            }
            @unlink($tempPath);
            throw new \RuntimeException('По ссылке получен не PDF/ZIP (возможно, HTML-страница облачного хранилища).');
        }

        return [
            $tempPath,
            MediaContentValidator::filenameFromContentDisposition($contentDisposition),
        ];
    }

    private function ensurePreferredBasenameExtension(string $preferredBasename, string $sourceUrl): string
    {
        if (pathinfo($preferredBasename, PATHINFO_EXTENSION) !== '') {
            return $preferredBasename;
        }

        $extension = FabricMediaNaming::optionalExtensionFromUrl($sourceUrl);
        if ($extension === null) {
            return $preferredBasename;
        }

        return $preferredBasename . '.' . $extension;
    }

    private function buildGoogleDriveConfirmDownloadUrl(string $sourceUrl, string $downloadedHtmlPath): ?string
    {
        $html = @file_get_contents($downloadedHtmlPath);
        if (!is_string($html) || $html === '' || !str_contains($html, 'drive.google')) {
            return null;
        }

        if (!preg_match('/confirm=([0-9A-Za-z_-]+)/', $html, $confirmMatch)) {
            return null;
        }

        $fileId = null;
        if (preg_match('~[?&]id=([a-zA-Z0-9_-]+)~', $sourceUrl, $idMatch) === 1) {
            $fileId = $idMatch[1];
        } elseif (preg_match('~drive\.google\.com/file/d/([a-zA-Z0-9_-]+)~i', $sourceUrl, $idMatch) === 1) {
            $fileId = $idMatch[1];
        }

        if ($fileId === null || $fileId === '') {
            return null;
        }

        return 'https://drive.google.com/uc?export=download&id=' . $fileId . '&confirm=' . $confirmMatch[1];
    }

    private function isResolvedDownloadUrl(string $url): bool
    {
        return preg_match(
            '~^https?://.*(?:downloader\.disk\.yandex|weblink/view|drive\.google\.com/uc\?|googleusercontent\.com)~i',
            $url
        ) === 1;
    }

    private function enqueueSkippedUrl(
        ?int $importRunId,
        int $rowNumber,
        string $url,
        string $urlType,
        string $entityType
    ): void {
        if ($importRunId === null || $importRunId <= 0) {
            return;
        }

        $queue = new CatalogImportMediaQueue([
            'import_run_id' => $importRunId,
            'row_number' => $rowNumber,
            'source_url' => mb_substr($url, 0, 512),
            'url_type' => $urlType,
            'status' => $urlType === MediaUrlClassifier::TYPE_FOLDER
                ? CatalogImportMediaQueue::STATUS_FOLDER
                : CatalogImportMediaQueue::STATUS_PENDING,
            'entity_type' => $entityType,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $queue->save(false);
    }
}
