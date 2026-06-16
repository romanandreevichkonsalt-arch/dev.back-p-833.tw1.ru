<?php

namespace app\models\traits;

use yii\db\ActiveQuery;

trait SoftDeleteTrait
{
    public static function find(): ActiveQuery
    {
        return parent::find()->andWhere([static::tableName() . '.[[deleted_at]]' => null]);
    }

    public static function findWithDeleted(): ActiveQuery
    {
        return parent::find();
    }
}
