<?php

namespace app\services\vacancy;

use app\models\Vacancy;
use app\models\VacancyDirection;
use app\modules\admin\helpers\BlockFormPostHelper;
use app\services\cache\ApiCacheInvalidator;
use Yii;

class VacancyDirectionsAdminService
{
    /**
     * @return array<string, mixed>
     */
    public function buildFormData(): array
    {
        $directions = VacancyDirection::find()
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $rows = [];
        foreach ($directions as $direction) {
            $rows[] = $this->directionToFormRow($direction);
        }

        if ($rows === []) {
            $rows[] = $this->emptyDirectionRow();
        }

        return ['directions' => $rows];
    }

    public function save(array $post): bool
    {
        $transaction = Yii::$app->db->beginTransaction();

        try {
            $keptIds = [];
            foreach (BlockFormPostHelper::rows($post['directions'] ?? null) as $sortOrder => $row) {
                if (!is_array($row)) {
                    continue;
                }

                $direction = $this->upsertDirectionFromRow((int)$sortOrder, $row);
                if ($direction === null) {
                    continue;
                }

                if (!$direction->save()) {
                    $transaction->rollBack();

                    return false;
                }

                $keptIds[] = (int)$direction->id;
            }

            if (!$this->deleteRemovedDirections($keptIds)) {
                $transaction->rollBack();

                return false;
            }

            $transaction->commit();
            ApiCacheInvalidator::touch();

            return true;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error($e->getMessage(), __METHOD__);

            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function directionToFormRow(VacancyDirection $direction): array
    {
        return [
            'id' => (string)$direction->id,
            'slug' => $direction->slug,
            'number' => $direction->number,
            'title' => $direction->title,
            'description' => $direction->description ?? '',
            'empty_title' => $direction->empty_title,
            'empty_description' => $direction->empty_description,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function emptyDirectionRow(): array
    {
        return [
            'id' => '',
            'slug' => '',
            'number' => '',
            'title' => '',
            'description' => '',
            'empty_title' => 'Сейчас у нас нет открытых вакансий',
            'empty_description' => 'Следите за обновлениями — новые позиции появятся здесь',
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function upsertDirectionFromRow(int $sortOrder, array $row): ?VacancyDirection
    {
        $title = trim((string)($row['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $id = (int)($row['id'] ?? 0);
        $direction = $id > 0 ? VacancyDirection::findOne($id) : new VacancyDirection();
        if ($direction === null) {
            $direction = new VacancyDirection();
        }

        $slug = trim((string)($row['slug'] ?? ''));
        $direction->slug = $slug;

        $direction->title = $title;
        $direction->number = trim((string)($row['number'] ?? ''));
        $direction->description = trim((string)($row['description'] ?? ''));
        $direction->empty_title = trim((string)($row['empty_title'] ?? '')) ?: 'Сейчас у нас нет открытых вакансий';
        $direction->empty_description = trim((string)($row['empty_description'] ?? ''))
            ?: 'Следите за обновлениями — новые позиции появятся здесь';
        $direction->sort_order = $sortOrder;
        $direction->is_active = true;

        return $direction;
    }

    /**
     * @param list<int> $keptIds
     */
    private function deleteRemovedDirections(array $keptIds): bool
    {
        $query = VacancyDirection::find();
        if ($keptIds !== []) {
            $query->where(['not in', 'id', $keptIds]);
        }

        foreach ($query->all() as $direction) {
            $vacancyCount = Vacancy::find()->where(['direction_id' => (int)$direction->id])->count();
            if ($vacancyCount > 0) {
                Yii::$app->session->setFlash(
                    'error',
                    'Направление «' . $direction->title . '» не удалено: к нему привязаны вакансии.'
                );
                continue;
            }

            if ($direction->delete() === false) {
                return false;
            }
        }

        return true;
    }
}
