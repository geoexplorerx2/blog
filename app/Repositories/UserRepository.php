<?php

namespace App\Repositories;

use App\Contracts\UserRepositoryInterface;
use App\Models\User;
use PDO;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function ensureTableExists(): void
    {
        $db = $this->getDb();
        if ($db === null) return;

        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Ensure default admin exists
        $stmt = $db->prepare("SELECT id, password FROM users WHERE username = :username");
        $stmt->execute([':username' => 'admin']);
        $user = $stmt->fetch();

        if (!$user) {
            $this->createUser('admin', password_hash('Fa@90904030', PASSWORD_DEFAULT));
        }
    }

    public function findByUsername(string $username): ?User
    {
        $this->ensureTableExists();
        $db = $this->getDb();
        if ($db === null) return null;

        $stmt = $db->prepare("SELECT id, username, password, created_at FROM users WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => trim($username)]);
        $row = $stmt->fetch();

        if (!$row) return null;

        return new User(
            (int)$row['id'],
            $row['username'],
            $row['password'],
            $row['created_at'] ?? null
        );
    }

    public function createUser(string $username, string $passwordHash): bool
    {
        $db = $this->getDb();
        if ($db === null) return false;

        $insert = $db->prepare("INSERT INTO users (username, password) VALUES (:username, :password)");
        return $insert->execute([
            ':username' => trim($username),
            ':password' => $passwordHash
        ]);
    }
}
