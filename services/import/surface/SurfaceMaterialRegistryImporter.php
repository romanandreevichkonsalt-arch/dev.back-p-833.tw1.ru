<?php

namespace app\services\import\surface;

use app\helpers\SlugHelper;
use app\models\CatalogImportRun;
use app\models\CatalogSurfaceMaterial;
use app\services\import\fabric\CloudStorageUrlResolver;
use app\services\import\fabric\MediaImportService;
use Yii;

class SurfaceMaterialRegistryImporter
{
    public function __construct(
        private readonly SurfaceMaterialRegistrySpreadsheetReader $spreadsheetReader = new SurfaceMaterialRegistrySpreadsheetReader(),
        private readonly MediaImportService $mediaImportService = new MediaImportService(),
        private readonly AppliedCatalogCollectionResolver $collectionResolver = new AppliedCatalogCollectionResolver(),
    ) {
    }

    public function import(
        string $filePath,
        SurfaceMaterialRegistryImportOptions $options,
        ?CatalogImportRun $run = null,
        int $startIndex = 0,
        ?SurfaceMaterialRegistryImportResult $accumulatedResult = null,
    ): SurfaceMaterialRegistryImportResult {
        $result = $accumulatedResult ?? new SurfaceMaterialRegistryImportResult();
        $importRun = $run;

        if ($importRun === null && $options->shouldWrite() && !$options->asyncMode) {
            $importRun = new CatalogImportRun([
                'type' => CatalogImportRun::TYPE_SURFACE_MATERIAL,
                'user_id' => $options->userId,
                'filename' => $options->filename !== '' ? $options->filename : basename($filePath),
                'sheet' => SurfaceMaterialRegistrySpreadsheetReader::SHEET,
                'status' => CatalogImportRun::STATUS_PROCESSING,
                'phase' => CatalogImportRun::PHASE_PARSING,
                'created_at' => date('Y-m-d H:i:s'),
                'started_at' => date('Y-m-d H:i:s'),
            ]);
            $importRun->save(false);
            $result->importRun = $importRun;
        } elseif ($importRun !== null) {
            $result->importRun = $importRun;
            $options->importRunId = (int)$importRun->id;
        }

        $useTransaction = $options->shouldWrite() && !$options->asyncMode;
        $transaction = $useTransaction ? Yii::$app->db->beginTransaction() : null;

        try {
            if ($importRun !== null && $options->asyncMode) {
                $importRun->markProgress(
                    $startIndex,
                    (int)$importRun->total_rows,
                    CatalogImportRun::PHASE_PARSING,
                    'Чтение файла…'
                );
            }

            $rows = $this->spreadsheetReader->read($filePath);
            $totalRows = count($rows);
            $result->stats['rows_total'] = $totalRows;

            if ($importRun !== null) {
                $importRun->total_rows = $totalRows;
                $importRun->save(false, ['total_rows']);
            }

            if ($startIndex > 0) {
                $rows = array_slice($rows, $startIndex);
            }

            foreach ($rows as $offset => $row) {
                if ($result->aborted) {
                    break;
                }

                $absoluteIndex = $startIndex + $offset;
                $rowResult = $this->importRow($row, $options, $importRun?->id);
                $result->addRow($rowResult);
                $this->updateStats($result, $rowResult);

                if ($rowResult->action === SurfaceMaterialRegistryImportRowResult::ACTION_CONFLICT) {
                    $result->pendingConflict = $rowResult->conflict;
                    $conflictAction = $options->resolveConflictAction();

                    if ($conflictAction === SurfaceMaterialRegistryImportOptions::CONFLICT_ABORT) {
                        $result->aborted = true;
                        $result->success = false;

                        if ($options->asyncMode && $importRun !== null) {
                            $importRun->resume_row_index = $absoluteIndex;
                            $this->persistRunState($importRun, $result, CatalogImportRun::STATUS_AWAITING_CONFLICT);
                        }
                        break;
                    }

                    if ($conflictAction === SurfaceMaterialRegistryImportOptions::CONFLICT_SKIP) {
                        $result->stats['materials_conflict'] = max(0, $result->stats['materials_conflict'] - 1);
                        $result->stats['links_conflict'] = $result->stats['materials_conflict'];
                        $rowResult->action = SurfaceMaterialRegistryImportRowResult::ACTION_SKIP;
                        $rowResult->addMessage('Материал уже есть — пропущен.');
                        $this->updateStats($result, $rowResult);
                        $this->clearConflictResolutionOnce($options, $importRun);
                        continue;
                    }

                    if ($conflictAction === SurfaceMaterialRegistryImportOptions::CONFLICT_UPDATE) {
                        $updated = $this->updateExistingMaterial($row, $options, $importRun?->id, $rowResult);
                        if ($updated) {
                            $rowResult->action = SurfaceMaterialRegistryImportRowResult::ACTION_UPDATE;
                            $result->stats['materials_updated']++;
                            $result->stats['links_updated'] = $result->stats['materials_updated'];
                            $result->stats['materials_conflict'] = max(0, $result->stats['materials_conflict'] - 1);
                            $result->stats['links_conflict'] = $result->stats['materials_conflict'];
                        }
                        $this->clearConflictResolutionOnce($options, $importRun);
                    }
                }

                if ($importRun !== null && $options->asyncMode) {
                    $processed = $absoluteIndex + 1;
                    if ($processed % 5 === 0 || $processed === $totalRows) {
                        $importRun->markProgress(
                            $processed,
                            $totalRows,
                            CatalogImportRun::PHASE_PROCESSING,
                            sprintf('Обработано %d строк из %d', $processed, $totalRows)
                        );
                        $this->persistRunStats($importRun, $result);
                    }
                }
            }

            if ($transaction !== null) {
                if ($result->aborted && !$options->asyncMode) {
                    $transaction->rollBack();
                } else {
                    $transaction->commit();
                }
            }

            if ($importRun !== null && !($result->aborted && $options->asyncMode)) {
                $finalStatus = $result->aborted
                    ? CatalogImportRun::STATUS_ABORTED
                    : ($options->dryRun ? 'dry_run' : CatalogImportRun::STATUS_COMPLETED);
                $this->persistRunState($importRun, $result, $finalStatus);
            }
        } catch (\Throwable $exception) {
            if ($transaction !== null && $transaction->isActive) {
                $transaction->rollBack();
            }

            $result->success = false;
            $result->aborted = true;
            $result->stats['errors']++;

            if ($importRun !== null) {
                $importRun->error_message = $exception->getMessage();
                $this->persistRunState($importRun, $result, CatalogImportRun::STATUS_FAILED);
            }

            throw $exception;
        }

        return $result;
    }

