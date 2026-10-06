<?php

namespace app\services\catalog;

use app\models\CatalogFabricCollection;
use Yii;
use yii\helpers\FileHelper;

class FabricLibraryArchiveBuilder
{
    public function __construct(
        private readonly FabricLibraryPhotoZipBuilder $photoZipBuilder = new FabricLibraryPhotoZipBuilder(),
    ) {
    }

    /**
     * @return array{success: bool, path: ?string, publicUrl: ?string, message: string, fileCount: int}
     */
    public function build(): array
    {
        $config = $this->archiveConfig();
        $targetRelative = (string)$config['relativePath'];
        $webroot = Yii::getAlias('@webroot');
        $targetPath = $webroot . '/' . ltrim($targetRelative, '/');
        $tempPath = $targetPath . '.tmp-' . getmypid();

        FileHelper::createDirectory(dirname($targetPath));

        $lockPath = Yii::getAlias('@runtime/fabric-library-archive/build.lock');
        $lockHandle = $this->acquireBuildLock($lockPath);
        if ($lockHandle === null) {
            return [
                'success' => false,
                'path' => is_file($targetPath) ? $targetPath : null,
                'publicUrl' => is_file($targetPath) ? FabricLibraryArchiveUrls::publicUrl() : null,
                'message' => 'Сборка архива уже выполняется. Подождите минуту и обновите страницу.',
                'fileCount' => 0,
            ];
        }

        try {
            @ini_set('memory_limit', '512M');
            @set_time_limit(0);

            if (is_file($tempPath)) {
                @unlink($tempPath);
            }

            $collections = $this->loadActiveCollections();
            $buildResult = $this->photoZipBuilder->buildZip($tempPath, $collections);

            if (is_file($targetPath)) {
                @unlink($targetPath);
            }
            if (!rename($tempPath, $targetPath)) {
                throw new \RuntimeException('Не удалось опубликовать архив фото тканей.');
            }

            return [
                'success' => true,
                'path' => $targetPath,
                'publicUrl' => FabricLibraryArchiveUrls::publicUrl(),
                'message' => sprintf(
                    'Архив фото тканей обновлён (%d файлов%s).',
                    $buildResult['fileCount'],
                    $buildResult['skipped'] > 0 ? ', пропущено без фото: ' . $buildResult['skipped'] : ''
                ),
                'fileCount' => $buildResult['fileCount'],
            ];
        } catch (\Throwable $exception) {
            Yii::error($exception->getMessage(), __METHOD__);
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }

            return [
                'success' => false,
                'path' => is_file($targetPath) ? $targetPath : null,
                'publicUrl' => is_file($targetPath) ? FabricLibraryArchiveUrls::publicUrl() : null,
                'message' => $exception->getMessage(),
                'fileCount' => 0,
            ];
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            @unlink($lockPath);
        }
    }

    /**
     * @return resource|null
     */
    private function acquireBuildLock(string $lockPath)
    {
        FileHelper::createDirectory(dirname($lockPath));

        for ($attempt = 0; $attempt < 3; $attempt++) {
            if ($attempt > 0) {
                $this->clearStaleBuildLock($lockPath);
                usleep($attempt === 1 ? 300_000 : 800_000);
            }

            $lockHandle = fopen($lockPath, 'c+');
            if ($lockHandle === false) {
                continue;
            }

            if (flock($lockHandle, LOCK_EX | LOCK_NB)) {
                return $lockHandle;
            }

            fclose($lockHandle);
        }

        return null;
    }

    private function clearStaleBuildLock(string $lockPath): void
    {
        if (!is_file($lockPath)) {
            return;
        }

        $lockHandle = @fopen($lockPath, 'c+');
        if ($lockHandle === false) {
            @unlink($lockPath);

            return;
        }

        if (flock($lockHandle, LOCK_EX | LOCK_NB)) {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            @unlink($lockPath);

            return;
        }

        fclose($lockHandle);

        $mtime = filemtime($lockPath);
        if ($mtime !== false && time() - $mtime >= 120) {
            @unlink($lockPath);
        }
    }

    /**
     * @return list<CatalogFabricCollection>
     */
    public function loadActiveCollections(): array
    {
        return CatalogFabricCollection::find()
            ->where(['is_active' => true])
            ->with([
                'activeColors.catalogColor',
                'activeColors.swatchMedia',
            ])
            ->orderBy(['texture' => SORT_ASC, 'name' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
    }

    /**
     * @return array{relativePath: string}
     */
    private function archiveConfig(): array
    {
        $config = Yii::$app->params['fabricLibraryArchive'] ?? [];
        if (!is_array($config)) {
            $config = [];
        }

        $relativePath = trim((string)($config['relativePath'] ?? 'files/library-fabrics.zip'));
        if ($relativePath === '') {
            $relativePath = 'files/library-fabrics.zip';
        }

        return ['relativePath' => $relativePath];
    }
}
