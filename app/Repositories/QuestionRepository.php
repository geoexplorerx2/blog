<?php

namespace App\Repositories;

use App\Contracts\QuestionRepositoryInterface;
use App\Models\Question;
use Exception;
use PDO;
use PDOException;
use RuntimeException;

class QuestionRepository extends BaseRepository implements QuestionRepositoryInterface
{
    public function __construct(?PDO $db = null)
    {
        parent::__construct($db);
        if ($this->getDb() !== null) {
            $this->ensureTableExists();
            $this->ensureCategoriesTableExists();
        }
    }

    public function ensureTableExists(): void
    {
        $db = $this->getDb();
        if ($db === null) return;

        $sql = "CREATE TABLE IF NOT EXISTS questions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            question TEXT NOT NULL,
            answer TEXT NOT NULL,
            category VARCHAR(100) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $db->exec($sql);
    }

    public function ensureCategoriesTableExists(): void
    {
        $db = $this->getDb();
        if ($db === null) return;

        $sql = "CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT NULL,
            image VARCHAR(500) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $db->exec($sql);
    }

    /**
     * @return Question[]
     */
    public function getAll(): array
    {
        $db = $this->getDb();
        if ($db === null) return [];

        try {
            $stmt = $db->query('SELECT id, question, answer, category FROM questions ORDER BY id');
            $rows = $stmt->fetchAll();
            return array_map(
                fn($row) => new Question(
                    (int)$row['id'],
                    $row['question'],
                    $row['answer'],
                    $row['category'] ?? null
                ),
                $rows
            );
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * @return Question[]
     */
    public function getByCategory(string $category): array
    {
        $all = $this->getAll();
        if (strcasecmp($category, 'all') === 0) {
            return $all;
        }
        return array_values(array_filter(
            $all,
            fn($q) => strcasecmp($q->category ?? 'General', $category) === 0
        ));
    }

    public function getCategories(): array
    {
        $db = $this->getDb();
        if ($db === null) return [];

        try {
            $this->ensureCategoriesTableExists();

            $meta = [];
            $stmt = $db->query('SELECT name, description, image FROM categories ORDER BY name');
            foreach ($stmt->fetchAll() as $row) {
                $meta[$row['name']] = $row;
            }

            $result = [];
            $seen = [];
            $qStmt = $db->query('SELECT DISTINCT COALESCE(NULLIF(category, ""), "General") AS cat FROM questions ORDER BY cat');
            foreach ($qStmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
                $seen[$name] = true;
                $result[] = [
                    'name' => $name,
                    'description' => $meta[$name]['description'] ?? null,
                    'image' => $meta[$name]['image'] ?? null,
                ];
            }
            foreach ($meta as $name => $row) {
                if (!isset($seen[$name])) {
                    $result[] = [
                        'name' => $name,
                        'description' => $row['description'],
                        'image' => $row['image'],
                    ];
                }
            }
            return $result;
        } catch (PDOException $e) {
            return [];
        }
    }

    public function addCategory(string $name, ?string $description, ?string $image): void
    {
        $db = $this->getDb();
        if ($db === null) {
            throw new RuntimeException('Database connection not available.');
        }
        $this->ensureCategoriesTableExists();
        $stmt = $db->prepare('INSERT INTO categories (name, description, image) VALUES (:name, :description, :image)
            ON DUPLICATE KEY UPDATE description = VALUES(description), image = VALUES(image)');
        $stmt->execute([
            ':name' => trim($name),
            ':description' => $description !== null && trim($description) !== '' ? trim($description) : null,
            ':image' => $image !== null && trim($image) !== '' ? trim($image) : null,
        ]);
    }

    public function updateCategory(string $oldName, string $newName, ?string $description, ?string $image): int
    {
        $db = $this->getDb();
        if ($db === null) {
            throw new RuntimeException('Database connection not available.');
        }
        $old = trim($oldName);
        $new = trim($newName);
        if ($old === '' || $new === '') return 0;

        $stmt = $db->prepare('UPDATE questions SET category = :new WHERE LOWER(COALESCE(NULLIF(category, ""), "General")) = LOWER(:old)');
        $stmt->execute([':new' => $new, ':old' => $old]);
        $updated = $stmt->rowCount();

        $this->ensureCategoriesTableExists();
        $desc = $description !== null && trim($description) !== '' ? trim($description) : null;
        $img = $image !== null && trim($image) !== '' ? trim($image) : null;

        $catStmt = $db->prepare('INSERT INTO categories (name, description, image) VALUES (:name, :description, :image)
            ON DUPLICATE KEY UPDATE description = VALUES(description), image = VALUES(image)');
        $catStmt->execute([':name' => $new, ':description' => $desc, ':image' => $img]);

        if ($old !== $new) {
            $delStmt = $db->prepare('DELETE FROM categories WHERE LOWER(name) = LOWER(:old)');
            $delStmt->execute([':old' => $old]);
        }

        return $updated;
    }

    public function renameCategory(string $oldCategory, string $newCategory): int
    {
        $db = $this->getDb();
        if ($db === null) {
            throw new RuntimeException('Database connection not available.');
        }
        $old = trim($oldCategory);
        $new = trim($newCategory);
        if ($old === '' || $new === '') return 0;

        $stmt = $db->prepare('UPDATE questions SET category = :new WHERE LOWER(COALESCE(NULLIF(category, ""), "General")) = LOWER(:old)');
        $stmt->execute([':new' => $new, ':old' => $old]);
        $updated = $stmt->rowCount();

        $this->ensureCategoriesTableExists();
        $catStmt = $db->prepare('UPDATE categories SET name = :new WHERE LOWER(name) = LOWER(:old)');
        $catStmt->execute([':new' => $new, ':old' => $old]);

        return $updated;
    }

    public function deleteByCategory(string $category): int
    {
        $db = $this->getDb();
        if ($db === null) {
            throw new RuntimeException('Database connection not available.');
        }
        if (trim($category) === '') return 0;

        $stmt = $db->prepare('DELETE FROM questions WHERE LOWER(COALESCE(NULLIF(category, ""), "General")) = LOWER(:category)');
        $stmt->execute([':category' => trim($category)]);
        $deleted = $stmt->rowCount();

        $this->ensureCategoriesTableExists();
        $catStmt = $db->prepare('DELETE FROM categories WHERE LOWER(name) = LOWER(:category)');
        $catStmt->execute([':category' => trim($category)]);

        return $deleted;
    }

    public function getById(int $id): ?Question
    {
        $db = $this->getDb();
        if ($db === null) return null;

        try {
            $stmt = $db->prepare('SELECT id, question, answer, category FROM questions WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if (!$row) return null;
            return new Question(
                (int)$row['id'],
                $row['question'],
                $row['answer'],
                $row['category'] ?? null
            );
        } catch (PDOException $e) {
            return null;
        }
    }

    public function addQuestion(string $question, string $answer, ?string $category): int
    {
        $db = $this->getDb();
        if ($db === null) {
            throw new RuntimeException('Database connection not available.');
        }
        $cat = $category !== null && trim($category) !== '' ? trim($category) : 'General';
        $stmt = $db->prepare('INSERT INTO questions (question, answer, category) VALUES (:question, :answer, :category)');
        $stmt->execute([
            ':question' => trim($question),
            ':answer'   => trim($answer),
            ':category' => $cat,
        ]);
        return (int)$db->lastInsertId();
    }

    public function updateQuestion(int $id, string $question, string $answer, ?string $category): bool
    {
        $db = $this->getDb();
        if ($db === null) {
            throw new RuntimeException('Database connection not available.');
        }
        $cat = $category !== null && trim($category) !== '' ? trim($category) : 'General';
        $stmt = $db->prepare('UPDATE questions SET question = :question, answer = :answer, category = :category WHERE id = :id');
        return $stmt->execute([
            ':id'       => $id,
            ':question' => trim($question),
            ':answer'   => trim($answer),
            ':category' => $cat,
        ]);
    }

    public function deleteQuestion(int $id): bool
    {
        $db = $this->getDb();
        if ($db === null) {
            throw new RuntimeException('Database connection not available.');
        }
        $stmt = $db->prepare('DELETE FROM questions WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function importQuestions(array $questions): int
    {
        $db = $this->getDb();
        if ($db === null) {
            throw new RuntimeException('Database connection not available.');
        }
        if (empty($questions)) return 0;

        $stmt = $db->prepare('INSERT INTO questions (question, answer, category) VALUES (:question, :answer, :category)');
        $db->beginTransaction();
        $inserted = 0;
        try {
            foreach ($questions as $q) {
                if (!isset($q['question']) || !isset($q['answer'])) continue;
                $cat = isset($q['category']) && trim($q['category']) !== '' ? trim($q['category']) : 'General';
                $stmt->execute([
                    ':question' => trim($q['question']),
                    ':answer'   => trim($q['answer']),
                    ':category' => $cat,
                ]);
                $inserted++;
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        return $inserted;
    }

    public function countAll(): int
    {
        $db = $this->getDb();
        if ($db === null) return 0;
        try {
            return (int)$db->query('SELECT COUNT(*) FROM questions')->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    public function clearAll(): void
    {
        $db = $this->getDb();
        if ($db === null) return;
        $db->exec('TRUNCATE TABLE questions');
    }
}