    private function importRow(
        SurfaceMaterialRegistryRowDto $row,
        SurfaceMaterialRegistryImportOptions $options,
        ?int $importRunId
    ): SurfaceMaterialRegistryImportRowResult {
        $rowResult = new SurfaceMaterialRegistryImportRowResult();
        $rowResult->rowNumber = $row->rowNumber;
        $rowResult->materialType = $row->materialType;
        $rowResult->name = $row->name;

        if ($row->isSkippable()) {
            $rowResult->action = SurfaceMaterialRegistryImportRowResult::ACTION_SKIP;
            $rowResult->addMessage('Строка пропущена: пустое название или шаблон.');

            return $rowResult;
        }

        $slug = SlugHelper::slugify($row->name);
        $existing = CatalogSurfaceMaterial::find()
            ->where(['material_type' => $row->materialType, 'slug' => $slug])
            ->one();

        if ($existing !== null && !$options->updateExisting && $options->resolveConflictAction() !== SurfaceMaterialRegistryImportOptions::CONFLICT_UPDATE) {
            $rowResult->action = SurfaceMaterialRegistryImportRowResult::ACTION_CONFLICT;
            $rowResult->conflict = [
                'row_number' => $row->rowNumber,
                'collection' => $row->materialType,
                'design_code' => $row->name,
                'material_type' => $row->materialType,
                'name' => $row->name,
                'existing' => $this->materialSnapshot($existing),
                'incoming' => $row->fingerprint(),
            ];
            $rowResult->addWarning('Найден дубликат: тип + название уже существуют.');

            return $rowResult;
        }

        if (!$options->shouldWrite()) {
            $rowResult->action = $existing !== null
                ? SurfaceMaterialRegistryImportRowResult::ACTION_UPDATE
                : SurfaceMaterialRegistryImportRowResult::ACTION_CREATE;
            $rowResult->addMessage('Dry-run: изменения не сохранены.');
            if ($options->importMedia) {
                $this->previewMedia($row, $rowResult);
            }
            $this->previewCollections($row, $rowResult);

            return $rowResult;
        }

        $material = $existing ?? new CatalogSurfaceMaterial();
        $isNew = $material->isNewRecord;

        $this->applyRowToMaterial($material, $row, $options);
        if (!$material->save()) {
            $rowResult->action = SurfaceMaterialRegistryImportRowResult::ACTION_ERROR;
            $rowResult->addWarning('Ошибка сохранения: ' . implode(', ', $material->getFirstErrors()));

            return $rowResult;
        }

        $rowResult->materialId = (int)$material->id;
        $rowResult->action = $isNew
            ? SurfaceMaterialRegistryImportRowResult::ACTION_CREATE
            : SurfaceMaterialRegistryImportRowResult::ACTION_UPDATE;

        $rowResult->collectionsLinked = $this->syncCollectionsFromRow($material, $row, $rowResult);
        $this->importMediaForMaterial($row, $material, $importRunId, $rowResult, $options);
        $material->save(false);

        return $rowResult;
    }

