<?php

namespace App\Repositories;

use App\Contracts\FreelanceRepositoryInterface;
use PDO;
use PDOException;
use RuntimeException;

class FreelanceRepository extends BaseRepository implements FreelanceRepositoryInterface
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

        // 1. Companies Table
        $db->exec("CREATE TABLE IF NOT EXISTS freelance_companies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            client_name VARCHAR(255) DEFAULT NULL,
            client_email VARCHAR(255) DEFAULT NULL,
            color VARCHAR(30) DEFAULT '#12466f',
            share_token VARCHAR(64) UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // 2. Projects Table
        $db->exec("CREATE TABLE IF NOT EXISTS freelance_projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            hourly_rate DECIMAL(15,2) DEFAULT 500000.00,
            currency VARCHAR(20) DEFAULT 'تومان',
            status VARCHAR(50) DEFAULT 'in_progress',
            share_token VARCHAR(64) UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_company (company_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // 3. Tasks Table
        $db->exec("CREATE TABLE IF NOT EXISTS freelance_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            task_date DATE NOT NULL,
            start_date DATE DEFAULT NULL,
            end_date DATE DEFAULT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            price_per_hour DECIMAL(15,2) NOT NULL DEFAULT 500000.00,
            duration_hours DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            total_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            description TEXT DEFAULT NULL,
            status VARCHAR(50) DEFAULT 'completed',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_project (project_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Dynamically add start_date / end_date columns if upgrading existing table
        try {
            $cols = $db->query("SHOW COLUMNS FROM freelance_tasks LIKE 'start_date'")->fetchAll();
            if (empty($cols)) {
                $db->exec("ALTER TABLE freelance_tasks ADD COLUMN start_date DATE DEFAULT NULL AFTER task_date");
                $db->exec("UPDATE freelance_tasks SET start_date = task_date WHERE start_date IS NULL");
            }
            $cols2 = $db->query("SHOW COLUMNS FROM freelance_tasks LIKE 'end_date'")->fetchAll();
            if (empty($cols2)) {
                $db->exec("ALTER TABLE freelance_tasks ADD COLUMN end_date DATE DEFAULT NULL AFTER start_date");
                $db->exec("UPDATE freelance_tasks SET end_date = COALESCE(start_date, task_date) WHERE end_date IS NULL");
            }
        } catch (PDOException $e) {
            // Ignore column upgrade errors
        }
    }

    public function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function getFullStructure(): array
    {
        $db = $this->getDb();
        if ($db === null) return [];

        try {
            $stmt = $db->query("SELECT * FROM freelance_companies ORDER BY name ASC");
            $companies = $stmt->fetchAll();

            foreach ($companies as &$comp) {
                $stmtProj = $db->prepare("SELECT p.*, 
                    COUNT(t.id) as task_count, 
                    COALESCE(SUM(t.duration_hours), 0) as total_hours, 
                    COALESCE(SUM(t.total_price), 0) as total_price 
                    FROM freelance_projects p 
                    LEFT JOIN freelance_tasks t ON p.id = t.project_id 
                    WHERE p.company_id = ? 
                    GROUP BY p.id 
                    ORDER BY p.id DESC");
                $stmtProj->execute([$comp['id']]);
                $comp['projects'] = $stmtProj->fetchAll();
                
                $compTotalTasks = 0;
                $compTotalPrice = 0;
                foreach ($comp['projects'] as $prj) {
                    $compTotalTasks += (int)$prj['task_count'];
                    $compTotalPrice += (float)$prj['total_price'];
                }
                $comp['total_tasks'] = $compTotalTasks;
                $comp['total_price'] = round($compTotalPrice, 2);
            }

            return $companies;
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getCompanyByShareToken(string $token): ?array
    {
        $db = $this->getDb();
        if ($db === null) return null;

        try {
            $stmt = $db->prepare("SELECT * FROM freelance_companies WHERE share_token = ?");
            $stmt->execute([$token]);
            $company = $stmt->fetch();

            if (!$company) return null;

            $stmtProjects = $db->prepare("SELECT * FROM freelance_projects WHERE company_id = ? ORDER BY id DESC");
            $stmtProjects->execute([$company['id']]);
            $projects = $stmtProjects->fetchAll();

            $overallTotalHours = 0;
            $overallTotalPrice = 0;

            foreach ($projects as &$p) {
                $stmtTasks = $db->prepare("SELECT * FROM freelance_tasks WHERE project_id = ? ORDER BY task_date DESC, start_time DESC");
                $stmtTasks->execute([$p['id']]);
                $pTasks = $stmtTasks->fetchAll();
                $p['tasks'] = $pTasks;

                $pHours = 0;
                $pPrice = 0;
                foreach ($pTasks as $t) {
                    $pHours += (float)$t['duration_hours'];
                    $pPrice += (float)$t['total_price'];
                }
                $p['total_hours'] = round($pHours, 2);
                $p['total_price'] = round($pPrice, 2);

                $overallTotalHours += $pHours;
                $overallTotalPrice += $pPrice;
            }

            return [
                'company' => $company,
                'projects' => $projects,
                'summary' => [
                    'total_projects' => count($projects),
                    'overall_hours' => round($overallTotalHours, 2),
                    'overall_price' => round($overallTotalPrice, 2)
                ]
            ];
        } catch (PDOException $e) {
            return null;
        }
    }

    public function getProjectByShareToken(string $token): ?array
    {
        $db = $this->getDb();
        if ($db === null) return null;

        try {
            $stmt = $db->prepare("SELECT p.*, c.name AS company_name, c.client_name, c.client_email, c.color AS company_color
                                  FROM freelance_projects p
                                  JOIN freelance_companies c ON p.company_id = c.id
                                  WHERE p.share_token = ?");
            $stmt->execute([$token]);
            $project = $stmt->fetch();

            if (!$project) return null;

            $stmtTasks = $db->prepare("SELECT * FROM freelance_tasks WHERE project_id = ? ORDER BY task_date DESC, start_time DESC");
            $stmtTasks->execute([$project['id']]);
            $tasks = $stmtTasks->fetchAll();

            $totalHours = 0;
            $totalPrice = 0;
            foreach ($tasks as $t) {
                $totalHours += (float)$t['duration_hours'];
                $totalPrice += (float)$t['total_price'];
            }

            return [
                'project' => $project,
                'tasks' => $tasks,
                'summary' => [
                    'total_tasks' => count($tasks),
                    'total_hours' => round($totalHours, 2),
                    'total_price' => round($totalPrice, 2)
                ]
            ];
        } catch (PDOException $e) {
            return null;
        }
    }

    public function getProjectDetails(int $id): ?array
    {
        $db = $this->getDb();
        if ($db === null) return null;

        try {
            $stmt = $db->prepare("SELECT p.*, c.name as company_name, c.color as company_color, c.share_token as company_share_token 
                                  FROM freelance_projects p 
                                  JOIN freelance_companies c ON p.company_id = c.id 
                                  WHERE p.id = ?");
            $stmt->execute([$id]);
            $project = $stmt->fetch();

            if (!$project) return null;

            $stmtTasks = $db->prepare("SELECT * FROM freelance_tasks WHERE project_id = ? ORDER BY task_date DESC, start_time DESC");
            $stmtTasks->execute([$id]);
            $tasks = $stmtTasks->fetchAll();

            $totalHours = 0;
            $totalPrice = 0;
            foreach ($tasks as $t) {
                $totalHours += (float)$t['duration_hours'];
                $totalPrice += (float)$t['total_price'];
            }

            return [
                'project' => $project,
                'tasks' => $tasks,
                'summary' => [
                    'total_tasks' => count($tasks),
                    'total_hours' => round($totalHours, 2),
                    'total_price' => round($totalPrice, 2)
                ]
            ];
        } catch (PDOException $e) {
            return null;
        }
    }

    public function saveCompany(array $data): int
    {
        $db = $this->getDb();
        if ($db === null) {
            throw new RuntimeException("Database connection not available.");
        }

        $id = !empty($data['id']) ? (int)$data['id'] : null;
        $name = trim($data['name'] ?? '');
        $clientName = trim($data['client_name'] ?? '');
        $clientEmail = trim($data['client_email'] ?? '');
        $color = trim($data['color'] ?? '#12466f');

        if ($id) {
            $stmt = $db->prepare("UPDATE freelance_companies SET name = ?, client_name = ?, client_email = ?, color = ? WHERE id = ?");
            $stmt->execute([$name, $clientName, $clientEmail, $color, $id]);
            return $id;
        } else {
            $token = $this->generateToken();
            $stmt = $db->prepare("INSERT INTO freelance_companies (name, client_name, client_email, color, share_token) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $clientName, $clientEmail, $color, $token]);
            return (int)$db->lastInsertId();
        }
    }

    public function deleteCompany(int $id): bool
    {
        $db = $this->getDb();
        if ($db === null) {
            throw new RuntimeException("Database connection not available.");
        }

        $stmtProjIds = $db->prepare("SELECT id FROM freelance_projects WHERE company_id = ?");
        $stmtProjIds->execute([$id]);
        $projIds = $stmtProjIds->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($projIds)) {
            $inClause = implode(',', array_map('intval', $projIds));
            $db->exec("DELETE FROM freelance_tasks WHERE project_id IN ($inClause)");
        }
        $stmtDelProj = $db->prepare("DELETE FROM freelance_projects WHERE company_id = ?");
        $stmtDelProj->execute([$id]);

        $stmt = $db->prepare("DELETE FROM freelance_companies WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function deleteAllCompanies(): void
    {
        $db = $this->getDb();
        if ($db === null) return;
        $db->exec("DELETE FROM freelance_tasks");
        $db->exec("DELETE FROM freelance_projects");
        $db->exec("DELETE FROM freelance_companies");
    }

    public function saveProject(array $data): int
    {
        $db = $this->getDb();
        if ($db === null) {
            throw new RuntimeException("Database connection not available.");
        }

        $id = !empty($data['id']) ? (int)$data['id'] : null;
        $companyId = (int)($data['company_id'] ?? 0);
        $title = trim($data['title'] ?? '');
        $desc = trim($data['description'] ?? '');
        $hourlyRate = (float)($data['hourly_rate'] ?? 50.00);
        $currency = trim($data['currency'] ?? 'تومان');
        $status = trim($data['status'] ?? 'in_progress');

        if ($id) {
            $stmt = $db->prepare("UPDATE freelance_projects SET company_id = ?, title = ?, description = ?, hourly_rate = ?, currency = ?, status = ? WHERE id = ?");
            $stmt->execute([$companyId, $title, $desc, $hourlyRate, $currency, $status, $id]);
            return $id;
        } else {
            $token = $this->generateToken();
            $stmt = $db->prepare("INSERT INTO freelance_projects (company_id, title, description, hourly_rate, currency, status, share_token) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$companyId, $title, $desc, $hourlyRate, $currency, $status, $token]);
            return (int)$db->lastInsertId();
        }
    }

    public function updateProjectCurrency(int $id, string $currency, ?float $hourlyRate = null, bool $applyToTasks = false): bool
    {
        $db = $this->getDb();
        if ($db === null) {
            throw new RuntimeException("Database connection not available.");
        }

        if ($hourlyRate !== null && $hourlyRate >= 0) {
            $stmt = $db->prepare("UPDATE freelance_projects SET currency = ?, hourly_rate = ? WHERE id = ?");
            $stmt->execute([$currency, $hourlyRate, $id]);

            if ($applyToTasks) {
                $stmtTasks = $db->prepare("UPDATE freelance_tasks SET price_per_hour = ?, total_price = ROUND(duration_hours * ?, 2) WHERE project_id = ?");
                $stmtTasks->execute([$hourlyRate, $hourlyRate, $id]);
            }
        } else {
            $stmt = $db->prepare("UPDATE freelance_projects SET currency = ? WHERE id = ?");
            $stmt->execute([$currency, $id]);
        }

        return true;
    }

    public function deleteProject(int $id): bool
    {
        $db = $this->getDb();
        if ($db === null) {
            throw new RuntimeException("Database connection not available.");
        }
        $db->prepare("DELETE FROM freelance_tasks WHERE project_id = ?")->execute([$id]);
        $stmt = $db->prepare("DELETE FROM freelance_projects WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function saveTask(array $data): int
    {
        $db = $this->getDb();
        if ($db === null) {
            throw new RuntimeException("Database connection not available.");
        }

        $id = !empty($data['id']) ? (int)$data['id'] : null;
        $projectId = (int)($data['project_id'] ?? 0);
        $title = trim($data['title'] ?? '');
        $startDate = trim($data['start_date'] ?? $data['task_date'] ?? date('Y-m-d'));
        $endDate = trim($data['end_date'] ?? $startDate);
        $taskDate = $startDate;
        $startTime = trim($data['start_time'] ?? '09:00');
        $endTime = trim($data['end_time'] ?? '17:00');
        $rate = (float)($data['price_per_hour'] ?? 500000.00);
        $desc = trim($data['description'] ?? '');
        $status = trim($data['status'] ?? 'completed');

        if (strlen($startTime) === 5) $startTime .= ':00';
        if (strlen($endTime) === 5) $endTime .= ':00';

        $durationHours = (float)($data['duration_hours'] ?? 0.00);
        $totalPrice = (float)($data['total_price'] ?? 0.00);

        if ($id) {
            $stmt = $db->prepare("UPDATE freelance_tasks SET 
                title = ?, task_date = ?, start_date = ?, end_date = ?, start_time = ?, end_time = ?, price_per_hour = ?, 
                duration_hours = ?, total_price = ?, description = ?, status = ? 
                WHERE id = ?");
            $stmt->execute([
                $title,
                $taskDate,
                $startDate,
                $endDate,
                $startTime,
                $endTime,
                $rate,
                $durationHours,
                $totalPrice,
                $desc,
                $status,
                $id
            ]);
            return $id;
        } else {
            $stmt = $db->prepare("INSERT INTO freelance_tasks 
                (project_id, title, task_date, start_date, end_date, start_time, end_time, price_per_hour, duration_hours, total_price, description, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $projectId,
                $title,
                $taskDate,
                $startDate,
                $endDate,
                $startTime,
                $endTime,
                $rate,
                $durationHours,
                $totalPrice,
                $desc,
                $status
            ]);
            return (int)$db->lastInsertId();
        }
    }

    public function deleteTask(int $id): bool
    {
        $db = $this->getDb();
        if ($db === null) {
            throw new RuntimeException("Database connection not available.");
        }
        $stmt = $db->prepare("DELETE FROM freelance_tasks WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function importCompanyData(array $data): bool
    {
        // Reserved for future comprehensive multi-company import
        return true;
    }

    public function importProjectData(int $companyId, array $data): int
    {
        // Reserved for project-specific import
        return 0;
    }
}
