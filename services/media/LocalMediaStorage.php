<?php

namespace app\services\media;

use app\models\MediaFile;
use app\models\MediaFolder;
use Yii;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

class LocalMediaStorage
{
    private string $basePath;
    private string $publicPrefix;

    public function __construct()
    {
        $this->basePath = Yii::getAlias(Yii::$app->params['mediaStoragePath'] ?? '@webroot/uploads/media');
        $this->publicPrefix = Yii::$app->params['mediaPublicPrefix'] ?? 'uploads/media';
    }

    public function upload(
        UploadedFile $file,
        ?string $alt = null,
        string $kind = MediaFile::KIND_IMAGE,
        ?int $folderId = null,
        ?string $folderSlug = null
    ): MediaFile {
        $kind = MediaFile::normalizeKind($kind);
        $this->validateKind($file, $kind);
        $this->ensureDirectory();

        $folder = $this->resolveFolder($folderId, $folderSlug, $kind);
        if ($folder === null) {
            throw new \RuntimeException('Укажите папку для загрузки файла.');
        }

        $folderSegment = $folder->slug;
        $subdir = $folderSegment . '/' . date('Y/m');
        $targetDir = $this->basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subdir);
        FileHelper::createDirectory($targetDir);

        $basename = $this->generateBasename($file->extension);
        $fullPath = $targetDir . DIRECTORY_SEPARATOR . $basename;

        if (!$file->saveAs($fullPath)) {
            throw new \RuntimeException('Не удалось сохранить файл.');
        }

        $relativePath = $this->buildRelativePath($subdir, $basename);
        $dimensions = $this->readImageDimensions($fullPath, $file->type);
        [$pathLarge, $pathMedium, $pathMini] = $this->generateVariantPaths($fullPath, $basename, $subdir, $kind);

        $media = new MediaFile([
            'filename' => $file->name,
            'path' => $relativePath,
            'path_large' => $pathLarge,
            'path_medium' => $pathMedium,
            'path_mini' => $pathMini,
            'kind' => $kind,
            'folder_id' => $folder->id,
            'mime' => $file->type,
            'size' => (int)$file->size,
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'alt' => $alt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$media->save()) {
            $this->unlinkPaths([$relativePath, $pathLarge, $pathMedium, $pathMini]);
            throw new \RuntimeException('Не удалось сохранить запись медиафайла.');
        }

        return $media;
    }

    public function importFromPath(
        string $sourcePath,
        string $originalFilename,
        string $kind = MediaFile::KIND_IMAGE,
        ?string $folderSlug = null,
        ?string $preferredBasename = null,
        ?string $alt = null
    ): MediaFile {
        if (!is_file($sourcePath)) {
            throw new \RuntimeException('Файл не найден: ' . $sourcePath);
        }

        $kind = MediaFile::normalizeKind($kind);
        $this->ensureDirectory();

        $folder = $this->resolveFolder(null, $folderSlug, $kind);
        if ($folder === null) {
            throw new \RuntimeException('Укажите папку для загрузки файла.');
        }

        $extension = strtolower(pathinfo($preferredBasename ?: $originalFilename, PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'jpg');
        }

        $folderSegment = $folder->slug;
        $subdir = $folderSegment . '/' . date('Y/m');
        $targetDir = $this->basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subdir);
        FileHelper::createDirectory($targetDir);

        $basename = $preferredBasename !== null && $preferredBasename !== ''
            ? $this->sanitizeBasename($preferredBasename)
            : $this->generateBasename($extension);
        $relativePath = $this->buildRelativePath($subdir, $basename);
        $fullPath = $targetDir . DIRECTORY_SEPARATOR . $basename;
        $existing = MediaFile::findOne(['path' => $relativePath]);

        if (!copy($sourcePath, $fullPath)) {
            throw new \RuntimeException('Не удалось скопировать файл.');
        }

        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
        $dimensions = $this->readImageDimensions($fullPath, $mime);
        [$pathLarge, $pathMedium, $pathMini] = $this->generateVariantPaths($fullPath, $basename, $subdir, $kind);

        if ($existing !== null) {
            $this->unlinkPaths([$existing->path_large, $existing->path_medium, $existing->path_mini]);
        }

        if ($existing !== null) {
            $existing->filename = $originalFilename !== '' ? $originalFilename : $basename;
            $existing->path_large = $pathLarge;
            $existing->path_medium = $pathMedium;
            $existing->path_mini = $pathMini;
            $existing->kind = $kind;
            $existing->folder_id = $folder->id;
            $existing->mime = $mime;
            $existing->size = (int)filesize($fullPath);
            $existing->width = $dimensions['width'];
            $existing->height = $dimensions['height'];
            if ($alt !== null) {
                $existing->alt = $alt;
            }

            if (!$existing->save()) {
                throw new \RuntimeException('Не удалось обновить запись медиафайла.');
            }

            return $existing;
        }

        $media = new MediaFile([
            'filename' => $originalFilename !== '' ? $originalFilename : $basename,
            'path' => $relativePath,
            'path_large' => $pathLarge,
            'path_medium' => $pathMedium,
            'path_mini' => $pathMini,
            'kind' => $kind,
            'folder_id' => $folder->id,
            'mime' => $mime,
            'size' => (int)filesize($fullPath),
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'alt' => $alt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$media->save()) {
            $this->unlinkPaths([$relativePath, $pathLarge, $pathMedium, $pathMini]);
            throw new \RuntimeException('Не удалось сохранить запись медиафайла.');
        }

        return $media;
    }

