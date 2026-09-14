<?php

namespace App\Core;

use App\Config\DatabaseConfig;
use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): ?PDO
    {
        if (self::$connection === null) {
            try {
                self::$connection = new PDO(
                    DatabaseConfig::getDsn(),
                    DatabaseConfig::getUsername(),
                    DatabaseConfig::getPassword(),
                    DatabaseConfig::getPdoOptions()
                );
            } catch (PDOException $e) {
                self::$connection = null;
            }
        }
        return self::$connection;
    }

    public static function isAvailable(): bool
    {
        return self::getConnection() !== null;
    }

    public static function setConnection(?PDO $pdo): void
    {
        self::$connection = $pdo;
    }
}
