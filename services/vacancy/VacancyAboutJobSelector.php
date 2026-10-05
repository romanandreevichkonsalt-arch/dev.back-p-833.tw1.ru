<?php

namespace app\services\vacancy;

class VacancyAboutJobSelector
{
    /**
     * @param array<int, array<int, object{id: int|string|null}>> $grouped
     * @param int[] $groupOrder
     * @return array<int, object{id: int|string|null}>
     */
    public static function select(array $grouped, array $groupOrder, int $limit, array $excludeIds = []): array
    {
        if ($limit <= 0) {
            return [];
        }

        $exclude = array_fill_keys(array_map('intval', $excludeIds), true);
        $picked = [];
        $pickedIds = [];

        while (count($picked) < $limit) {
            $addedInRound = false;

            foreach ($groupOrder as $groupId) {
                if (count($picked) >= $limit) {
                    break;
                }

                foreach ($grouped[$groupId] ?? [] as $vacancy) {
                    $id = (int)$vacancy->id;
                    if (isset($exclude[$id]) || in_array($id, $pickedIds, true)) {
                        continue;
                    }

                    $picked[] = $vacancy;
                    $pickedIds[] = $id;
                    $addedInRound = true;
                    break;
                }
            }

            if (!$addedInRound) {
                break;
            }
        }

        return $picked;
    }
}
