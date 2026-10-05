<?php

namespace app\services\catalog;

use app\models\CatalogFabricCollection;
use Yii;
use yii\helpers\FileHelper;

class FabricLibraryArchiveBuilder
{
    public function __construct(
        private readonly FabricLibraryPdfRenderer $pdfRenderer = new FabricLibraryPdfRenderer(),
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
        FileHelper::createDirectory(dirname($lockPath));
        $lockHandle = fopen($lockPath, 'c+');
        if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
            if ($lockHandle !== false) {
                fclose($lockHandle);
            }

            return [
                'success' => false,
                'path' => is_file($targetPath) ? $targetPath : null,
                'publicUrl' => is_file($targetPath) ? FabricLibraryArchiveUrls::publicUrl() : null,
                'message' => 'Сборка каталога уже выполняется.',
                'fileCount' => 0,
            ];
        }

        try {
            @ini_set('memory_limit', '768M');
            @set_time_limit(0);

            if (is_file($tempPath)) {
                @unlink($tempPath);
            }

            $collections = $this->loadActiveCollections();
            $this->pdfRenderer->renderToFile($tempPath, $collections);

            if (is_file($targetPath)) {
                @unlink($targetPath);
            }
            if (!rename($tempPath, $targetPath)) {
                throw new \RuntimeException('Не удалось опубликовать PDF-каталог.');
            }

            $colorCount = 0;
            foreach ($collections as $collection) {
                $colorCount += count($collection->activeColors);
            }

            return [
                'success' => true,
                'path' => $targetPath,
                'publicUrl' => FabricLibraryArchiveUrls::publicUrl(),
                'message' => 'PDF-каталог обновлён.',
                'fileCount' => $colorCount,
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
            ->orderBy(['name' => SORT_ASC, 'id' => SORT_ASC])
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

        $relativePath = trim((string)($config['relativePath'] ?? 'files/library-fabrics.pdf'));
        if ($relativePath === '') {
            $relativePath = 'files/library-fabrics.pdf';
        }

        return ['relativePath' => $relativePath];
    }
}
