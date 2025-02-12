<?php
declare(strict_types=1);

namespace JUNU\Utils;

class Logger
{
    public static function info(string $message): void
    {
        echo "[INFO] {$message}" . PHP_EOL;
    }

    public static function warning(string $message): void
    {
        echo "[WARNING] {$message}" . PHP_EOL;
    }

    public static function error(string $message): void
    {
        echo "[ERROR] {$message}" . PHP_EOL;
    }
}
