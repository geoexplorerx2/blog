<?php

namespace App\Config;

use PDO;

class DatabaseConfig
{
    public static function getHost(): string
    {
        return 'localhost';
    }

    public static function getDbName(): string
    {
        return 'q_db';
    }

    public static function getUsername(): string
    {
        return 'root';
    }

    public static function getPassword(): string
    {
        return 'root';
    }

    public static function getCharset(): string
    {
        return 'utf8mb4';
    }

    public static function getDsn(): string
    {
        return sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            self::getHost(),
            self::getDbName(),
            self::getCharset()
        );
    }

    public static function getPdoOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
    }
}
