<?php

namespace App\Repositories;

use App\Contracts\RepositoryInterface;
use App\Core\Database;
use PDO;

abstract class BaseRepository implements RepositoryInterface
{
    protected ?PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function getDb(): ?PDO
    {
        if ($this->db === null) {
            $this->db = Database::getConnection();
        }
        return $this->db;
    }

    abstract public function ensureTableExists(): void;
}