    private function updateExistingMaterial(
        SurfaceMaterialRegistryRowDto $row,
        SurfaceMaterialRegistryImportOptions $options,
        ?int $importRunId,
        SurfaceMaterialRegistryImportRowResult $rowResult
    ): bool {
        $slug = SlugHelper::slugify($row->name);
        $material = CatalogSurfaceMaterial::find()
            ->where(['material_type' => $row->materialType, 'slug' => $slug])
            ->one();
        if ($material === null) {
            return false;
        }

        if (!$options->shouldWrite()) {
            return true;
        }

        $this->applyRowToMaterial($material, $row, $options);
        if (!$material->save()) {
            $rowResult->addWarning('Ошибка обновления: ' . implode(', ', $material->getFirstErrors()));

            return false;
        }

        $rowResult->materialId = (int)$material->id;
        $rowResult->collectionsLinked = $this->syncCollectionsFromRow($material, $row, $rowResult);
        $this->importMediaForMaterial($row, $material, $importRunId, $rowResult, $options);
        $material->save(false);

        return true;
    }

    private function applyRowToMaterial(
        CatalogSurfaceMaterial $material,
        SurfaceMaterialRegistryRowDto $row,
        SurfaceMaterialRegistryImportOptions $options
    ): void {
        $material->registry_number = $row->registryNumber;
        $material->material_type = $row->materialType;
        $material->name = $row->name;
        $material->slug = SlugHelper::slugify($row->name);
        $material->applied_models_text = $row->appliedModelsText;
        $material->description = $row->description;
        $material->source_photo_url = $row->photoUrl;
        $material->source_texture_url = $this->effectiveTextureSourceUrl($row);
        $material->import_source = $options->importSource;
        $material->import_row_hash = $row->rowHash();
        $material->is_active = true;
        if ($material->isNewRecord && $row->registryNumber !== null) {
            $material->sort_order = $row->registryNumber;
        }
    }

    private function syncCollectionsFromRow(
        CatalogSurfaceMaterial $material,
        SurfaceMaterialRegistryRowDto $row,
        SurfaceMaterialRegistryImportRowResult $rowResult
    ): int {
        $resolved = $this->collectionResolver->resolveFromAppliedModelsText($row->appliedModelsText);
        foreach ($resolved['unmatched'] as $token) {
            $rowResult->addWarning('Коллекция каталога не найдена: «' . $token . '».');
        }

        if ($resolved['matched'] === []) {
            return 0;
        }

        $before = count($material->getLinkedCollectionIds());
        $material->syncCollectionLinks($resolved['matched']);
        $after = count($material->getLinkedCollectionIds());
        $added = max(0, $after - $before);

        if ($added > 0) {
            $rowResult->addMessage('Связано с коллекциями: ' . $added);
        }

        return $added;
    }

    private function importMediaForMaterial(
        SurfaceMaterialRegistryRowDto $row,
        CatalogSurfaceMaterial $material,
        ?int $importRunId,
        SurfaceMaterialRegistryImportRowResult $rowResult,
        SurfaceMaterialRegistryImportOptions $options,
    ): void {
        if (!$options->shouldImportMedia()) {
            return;
        }

        $typeSlug = SlugHelper::slugify($row->materialType);
        $materialSlug = SlugHelper::slugify($row->name);

        if ($row->photoUrl !== null && $row->photoUrl !== '') {
            $this->importPhotoUrl($row->photoUrl, $typeSlug, $materialSlug, $material, $importRunId, $row->rowNumber, $rowResult);
        }

        $textureSourceUrl = $this->effectiveTextureSourceUrl($row);
        if ($textureSourceUrl === null || $textureSourceUrl === '') {
            return;
        }

        if (
            $material->photo_media_id !== null
            && $row->photoUrl !== null
            && $this->urlsMatch($textureSourceUrl, $row->photoUrl)
        ) {
            $material->texture_media_id = (int)$material->photo_media_id;
            $rowResult->textureImported = true;
            $rowResult->addMessage('Текстура: тот же файл, что и фото.');

            return;
        }

        $this->importTextureUrl($textureSourceUrl, $typeSlug, $materialSlug, $material, $importRunId, $row->rowNumber, $rowResult);
    }