    public function replaceFromPath(MediaFile $media, string $sourcePath): MediaFile
    {
        if (!is_file($sourcePath)) {
            throw new \RuntimeException('Файл не найден: ' . $sourcePath);
        }

        $fullPath = $this->resolveFullPath($media->path);
        FileHelper::createDirectory(dirname($fullPath));

        if (!copy($sourcePath, $fullPath)) {
            throw new \RuntimeException('Не удалось скопировать файл.');
        }

        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
        $dimensions = $this->readImageDimensions($fullPath, $mime);
        $subdir = trim(str_replace($this->publicPrefix, '', dirname($media->path)), '/');
        [$pathLarge, $pathMedium, $pathMini] = $this->generateVariantPaths(
            $fullPath,
            basename($media->path),
            $subdir,
            $media->kind
        );

        $this->unlinkPaths([$media->path_large, $media->path_medium, $media->path_mini]);

        $media->path_large = $pathLarge;
        $media->path_medium = $pathMedium;
        $media->path_mini = $pathMini;
        $media->mime = $mime;
        $media->size = (int)filesize($fullPath);
        $media->width = $dimensions['width'];
        $media->height = $dimensions['height'];

        if (!$media->save()) {
            throw new \RuntimeException('Не удалось обновить запись медиафайла.');
        }

        return $media;
    }

    public function delete(MediaFile $media): void
    {
        $this->unlinkPaths([
            $media->path,
            $media->path_large,
            $media->path_medium,
            $media->path_mini,
        ]);
        $media->delete();
    }

    public function resolveFullPath(string $relativePath): string
    {
        return Yii::getAlias('@webroot') . '/' . ltrim($relativePath, '/');
    }

    private function resolveFolder(?int $folderId, ?string $folderSlug, string $kind): ?MediaFolder
    {
        if ($folderId !== null && $folderId > 0) {
            return MediaFolder::findOne($folderId);
        }

        if ($folderSlug !== null && $folderSlug !== '') {
            return MediaFolder::findBySlug($folderSlug);
        }

        if ($kind === MediaFile::KIND_VIDEO) {
            return MediaFolder::findBySlug(MediaFolder::SLUG_VIDEO);
        }

        if ($kind === MediaFile::KIND_DOCUMENT) {
            return MediaFolder::findBySlug(MediaFolder::SLUG_DOCUMENTS);
        }

        return null;
    }

    private function buildRelativePath(string $subdir, string $basename): string
    {
        return $this->publicPrefix . '/' . $subdir . '/' . $basename;
    }

    /**
     * @return array{0:?string,1:?string,2:?string}
     */
    private function generateVariantPaths(string $fullPath, string $basename, string $subdir, string $kind): array
    {
        $pathLarge = null;
        $pathMedium = null;
        $pathMini = null;

        if ($kind !== MediaFile::KIND_IMAGE) {
            return [$pathLarge, $pathMedium, $pathMini];
        }

        $basenameNoExt = pathinfo($basename, PATHINFO_FILENAME);
        $generator = new ImageVariantGenerator();
        $variants = $generator->generate($fullPath, $basenameNoExt);
        if ($variants['large'] !== null) {
            $pathLarge = $this->buildRelativePath($subdir, basename($variants['large']));
        }
        if ($variants['medium'] !== null) {
            $pathMedium = $this->buildRelativePath($subdir, basename($variants['medium']));
        }
        if ($variants['mini'] !== null) {
            $pathMini = $this->buildRelativePath($subdir, basename($variants['mini']));
        }

        return [$pathLarge, $pathMedium, $pathMini];
    }

  /**
     * @param array<int, string|null> $paths
     */
    private function unlinkPaths(array $paths): void
    {
        foreach ($paths as $relativePath) {
            if ($relativePath === null || $relativePath === '') {
                continue;
            }
            $fullPath = $this->resolveFullPath($relativePath);
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->basePath)) {
            FileHelper::createDirectory($this->basePath);
        }
    }

    private function generateBasename(string $extension): string
    {
        return uniqid('media_', true) . '.' . strtolower($extension);
    }

    private function sanitizeBasename(string $basename): string
    {
        $basename = basename(str_replace('\\', '/', $basename));
        $basename = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $basename) ?? $basename;

        return trim($basename, '.-') !== '' ? trim($basename, '.-') : $this->generateBasename('jpg');
    }

    private function validateKind(UploadedFile $file, string $kind): void
    {
        if ($kind === MediaFile::KIND_VIDEO) {
            if (strpos($file->type, 'video/') !== 0) {
                throw new \RuntimeException('Разрешены только видеофайлы.');
            }

            return;
        }

        if ($kind === MediaFile::KIND_DOCUMENT) {
            $extension = strtolower((string)$file->extension);
            $allowedExtensions = ['pdf', 'zip', 'xlsx', 'xls', 'doc', 'docx', 'glb', 'gltf', 'fbx', 'obj', '3ds', 'dae'];
            if (!in_array($extension, $allowedExtensions, true)) {
                throw new \RuntimeException('Разрешены PDF, Excel, Word, ZIP и 3D-файлы (GLB, GLTF, FBX, OBJ и др.).');
            }

            return;
        }

        if (@getimagesize($file->tempName) === false) {
            throw new \RuntimeException('Разрешены только файлы изображений.');
        }
    }

    /**
     * @return array{width:?int,height:?int}
     */
    private function readImageDimensions(string $path, string $mime): array
    {
        if (strpos($mime, 'image/') !== 0) {
            return ['width' => null, 'height' => null];
        }

        $size = @getimagesize($path);
        if ($size === false) {
            return ['width' => null, 'height' => null];
        }

        return ['width' => (int)$size[0], 'height' => (int)$size[1]];
    }
}
