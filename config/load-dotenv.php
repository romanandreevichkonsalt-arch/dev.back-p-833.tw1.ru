<?php

declare(strict_types=1);

/**
 * Loads KEY=VALUE pairs from a .env file into the process environment.
 * Existing env vars are not overwritten.
 */
function loadDotEnv(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        if ($name === '') {
            continue;
        }
        // Переменные YOOKASSA_* из .env имеют приоритет над env пула PHP-FPM.
        $overwrite = str_starts_with($name, 'YOOKASSA_');
        if (!$overwrite && getenv($name) !== false) {
            continue;
        }

        $value = trim($value);
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}
