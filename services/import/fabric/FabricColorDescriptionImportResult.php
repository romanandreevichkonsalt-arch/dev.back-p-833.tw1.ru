<?php

namespace app\services\import\fabric;

class FabricColorDescriptionImportResult
{
    public int $totalRows = 0;
    public int $updated = 0;
    public int $unchanged = 0;
    public int $notFound = 0;
    public int $skippedExisting = 0;

    /** @var string[] */
    public array $messages = [];

    public function addMessage(string $message): void
    {
        if (count($this->messages) >= 50) {
            return;
        }

        $this->messages[] = $message;
    }

    public function buildSummary(): string
    {
        return sprintf(
            'Строк в файле: %d. Обновлено: %d. Без изменений: %d. Не найдено в каталоге: %d. Пропущено (уже есть описание): %d.',
            $this->totalRows,
            $this->updated,
            $this->unchanged,
            $this->notFound,
            $this->skippedExisting
        );
    }
}
