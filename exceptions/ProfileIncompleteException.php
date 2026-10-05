<?php

namespace app\exceptions;

use yii\web\ForbiddenHttpException;

class ProfileIncompleteException extends ForbiddenHttpException
{
    public function __construct(string $message = 'Заполните профиль перед продолжением.')
    {
        parent::__construct($message, 0, null);
    }
}
