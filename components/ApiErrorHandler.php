<?php

namespace app\components;

use app\exceptions\ApiValidationException;
use app\exceptions\ProfileIncompleteException;
use Throwable;
use Yii;
use yii\web\ErrorHandler;
use yii\web\HttpException;
use yii\web\Response;

class ApiErrorHandler extends ErrorHandler
{
    protected function renderException($exception): void
    {
        if ($this->isApiRequest()) {
            $this->renderApiException($exception);

            return;
        }

        parent::renderException($exception);
    }

    private function isApiRequest(): bool
    {
        $path = Yii::$app->request->getPathInfo();

        return strpos($path, 'api/v1') === 0;
    }

    private function renderApiException(Throwable $exception): void
    {
        $response = Yii::$app->getResponse();
        $response->format = Response::FORMAT_JSON;
        $response->statusCode = $exception instanceof HttpException
            ? $exception->statusCode
            : 500;

        $message = $exception instanceof HttpException
            ? $exception->getMessage()
            : (YII_DEBUG ? $exception->getMessage() : 'Внутренняя ошибка сервера');

        $payload = [
            'message' => $message,
            'detail' => $message,
        ];

        if ($exception instanceof ApiValidationException) {
            $payload['errors'] = $exception->errors;
        }

        if ($exception instanceof ProfileIncompleteException) {
            $payload['code'] = 'PROFILE_INCOMPLETE';
        }

        $response->data = $payload;
        $response->send();
    }
}
