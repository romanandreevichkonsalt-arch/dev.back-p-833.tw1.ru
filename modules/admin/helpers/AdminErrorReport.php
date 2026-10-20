<?php

namespace app\modules\admin\helpers;

use Throwable;
use Yii;
use yii\web\HttpException;

final class AdminErrorReport
{
    public static function statusCode(Throwable $exception): int
    {
        return $exception instanceof HttpException ? $exception->statusCode : 500;
    }

    public static function userMessage(Throwable $exception): string
    {
        if ($exception instanceof HttpException) {
            return $exception->getMessage();
        }

        return YII_DEBUG
            ? $exception->getMessage()
            : 'На сервере произошла ошибка. Скопируйте текст для разработчика и попробуйте позже.';
    }

    public static function developerText(Throwable $exception): string
    {
        $lines = [
            'Application: fabrica-beget admin',
            'Time: ' . gmdate('c'),
        ];

        if (Yii::$app->has('request')) {
            $request = Yii::$app->request;
            try {
                $lines[] = 'URL: ' . $request->getAbsoluteUrl();
                $lines[] = 'Method: ' . $request->getMethod();
            } catch (\Throwable) {
                $lines[] = 'URL: (unavailable)';
            }
        }

        $lines = array_merge($lines, [
            'Status: ' . self::statusCode($exception),
            'Exception: ' . $exception::class,
            'Message: ' . $exception->getMessage(),
            'File: ' . $exception->getFile() . ':' . $exception->getLine(),
        ]);

        $previous = $exception->getPrevious();
        if ($previous !== null) {
            $lines[] = 'Previous: ' . $previous::class . ' — ' . $previous->getMessage();
            $lines[] = 'Previous file: ' . $previous->getFile() . ':' . $previous->getLine();
        }

        if (YII_DEBUG) {
            $lines[] = "Stack trace:\n" . $exception->getTraceAsString();
        }

        return implode("\n", $lines);
    }
}
