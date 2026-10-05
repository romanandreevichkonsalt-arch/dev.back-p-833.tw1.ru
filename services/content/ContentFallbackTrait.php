<?php

namespace app\services\content;

use Yii;
use yii\web\NotFoundHttpException;

trait ContentFallbackTrait
{
    protected function allowsJsonFallback(): bool
    {
        return (bool)(Yii::$app->params['content']['jsonFallback'] ?? true);
    }

    protected function fallbackOrThrow(callable $fromJson, string $message = 'Контент не найден.'): mixed
    {
        if ($this->allowsJsonFallback()) {
            return $fromJson();
        }

        throw new NotFoundHttpException($message);
    }
}
