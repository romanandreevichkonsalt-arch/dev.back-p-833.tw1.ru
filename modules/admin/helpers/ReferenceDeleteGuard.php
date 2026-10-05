<?php

namespace app\modules\admin\helpers;

use yii\db\ActiveQuery;
use yii\web\BadRequestHttpException;

class ReferenceDeleteGuard
{
    /**
     * @param array<int, ActiveQuery> $relations map of label => query with exists check
     */
    public static function ensureCanDelete(array $relations): void
    {
        foreach ($relations as $label => $query) {
            if ($query->exists()) {
                throw new BadRequestHttpException('Нельзя удалить: ' . $label . '.');
            }
        }
    }
}
