<?php

namespace app\models;

use yii\db\ActiveRecord;

class CatalogImportRun extends ActiveRecord
{
    public const TYPE_FABRIC = 'fabric';
    public const TYPE_MODEL = 'model';
    public const TYPE_SURFACE_MATERIAL = 'surface_material';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_PARSING = 'parsing';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_AWAITING_CONFLICT = 'awaiting_conflict';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ABORTED = 'aborted';

    public const PHASE_QUEUED = 'queued';
    public const PHASE_PARSING = 'parsing';
    public const PHASE_PROCESSING = 'processing';
    public const PHASE_MEDIA = 'media';
    public const PHASE_DONE = 'done';

    public static function tableName(): string
    {
        return '{{%catalog_import_runs}}';
    }

    public function rules(): array
    {
        return [
            [['filename', 'sheet', 'status', 'created_at'], 'required'],
            [['user_id', 'total_rows', 'processed_rows', 'resume_row_index'], 'integer'],
            [['stats_json', 'options_json', 'error_message'], 'string'],
            [['filename'], 'string', 'max' => 255],
            [['file_path'], 'string', 'max' => 512],
            [['type', 'sheet', 'phase'], 'string', 'max' => 64],
            [['phase_message'], 'string', 'max' => 255],
            [['status'], 'string', 'max' => 32],
            [['created_at', 'started_at', 'finished_at'], 'safe'],
        ];
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_ABORTED,
        ], true);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_QUEUED,
            self::STATUS_PARSING,
            self::STATUS_PROCESSING,
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        if ($this->stats_json === null || $this->stats_json === '') {
            return [];
        }

        $decoded = json_decode($this->stats_json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $stats
     */
    public function setStats(array $stats): void
    {
        $this->stats_json = json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        if ($this->options_json === null || $this->options_json === '') {
            return [];
        }

        $decoded = json_decode($this->options_json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): void
    {
        $this->options_json = json_encode($options, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function markProgress(int $processed, int $total, string $phase, string $message): void
    {
        $this->processed_rows = max(0, $processed);
        $this->total_rows = max(0, $total);
        $this->phase = $phase;
        $this->phase_message = $message;
        if ($this->status === self::STATUS_QUEUED) {
            $this->status = self::STATUS_PROCESSING;
        }
        if ($total > 0 && $processed >= $total && !$this->isTerminal()) {
            $this->status = self::STATUS_COMPLETED;
            $this->phase = self::PHASE_DONE;
            $this->phase_message = sprintf('Обработано %d строк из %d', $total, $total);
            if ($this->finished_at === null) {
                $this->finished_at = date('Y-m-d H:i:s');
            }
        }
        if ($this->started_at === null) {
            $this->started_at = date('Y-m-d H:i:s');
        }
        $this->save(false, [
            'processed_rows',
            'total_rows',
            'phase',
            'phase_message',
            'status',
            'started_at',
            'finished_at',
        ]);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getMediaQueueItems()
    {
        return $this->hasMany(CatalogImportMediaQueue::class, ['import_run_id' => 'id'])
            ->orderBy(['row_number' => SORT_ASC, 'id' => SORT_ASC]);
    }
}
