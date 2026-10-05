<?php

namespace app\exceptions;

use yii\web\BadRequestHttpException;

class ApiValidationException extends BadRequestHttpException
{
    /** @var array<string, string[]> */
    public array $errors;

    /**
     * @param array<string, string[]> $errors
     */
    public function __construct(string $message, array $errors = [])
    {
        $this->errors = $errors;
        parent::__construct($message);
    }
}
