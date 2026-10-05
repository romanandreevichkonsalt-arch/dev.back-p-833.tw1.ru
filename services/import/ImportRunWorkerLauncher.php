<?php

namespace app\services\import;

use Yii;
use yii\helpers\FileHelper;

class ImportRunWorkerLauncher
{
    public static function phpBinary(): string
    {
        if (defined('PHP_BINARY') && PHP_BINARY !== '') {
            return PHP_BINARY;
        }

        foreach (['/usr/bin/php8.3', '/usr/bin/php8.2', '/usr/bin/php8.1', '/usr/bin/php', '/usr/local/bin/php'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return 'php';
    }

    public static function dispatch(string $consoleCommand, int $runId, string $runtimeSubdir): void
    {
        $yii = Yii::getAlias('@app/yii');
        $php = self::phpBinary();
        $logDir = Yii::getAlias('@runtime/' . $runtimeSubdir);
        FileHelper::createDirectory($logDir);
        $logFile = $logDir . '/run-' . $runId . '.log';
        $root = dirname($yii);

        $cmd = sprintf(
            'cd %s && %s %s %s %d >> %s 2>&1 &',
            escapeshellarg($root),
            escapeshellarg($php),
            escapeshellarg($yii),
            $consoleCommand,
            $runId,
            escapeshellarg($logFile)
        );

        if (!function_exists('exec') || in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true)) {
            Yii::warning('exec() недоступен, worker не запущен: ' . $consoleCommand, __METHOD__);

            return;
        }

        exec($cmd);
    }

    public static function dispatchAction(string $consoleAction, string $runtimeSubdir): void
    {
        $yii = Yii::getAlias('@app/yii');
        $php = self::phpBinary();
        $logDir = Yii::getAlias('@runtime/' . $runtimeSubdir);
        FileHelper::createDirectory($logDir);
        $logFile = $logDir . '/job-' . date('YmdHis') . '-' . substr(md5($consoleAction), 0, 8) . '.log';
        $root = dirname($yii);

        $cmd = sprintf(
            'cd %s && %s %s %s >> %s 2>&1 &',
            escapeshellarg($root),
            escapeshellarg($php),
            escapeshellarg($yii),
            $consoleAction,
            escapeshellarg($logFile)
        );

        if (!function_exists('exec') || in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true)) {
            Yii::warning('exec() недоступен, worker не запущен: ' . $consoleAction, __METHOD__);

            return;
        }

        exec($cmd);
    }
}