    private function effectiveTextureSourceUrl(SurfaceMaterialRegistryRowDto $row): ?string
    {
        $textureUrl = trim((string)($row->textureUrl ?? ''));
        if ($textureUrl !== '') {
            return $textureUrl;
        }

        $photoUrl = trim((string)($row->photoUrl ?? ''));

        return $photoUrl !== '' ? $photoUrl : null;
    }

    private function urlsMatch(string $a, string $b): bool
    {
        return trim($a) === trim($b);
    }

    private function importPhotoUrl(
        string $url,
        string $typeSlug,
        string $materialSlug,
        CatalogSurfaceMaterial $material,
        ?int $importRunId,
        int $rowNumber,
        SurfaceMaterialRegistryImportRowResult $rowResult,
    ): void {
        $downloadUrl = CloudStorageUrlResolver::resolve($url);
        if ($downloadUrl === null) {
            $rowResult->photoSkipped = true;
            $reason = CloudStorageUrlResolver::unsupportedReason($url)
                ?? 'Ссылка не является прямым файлом изображения.';
            $rowResult->addMessage('Фото не загружено: ' . $reason . ' Сохранена исходная ссылка.');

            return;
        }

        try {
            $media = $this->mediaImportService->importSurfacePhoto(
                $downloadUrl,
                $typeSlug,
                $materialSlug,
                $importRunId,
                $rowNumber
            );
            if ($media === null) {
                $rowResult->photoSkipped = true;
                $rowResult->addMessage('Фото не загружено: ссылка не прямая или папка.');

                return;
            }

            $material->photo_media_id = (int)$media->id;
            $rowResult->photoImported = true;
        } catch (\Throwable $e) {
            $rowResult->photoSkipped = true;
            $rowResult->addWarning('Ошибка загрузки фото: ' . $e->getMessage());
        }
    }

    private function importTextureUrl(
        string $url,
        string $typeSlug,
        string $materialSlug,
        CatalogSurfaceMaterial $material,
        ?int $importRunId,
        int $rowNumber,
        SurfaceMaterialRegistryImportRowResult $rowResult,
    ): void {
        $downloadUrl = CloudStorageUrlResolver::resolve($url);
        if ($downloadUrl === null) {
            $rowResult->textureSkipped = true;
            $reason = CloudStorageUrlResolver::unsupportedReason($url)
                ?? 'Ссылка не является прямым файлом изображения.';
            $rowResult->addMessage('Текстура не загружена: ' . $reason . ' Сохранена исходная ссылка.');

            return;
        }

        try {
            $media = $this->mediaImportService->importSurfaceTexture(
                $downloadUrl,
                $typeSlug,
                $materialSlug,
                $importRunId,
                $rowNumber
            );
            if ($media === null) {
                $rowResult->textureSkipped = true;
                $rowResult->addMessage('Текстура не загружена: ссылка не прямая или папка.');

                return;
            }

            $material->texture_media_id = (int)$media->id;
            $rowResult->textureImported = true;
        } catch (\Throwable $e) {
            $rowResult->textureSkipped = true;
            $rowResult->addWarning('Ошибка загрузки текстуры: ' . $e->getMessage());
        }
    }

