<?php

namespace app\behaviors;

use yii\base\Behavior;
use yii\db\ActiveRecord;

class SoftDeleteBehavior extends Behavior
{
    public string $deletedAtAttribute = 'deleted_at';

    public function events(): array
    {
        return [
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
        ];
    }

    public function beforeDelete($event): void
    {
        if ($this->softDelete()) {
            $event->isValid = false;
        }
    }

    public function softDelete(): bool
    {
        /** @var ActiveRecord $owner */
        $owner = $this->owner;
        $owner->{$this->deletedAtAttribute} = date('Y-m-d H:i:s');

        return $owner->save(false, [$this->deletedAtAttribute]);
    }

    public function restore(): bool
    {
        /** @var ActiveRecord $owner */
        $owner = $this->owner;
        $owner->{$this->deletedAtAttribute} = null;

        return $owner->save(false, [$this->deletedAtAttribute]);
    }

    public function isDeleted(): bool
    {
        return $this->owner->{$this->deletedAtAttribute} !== null;
    }
}
