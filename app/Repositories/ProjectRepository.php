<?php

namespace App\Repositories;

use App\Contracts\ProjectRepositoryInterface;
use App\Models\Project;
use PDO;
use PDOException;
use RuntimeException;

class ProjectRepository extends BaseRepository implements ProjectRepositoryInterface
{
    public function __construct(?PDO $db = null)
    {
        parent::__construct($db);
        if ($this->getDb() !== null) {
            $this->ensureTableExists();
        }
    }

    public function ensureTableExists(): void
    {
        $db = $this->getDb();
        if ($db === null) return;

        $db->exec("CREATE TABLE IF NOT EXISTS projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            project_url VARCHAR(500) DEFAULT NULL,
            github_url VARCHAR(500) DEFAULT NULL,
            company VARCHAR(150) DEFAULT NULL,
            duration VARCHAR(100) DEFAULT NULL,
            role VARCHAR(150) DEFAULT NULL,
            technologies JSON DEFAULT NULL,
            highlights JSON DEFAULT NULL,
            featured TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /**
     * @return Project[]
     */
    public function getAll(): array
    {
        $db = $this->getDb();
        if ($db === null) return [];

        try {
            $stmt = $db->query("SELECT * FROM projects ORDER BY featured DESC, id DESC");
            $rows = $stmt->fetchAll();
            return array_map(function ($row) {
                return new Project(
                    (int)$row['id'],
                    $row['title'],
                    $row['description'],
                    $row['project_url'] ?? null,
                    $row['github_url'] ?? null,
                    $row['company'] ?? null,
                    $row['duration'] ?? null,
                    $row['role'] ?? null,
                    !empty($row['technologies']) ? (is_array($row['technologies']) ? $row['technologies'] : (json_decode($row['technologies'], true) ?: [])) : [],
                    !empty($row['highlights']) ? (is_array($row['highlights']) ? $row['highlights'] : (json_decode($row['highlights'], true) ?: [])) : [],
                    !empty($row['featured']),
                    $row['created_at'] ?? null,
                    $row['updated_at'] ?? null
                );
            }, $rows);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getById(int $id): ?Project
    {
        $db = $this->getDb();
        if ($db === null) return null;

        try {
            $stmt = $db->prepare("SELECT * FROM projects WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if (!$row) return null;

            return new Project(
                (int)$row['id'],
                $row['title'],
                $row['description'],
                $row['project_url'] ?? null,
                $row['github_url'] ?? null,
                $row['company'] ?? null,
                $row['duration'] ?? null,
                $row['role'] ?? null,
                !empty($row['technologies']) ? (is_array($row['technologies']) ? $row['technologies'] : (json_decode($row['technologies'], true) ?: [])) : [],
                !empty($row['highlights']) ? (is_array($row['highlights']) ? $row['highlights'] : (json_decode($row['highlights'], true) ?: [])) : [],
                !empty($row['featured']),
                $row['created_at'] ?? null,
                $row['updated_at'] ?? null
            );
        } catch (PDOException $e) {
            return null;
        }
    }

    public function save(array $data): int
    {
        $db = $this->getDb();
        if ($db === null) {
            throw new RuntimeException("Database connection not available.");
        }

        $id = !empty($data['id']) ? (int)$data['id'] : null;
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $projectUrl = trim($data['project_url'] ?? '');
        $githubUrl = trim($data['github_url'] ?? '');
        $company = trim($data['company'] ?? '');
        $duration = trim($data['duration'] ?? '');
        $role = trim($data['role'] ?? '');
        $featured = !empty($data['featured']) ? 1 : 0;

        $technologies = is_array($data['technologies'] ?? null) 
            ? json_encode($data['technologies'], JSON_UNESCAPED_UNICODE) 
            : ($data['technologies'] ?? '[]');
        $highlights = is_array($data['highlights'] ?? null) 
            ? json_encode($data['highlights'], JSON_UNESCAPED_UNICODE) 
            : ($data['highlights'] ?? '[]');

        if ($id) {
            $stmt = $db->prepare("UPDATE projects SET
                title = :title,
                description = :description,
                project_url = :project_url,
                github_url = :github_url,
                company = :company,
                duration = :duration,
                role = :role,
                technologies = :technologies,
                highlights = :highlights,
                featured = :featured
                WHERE id = :id");
            $stmt->execute([
                ':id' => $id,
                ':title' => $title,
                ':description' => $description,
                ':project_url' => $projectUrl !== '' ? $projectUrl : null,
                ':github_url' => $githubUrl !== '' ? $githubUrl : null,
                ':company' => $company !== '' ? $company : null,
                ':duration' => $duration !== '' ? $duration : null,
                ':role' => $role !== '' ? $role : null,
                ':technologies' => $technologies,
                ':highlights' => $highlights,
                ':featured' => $featured,
            ]);
            return $id;
        } else {
            $stmt = $db->prepare("INSERT INTO projects (
                title, description, project_url, github_url, company, duration, role, technologies, highlights, featured
            ) VALUES (
                :title, :description, :project_url, :github_url, :company, :duration, :role, :technologies, :highlights, :featured
            )");
            $stmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':project_url' => $projectUrl !== '' ? $projectUrl : null,
                ':github_url' => $githubUrl !== '' ? $githubUrl : null,
                ':company' => $company !== '' ? $company : null,
                ':duration' => $duration !== '' ? $duration : null,
                ':role' => $role !== '' ? $role : null,
                ':technologies' => $technologies,
                ':highlights' => $highlights,
                ':featured' => $featured,
            ]);
            return (int)$db->lastInsertId();
        }
    }

    public function delete(int $id): bool
    {
        $db = $this->getDb();
        if ($db === null) {
            throw new RuntimeException("Database connection not available.");
        }
        $stmt = $db->prepare("DELETE FROM projects WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function toggleFeatured(int $id): bool
    {
        $db = $this->getDb();
        if ($db === null) {
            throw new RuntimeException("Database connection not available.");
        }
        $stmt = $db->prepare("UPDATE projects SET featured = CASE WHEN featured = 1 THEN 0 ELSE 1 END WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