    private function previewMedia(SurfaceMaterialRegistryRowDto $row, SurfaceMaterialRegistryImportRowResult $rowResult): void
    {
        $textureUrl = $this->effectiveTextureSourceUrl($row);
        foreach ([
            ['url' => $row->photoUrl, 'label' => 'Фото'],
            ['url' => $textureUrl, 'label' => 'Текстура'],
        ] as $item) {
            $url = trim((string)($item['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            if (
                $item['label'] === 'Текстура'
                && $row->photoUrl !== null
                && $this->urlsMatch($url, $row->photoUrl)
                && ($row->textureUrl === null || trim($row->textureUrl) === '')
            ) {
                $rowResult->addMessage('Текстура: будет использована та же ссылка, что и для фото.');

                continue;
            }
            $downloadUrl = CloudStorageUrlResolver::resolve($url);
            if ($downloadUrl === null) {
                $rowResult->addMessage($item['label'] . ': потребуется прямая ссылка на изображение.');
            } else {
                $rowResult->addMessage($item['label'] . ': будет загружено в медиатеку «Материалы».');
            }
        }
    }

    private function previewCollections(SurfaceMaterialRegistryRowDto $row, SurfaceMaterialRegistryImportRowResult $rowResult): void
    {
        $resolved = $this->collectionResolver->resolveFromAppliedModelsText($row->appliedModelsText);
        if ($resolved['matched'] !== []) {
            $rowResult->addMessage('Коллекции каталога: найдено ' . count($resolved['matched']));
        }
        foreach ($resolved['unmatched'] as $token) {
            $rowResult->addWarning('Коллекция не найдена: «' . $token . '».');
        }
    }

    /**
     * @return array<string, scalar|null>
     */
    private function materialSnapshot(CatalogSurfaceMaterial $material): array
    {
        return [
            'id' => $material->id,
            'material_type' => $material->material_type,
            'name' => $material->name,
            'applied_models_text' => $material->applied_models_text,
        ];
    }

    private function clearConflictResolutionOnce(
        SurfaceMaterialRegistryImportOptions $options,
        ?CatalogImportRun $importRun,
    ): void {
        if (!$options->asyncMode) {
            return;
        }

        $options->clearConflictResolutionOnce();
        if ($importRun === null) {
            return;
        }

        $runOptions = $importRun->getOptions();
        unset($runOptions['conflict_resolution_once']);
        $importRun->setOptions($runOptions);
        $importRun->save(false, ['options_json']);
    }

    private function persistRunStats(CatalogImportRun $importRun, SurfaceMaterialRegistryImportResult $result): void
    {
        $payload = $result->toArray();
        unset($payload['rows']);
        $importRun->setStats($payload);
        $importRun->save(false, ['stats_json', 'processed_rows', 'phase_message']);
    }

    private function persistRunState(
        CatalogImportRun $importRun,
        SurfaceMaterialRegistryImportResult $result,
        string $status
    ): void {
        $importRun->status = $status;
        $importRun->phase = $status === CatalogImportRun::STATUS_COMPLETED
            ? CatalogImportRun::PHASE_DONE
            : $importRun->phase;
        if ($importRun->isTerminal() || $status === CatalogImportRun::STATUS_AWAITING_CONFLICT) {
            $importRun->finished_at = $status === CatalogImportRun::STATUS_AWAITING_CONFLICT
                ? null
                : date('Y-m-d H:i:s');
        }
        if ($status === CatalogImportRun::STATUS_COMPLETED) {
            $importRun->processed_rows = (int)$importRun->total_rows;
            $importRun->phase_message = sprintf(
                'Обработано %d строк из %d',
                (int)$importRun->processed_rows,
                (int)$importRun->total_rows
            );
        }
        $importRun->setStats($result->toArray());
        $importRun->save(false);
    }

    private function updateStats(SurfaceMaterialRegistryImportResult $result, SurfaceMaterialRegistryImportRowResult $rowResult): void
    {
        if ($rowResult->action === SurfaceMaterialRegistryImportRowResult::ACTION_SKIP) {
            $result->stats['rows_skipped']++;
        }
        if ($rowResult->action === SurfaceMaterialRegistryImportRowResult::ACTION_CREATE) {
            $result->stats['materials_created']++;
            $result->stats['links_created'] = $result->stats['materials_created'];
        }
        if ($rowResult->action === SurfaceMaterialRegistryImportRowResult::ACTION_UPDATE) {
            $result->stats['materials_updated']++;
            $result->stats['links_updated'] = $result->stats['materials_updated'];
        }
        if ($rowResult->action === SurfaceMaterialRegistryImportRowResult::ACTION_CONFLICT) {
            $result->stats['materials_conflict']++;
            $result->stats['links_conflict'] = $result->stats['materials_conflict'];
        }
        if ($rowResult->action === SurfaceMaterialRegistryImportRowResult::ACTION_ERROR) {
            $result->stats['errors']++;
        }
        if ($rowResult->photoImported) {
            $result->stats['photo_imported']++;
        }
        if ($rowResult->photoSkipped) {
            $result->stats['photo_skipped']++;
        }
        if ($rowResult->textureImported) {
            $result->stats['texture_imported']++;
        }
        if ($rowResult->textureSkipped) {
            $result->stats['texture_skipped']++;
        }
        $result->stats['collection_links_added'] += $rowResult->collectionsLinked;
    }
}
