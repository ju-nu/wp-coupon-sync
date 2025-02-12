<?php
declare(strict_types=1);

namespace JUNU;

final class Config
{
    public static function getWpCliPath(): string
    {
        return $_ENV['WP_CLI_PATH'] ?? '/usr/local/bin/wp';
    }

    public static function getWpPath(): string
    {
        return $_ENV['WP_PATH'] ?? '/path/to/wordpress';
    }

    public static function getDbDsn(): string
    {
        $host    = $_ENV['DB_HOST'] ?? 'localhost';
        $dbname  = $_ENV['DB_NAME'] ?? 'your_db_name';
        $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
        return "mysql:host={$host};dbname={$dbname};charset={$charset}";
    }

    public static function getDbUser(): string
    {
        return $_ENV['DB_USER'] ?? 'your_db_user';
    }

    public static function getDbPassword(): string
    {
        return $_ENV['DB_PASSWORD'] ?? 'your_db_password';
    }

    public static function getAdcellUser(): string
    {
        return $_ENV['ADCELL_USER'] ?? 'your_adcell_user';
    }

    public static function getAdcellApiPassword(): string
    {
        return $_ENV['ADCELL_API_PASSWORD'] ?? 'your_adcell_api_password';
    }
}
