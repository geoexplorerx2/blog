<?php
/**
 * Freelancer Project & Task Management Hub
 * 
 * Features:
 * - 2-Section Layout: Left (Dropdown Menu: Company -> Projects), Right (Project details, Action buttons, Collapsible Tasks)
 * - Base Theme: #12466f
 * - Task details: Start Time, End Time, Date, Price Per Hour, Description, Duration, Total Price calculation
 * - Project Actions: Create Task, Share Link (Project + Total Price), Edit Project, Delete Project
 * - Authentication: Integrated with session & users table
 * - Public Shareable View: Accessible to anyone with share token without logging in
 * - Fully Responsive (Desktop, Tablet, Mobile)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): ?PDO
    {
        if (self::$connection === null) {
            $host = 'localhost';
            $dbname = 'q_db';
            $username = 'root';
            $password = 'root';
            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

            try {
                self::$connection = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                self::$connection = null;
            }
        }
        return self::$connection;
    }
}

class Auth
{
    public static function ensureUserTableExists(): void
    {
        $db = Database::getConnection();
        if ($db === null) return;

        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $db->prepare("SELECT id FROM users WHERE username = :u");
        $stmt->execute([':u' => 'admin']);
        if (!$stmt->fetch()) {
            $hashed = password_hash('Fa@90904030', PASSWORD_DEFAULT);
            $ins = $db->prepare("INSERT INTO users (username, password) VALUES (:u, :p)");
            $ins->execute([':u' => 'admin', ':p' => $hashed]);
        }
    }

    public static function login(string $username, string $password): bool
    {
        self::ensureUserTableExists();
        $db = Database::getConnection();
        if ($db === null) return false;

        $stmt = $db->prepare("SELECT * FROM users WHERE username = :u LIMIT 1");
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['logged_in_user'] = $user['username'];
            return true;
        }
        return false;
    }

    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        @session_destroy();
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['logged_in_user']);
    }

    public static function getCurrentUser(): ?string
    {
        return $_SESSION['logged_in_user'] ?? null;
    }
}

// Include Global Footer Component
if (file_exists(__DIR__ . '/footer_component.php')) {
    require_once __DIR__ . '/footer_component.php';
}

class FreelanceManager
{
    public static function ensureTables(): void
    {
        $db = Database::getConnection();
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

    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function calculateDurationAndPrice(string $startTime, string $endTime, float $hourlyRate): array
    {
        $start = strtotime("1970-01-01 $startTime");
        $end = strtotime("1970-01-01 $endTime");
        if ($end <= $start) {
            // Handle cross-midnight or zero
            $diffSeconds = (24 * 3600 - $start) + $end;
        } else {
            $diffSeconds = $end - $start;
        }
        $hours = round($diffSeconds / 3600, 2);
        $total = round($hours * $hourlyRate, 2);
        return [
            'duration_hours' => $hours,
            'total_price' => $total
        ];
    }

    public static function seedInitialData(PDO $db): void
    {
        // Company 1: Apex Cloud Technologies
        $stmt = $db->prepare("INSERT INTO freelance_companies (name, client_name, client_email, color, share_token) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['Apex Cloud Technologies', 'David Miller', 'david@apexcloud.io', '#12466f', self::generateToken()]);
        $c1 = (int)$db->lastInsertId();

        // Company 2: Nexa Health Systems
        $stmt->execute(['Nexa Health Systems', 'Sarah Jenkins', 's.jenkins@nexahealth.com', '#0f766e', self::generateToken()]);
        $c2 = (int)$db->lastInsertId();

        // Project 1.1 under Apex
        $stmtProj = $db->prepare("INSERT INTO freelance_projects (company_id, title, description, hourly_rate, currency, status, share_token) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtProj->execute([
            $c1,
            'Enterprise API Microservices & Gateway',
            'Architecting and implementing high-throughput REST and GraphQL gateways with Redis caching and JWT authentication.',
            65.00,
            '$',
            'in_progress',
            self::generateToken()
        ]);
        $p1 = (int)$db->lastInsertId();

        // Project 1.2 under Apex
        $stmtProj->execute([
            $c1,
            'Kubernetes CI/CD Pipeline Automation',
            'Automated GitLab CI/CD runner deployment, Docker container image optimization, and canary release strategies.',
            70.00,
            '$',
            'completed',
            self::generateToken()
        ]);
        $p2 = (int)$db->lastInsertId();

        // Project 2.1 under Nexa Health
        $stmtProj->execute([
            $c2,
            'HIPAA Compliant Patient Telehealth Portal',
            'End-to-end encrypted video consultation module, appointment scheduling, and EHR database synchronization.',
            60.00,
            '$',
            'in_progress',
            self::generateToken()
        ]);
        $p3 = (int)$db->lastInsertId();

        // Tasks for Project 1.1
        $stmtTask = $db->prepare("INSERT INTO freelance_tasks (project_id, title, task_date, start_time, end_time, price_per_hour, duration_hours, total_price, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $cTime1 = self::calculateDurationAndPrice('09:00:00', '12:30:00', 65.00);
        $stmtTask->execute([
            $p1,
            'Setup Redis Clustering & Caching Layer',
            date('Y-m-d', strtotime('-4 days')),
            '09:00:00',
            '12:30:00',
            65.00,
            $cTime1['duration_hours'],
            $cTime1['total_price'],
            'Configured distributed master-replica Redis cluster with automatic failover and low-latency cache invalidation hooks.',
            'completed'
        ]);

        $cTime2 = self::calculateDurationAndPrice('13:30:00', '17:45:00', 65.00);
        $stmtTask->execute([
            $p1,
            'JWT Token Refresh & RBAC Middleware',
            date('Y-m-d', strtotime('-2 days')),
            '13:30:00',
            '17:45:00',
            65.00,
            $cTime2['duration_hours'],
            $cTime2['total_price'],
            'Implemented asymmetric RSA256 signature verification, refresh token blacklisting, and granular endpoint permission gates.',
            'completed'
        ]);

        $cTime3 = self::calculateDurationAndPrice('10:00:00', '14:15:00', 65.00);
        $stmtTask->execute([
            $p1,
            'Rate Limiting & DDoS Shield Integration',
            date('Y-m-d'),
            '10:00:00',
            '14:15:00',
            65.00,
            $cTime3['duration_hours'],
            $cTime3['total_price'],
            'Added token-bucket rate limiter per API key and integrated Cloudflare proxy origin header validation.',
            'in_progress'
        ]);

        // Tasks for Project 1.2
        $cTime4 = self::calculateDurationAndPrice('08:30:00', '12:00:00', 70.00);
        $stmtTask->execute([
            $p2,
            'Helm Charts & Multi-Stage Dockerfiles',
            date('Y-m-d', strtotime('-6 days')),
            '08:30:00',
            '12:00:00',
            70.00,
            $cTime4['duration_hours'],
            $cTime4['total_price'],
            'Standardized alpine-based multi-stage container builds, cutting bundle size by 62% and authored Helm value templates.',
            'completed'
        ]);

        // Tasks for Project 2.1
        $cTime5 = self::calculateDurationAndPrice('09:30:00', '13:00:00', 60.00);
        $stmtTask->execute([
            $p3,
            'WebRTC Signaling & Video Stream Encryption',
            date('Y-m-d', strtotime('-1 days')),
            '09:30:00',
            '13:00:00',
            60.00,
            $cTime5['duration_hours'],
            $cTime5['total_price'],
            'Integrated peer-to-peer WebRTC rooms with STUN/TURN fallback and strict AES-256 media packet encryption.',
            'completed'
        ]);
    }
}

// Ensure database and auth tables exist
Auth::ensureUserTableExists();
FreelanceManager::ensureTables();

// Handle GET direct logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    Auth::logout();
    header('Location: freelance.php');
    exit;
}

// -------------------------------------------------------------
// REST API Endpoints
// -------------------------------------------------------------
if (isset($_GET['api_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $apiAction = $_GET['api_action'];
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;
    $db = Database::getConnection();

    if ($db === null) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
        exit;
    }

    // --- Authentication APIs ---
    if ($apiAction === 'login') {
        $u = trim($data['username'] ?? '');
        $p = $data['password'] ?? '';
        if (Auth::login($u, $p)) {
            echo json_encode(['success' => true, 'username' => Auth::getCurrentUser()]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid username or password.']);
        }
        exit;
    }

    if ($apiAction === 'logout') {
        Auth::logout();
        echo json_encode(['success' => true]);
        exit;
    }

    if ($apiAction === 'check_auth') {
        echo json_encode([
            'authenticated' => Auth::isLoggedIn(),
            'username' => Auth::getCurrentUser()
        ]);
        exit;
    }

    // --- Public Share Info (Unauthenticated allowed) ---
    if ($apiAction === 'get_shared_project') {
        $token = trim($_GET['token'] ?? '');
        if (empty($token)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Share token missing.']);
            exit;
        }

        $stmt = $db->prepare("SELECT p.*, c.name AS company_name, c.client_name, c.client_email, c.color AS company_color
                              FROM freelance_projects p
                              JOIN freelance_companies c ON p.company_id = c.id
                              WHERE p.share_token = ?");
        $stmt->execute([$token]);
        $project = $stmt->fetch();

        if (!$project) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Project not found or share link expired.']);
            exit;
        }

        $stmtTasks = $db->prepare("SELECT * FROM freelance_tasks WHERE project_id = ? ORDER BY task_date DESC, start_time DESC");
        $stmtTasks->execute([$project['id']]);
        $tasks = $stmtTasks->fetchAll();

        $totalHours = 0;
        $totalPrice = 0;
        foreach ($tasks as $t) {
            $totalHours += (float)$t['duration_hours'];
            $totalPrice += (float)$t['total_price'];
        }

        echo json_encode([
            'success' => true,
            'project' => $project,
            'tasks' => $tasks,
            'summary' => [
                'total_tasks' => count($tasks),
                'total_hours' => round($totalHours, 2),
                'total_price' => round($totalPrice, 2)
            ]
        ]);
        exit;
    }

    if ($apiAction === 'get_shared_company') {
        $token = trim($_GET['token'] ?? '');
        if (empty($token)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Share token missing.']);
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM freelance_companies WHERE share_token = ?");
        $stmt->execute([$token]);
        $company = $stmt->fetch();

        if (!$company) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Company not found or share link expired.']);
            exit;
        }

        $stmtProjects = $db->prepare("SELECT * FROM freelance_projects WHERE company_id = ? ORDER BY id DESC");
        $stmtProjects->execute([$company['id']]);
        $projects = $stmtProjects->fetchAll();

        $allTasks = [];
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

        echo json_encode([
            'success' => true,
            'company' => $company,
            'projects' => $projects,
            'summary' => [
                'total_projects' => count($projects),
                'overall_hours' => round($overallTotalHours, 2),
                'overall_price' => round($overallTotalPrice, 2)
            ]
        ]);
        exit;
    }

    // --- All subsequent APIs require Authentication ---
    if (!Auth::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required.', 'require_login' => true]);
        exit;
    }

    // --- Company APIs ---
    if ($apiAction === 'get_tree') {
        // Fetch all companies with nested projects & summary numbers
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

        echo json_encode(['success' => true, 'data' => $companies]);
        exit;
    }

    if ($apiAction === 'create_company') {
        $name = trim($data['name'] ?? '');
        $clientName = trim($data['client_name'] ?? '');
        $clientEmail = trim($data['client_email'] ?? '');
        $color = trim($data['color'] ?? '#12466f');

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Company name is required.']);
            exit;
        }

        $token = FreelanceManager::generateToken();
        $stmt = $db->prepare("INSERT INTO freelance_companies (name, client_name, client_email, color, share_token) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $clientName, $clientEmail, $color, $token]);
        $id = (int)$db->lastInsertId();

        echo json_encode(['success' => true, 'id' => $id, 'share_token' => $token, 'message' => 'Company created successfully!']);
        exit;
    }

    if ($apiAction === 'update_company') {
        $id = (int)($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $clientName = trim($data['client_name'] ?? '');
        $clientEmail = trim($data['client_email'] ?? '');
        $color = trim($data['color'] ?? '#12466f');

        if ($id <= 0 || empty($name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Valid ID and Company name are required.']);
            exit;
        }

        $stmt = $db->prepare("UPDATE freelance_companies SET name = ?, client_name = ?, client_email = ?, color = ? WHERE id = ?");
        $stmt->execute([$name, $clientName, $clientEmail, $color, $id]);

        echo json_encode(['success' => true, 'message' => 'Company updated successfully!']);
        exit;
    }

    if ($apiAction === 'delete_company') {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid company ID.']);
            exit;
        }

        // Delete all tasks in projects belonging to this company
        $stmtProjIds = $db->prepare("SELECT id FROM freelance_projects WHERE company_id = ?");
        $stmtProjIds->execute([$id]);
        $projIds = $stmtProjIds->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($projIds)) {
            $inClause = implode(',', array_map('intval', $projIds));
            $db->exec("DELETE FROM freelance_tasks WHERE project_id IN ($inClause)");
            $db->exec("DELETE FROM freelance_projects WHERE company_id = $id");
        }

        $stmt = $db->prepare("DELETE FROM freelance_companies WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Company and all associated projects/tasks deleted.']);
        exit;
    }

    if ($apiAction === 'delete_all_companies') {
        $db->exec("DELETE FROM freelance_tasks");
        $db->exec("DELETE FROM freelance_projects");
        $db->exec("DELETE FROM freelance_companies");
        echo json_encode(['success' => true, 'message' => 'All companies, projects, and tasks have been permanently deleted.']);
        exit;
    }

    if ($apiAction === 'seed_demo_data') {
        FreelanceManager::seedInitialData($db);
        echo json_encode(['success' => true, 'message' => 'Sample demo companies, projects, and tasks have been loaded!']);
        exit;
    }

    // --- Project APIs ---
    if ($apiAction === 'get_project_details') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid project ID.']);
            exit;
        }

        $stmt = $db->prepare("SELECT p.*, c.name as company_name, c.color as company_color, c.share_token as company_share_token 
                              FROM freelance_projects p 
                              JOIN freelance_companies c ON p.company_id = c.id 
                              WHERE p.id = ?");
        $stmt->execute([$id]);
        $project = $stmt->fetch();

        if (!$project) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Project not found.']);
            exit;
        }

        $stmtTasks = $db->prepare("SELECT * FROM freelance_tasks WHERE project_id = ? ORDER BY task_date DESC, start_time DESC");
        $stmtTasks->execute([$id]);
        $tasks = $stmtTasks->fetchAll();

        $totalHours = 0;
        $totalPrice = 0;
        foreach ($tasks as $t) {
            $totalHours += (float)$t['duration_hours'];
            $totalPrice += (float)$t['total_price'];
        }

        echo json_encode([
            'success' => true,
            'project' => $project,
            'tasks' => $tasks,
            'summary' => [
                'total_tasks' => count($tasks),
                'total_hours' => round($totalHours, 2),
                'total_price' => round($totalPrice, 2)
            ]
        ]);
        exit;
    }

    if ($apiAction === 'create_project') {
        $companyId = (int)($data['company_id'] ?? 0);
        $title = trim($data['title'] ?? '');
        $desc = trim($data['description'] ?? '');
        $hourlyRate = (float)($data['hourly_rate'] ?? 50.00);
        $currency = trim($data['currency'] ?? '$');
        $status = trim($data['status'] ?? 'in_progress');

        if ($companyId <= 0 || empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Company and Project Title are required.']);
            exit;
        }

        $token = FreelanceManager::generateToken();
        $stmt = $db->prepare("INSERT INTO freelance_projects (company_id, title, description, hourly_rate, currency, status, share_token) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$companyId, $title, $desc, $hourlyRate, $currency, $status, $token]);
        $id = (int)$db->lastInsertId();

        echo json_encode(['success' => true, 'id' => $id, 'share_token' => $token, 'message' => 'Project created successfully!']);
        exit;
    }

    if ($apiAction === 'update_project') {
        $id = (int)($data['id'] ?? 0);
        $companyId = (int)($data['company_id'] ?? 0);
        $title = trim($data['title'] ?? '');
        $desc = trim($data['description'] ?? '');
        $hourlyRate = (float)($data['hourly_rate'] ?? 50.00);
        $currency = trim($data['currency'] ?? '$');
        $status = trim($data['status'] ?? 'in_progress');

        if ($id <= 0 || empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Valid ID and Project Title are required.']);
            exit;
        }

        $stmt = $db->prepare("UPDATE freelance_projects SET company_id = ?, title = ?, description = ?, hourly_rate = ?, currency = ?, status = ? WHERE id = ?");
        $stmt->execute([$companyId, $title, $desc, $hourlyRate, $currency, $status, $id]);

        echo json_encode(['success' => true, 'message' => 'Project updated successfully!']);
        exit;
    }

    if ($apiAction === 'update_project_currency') {
        $id = (int)($data['id'] ?? 0);
        $currency = trim($data['currency'] ?? '$');
        $hourlyRate = isset($data['hourly_rate']) ? (float)$data['hourly_rate'] : null;
        $applyToTasks = !empty($data['apply_to_tasks']);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Valid Project ID is required.']);
            exit;
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

        echo json_encode(['success' => true, 'message' => 'Project currency and rate updated successfully!']);
        exit;
    }

    if ($apiAction === 'delete_project') {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid project ID.']);
            exit;
        }

        $db->prepare("DELETE FROM freelance_tasks WHERE project_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM freelance_projects WHERE id = ?")->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Project and its tasks deleted successfully!']);
        exit;
    }

    // --- Task APIs ---
    if ($apiAction === 'create_task') {
        $projectId = (int)($data['project_id'] ?? 0);
        $title = trim($data['title'] ?? '');
        $taskDate = trim($data['task_date'] ?? date('Y-m-d'));
        $startTime = trim($data['start_time'] ?? '09:00');
        $endTime = trim($data['end_time'] ?? '17:00');
        $rate = (float)($data['price_per_hour'] ?? 50.00);
        $desc = trim($data['description'] ?? '');
        $status = trim($data['status'] ?? 'completed');

        if ($projectId <= 0 || empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Project and Task title are required.']);
            exit;
        }

        // Format times to HH:MM:SS
        if (strlen($startTime) === 5) $startTime .= ':00';
        if (strlen($endTime) === 5) $endTime .= ':00';

        $calc = FreelanceManager::calculateDurationAndPrice($startTime, $endTime, $rate);

        $stmt = $db->prepare("INSERT INTO freelance_tasks 
            (project_id, title, task_date, start_time, end_time, price_per_hour, duration_hours, total_price, description, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $projectId,
            $title,
            $taskDate,
            $startTime,
            $endTime,
            $rate,
            $calc['duration_hours'],
            $calc['total_price'],
            $desc,
            $status
        ]);
        $id = (int)$db->lastInsertId();

        echo json_encode(['success' => true, 'id' => $id, 'message' => 'Task created successfully!']);
        exit;
    }

    if ($apiAction === 'update_task') {
        $id = (int)($data['id'] ?? 0);
        $title = trim($data['title'] ?? '');
        $taskDate = trim($data['task_date'] ?? date('Y-m-d'));
        $startTime = trim($data['start_time'] ?? '09:00');
        $endTime = trim($data['end_time'] ?? '17:00');
        $rate = (float)($data['price_per_hour'] ?? 50.00);
        $desc = trim($data['description'] ?? '');
        $status = trim($data['status'] ?? 'completed');

        if ($id <= 0 || empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Valid task ID and title are required.']);
            exit;
        }

        if (strlen($startTime) === 5) $startTime .= ':00';
        if (strlen($endTime) === 5) $endTime .= ':00';

        $calc = FreelanceManager::calculateDurationAndPrice($startTime, $endTime, $rate);

        $stmt = $db->prepare("UPDATE freelance_tasks SET 
            title = ?, task_date = ?, start_time = ?, end_time = ?, price_per_hour = ?, 
            duration_hours = ?, total_price = ?, description = ?, status = ? 
            WHERE id = ?");
        $stmt->execute([
            $title,
            $taskDate,
            $startTime,
            $endTime,
            $rate,
            $calc['duration_hours'],
            $calc['total_price'],
            $desc,
            $status,
            $id
        ]);

        echo json_encode(['success' => true, 'message' => 'Task updated successfully!']);
        exit;
    }

    if ($apiAction === 'delete_task') {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid task ID.']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM freelance_tasks WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Task deleted successfully!']);
        exit;
    }

    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Unknown API action.']);
    exit;
}

// -------------------------------------------------------------
// Check for Public Share Modes in GET Query:
// ?share=PROJECT_TOKEN or ?share_company=COMPANY_TOKEN
// -------------------------------------------------------------
$isPublicProjectShare = !empty($_GET['share']);
$isPublicCompanyShare = !empty($_GET['share_company']);
$isPublicMode = $isPublicProjectShare || $isPublicCompanyShare;

// Require login for main management view if not in public share mode
$isLoggedIn = Auth::isLoggedIn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isPublicMode ? 'Timesheet & Project Breakdown' : 'Freelance Workspace & Task Management' ?> | Farshad Nabizadeh</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="stylesheet" href="assets/fonts.css">
    <link rel="stylesheet" href="assets/poppins.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --brand-primary: #12466f;
            --brand-dark: #0a2942;
            --brand-deep: #082035;
            --brand-light: #eaf2f8;
            --brand-accent: #2a7db5;
            --brand-glow: rgba(18, 70, 111, 0.15);
            --brand-gradient: linear-gradient(135deg, #12466f 0%, #1d6fa5 100%);
            --sidebar-bg: #0e3352;
            --sidebar-hover: #13446c;
            --sidebar-active: #19588c;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --text-light: #94a3b8;
            --bg-page: #f8fafc;
            --bg-card: #ffffff;
            --border-color: #e2e8f0;
            --border-subtle: #edf2f7;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --radius-xl: 20px;
            --topbar-height: 68px;
            --shadow-subtle: 0 2px 4px rgba(0,0,0,0.03), 0 1px 2px rgba(0,0,0,0.02);
            --shadow-card: 0 4px 12px -2px rgba(18, 70, 111, 0.08), 0 2px 6px -1px rgba(0,0,0,0.04);
            --shadow-modal: 0 20px 40px -10px rgba(10, 41, 66, 0.3);
            --success: #10b981;
            --success-bg: #ecfdf5;
            --warning: #f59e0b;
            --warning-bg: #fffbeb;
            --danger: #ef4444;
            --danger-bg: #fef2f2;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.5;
        }

        /* Top App Navigation Bar */
        .app-topbar {
            background-color: #ffffff;
            border-bottom: 1px solid var(--border-color);
            height: var(--topbar-height);
            min-height: var(--topbar-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow-subtle);
            box-sizing: border-box;
            width: 100%;
            gap: 12px;
            transition: height 0.2s ease, padding 0.2s ease;
        }

        .topbar-brand-container {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
            min-width: 0;
        }

        .brand-badge {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
            flex-shrink: 0;
            min-width: 0;
        }

        .brand-logo-icon {
            width: 42px;
            height: 42px;
            background: var(--brand-gradient);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(18, 70, 111, 0.25);
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .brand-title-group {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .brand-title-group h1 {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--brand-dark);
            letter-spacing: -0.02em;
            line-height: 1.2;
            white-space: nowrap;
        }

        .brand-subtitle {
            font-size: 0.76rem;
            color: var(--text-muted);
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.3;
            margin-top: 1px;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .topbar-user-badge {
            font-size: 0.82rem;
            color: var(--text-muted);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            background: #f8fafc;
            padding: 5px 10px;
            border-radius: var(--radius-sm);
            border: 1px solid #e2e8f0;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .user-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--success);
            flex-shrink: 0;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
        }

        .btn-topbar-action {
            white-space: nowrap;
            flex-shrink: 0;
            transition: all 0.18s ease;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.86rem;
            font-weight: 600;
            padding: 9px 16px;
            border-radius: var(--radius-md);
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
            text-decoration: none;
            user-select: none;
            line-height: 1;
        }

        .btn:active {
            transform: scale(0.98);
        }

        .btn-primary {
            background-color: var(--brand-primary);
            color: #ffffff;
            border-color: var(--brand-primary);
            box-shadow: 0 2px 6px rgba(18, 70, 111, 0.2);
        }

        .btn-primary:hover {
            background-color: #0d3656;
            border-color: #0d3656;
            box-shadow: 0 4px 10px rgba(18, 70, 111, 0.3);
        }

        .btn-secondary {
            background-color: #ffffff;
            color: var(--brand-primary);
            border-color: #cbd5e1;
        }

        .btn-secondary:hover {
            background-color: #f1f5f9;
            border-color: #94a3b8;
        }

        .btn-success {
            background-color: var(--success);
            color: #ffffff;
        }

        .btn-success:hover {
            background-color: #059669;
        }

        .btn-danger {
            background-color: #ffffff;
            color: var(--danger);
            border-color: #fca5a5;
        }

        .btn-danger:hover {
            background-color: var(--danger-bg);
            border-color: var(--danger);
        }

        .btn-ghost {
            background: transparent;
            color: var(--text-muted);
            border: none;
            padding: 8px 12px;
        }

        .btn-ghost:hover {
            background: #f1f5f9;
            color: var(--text-main);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
            border-radius: var(--radius-sm);
        }

        /* App Container (2 Columns) */
        .freelance-app-container {
            display: flex;
            flex: 1;
            position: relative;
            align-items: flex-start;
            min-height: calc(100vh - var(--topbar-height));
        }

        /* -------------------------------------------------------------
           LEFT SECTION: Companies & Projects Menu with Dropdowns
           ------------------------------------------------------------- */
        .freelance-sidebar {
            width: 320px;
            min-width: 320px;
            background-color: #ffffff;
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            position: sticky;
            top: var(--topbar-height);
            height: calc(100vh - var(--topbar-height));
            overflow-y: auto;
            z-index: 90;
            transition: transform 0.25s ease;
        }

        .sidebar-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-subtle);
            background: #fafcff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar-header h2 {
            font-size: 0.88rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--brand-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sidebar-search {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-subtle);
        }

        .search-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-input-wrapper svg {
            position: absolute;
            left: 12px;
            color: var(--text-light);
            pointer-events: none;
        }

        .search-input {
            width: 100%;
            padding: 8px 12px 8px 36px;
            font-size: 0.84rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            outline: none;
            background: #ffffff;
            transition: all 0.15s ease;
            font-family: inherit;
        }

        .search-input:focus {
            border-color: var(--brand-accent);
            box-shadow: 0 0 0 3px rgba(42, 125, 181, 0.15);
        }

        /* Company Dropdown Accordion Item */
        .companies-tree-list {
            list-style: none;
            padding: 12px 10px;
            flex: 1;
            overflow-y: auto;
        }

        .company-item {
            margin-bottom: 8px;
            border-radius: var(--radius-md);
            overflow: hidden;
            border: 1px solid var(--border-subtle);
            background: #ffffff;
            transition: border-color 0.15s ease;
        }

        .company-item.open {
            border-color: #cbd5e1;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
        }

        .company-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 11px 14px;
            cursor: pointer;
            background: #f8fafc;
            user-select: none;
            transition: background 0.15s ease;
        }

        .company-header:hover {
            background: #f1f5f9;
        }

        .company-info-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 0;
        }

        .company-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: var(--brand-primary);
            flex-shrink: 0;
        }

        .company-title {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--brand-dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .company-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .badge-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
            background: #e2e8f0;
            color: #475569;
            min-width: 24px;
            text-align: center;
            line-height: 1.2;
        }

        .chevron-icon {
            transition: transform 0.2s ease;
            color: var(--text-light);
        }

        .company-item.open .chevron-icon {
            transform: rotate(180deg);
        }

        /* Projects Under Company */
        .projects-dropdown-list {
            list-style: none;
            padding: 6px 8px 8px 8px;
            background: #ffffff;
            border-top: 1px solid var(--border-subtle);
            display: none;
        }

        .company-item.open .projects-dropdown-list {
            display: block;
        }

        .project-item-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            margin-bottom: 3px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            color: var(--text-main);
            font-size: 0.84rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
            position: relative;
        }

        .project-item-link:hover {
            background-color: var(--brand-light);
            color: var(--brand-primary);
        }

        .project-item-link.active {
            background-color: var(--brand-primary);
            color: #ffffff;
            font-weight: 600;
        }

        .project-item-link.active .badge-count {
            background: rgba(255,255,255,0.25);
            color: #ffffff;
        }

        .project-name-text {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-right: 8px;
        }

        .add-project-quick-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            width: 100%;
            padding: 7px 12px;
            margin-top: 4px;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--brand-accent);
            background: transparent;
            border: 1px dashed #cbd5e1;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .add-project-quick-btn:hover {
            background: #f0f7fc;
            border-color: var(--brand-accent);
            color: var(--brand-primary);
        }

        /* -------------------------------------------------------------
           RIGHT SECTION: Main Project View & Task Management
           ------------------------------------------------------------- */
        .freelance-main-content {
            flex: 1;
            padding: 28px 36px 0 36px;
            display: flex;
            flex-direction: column;
            gap: 24px;
            width: 100%;
            min-width: 0;
            min-height: calc(100vh - var(--topbar-height));
        }

        /* Project Banner Card */
        .project-hero-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            box-shadow: var(--shadow-card);
            position: relative;
            border-top: 5px solid var(--brand-primary);
        }

        .project-breadcrumbs {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .hero-top-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }

        .project-title-area h2 {
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--brand-dark);
            letter-spacing: -0.02em;
            margin-bottom: 6px;
        }

        .project-description-text {
            font-size: 0.92rem;
            color: #475569;
            max-width: 780px;
            line-height: 1.55;
            margin-top: 6px;
        }

        /* Project Action Buttons */
        .project-actions-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Metric Highlights Bar */
        .project-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid var(--border-subtle);
        }

        .stat-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-md);
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            background: var(--brand-light);
            color: var(--brand-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-meta .stat-label {
            font-size: 0.74rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }

        .stat-meta .stat-value {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--brand-dark);
            font-family: 'JetBrains Mono', monospace;
            line-height: 1.2;
            margin-top: 2px;
        }

        .stat-highlight {
            background: linear-gradient(135deg, #12466f 0%, #1d6fa5 100%);
            border-color: transparent;
            color: #ffffff;
        }

        .stat-highlight .stat-icon {
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
        }

        .stat-highlight .stat-meta .stat-label {
            color: rgba(255, 255, 255, 0.85);
        }

        .stat-highlight .stat-meta .stat-value {
            color: #ffffff;
        }

        /* -------------------------------------------------------------
           TASK SECTION & LIST
           ------------------------------------------------------------- */
        .tasks-container-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .tasks-container-header h3 {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--brand-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .tasks-filter-bar {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-btn {
            font-size: 0.8rem;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            background: #ffffff;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .filter-btn.active, .filter-btn:hover {
            background: var(--brand-primary);
            color: #ffffff;
            border-color: var(--brand-primary);
        }

        .tasks-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        /* Collapsible Task Card Item */
        .task-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-subtle);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .task-card:hover {
            border-color: #b0c4de;
            box-shadow: var(--shadow-card);
        }

        .task-card.expanded {
            border-color: var(--brand-accent);
            box-shadow: 0 4px 16px rgba(18, 70, 111, 0.12);
        }

        /* Task Header (Always Visible) */
        .task-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            cursor: pointer;
            user-select: none;
            background: #ffffff;
            gap: 16px;
        }

        .task-header:hover {
            background: #fafcff;
        }

        .task-title-group {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            min-width: 0;
        }

        .task-status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: var(--success);
            flex-shrink: 0;
        }

        .task-status-indicator.in_progress {
            background-color: var(--warning);
        }

        .task-status-indicator.pending {
            background-color: var(--text-light);
        }

        .task-title {
            font-size: 0.98rem;
            font-weight: 700;
            color: var(--brand-dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .task-summary-badges {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .task-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.78rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
            background: #f1f5f9;
            color: #475569;
            font-family: 'JetBrains Mono', monospace;
        }

        .task-badge-hours {
            background: #e0f2fe;
            color: #0369a1;
        }

        .task-badge-price {
            background: #ecfdf5;
            color: #047857;
            font-weight: 700;
        }

        .task-toggle-icon {
            transition: transform 0.25s ease;
            color: var(--text-light);
            display: flex;
            align-items: center;
        }

        .task-card.expanded .task-toggle-icon {
            transform: rotate(180deg);
            color: var(--brand-primary);
        }

        /* Collapsible Body (Hidden by default, smooth expand) */
        .task-details-pane {
            display: none;
            padding: 0 20px 20px 20px;
            border-top: 1px solid var(--border-subtle);
            background: #fcfdfe;
            animation: fadeIn 0.25s ease forwards;
        }

        .task-card.expanded .task-details-pane {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .task-metrics-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin: 16px 0;
            background: #ffffff;
            padding: 14px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-subtle);
        }

        .metric-item {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .metric-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-muted);
        }

        .metric-value {
            font-size: 0.94rem;
            font-weight: 700;
            color: var(--brand-dark);
            font-family: 'JetBrains Mono', monospace;
        }

        .task-description-box {
            background: #ffffff;
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            padding: 14px 18px;
            font-size: 0.9rem;
            line-height: 1.6;
            color: #334155;
            white-space: pre-wrap;
            margin-bottom: 16px;
        }

        .task-actions-row {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 56px 24px;
            background: linear-gradient(180deg, #ffffff 0%, #fcfdfe 100%);
            border: 1.5px dashed #cbd5e1;
            border-radius: var(--radius-lg);
            color: var(--text-muted);
            box-shadow: 0 4px 16px -4px rgba(18, 70, 111, 0.04);
            transition: all 0.25s ease;
        }

        .empty-state:hover {
            border-color: #94a3b8;
        }

        .empty-state-icon-wrapper {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px auto;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(18, 70, 111, 0.08) 0%, rgba(29, 111, 165, 0.04) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(18, 70, 111, 0.12);
        }

        .empty-state-icon-wrapper svg {
            width: 30px;
            height: 30px;
            color: var(--brand-primary);
            margin-bottom: 0;
        }

        .empty-state h4 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.2rem;
            color: var(--brand-dark);
            font-weight: 600;
            letter-spacing: -0.01em;
            margin-bottom: 8px;
        }

        .empty-state p {
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            font-weight: 400;
            color: var(--text-muted);
            max-width: 480px;
            line-height: 1.6;
            margin: 0 auto 20px auto;
        }

        /* Minimal & Beautiful Action Buttons */
        .empty-state-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .btn-minimal-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 22px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.88rem;
            font-weight: 500;
            letter-spacing: 0.01em;
            color: #ffffff;
            background: linear-gradient(135deg, #12466f 0%, #1a5b8e 100%);
            border: 1px solid #12466f;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(18, 70, 111, 0.18), 0 1px 2px rgba(18, 70, 111, 0.12);
            transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
            text-decoration: none;
            user-select: none;
        }

        .btn-minimal-primary:hover {
            background: linear-gradient(135deg, #0f3c5f 0%, #164f7c 100%);
            box-shadow: 0 6px 16px rgba(18, 70, 111, 0.28);
            transform: translateY(-1.5px);
            color: #ffffff;
        }

        .btn-minimal-primary:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(18, 70, 111, 0.18);
        }

        .btn-minimal-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 20px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.88rem;
            font-weight: 500;
            letter-spacing: 0.01em;
            color: #1e3b4f;
            background: #ffffff;
            border: 1px solid #d5e0ea;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
            text-decoration: none;
            user-select: none;
        }

        .btn-minimal-secondary:hover {
            background: #f6f9fc;
            border-color: #12466f;
            color: #12466f;
            box-shadow: 0 4px 14px rgba(18, 70, 111, 0.1);
            transform: translateY(-1.5px);
        }

        .btn-minimal-secondary:active {
            transform: translateY(0);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        /* Dedicated Currency & Pricing Section */
        .project-currency-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 12px 18px;
            margin-top: 18px;
            box-shadow: var(--shadow-subtle);
        }

        .currency-selector-group {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .currency-selector-label {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 0.84rem;
            font-weight: 600;
            color: var(--brand-dark);
            font-family: 'Poppins', sans-serif;
        }

        .currency-selector-label svg {
            color: var(--brand-primary);
        }

        .currency-pills {
            display: inline-flex;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 3px;
            gap: 4px;
        }

        .currency-pill-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
            background: transparent;
            padding: 6px 14px;
            border-radius: 6px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.82rem;
            font-weight: 500;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
        }

        .currency-pill-btn:hover {
            color: var(--brand-primary);
            background: rgba(255, 255, 255, 0.7);
        }

        .currency-pill-btn.active {
            background: var(--brand-primary);
            color: #ffffff;
            font-weight: 600;
            box-shadow: 0 2px 6px rgba(18, 70, 111, 0.25);
        }

        .currency-pill-btn .curr-flag {
            font-size: 0.95rem;
            line-height: 1;
        }

        .currency-rate-setter {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .currency-rate-info {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.86rem;
            font-family: 'Poppins', sans-serif;
        }

        .currency-rate-info .rate-label {
            color: var(--text-muted);
        }

        .currency-rate-info .rate-value {
            color: var(--brand-primary);
            font-size: 0.96rem;
            font-weight: 700;
        }

        /* -------------------------------------------------------------
           MODALS (Forms for Company, Project, Task, Share, Login)
           ------------------------------------------------------------- */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(10, 32, 53, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }

        .modal-backdrop.open {
            display: flex;
        }

        .modal-box {
            background: #ffffff;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 560px;
            box-shadow: var(--shadow-modal);
            overflow: hidden;
            animation: modalPop 0.22s cubic-bezier(0.16, 1, 0.3, 1);
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        @keyframes modalPop {
            from { opacity: 0; transform: scale(0.95) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal-header {
            padding: 18px 24px;
            background: #f8fafc;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--brand-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            color: var(--text-light);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4px;
            border-radius: 4px;
            transition: color 0.15s;
        }

        .modal-close-btn:hover {
            color: var(--danger);
            background: #fee2e2;
        }

        .modal-body {
            padding: 22px 24px;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 16px 24px;
            background: #f8fafc;
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--brand-dark);
            margin-bottom: 6px;
            letter-spacing: 0.01em;
        }

        .form-control {
            width: 100%;
            padding: 9px 13px;
            font-size: 0.88rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            outline: none;
            background: #ffffff;
            font-family: inherit;
            color: var(--text-main);
            transition: all 0.15s ease;
        }

        .form-control:focus {
            border-color: var(--brand-accent);
            box-shadow: 0 0 0 3px rgba(42, 125, 181, 0.15);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .calc-preview-bar {
            background: var(--brand-light);
            border: 1px solid #c8e0f2;
            border-radius: var(--radius-md);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 10px;
        }

        .calc-preview-bar span {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--brand-primary);
        }

        .calc-preview-bar strong {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--brand-dark);
            font-family: 'JetBrains Mono', monospace;
        }

        /* -------------------------------------------------------------
           MANAGE COMPANIES & PROJECTS LIST MODAL
           ------------------------------------------------------------- */
        .modal-box-lg {
            max-width: 860px;
        }

        .manage-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .manage-search-input {
            flex: 1;
            min-width: 220px;
        }

        .manage-company-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            margin-bottom: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .manage-company-card:hover {
            border-color: #b8d4e9;
            box-shadow: 0 4px 12px rgba(18, 70, 111, 0.06);
        }

        .manage-company-header {
            background: #f8fafc;
            padding: 12px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1px solid var(--border-subtle);
            flex-wrap: wrap;
        }

        .manage-company-info {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 220px;
        }

        .manage-company-title {
            font-size: 0.98rem;
            font-weight: 800;
            color: #1e3b4f;
        }

        .manage-company-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-left: 4px;
        }

        .manage-company-actions {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .manage-projects-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .manage-project-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 11px 18px 11px 28px;
            border-bottom: 1px solid var(--border-subtle);
            gap: 12px;
            flex-wrap: wrap;
            transition: background 0.1s ease;
        }

        .manage-project-item:last-child {
            border-bottom: none;
        }

        .manage-project-item:hover {
            background: #fbfcfe;
        }

        .manage-project-main {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 240px;
        }

        .manage-project-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #1e3b4f;
        }

        .manage-project-meta {
            font-size: 0.8rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .manage-project-actions {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-action-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: #ffffff;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
        }

        .btn-action-icon:hover {
            background: #f1f5f9;
            color: var(--brand-primary);
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }

        .btn-action-icon.danger {
            color: #ef4444;
            border-color: #fecaca;
        }

        .btn-action-icon.danger:hover {
            background: #fef2f2;
            color: #dc2626;
            border-color: #f87171;
        }

        /* Quick Delete Button for Projects in Sidebar */
        .project-item-link .btn-quick-delete-project {
            display: none;
            background: transparent;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 2px 5px;
            border-radius: 4px;
            transition: color 0.15s, background 0.15s;
            margin-left: 4px;
        }

        .project-item-link:hover .btn-quick-delete-project {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .project-item-link .btn-quick-delete-project:hover {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.15);
        }

        .company-header .btn-company-quick-del {
            display: none;
            background: transparent;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 2px 4px;
            border-radius: 4px;
            transition: color 0.15s, background 0.15s;
            margin-right: 4px;
        }

        .company-header:hover .btn-company-quick-del {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .company-header .btn-company-quick-del:hover {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.15);
        }

        /* -------------------------------------------------------------
           PUBLIC SHARE / INVOICE VIEW (Full Width)
           ------------------------------------------------------------- */
        .public-share-wrapper {
            max-width: 100%;
            margin: 0;
            padding: 30px 40px 60px 40px;
            width: 100%;
            flex: 1;
            box-sizing: border-box;
        }

        .share-invoice-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 36px 44px;
            box-shadow: var(--shadow-card);
            border-top: 8px solid var(--brand-primary);
            width: 100%;
            box-sizing: border-box;
        }

        .share-badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            background: #eaf2f8;
            color: var(--brand-primary);
            font-size: 0.78rem;
            font-weight: 700;
            border-radius: 30px;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .share-table-wrapper {
            overflow-x: auto;
            margin-top: 24px;
        }

        .share-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
        }

        .share-table th {
            background: #f8fafc;
            color: var(--brand-primary);
            font-weight: 700;
            text-align: left;
            padding: 12px 16px;
            border-bottom: 2px solid var(--border-color);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .share-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-subtle);
            vertical-align: top;
        }

        .share-table tr:hover td {
            background: #fafcff;
        }

        .total-summary-card {
            margin-top: 30px;
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast-msg {
            background: #ffffff;
            border-left: 4px solid var(--brand-primary);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-radius: var(--radius-sm);
            padding: 12px 18px;
            font-size: 0.86rem;
            font-weight: 600;
            color: var(--brand-dark);
            animation: slideIn 0.2s ease forwards;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toast-msg.success {
            border-color: var(--success);
        }

        .toast-msg.error {
            border-color: var(--danger);
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* Mobile drawer toggle */
        .mobile-menu-btn {
            display: none;
            background: transparent;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 8px;
            color: var(--brand-primary);
            cursor: pointer;
        }

        /* Print Media Styles */
        @media print {
            .app-topbar, .project-actions-bar, .sidebar-header, .search-input-wrapper,
            .add-project-quick-btn, .modal-backdrop, .toast-container, .freelance-sidebar,
            .btn, .filter-btn, .task-actions-row, .app-global-footer, .float-menu-wrapper {
                display: none !important;
            }
            body, .freelance-app-container, .freelance-main-content {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .task-details-pane {
                display: block !important;
            }
            .share-invoice-card {
                box-shadow: none !important;
                border: 1px solid #ccc !important;
                padding: 10px !important;
            }
        }

        /* -------------------------------------------------------------
           RESPONSIVE DESIGN (Tablets & Mobile)
           ------------------------------------------------------------- */
        @media (max-width: 1250px) {
            .brand-subtitle {
                display: none !important;
            }
            .topbar-nav-menu {
                gap: 4px;
                margin-left: 10px;
            }
            .topbar-nav-link {
                padding: 6px 10px;
                font-size: 0.8rem;
                gap: 5px;
            }
        }

        @media (max-width: 1100px) {
            .topbar-nav-menu {
                display: none !important;
            }
            .topbar-user-text {
                display: none;
            }
        }

        @media (max-width: 960px) {
            .mobile-menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .freelance-sidebar {
                position: fixed;
                top: var(--topbar-height);
                left: 0;
                height: calc(100vh - var(--topbar-height));
                box-shadow: var(--shadow-modal);
                transform: translateX(-100%);
                z-index: 1100;
            }

            .freelance-sidebar.mobile-open {
                transform: translateX(0);
            }

            .freelance-main-content {
                padding: 20px 16px 0 16px;
            }

            .freelance-main-content .app-global-footer {
                margin-left: -16px;
                margin-right: -16px;
                margin-bottom: 0;
                width: calc(100% + 32px);
            }

            .project-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .public-share-wrapper {
                padding: 24px 20px 48px 20px;
            }
            .share-invoice-card {
                padding: 28px 24px;
            }
        }

        @media (max-width: 860px) {
            .topbar-user-badge {
                display: none !important;
            }
            .app-topbar {
                padding: 0 16px;
                gap: 8px;
            }
        }

        @media (max-width: 768px) {
            :root {
                --topbar-height: 60px;
            }

            .app-topbar {
                padding: 0 12px;
                gap: 8px;
            }

            .topbar-brand-container {
                gap: 8px;
            }

            .brand-badge {
                gap: 8px;
            }

            .brand-logo-icon {
                width: 36px;
                height: 36px;
                border-radius: 8px;
            }

            .brand-logo-icon svg {
                width: 18px;
                height: 18px;
            }

            .brand-title-group h1 {
                font-size: 1.05rem;
            }

            .topbar-actions {
                gap: 6px;
            }

            .btn-topbar-action {
                padding: 7px 10px;
                font-size: 0.82rem;
            }

            .btn-topbar-action .btn-text {
                display: none !important;
            }

            .btn-topbar-action svg {
                margin: 0;
            }
        }

        @media (max-width: 640px) {
            .project-stats-grid {
                grid-template-columns: 1fr;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .task-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            .task-summary-badges {
                width: 100%;
                justify-content: space-between;
            }
            .hero-top-row {
                flex-direction: column;
            }
            .project-actions-bar {
                width: 100%;
            }
            .project-actions-bar .btn {
                flex: 1;
            }
            .public-share-wrapper {
                padding: 16px 12px 36px 12px;
            }
            .share-invoice-card {
                padding: 20px 14px;
                border-radius: var(--radius-lg);
            }
            .freelance-main-content {
                padding: 16px 12px 0 12px;
            }
            .freelance-main-content .app-global-footer {
                margin-left: -12px;
                margin-right: -12px;
                margin-bottom: 0;
                width: calc(100% + 24px);
            }
        }

        @media (max-width: 480px) {
            :root {
                --topbar-height: 56px;
            }

            .app-topbar {
                padding: 0 10px;
                gap: 6px;
            }

            .topbar-brand-container {
                gap: 6px;
            }

            .mobile-menu-btn {
                padding: 6px;
                border-radius: 6px;
            }

            .mobile-menu-btn svg {
                width: 18px;
                height: 18px;
            }

            .brand-badge {
                gap: 7px;
            }

            .brand-logo-icon {
                width: 32px;
                height: 32px;
                border-radius: 7px;
            }

            .brand-logo-icon svg {
                width: 16px;
                height: 16px;
            }

            .brand-title-group h1 {
                font-size: 0.94rem;
            }

            .topbar-actions {
                gap: 4px;
            }

            .btn-topbar-action {
                padding: 6px 8px;
                border-radius: 6px;
            }

            .btn-topbar-action svg {
                width: 14px;
                height: 14px;
            }
        }

        @media (max-width: 360px) {
            .app-topbar {
                padding: 0 6px;
            }

            .brand-title-group h1 {
                font-size: 0.86rem;
            }

            .btn-topbar-action {
                padding: 5px 6px;
            }
        }

        /* =============================================================
           USER REQUIREMENT: Text Color For Project Must Be #1e3b4f
           ============================================================= */
        .project-item-link,
        .project-item-link .project-name-text,
        .project-name-text,
        #heroProjectTitle,
        .project-title-area h2,
        #breadcrumbProject,
        #sharedTitle,
        #projectModalTitle,
        .tasks-heading {
            color: #1e3b4f !important;
        }

        .project-item-link:hover,
        .project-item-link:hover .project-name-text {
            color: #1e3b4f !important;
        }

        .project-item-link.active,
        .project-item-link.active .project-name-text {
            color: #ffffff !important;
        }

        /* =============================================================
           PLATFORM GLOBAL FOOTER (Editable & Dynamic)
           ============================================================= */
        .btn-edit-footer {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #94a3b8;
            padding: 0.35rem 0.8rem;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            font-family: inherit;
        }

        .btn-edit-footer:hover {
            background: rgba(56, 189, 248, 0.18);
            color: #38bdf8;
            border-color: #38bdf8;
        }

        .app-global-footer {
            background: linear-gradient(180deg, #0a2540 0%, #06182a 100%);
            color: #94a3b8;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            margin-top: auto;
            padding: 3.5rem 1.5rem 1.5rem 1.5rem;
            position: relative;
            font-size: 0.9rem;
            width: 100%;
            z-index: 10;
        }

        .freelance-main-content .app-global-footer {
            margin-top: auto;
            margin-left: -36px;
            margin-right: -36px;
            margin-bottom: 0;
            width: calc(100% + 72px);
            border-radius: 0;
            z-index: 10;
        }

        .footer-inner-container {
            max-width: 1150px;
            margin: 0 auto;
            width: 100%;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.4fr 0.9fr 1.1fr 1fr;
            gap: 2.5rem;
            margin-bottom: 3rem;
        }

        .footer-col {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .footer-brand-title {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            margin-bottom: 0.25rem;
        }

        .brand-avatar {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: linear-gradient(135deg, #12466f 0%, #2a7db5 100%);
            color: white;
            font-weight: 800;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(18, 70, 111, 0.35);
            flex-shrink: 0;
        }

        .footer-brand-title h3 {
            color: #ffffff;
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: -0.015em;
        }

        .brand-subtitle {
            font-size: 0.78rem;
            color: #38bdf8;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .brand-bio {
            color: #cbd5e1;
            font-size: 0.86rem;
            line-height: 1.6;
        }

        .availability-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.28);
            color: #4ade80;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.76rem;
            font-weight: 600;
            width: fit-content;
            margin-top: 0.25rem;
        }

        .status-pulse {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 6px #22c55e;
            animation: footerPulse 2s infinite ease-in-out;
        }

        @keyframes footerPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(1.3); }
        }

        .footer-heading {
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            margin-bottom: 0.4rem;
            position: relative;
            padding-bottom: 0.4rem;
        }

        .footer-heading::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 24px;
            height: 2px;
            background: #2a7db5;
            border-radius: 2px;
        }

        .footer-nav-list {
            list-style: none;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .footer-nav-list a {
            color: #94a3b8;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.88rem;
            transition: all 0.15s ease;
        }

        .footer-nav-list a svg {
            color: #38bdf8;
            opacity: 0.8;
            transition: transform 0.15s ease;
        }

        .footer-nav-list a:hover {
            color: #ffffff;
            transform: translateX(3px);
        }

        .footer-nav-list a:hover svg {
            opacity: 1;
            transform: scale(1.1);
        }

        .footer-tech-cloud {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
        }

        .tech-tag-pill {
            background: rgba(255, 255, 255, 0.06);
            color: #cbd5e1;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0.25rem 0.55rem;
            border-radius: 6px;
            font-size: 0.76rem;
            font-weight: 600;
            transition: all 0.15s ease;
        }

        .tech-tag-pill:hover {
            background: rgba(56, 189, 248, 0.15);
            border-color: #38bdf8;
            color: #38bdf8;
        }

        .contact-links-list {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }

        .contact-item-link {
            color: #cbd5e1;
            text-decoration: none;
            font-size: 0.86rem;
            display: flex;
            align-items: center;
            gap: 0.55rem;
            transition: color 0.15s ease;
        }

        .contact-item-link svg {
            color: #38bdf8;
            flex-shrink: 0;
        }

        .contact-item-link:hover {
            color: #38bdf8;
        }

        .contact-item-link.location {
            color: #94a3b8;
        }

        .footer-bottom-bar {
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.82rem;
            color: #64748b;
        }

        .footer-bottom-bar strong {
            color: #cbd5e1;
        }

        .btn-footer-top {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #cbd5e1;
            padding: 0.4rem 0.85rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            font-family: inherit;
        }

        .btn-footer-top:hover {
            background: rgba(56, 189, 248, 0.15);
            border-color: #38bdf8;
            color: #38bdf8;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(10, 32, 53, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .modal-overlay.active {
            display: flex;
        }

        @media (max-width: 900px) {
            .footer-grid {
                grid-template-columns: 1fr 1fr;
                gap: 2rem;
            }
        }

        @media (max-width: 580px) {
            .footer-grid {
                grid-template-columns: 1fr;
                gap: 1.75rem;
            }
            .app-global-footer {
                padding: 2.5rem 1.25rem 1.25rem 1.25rem;
            }
            .footer-bottom-bar {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        /* =============================================================
           PLATFORM NAVIGATION MENU (Topbar, Sidebar & Floating FAB)
           ============================================================= */
        /* Topbar Nav Menu */
        .topbar-nav-menu {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-left: 20px;
        }

        .topbar-nav-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 13px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-size: 0.84rem;
            font-weight: 600;
            color: var(--text-muted);
            transition: all 0.15s ease;
        }

        .topbar-nav-link:hover {
            background: #f1f5f9;
            color: var(--brand-primary);
        }

        .topbar-nav-link.active {
            background: #eaf2f8;
            color: var(--brand-primary);
            font-weight: 700;
        }

        @media (max-width: 1024px) {
            .topbar-nav-menu {
                display: none;
            }
        }

        /* Sidebar Platform Navigation Section */
        .sidebar-nav-section {
            padding: 12px 14px 14px 14px;
            border-bottom: 1px solid var(--border-subtle);
            background: #fafcff;
        }

        .sidebar-nav-header {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--brand-primary);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 8px;
        }

        .sidebar-nav-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
        }

        .sidebar-nav-tab {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 7px 10px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-muted);
            background: #ffffff;
            border: 1px solid var(--border-color);
            transition: all 0.15s ease;
        }

        .sidebar-nav-tab:hover {
            background: #f8fafc;
            color: var(--brand-primary);
            border-color: #cbd5e1;
        }

        .sidebar-nav-tab.active {
            background: #eaf2f8;
            color: var(--brand-primary);
            border-color: #b8d4e9;
            font-weight: 700;
        }

        /* Floating Navigation FAB & Panel */
        .float-menu-fab {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: var(--brand-gradient);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 6px 20px rgba(18, 70, 111, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1200;
            transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s ease, background-color 0.2s ease;
            outline: none;
        }

        .float-menu-fab:hover {
            transform: scale(1.08) translateY(-2px);
            box-shadow: 0 8px 26px rgba(18, 70, 111, 0.45);
        }

        .float-menu-fab.active {
            transform: rotate(90deg);
            background: var(--brand-dark);
            border-color: #38bdf8;
        }

        .float-menu-backdrop {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(10, 37, 64, 0.2);
            backdrop-filter: blur(2px);
            z-index: 1190;
            display: none;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .float-menu-backdrop.active {
            display: block;
            opacity: 1;
        }

        .float-menu-panel {
            position: fixed;
            bottom: 5.75rem;
            right: 1.75rem;
            width: 290px;
            max-width: calc(100vw - 2.5rem);
            background: rgba(255, 255, 255, 0.98);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: 0 16px 40px rgba(10, 37, 64, 0.22);
            z-index: 1200;
            display: none;
            flex-direction: column;
            overflow: hidden;
            backdrop-filter: blur(12px);
            animation: floatMenuIn 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .float-menu-panel.active {
            display: flex;
        }

        @keyframes floatMenuIn {
            from { opacity: 0; transform: translateY(12px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .float-menu-header {
            padding: 0.85rem 1.1rem;
            background: linear-gradient(135deg, #0a2540 0%, #06182a 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .float-menu-title {
            font-size: 0.9rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .float-menu-body {
            padding: 0.75rem 0.6rem;
            max-height: 70vh;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }

        .float-menu-group-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            padding: 0.2rem 0.6rem 0.1rem 0.6rem;
        }

        .float-menu-item {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.55rem 0.75rem;
            color: var(--text-main);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.15s ease;
            background: transparent;
            border: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
            font-family: inherit;
        }

        .float-menu-item:hover {
            background: var(--brand-light);
            color: var(--brand-primary);
            transform: translateX(3px);
        }

        .float-menu-item svg {
            color: var(--brand-accent);
            flex-shrink: 0;
        }

        .float-menu-divider {
            height: 1px;
            background: var(--border-color);
            margin: 0.25rem 0.5rem;
        }

        .float-menu-badge {
            margin-left: auto;
            font-size: 0.72rem;
            padding: 0.15rem 0.45rem;
            border-radius: 12px;
            background: #e2e8f0;
            color: #475569;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <!-- TOP HEADER -->
    <header class="app-topbar">
        <div class="topbar-brand-container">
            <button type="button" class="mobile-menu-btn" id="mobileMenuToggle" title="Toggle Companies & Projects Menu">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </button>
            <a href="freelance.php" class="brand-badge">
                <div class="brand-logo-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                </div>
                <div class="brand-title-group">
                    <h1>Freelance Hub</h1>
                    <p class="brand-subtitle">Projects, Work Breakdown &amp; Hourly Timesheets</p>
                </div>
            </a>
        </div>

        <!-- Platform Top Navigation Menu -->
        <nav class="topbar-nav-menu">
            <a href="index.php" class="topbar-nav-link" title="Questions & Knowledge Base">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                <span>Questions Base</span>
            </a>
            <a href="projects.php" class="topbar-nav-link" title="Projects Portfolio">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                <span>Projects Portfolio</span>
            </a>
            <a href="freelance.php" class="topbar-nav-link active" title="Freelance Hub (Current)">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                <span>Freelance Hub</span>
            </a>
            <a href="profile.php" class="topbar-nav-link" title="Resume & Experience">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                <span>Resume &amp; CV</span>
            </a>
        </nav>

        <div class="topbar-actions">
            <?php if ($isLoggedIn): ?>
                <span class="topbar-user-badge" title="Authenticated as <?= htmlspecialchars(Auth::getCurrentUser()) ?>">
                    <span class="user-status-dot"></span>
                    <span class="topbar-user-text"><span class="user-prefix">Logged in as </span><strong><?= htmlspecialchars(Auth::getCurrentUser()) ?></strong></span>
                </span>
                <button type="button" class="btn btn-secondary btn-sm btn-topbar-action" onclick="openManageListModal()" title="List to Manage Companies & Projects">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                    <span class="btn-text">Manage List</span>
                </button>
                <button type="button" class="btn btn-primary btn-sm btn-topbar-action" id="btnOpenAddCompany" title="Create New Company">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    <span class="btn-text">New Company</span>
                </button>
                <a href="freelance.php?action=logout" class="btn btn-secondary btn-sm btn-topbar-action" title="Sign Out">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    <span class="btn-text">Logout</span>
                </a>
            <?php else: ?>
                <a href="projects.php" class="btn btn-secondary btn-sm btn-topbar-action" title="Projects Portfolio">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                    <span class="btn-text">Projects</span>
                </a>
                <button type="button" class="btn btn-primary btn-sm btn-topbar-action" id="btnOpenLoginModal" title="Freelancer Login">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    <span class="btn-text">Freelancer Login</span>
                </button>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($isPublicMode): ?>
        <!-- =============================================================
             PUBLIC SHAREABLE CLIENT VIEW ("Share Link with Everybody")
             ================================----------------------------- -->
        <main class="public-share-wrapper">
            <div class="share-invoice-card" id="publicShareContainer">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px; margin-bottom:20px;">
                    <div>
                        <div class="share-badge-pill">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            Verified Freelance Timesheet &amp; Invoice
                        </div>
                        <h2 style="font-size:1.8rem; font-weight:800; color:var(--brand-dark); letter-spacing:-0.02em;" id="sharedTitle">
                            Loading Project Breakdown...
                        </h2>
                        <p style="color:var(--text-muted); font-size:0.95rem; margin-top:4px;" id="sharedSubtitle"></p>
                    </div>

                    <button type="button" class="btn btn-primary" onclick="window.print();">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                        <span>Print / Export PDF</span>
                    </button>
                </div>

                <div id="sharedDescription" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:var(--radius-md); padding:16px 20px; font-size:0.92rem; color:#334155; margin-bottom:24px;"></div>

                <h3 style="font-size:1.15rem; font-weight:700; color:var(--brand-dark); margin-bottom:12px; display:flex; align-items:center; gap:8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                    Completed Tasks &amp; Itemized Bill
                </h3>

                <div class="share-table-wrapper">
                    <table class="share-table">
                        <thead>
                            <tr>
                                <th style="width:25%;">Task &amp; Description</th>
                                <th style="width:15%;">Date</th>
                                <th style="width:20%;">Start - End Time</th>
                                <th style="width:15%;">Rate / Hr (تومان)</th>
                                <th style="width:10%;">Hours</th>
                                <th style="width:15%; text-align:right;">Subtotal (تومان)</th>
                            </tr>
                        </thead>
                        <tbody id="sharedTasksTbody">
                            <tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">Loading tasks data...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="total-summary-card">
                    <div>
                        <div style="font-size:0.82rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Overall Billable Hours</div>
                        <div style="font-size:1.5rem; font-weight:800; color:var(--brand-dark); font-family:'JetBrains Mono', monospace;" id="sharedTotalHours">0.00 hrs</div>
                    </div>
                    <div>
                        <div style="font-size:0.82rem; font-weight:700; color:var(--brand-primary); text-transform:uppercase;">Grand Total Price (مبلغ کل)</div>
                        <div style="font-size:2.1rem; font-weight:800; color:var(--brand-primary); font-family:'JetBrains Mono', monospace;" id="sharedTotalPrice">0 تومان</div>
                    </div>
                </div>
            </div>
        </main>

    <?php elseif (!$isLoggedIn): ?>
        <!-- =============================================================
             UNAUTHENTICATED VIEW -> Sleek Login Card
             ================================----------------------------- -->
        <main style="display:flex; flex-direction:column; align-items:center; justify-content:center; flex:1; padding:40px 20px;">
            <div style="background:#ffffff; border:1px solid var(--border-color); border-radius:var(--radius-xl); padding:36px; max-width:440px; width:100%; box-shadow:var(--shadow-card); border-top:6px solid var(--brand-primary);">
                <div style="text-align:center; margin-bottom:24px;">
                    <div style="width:54px; height:54px; border-radius:var(--radius-md); background:var(--brand-gradient); color:#ffffff; display:inline-flex; align-items:center; justify-content:center; margin-bottom:12px; box-shadow:0 6px 16px rgba(18, 70, 111, 0.25);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    </div>
                    <h2 style="font-size:1.4rem; font-weight:800; color:var(--brand-dark);">Freelancer Sign In</h2>
                    <p style="font-size:0.86rem; color:var(--text-muted); margin-top:4px;">Log in to access companies, projects, and hourly tasks</p>
                </div>

                <form id="standaloneLoginForm">
                    <div class="form-group">
                        <label for="loginUser">Username</label>
                        <input type="text" id="loginUser" class="form-control" required placeholder="Enter username (e.g. admin)">
                    </div>
                    <div class="form-group">
                        <label for="loginPass">Password</label>
                        <input type="password" id="loginPass" class="form-control" required placeholder="Enter password">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; padding:12px; margin-top:8px;">
                        <span>Sign In</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </button>
                </form>
            </div>
        </main>

    <?php else: ?>
        <!-- =============================================================
             AUTHENTICATED 2-SECTION FREELANCE WORKSPACE
             ================================----------------------------- -->
        <div class="freelance-app-container">

            <!-- ---------------------------------------------------------
                 LEFT SECTION: Company Dropdown & Projects List
                 --------------------------------------------------------- -->
            <aside class="freelance-sidebar" id="appSidebar">
                <div class="sidebar-header">
                    <h2>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                        Companies &amp; Projects
                    </h2>
                    <div style="display:flex; align-items:center; gap:4px;">
                        <button type="button" class="btn btn-ghost btn-sm" id="btnSidebarManageList" onclick="openManageListModal()" title="Manage Companies & Projects (List / Delete / Edit)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                        </button>
                        <button type="button" class="btn btn-ghost btn-sm" id="btnSidebarAddCompany" title="Add Company">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                        </button>
                    </div>
                </div>

                <!-- Platform Navigation Menu in Left Section -->
                <div class="sidebar-nav-section">
                    <div class="sidebar-nav-header">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                        <span>Platform Pages</span>
                    </div>
                    <div class="sidebar-nav-grid">
                        <a href="index.php" class="sidebar-nav-tab" title="Questions & Knowledge Base">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                            <span>Questions</span>
                        </a>
                        <a href="projects.php" class="sidebar-nav-tab" title="Projects Portfolio">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                            <span>Projects</span>
                        </a>
                        <a href="freelance.php" class="sidebar-nav-tab active" title="Freelance Hub (Active)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                            <span>Freelance</span>
                        </a>
                        <a href="profile.php" class="sidebar-nav-tab" title="Resume & Experience">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                            <span>Resume</span>
                        </a>
                    </div>
                </div>

                <div class="sidebar-search">
                    <div class="search-input-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" id="filterProjectsInput" class="search-input" placeholder="Search companies or projects...">
                    </div>
                </div>

                <ul class="companies-tree-list" id="companiesTreeList">
                    <!-- Populated dynamically via JS API -->
                    <li style="text-align:center; padding:20px; color:var(--text-muted); font-size:0.86rem;">
                        Loading workspaces...
                    </li>
                </ul>
            </aside>

            <!-- ---------------------------------------------------------
                 RIGHT SECTION: Project Details, Actions & Task Accordion
                 --------------------------------------------------------- -->
            <main class="freelance-main-content">
                
                <!-- Project Hero Card -->
                <div class="project-hero-card" id="projectHeroCard" style="display:none;">
                    <div class="project-breadcrumbs">
                        <span id="breadcrumbCompany">Company</span>
                        <span>/</span>
                        <span style="color:#1e3b4f; font-weight:700;" id="breadcrumbProject">Project</span>
                    </div>

                    <div class="hero-top-row">
                        <div class="project-title-area">
                            <h2 id="heroProjectTitle">Select a Project</h2>
                            <p class="project-description-text" id="heroProjectDesc"></p>
                        </div>

                        <!-- Project Action Buttons -->
                        <div class="project-actions-bar">
                            <button type="button" class="btn btn-primary" id="btnCreateTask">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                <span>Create Task</span>
                            </button>

                            <button type="button" class="btn btn-secondary" id="btnShareProject">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
                                <span>Share Link</span>
                            </button>

                            <button type="button" class="btn btn-secondary" id="btnEditProject">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                <span>Edit Project</span>
                            </button>

                            <button type="button" class="btn btn-danger" id="btnDeleteProject">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                <span>Delete Project</span>
                            </button>
                        </div>
                    </div>

                    <!-- Dedicated Currency & Pricing Section -->
                    <div class="project-currency-section">
                        <div class="currency-selector-group">
                            <span class="currency-selector-label">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="6" x2="12" y2="18"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                                <span>Currency (واحد پول):</span>
                            </span>
                            <div class="currency-pills" id="projectCurrencyPills">
                                <button type="button" class="currency-pill-btn active" data-currency="$" onclick="handleCurrencySelect('$')" title="Set currency to US Dollar ($)">
                                    <span class="curr-flag">💵</span>
                                    <span>Dollar ($)</span>
                                </button>
                                <button type="button" class="currency-pill-btn" data-currency="تومان" onclick="handleCurrencySelect('تومان')" title="Set currency to Iranian Toman (تومان)">
                                    <span class="curr-flag">🇮🇷</span>
                                    <span>Toman (تومان)</span>
                                </button>
                                <button type="button" class="currency-pill-btn" data-currency="€" onclick="handleCurrencySelect('€')" title="Set currency to Euro (€)">
                                    <span class="curr-flag">💶</span>
                                    <span>Euro (€)</span>
                                </button>
                            </div>
                        </div>

                        <div class="currency-rate-setter">
                            <span class="currency-rate-info">
                                <span class="rate-label">Hourly Rate:</span>
                                <strong class="rate-value" id="currencySectionRateDisplay">$0.00 / hr</strong>
                            </span>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="openSetRateModal()" title="Set or adjust hourly rate according to selected currency">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                <span>Set Price (تنظیم نرخ)</span>
                            </button>
                        </div>
                    </div>

                    <!-- Metric Highlights Bar -->
                    <div class="project-stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                            </div>
                            <div class="stat-meta">
                                <div class="stat-label">Total Tasks</div>
                                <div class="stat-value" id="statTaskCount">0</div>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            </div>
                            <div class="stat-meta">
                                <div class="stat-label">Total Hours</div>
                                <div class="stat-value" id="statTotalHours">0.00h</div>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                            </div>
                            <div class="stat-meta">
                                <div class="stat-label">Hourly Rate (نرخ ساعتی)</div>
                                <div class="stat-value" id="statHourlyRate">0 تومان / ساعت</div>
                            </div>
                        </div>

                        <div class="stat-card stat-highlight">
                            <div class="stat-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                            </div>
                            <div class="stat-meta">
                                <div class="stat-label">Total Price (مبلغ کل)</div>
                                <div class="stat-value" id="statTotalPrice">0 تومان</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State (Shown before a project is selected) -->
                <div class="empty-state" id="projectEmptyState">
                    <div class="empty-state-icon-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    <h4>Select a Company Project</h4>
                    <p>Choose a project from the left sidebar to view logged tasks, track billable hours, and generate client share links.</p>
                    <div class="empty-state-actions" style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap; margin-top:16px;">
                        <button type="button" class="btn-minimal-primary" onclick="openCreateCompanyModal()">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            <span>Add New Company</span>
                        </button>
                        <button type="button" class="btn-minimal-secondary" onclick="openManageListModal()">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                            <span>Manage Companies &amp; Projects</span>
                        </button>
                    </div>
                </div>

                <!-- Task List Section -->
                <div id="tasksListSection" style="display:none;">
                    <div class="tasks-container-header">
                        <h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                            Project Tasks Breakdown
                        </h3>

                        <div class="tasks-filter-bar">
                            <button type="button" class="filter-btn active" data-filter="all">All Tasks</button>
                            <button type="button" class="filter-btn" data-filter="completed">Completed</button>
                            <button type="button" class="filter-btn" data-filter="in_progress">In Progress</button>
                        </div>
                    </div>

                    <!-- Tasks Container -->
                    <div class="tasks-list" id="tasksListContainer" style="margin-top:16px;">
                        <!-- Dynamically populated task cards -->
                    </div>
                </div>

                <!-- Platform Global Footer for Authenticated Workspace -->
                <?php
                if (function_exists('renderGlobalFooter')) {
                    renderGlobalFooter();
                }
                ?>

            </main>
        </div>
    <?php endif; ?>

    <!-- Platform Global Footer for Public & Login Views -->
    <?php
    if (($isPublicMode || !$isLoggedIn) && function_exists('renderGlobalFooter')) {
        renderGlobalFooter();
    }
    ?>

    <!-- =============================================================
         MODALS
         ================================----------------------------- -->
    <?php if ($isLoggedIn): ?>
        <!-- 1. CREATE / EDIT COMPANY MODAL -->
        <div class="modal-backdrop" id="companyModal">
            <div class="modal-box">
                <div class="modal-header">
                    <h3 id="companyModalTitle">Create New Company</h3>
                    <button type="button" class="modal-close-btn" onclick="closeModal('companyModal')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>
                <form id="companyForm">
                    <div class="modal-body">
                        <input type="hidden" id="companyId" value="">
                        <div class="form-group">
                            <label for="companyName">Company / Client Name *</label>
                            <input type="text" id="companyName" class="form-control" required placeholder="e.g. Acme Corporation">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="companyClientName">Contact Person</label>
                                <input type="text" id="companyClientName" class="form-control" placeholder="e.g. John Doe">
                            </div>
                            <div class="form-group">
                                <label for="companyClientEmail">Contact Email</label>
                                <input type="email" id="companyClientEmail" class="form-control" placeholder="client@example.com">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="companyColor">Brand Accent Color</label>
                            <input type="color" id="companyColor" class="form-control" style="height:42px; padding:3px;" value="#12466f">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('companyModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="btnSaveCompany">Save Company</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 2. CREATE / EDIT PROJECT MODAL -->
        <div class="modal-backdrop" id="projectModal">
            <div class="modal-box">
                <div class="modal-header">
                    <h3 id="projectModalTitle">Create Project</h3>
                    <button type="button" class="modal-close-btn" onclick="closeModal('projectModal')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>
                <form id="projectForm">
                    <div class="modal-body">
                        <input type="hidden" id="projectId" value="">
                        <div class="form-group">
                            <label for="projectCompanySelect">Company *</label>
                            <select id="projectCompanySelect" class="form-control" required></select>
                        </div>
                        <div class="form-group">
                            <label for="projectTitle">Project Title *</label>
                            <input type="text" id="projectTitle" class="form-control" required placeholder="e.g. E-Commerce Platform Redesign">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="projectCurrency">Currency (واحد پول) *</label>
                                <select id="projectCurrency" class="form-control" onchange="handleProjectModalCurrencyChange(this.value)">
                                    <option value="$">💵 US Dollar ($ USD)</option>
                                    <option value="تومان">🇮🇷 Iranian Toman (تومان)</option>
                                    <option value="€">💶 Euro (€ EUR)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="projectHourlyRate" id="projectHourlyRateLabel">Default Hourly Rate ($ / hr) *</label>
                                <input type="number" step="any" min="0" id="projectHourlyRate" class="form-control" required value="50" placeholder="e.g. 50.00">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="projectStatus">Project Status</label>
                            <select id="projectStatus" class="form-control">
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="on_hold">On Hold</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="projectDescription">Description &amp; Goals</label>
                            <textarea id="projectDescription" class="form-control" rows="3" placeholder="Brief details about the project requirements..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('projectModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="btnSaveProject">Save Project</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 2.5 QUICK SET CURRENCY & RATE MODAL -->
        <div class="modal-backdrop" id="setCurrencyRateModal">
            <div class="modal-box" style="max-width: 480px;">
                <div class="modal-header">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        Select Currency &amp; Set Price
                    </h3>
                    <button type="button" class="modal-close-btn" onclick="closeModal('setCurrencyRateModal')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>
                <form id="setCurrencyRateForm">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="quickCurrencySelect">Currency Type (نوع واحد پول) *</label>
                            <select id="quickCurrencySelect" class="form-control" onchange="handleQuickCurrencyChange(this.value)">
                                <option value="$">💵 US Dollar ($ USD)</option>
                                <option value="تومان">🇮🇷 Iranian Toman (تومان)</option>
                                <option value="€">💶 Euro (€ EUR)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="quickHourlyRateInput" id="quickHourlyRateLabel">Hourly Rate ($ / hr) *</label>
                            <input type="number" step="any" min="0" id="quickHourlyRateInput" class="form-control" required placeholder="e.g. 65">
                            <small style="color:var(--text-muted); font-size:0.8rem; margin-top:4px; display:block;" id="quickRateHelpText">Set the hourly rate according to the selected currency.</small>
                        </div>

                        <div class="form-group" style="margin-top:14px; background:#f8fafc; padding:10px 12px; border-radius:8px; border:1px solid #e2e8f0;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:0.86rem; font-weight:500; margin-bottom:0;">
                                <input type="checkbox" id="quickApplyToExistingTasks" checked style="width:16px; height:16px; cursor:pointer;">
                                <span>Also update hourly price on all existing tasks in this project</span>
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('setCurrencyRateModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="btnSaveQuickCurrency">Apply Currency &amp; Price</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 3. CREATE / EDIT TASK MODAL (With Live Duration & Price Calculation) -->
        <div class="modal-backdrop" id="taskModal">
            <div class="modal-box">
                <div class="modal-header">
                    <h3 id="taskModalTitle">Create Task</h3>
                    <button type="button" class="modal-close-btn" onclick="closeModal('taskModal')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>
                <form id="taskForm">
                    <div class="modal-body">
                        <input type="hidden" id="taskId" value="">
                        
                        <div class="form-group">
                            <label for="taskTitle">Task Name / Deliverable *</label>
                            <input type="text" id="taskTitle" class="form-control" required placeholder="e.g. Build GraphQL API &amp; Auth Middleware">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="taskDate">Date *</label>
                                <input type="date" id="taskDate" class="form-control" required value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="form-group">
                                <label for="taskStatus">Task Status</label>
                                <select id="taskStatus" class="form-control">
                                    <option value="completed">Completed</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="pending">Pending</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="taskStartTime">Start Time *</label>
                                <input type="time" step="any" id="taskStartTime" class="form-control" required value="09:00">
                            </div>
                            <div class="form-group">
                                <label for="taskEndTime">End Time *</label>
                                <input type="time" step="any" id="taskEndTime" class="form-control" required value="13:00">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="taskPricePerHour" id="taskPricePerHourLabel">Price Per Hour *</label>
                            <input type="number" step="any" min="0" id="taskPricePerHour" class="form-control" required value="50" placeholder="e.g. 50">
                        </div>

                        <!-- Live Calculated Duration and Total Price preview -->
                        <div class="calc-preview-bar">
                            <span>Computed Duration: <strong id="calcDurationPreview">4.00 hrs</strong></span>
                            <span>Total Price: <strong id="calcPricePreview">0 تومان</strong></span>
                        </div>

                        <div class="form-group" style="margin-top:16px;">
                            <label for="taskDescription">Task Description &amp; Technical Notes *</label>
                            <textarea id="taskDescription" class="form-control" rows="4" placeholder="Detailed summary of work completed, commits, or notes..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('taskModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="btnSaveTask">Save Task</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 4. SHARE LINK MODAL ("Share Link with Everybody") -->
        <div class="modal-backdrop" id="shareModal">
            <div class="modal-box">
                <div class="modal-header">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
                        Share Project Timesheet &amp; Invoice
                    </h3>
                    <button type="button" class="modal-close-btn" onclick="closeModal('shareModal')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>
                <div class="modal-body">
                    <p style="font-size:0.88rem; color:var(--text-muted); margin-bottom:18px;">
                        Anyone with these links can view all task breakdowns, hours, and total price calculation without needing an account or login.
                    </p>

                    <div class="form-group">
                        <label>1. Project Share Link (Current Project Tasks + Total Price)</label>
                        <div style="display:flex; gap:8px;">
                            <input type="text" id="shareProjectUrlInput" class="form-control" readonly style="background:#f8fafc;">
                            <button type="button" class="btn btn-primary btn-sm" onclick="copyShareLink('shareProjectUrlInput')">Copy</button>
                            <a href="#" id="shareProjectPreviewBtn" target="_blank" class="btn btn-secondary btn-sm" title="Preview Public Link">Open</a>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:20px;">
                        <label>2. Company All-Projects Share Link (All Projects + Grand Total)</label>
                        <div style="display:flex; gap:8px;">
                            <input type="text" id="shareCompanyUrlInput" class="form-control" readonly style="background:#f8fafc;">
                            <button type="button" class="btn btn-primary btn-sm" onclick="copyShareLink('shareCompanyUrlInput')">Copy</button>
                            <a href="#" id="shareCompanyPreviewBtn" target="_blank" class="btn btn-secondary btn-sm" title="Preview Company Report">Open</a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('shareModal')">Close</button>
                </div>
            </div>
        </div>

        <!-- 5. MANAGE COMPANIES & PROJECTS LIST MODAL -->
        <div class="modal-backdrop" id="manageListModal">
            <div class="modal-box modal-box-lg">
                <div class="modal-header">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                        Manage Companies &amp; Projects
                    </h3>
                    <button type="button" class="modal-close-btn" onclick="closeModal('manageListModal')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>
                <div class="modal-body" style="padding:20px 24px; max-height: calc(85vh - 130px); overflow-y: auto;">
                    <div class="manage-toolbar">
                        <div class="manage-search-input">
                            <input type="text" id="manageListSearchInput" class="form-control" placeholder="Search companies or projects..." oninput="filterManageModalList(this.value)">
                        </div>
                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                            <button type="button" class="btn btn-primary btn-sm" onclick="openCreateCompanyModal()">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                <span>Add Company</span>
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="openCreateProjectModal()">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                <span>Add Project</span>
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="seedDemoData()" title="Import demo sample companies & projects">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                <span>Import Demo</span>
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" onclick="deleteAllCompanies()" title="Permanently delete all companies, projects, and tasks">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                <span>Delete All</span>
                            </button>
                        </div>
                    </div>

                    <!-- Company & Projects List Container -->
                    <div id="manageListContainer">
                        <!-- Populated dynamically via renderManageListModalContent() -->
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8fafc; border-top:1px solid var(--border-color); padding:12px 24px;">
                    <span style="font-size:0.84rem; color:var(--text-muted); margin-right:auto;" id="manageListSummaryText"></span>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('manageListModal')">Close</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Floating Quick Navigation Widget -->
    <div class="float-menu-backdrop" id="floatMenuBackdrop"></div>
    <button type="button" class="float-menu-fab" id="floatMenuFab" title="Open Quick Navigation Menu" aria-label="Open Floating Menu">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
    </button>

    <div class="float-menu-panel" id="floatMenuPanel">
        <div class="float-menu-header">
            <div class="float-menu-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                <span>Navigation &amp; Controls</span>
            </div>
            <button type="button" class="modal-close-btn" id="floatMenuCloseBtn" style="color: white; border: none; background: transparent; font-size: 1.4rem; cursor: pointer; display: flex; align-items: center; justify-content: center; width: 28px; height: 28px;">&times;</button>
        </div>

        <div class="float-menu-body">
            <!-- 1. Admin Status Section -->
            <div class="float-menu-group-label">Admin Status</div>
            <?php if ($isLoggedIn): ?>
                <div style="display:flex; align-items:center; gap:0.5rem; padding:0.55rem 0.75rem; background:rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.3); border-radius:8px; margin:0 0.15rem;">
                    <span style="width:8px; height:8px; border-radius:50%; background:#22c55e; box-shadow:0 0 6px #22c55e; flex-shrink:0;"></span>
                    <span style="font-size:0.82rem; font-weight:600; color:#15803d;">Admin: <strong><?= htmlspecialchars(Auth::getCurrentUser() ?? 'admin') ?></strong></span>
                    <span class="float-menu-badge" style="background:#dcfce7; color:#166534; margin-left:auto;">Online</span>
                </div>
            <?php else: ?>
                <div style="display:flex; align-items:center; gap:0.5rem; padding:0.55rem 0.75rem; background:rgba(100,116,139,0.08); border:1px solid rgba(100,116,139,0.2); border-radius:8px; margin:0 0.15rem;">
                    <span style="width:8px; height:8px; border-radius:50%; background:#94a3b8; flex-shrink:0;"></span>
                    <span style="font-size:0.82rem; font-weight:500; color:#475569;">Session: <strong>Guest (<?= $isPublicMode ? 'Shared View' : 'Read Only' ?>)</strong></span>
                </div>
            <?php endif; ?>

            <!-- 2. Authentication Section -->
            <div class="float-menu-divider"></div>
            <div class="float-menu-group-label">Authentication</div>
            <?php if ($isLoggedIn): ?>
                <a href="freelance.php?action=logout" class="float-menu-item" style="color:#dc2626; text-decoration:none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#ef4444;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    <span>Logout (Admin)</span>
                    <span class="float-menu-badge" style="background:#fee2e2; color:#b91c1c;">Exit</span>
                </a>
            <?php else: ?>
                <a href="freelance.php" class="float-menu-item" style="color:#0284c7; text-decoration:none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#0284c7;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    <span>Admin Login</span>
                    <span class="float-menu-badge" style="background:#e0f2fe; color:#0369a1;">Sign In</span>
                </a>
            <?php endif; ?>

            <!-- 3. Navigation Links -->
            <div class="float-menu-divider"></div>
            <div class="float-menu-group-label">Pages &amp; Navigation</div>
            <a href="index.php" class="float-menu-item">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                <span>Questions Base</span>
            </a>
            <a href="projects.php" class="float-menu-item">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                <span>Projects Portfolio</span>
            </a>
            <a href="freelance.php" class="float-menu-item" style="background:var(--brand-light); color:var(--brand-primary); font-weight:700;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                <span>Freelance Hub</span>
                <span class="float-menu-badge" style="background:var(--brand-primary); color:white;">Active</span>
            </a>
            <a href="profile.php" class="float-menu-item">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                <span>Resume &amp; CV</span>
            </a>

            <!-- 4. Quick Actions -->
            <div class="float-menu-divider"></div>
            <div class="float-menu-group-label">Quick Actions</div>
            <?php if ($isLoggedIn && !$isPublicMode): ?>
                <button type="button" class="float-menu-item" onclick="toggleFloatMenu(false); openManageListModal();">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                    <span>Manage Companies &amp; Projects</span>
                    <span class="float-menu-badge" style="background:#eaf2f8; color:#12466f;">List</span>
                </button>
                <button type="button" class="float-menu-item" onclick="toggleFloatMenu(false); openCreateCompanyModal();">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                    <span>Add New Company</span>
                </button>
                <button type="button" class="float-menu-item" id="floatCreateTaskBtn" onclick="toggleFloatMenu(false); if(activeProjectId) { openCreateTaskModal(); } else { showToast('Please select a project first', 'info'); }">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    <span>Log New Task</span>
                </button>
                <button type="button" class="float-menu-item" onclick="toggleFloatMenu(false); const fBtn = document.getElementById('editFooterTriggerBtn'); if(fBtn) fBtn.click();">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    <span>Edit Footer Sections</span>
                    <span class="float-menu-badge" style="background:#e0f2fe; color:#0369a1;">Config</span>
                </button>
            <?php endif; ?>
            <button type="button" class="float-menu-item" onclick="toggleFloatMenu(false); window.print();">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                <span>Print Page</span>
            </button>
            <button type="button" class="float-menu-item" onclick="toggleFloatMenu(false); window.scrollTo({ top: 0, behavior: 'smooth' });">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>
                <span>Back to Top</span>
            </button>
        </div>
    </div>

    <!-- Toast Notifications Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- =============================================================
         JAVASCRIPT LOGIC
         ================================----------------------------- -->
    <script>
        const API_BASE = 'freelance.php';
        let currentTreeData = [];
        let activeProjectId = null;
        let activeProjectData = null;
        let activeFilter = 'all';

        // --- Toast Helper ---
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = `toast-msg ${type}`;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(20px)';
                setTimeout(() => toast.remove(), 250);
            }, 3500);
        }

        // --- Modal Helpers ---
        function openModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.add('open');
        }

        function closeModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.remove('open');
        }

        function getCurrencySymbol(curr) {
            if (!curr) return '$';
            const c = String(curr).trim().toUpperCase();
            if (c === 'TOMAN' || c === 'تومان' || c === 'TMN' || c === 'IRR') return 'تومان';
            if (c === 'EUR' || c === '€') return '€';
            return '$';
        }

        function formatNumber(num) {
            const n = Math.round(Number(num) || 0);
            return n.toLocaleString('en-US');
        }

        function formatPrice(amount, currency = null) {
            const curr = currency || (activeProjectData?.project?.currency) || '$';
            const sym = getCurrencySymbol(curr);
            const n = parseFloat(amount) || 0;

            if (sym === 'تومان') {
                return Math.round(n).toLocaleString('en-US') + ' تومان';
            } else if (sym === '€') {
                return '€' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            } else {
                return '$' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }

        function formatRate(rate, currency = null) {
            const curr = currency || (activeProjectData?.project?.currency) || '$';
            const sym = getCurrencySymbol(curr);
            const r = parseFloat(rate) || 0;
            if (sym === 'تومان') {
                return `${Math.round(r).toLocaleString('en-US')} تومان / ساعت`;
            } else if (sym === '€') {
                return `€${r.toFixed(2)} / hr`;
            } else {
                return `$${r.toFixed(2)} / hr`;
            }
        }

        function formatToman(amount, curr = null) {
            return formatPrice(amount, curr);
        }

        // --- Duration and Price Live Calculation in Task Modal ---
        function updateTaskCalculations() {
            const startInput = document.getElementById('taskStartTime');
            const endInput = document.getElementById('taskEndTime');
            const rateInput = document.getElementById('taskPricePerHour');
            const durationEl = document.getElementById('calcDurationPreview');
            const priceEl = document.getElementById('calcPricePreview');

            if (!startInput || !endInput || !rateInput) return;

            const startVal = startInput.value || '09:00';
            const endVal = endInput.value || '13:00';
            const rateVal = parseFloat(rateInput.value) || 0;

            const startDate = new Date(`1970-01-01T${startVal}:00`);
            let endDate = new Date(`1970-01-01T${endVal}:00`);
            if (endDate <= startDate) {
                // handle overnight
                endDate = new Date(`1970-01-02T${endVal}:00`);
            }

            const diffHours = (endDate - startDate) / (1000 * 60 * 60);
            const roundedHours = Math.round(diffHours * 100) / 100;
            const totalPrice = Math.round(roundedHours * rateVal * 100) / 100;

            const curr = activeProjectData?.project?.currency || '$';
            if (durationEl) durationEl.textContent = `${roundedHours.toFixed(2)} hrs`;
            if (priceEl) priceEl.textContent = formatPrice(totalPrice, curr);
        }

        // Event listeners for task time calculation
        ['taskStartTime', 'taskEndTime', 'taskPricePerHour'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', updateTaskCalculations);
                el.addEventListener('change', updateTaskCalculations);
            }
        });

        // --- Mobile Menu Toggle ---
        const mobileMenuBtn = document.getElementById('mobileMenuToggle');
        const sidebarEl = document.getElementById('appSidebar');
        if (mobileMenuBtn && sidebarEl) {
            mobileMenuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebarEl.classList.toggle('mobile-open');
            });

            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 960 && sidebarEl.classList.contains('mobile-open')) {
                    if (!sidebarEl.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                        sidebarEl.classList.remove('mobile-open');
                    }
                }
            });
        }

        // --- Fetch Tree (Companies & Projects) ---
        async function loadCompaniesAndProjects(preferredProjectId = null) {
            try {
                const res = await fetch(`${API_BASE}?api_action=get_tree`);
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Failed to load tree');

                currentTreeData = json.data;
                renderSidebarTree(currentTreeData);

                // Populate Company Select in Project Modal
                const compSelect = document.getElementById('projectCompanySelect');
                if (compSelect) {
                    compSelect.innerHTML = currentTreeData.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
                }

                // If URL has ?project_id=, select it; otherwise select first project
                const urlParams = new URLSearchParams(window.location.search);
                const requestedId = preferredProjectId || urlParams.get('project_id');

                if (requestedId) {
                    selectProject(parseInt(requestedId, 10));
                } else if (currentTreeData.length > 0 && currentTreeData[0].projects && currentTreeData[0].projects.length > 0) {
                    selectProject(currentTreeData[0].projects[0].id);
                } else {
                    activeProjectId = null;
                    activeProjectData = null;
                    const emptyState = document.getElementById('projectEmptyState');
                    if (emptyState) emptyState.style.display = 'block';
                    const heroCard = document.getElementById('projectHeroCard');
                    if (heroCard) heroCard.style.display = 'none';
                    const tasksSec = document.getElementById('tasksListSection');
                    if (tasksSec) tasksSec.style.display = 'none';
                }
            } catch (err) {
                console.error('Tree load error:', err);
            }
        }

        // --- Render Sidebar Tree with Dropdowns ---
        function renderSidebarTree(companies) {
            const treeContainer = document.getElementById('companiesTreeList');
            if (!treeContainer) return;

            if (companies.length === 0) {
                treeContainer.innerHTML = `
                    <li style="padding:20px; text-align:center; color:var(--text-muted); font-size:0.86rem;">
                        No companies yet.<br>
                        <button type="button" class="btn btn-primary btn-sm" style="margin-top:10px;" onclick="openCreateCompanyModal()">+ Add Company</button>
                    </li>
                `;
                return;
            }

            treeContainer.innerHTML = companies.map((c, idx) => {
                const projectItems = c.projects.map(p => `
                    <li>
                        <a class="project-item-link ${p.id === activeProjectId ? 'active' : ''}" 
                           data-project-id="${p.id}" 
                           onclick="handleProjectClick(event, ${p.id})">
                            <span class="project-name-text">${escapeHtml(p.title)}</span>
                            <span class="badge-count" title="${p.task_count} Tasks">${p.task_count}</span>
                            <button type="button" class="btn-quick-delete-project" title="Delete Project" onclick="event.stopPropagation(); deleteProject(${p.id}, '${escapeJsString(p.title)}');">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            </button>
                        </a>
                    </li>
                `).join('');

                const isOpen = idx === 0 || c.projects.some(p => p.id === activeProjectId);

                return `
                    <li class="company-item ${isOpen ? 'open' : ''}" data-company-id="${c.id}">
                        <div class="company-header" onclick="toggleCompanyDropdown(${c.id})">
                            <div class="company-info-group">
                                <span class="company-dot" style="background-color: ${c.color || '#12466f'};"></span>
                                <span class="company-title" title="${escapeHtml(c.name)}">${escapeHtml(c.name)}</span>
                            </div>
                            <div class="company-meta">
                                <button type="button" class="btn-company-quick-del" title="Delete Company" onclick="event.stopPropagation(); deleteCompany(${c.id}, '${escapeJsString(c.name)}');">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                </button>
                                <span class="badge-count" title="${c.projects.length} Projects">${c.projects.length}</span>
                                <svg class="chevron-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            </div>
                        </div>
                        <ul class="projects-dropdown-list">
                            ${projectItems}
                            <li>
                                <button type="button" class="add-project-quick-btn" onclick="openCreateProjectModal(${c.id})">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                    <span>Add Project</span>
                                </button>
                            </li>
                        </ul>
                    </li>
                `;
            }).join('');
        }

        // Dropdown toggle for company accordion item
        function toggleCompanyDropdown(companyId) {
            const item = document.querySelector(`.company-item[data-company-id="${companyId}"]`);
            if (item) {
                item.classList.toggle('open');
            }
        }

        function handleProjectClick(e, projectId) {
            e.preventDefault();
            selectProject(projectId);
            if (sidebarEl) sidebarEl.classList.remove('mobile-open');
        }

        // --- Select and Load Project Details (Right Section) ---
        async function selectProject(projectId) {
            activeProjectId = projectId;

            // Highlight active link in sidebar
            document.querySelectorAll('.project-item-link').forEach(el => {
                if (parseInt(el.getAttribute('data-project-id'), 10) === projectId) {
                    el.classList.add('active');
                    // Ensure parent accordion is open
                    const parentCompanyItem = el.closest('.company-item');
                    if (parentCompanyItem) parentCompanyItem.classList.add('open');
                } else {
                    el.classList.remove('active');
                }
            });

            // Update URL without full refresh
            const url = new URL(window.location);
            url.searchParams.set('project_id', projectId);
            window.history.replaceState({}, '', url);

            try {
                const res = await fetch(`${API_BASE}?api_action=get_project_details&id=${projectId}`);
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Failed to load project details');

                activeProjectData = json;
                renderProjectHero(json.project, json.summary);
                renderTasksList(json.tasks);

                document.getElementById('projectEmptyState').style.display = 'none';
                document.getElementById('projectHeroCard').style.display = 'block';
                document.getElementById('tasksListSection').style.display = 'block';
            } catch (err) {
                console.error(err);
                showToast(err.message, 'error');
            }
        }

        // --- Render Project Header & Stats ---
        function renderProjectHero(project, summary) {
            document.getElementById('breadcrumbCompany').textContent = project.company_name || 'Company';
            document.getElementById('breadcrumbProject').textContent = project.title || 'Project';
            document.getElementById('heroProjectTitle').textContent = project.title;
            document.getElementById('heroProjectDesc').textContent = project.description || 'No specific description provided.';

            const curr = project.currency || '$';
            const sym = getCurrencySymbol(curr);

            // Update Currency Pills in the Currency Section
            const pills = document.querySelectorAll('#projectCurrencyPills .currency-pill-btn');
            pills.forEach(pill => {
                const pCurr = pill.getAttribute('data-currency');
                if (getCurrencySymbol(pCurr) === sym) {
                    pill.classList.add('active');
                } else {
                    pill.classList.remove('active');
                }
            });

            // Update Rate Displays
            const rateStr = formatRate(project.hourly_rate, curr);
            const currencySectionRateDisplay = document.getElementById('currencySectionRateDisplay');
            if (currencySectionRateDisplay) currencySectionRateDisplay.textContent = rateStr;

            document.getElementById('statHourlyRate').textContent = rateStr;
            document.getElementById('statTotalPrice').textContent = formatPrice(summary.total_price, curr);
            document.getElementById('statTaskCount').textContent = summary.total_tasks;
            document.getElementById('statTotalHours').textContent = `${summary.total_hours.toFixed(2)}h`;
        }

        // --- Render Collapsible Tasks List ---
        function renderTasksList(tasks) {
            const container = document.getElementById('tasksListContainer');
            if (!container) return;

            const projectCurrency = activeProjectData?.project?.currency || '$';

            let filtered = tasks;
            if (activeFilter !== 'all') {
                filtered = tasks.filter(t => t.status === activeFilter);
            }

            if (filtered.length === 0) {
                container.innerHTML = `
                    <div style="text-align:center; padding:40px 20px; background:#ffffff; border:1px dashed #cbd5e1; border-radius:var(--radius-md); color:var(--text-muted);">
                        <p style="font-size:0.92rem; margin-bottom:12px;">No tasks logged under this filter yet.</p>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openCreateTaskModal()">+ Log First Task</button>
                    </div>
                `;
                return;
            }

            container.innerHTML = filtered.map(t => {
                const formattedDate = formatDate(t.task_date);
                const formattedStart = formatTime(t.start_time);
                const formattedEnd = formatTime(t.end_time);
                const duration = parseFloat(t.duration_hours).toFixed(2);
                const price = formatPrice(t.total_price, projectCurrency);
                const rate = formatRate(t.price_per_hour, projectCurrency);

                return `
                    <div class="task-card" id="taskCard_${t.id}">
                        <!-- Always Visible Header -->
                        <div class="task-header" onclick="toggleTaskCard(${t.id})">
                            <div class="task-title-group">
                                <span class="task-status-indicator ${t.status}" title="Status: ${t.status}"></span>
                                <span class="task-title">${escapeHtml(t.title)}</span>
                            </div>

                            <div class="task-summary-badges">
                                <span class="task-badge">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                    ${formattedDate}
                                </span>
                                <span class="task-badge task-badge-hours">
                                    ${duration} hrs
                                </span>
                                <span class="task-badge task-badge-price">
                                    ${price}
                                </span>
                                <div class="task-toggle-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                </div>
                            </div>
                        </div>

                        <!-- Collapsible Detail Pane -->
                        <div class="task-details-pane">
                            <div class="task-metrics-row">
                                <div class="metric-item">
                                    <span class="metric-label">Start Time</span>
                                    <span class="metric-value">${formattedStart}</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-label">End Time</span>
                                    <span class="metric-value">${formattedEnd}</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-label">Date</span>
                                    <span class="metric-value">${formattedDate}</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-label">Price Per Hour</span>
                                    <span class="metric-value">${rate}</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-label">Duration &amp; Price</span>
                                    <span class="metric-value" style="color:var(--success);">${duration}h &times; ${formatPrice(t.price_per_hour, projectCurrency)} = ${price}</span>
                                </div>
                            </div>

                            <div class="task-description-box">
                                <strong style="display:block; margin-bottom:4px; font-size:0.76rem; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.04em;">Description &amp; Notes:</strong>
                                ${escapeHtml(t.description || 'No detailed description specified for this task.')}
                            </div>

                            <div class="task-actions-row">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="openEditTaskModal(${t.id})">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    <span>Edit</span>
                                </button>
                                <button type="button" class="btn btn-danger btn-sm" onclick="deleteTask(${t.id})">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    <span>Delete</span>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function toggleTaskCard(taskId) {
            const card = document.getElementById(`taskCard_${taskId}`);
            if (card) {
                card.classList.toggle('expanded');
            }
        }

        // --- Task Filter Buttons ---
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                activeFilter = btn.getAttribute('data-filter');
                if (activeProjectData) {
                    renderTasksList(activeProjectData.tasks);
                }
            });
        });

        // --- Task Search Filter in Sidebar ---
        const filterInput = document.getElementById('filterProjectsInput');
        if (filterInput) {
            filterInput.addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase().trim();
                if (!term) {
                    renderSidebarTree(currentTreeData);
                    return;
                }
                const filtered = currentTreeData.filter(c => {
                    const compMatch = c.name.toLowerCase().includes(term);
                    const projMatch = c.projects.some(p => p.title.toLowerCase().includes(term));
                    return compMatch || projMatch;
                });
                renderSidebarTree(filtered);
            });
        }

        // --- Helper for safe string in inline JS ---
        function escapeJsString(str) {
            if (!str) return '';
            return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
        }

        // --- MODAL HANDLERS: COMPANY ---
        function openCreateCompanyModal() {
            document.getElementById('companyModalTitle').textContent = 'Create New Company';
            document.getElementById('companyId').value = '';
            document.getElementById('companyName').value = '';
            document.getElementById('companyClientName').value = '';
            document.getElementById('companyClientEmail').value = '';
            document.getElementById('companyColor').value = '#12466f';
            openModal('companyModal');
        }

        function openEditCompanyModal(companyId) {
            const comp = currentTreeData.find(c => c.id == companyId);
            if (!comp) return;
            document.getElementById('companyModalTitle').textContent = 'Edit Company';
            document.getElementById('companyId').value = comp.id;
            document.getElementById('companyName').value = comp.name || '';
            document.getElementById('companyClientName').value = comp.client_name || '';
            document.getElementById('companyClientEmail').value = comp.client_email || '';
            document.getElementById('companyColor').value = comp.color || '#12466f';
            openModal('companyModal');
        }

        async function deleteCompany(companyId, companyName) {
            const nameStr = companyName || 'this company';
            if (!confirm(`Are you sure you want to delete company "${nameStr}"?\n\nWARNING: All projects and logged tasks under this company will be permanently deleted!`)) {
                return;
            }
            try {
                const res = await fetch(`${API_BASE}?api_action=delete_company`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: companyId })
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Failed to delete company');

                showToast(json.message || 'Company deleted successfully.', 'success');
                if (activeProjectData && activeProjectData.project && activeProjectData.project.company_id == companyId) {
                    activeProjectId = null;
                    activeProjectData = null;
                    document.getElementById('projectEmptyState').style.display = 'block';
                    document.getElementById('projectHeroCard').style.display = 'none';
                    document.getElementById('tasksListSection').style.display = 'none';
                }
                await loadCompaniesAndProjects();
                const mModal = document.getElementById('manageListModal');
                if (mModal && mModal.classList.contains('open')) {
                    const searchVal = document.getElementById('manageListSearchInput')?.value || '';
                    renderManageListModalContent(searchVal);
                }
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        const btnOpenAddComp = document.getElementById('btnOpenAddCompany');
        if (btnOpenAddComp) btnOpenAddComp.addEventListener('click', openCreateCompanyModal);
        const btnSidebarAddComp = document.getElementById('btnSidebarAddCompany');
        if (btnSidebarAddComp) btnSidebarAddComp.addEventListener('click', openCreateCompanyModal);

        const companyForm = document.getElementById('companyForm');
        if (companyForm) {
            companyForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const id = document.getElementById('companyId').value;
                const payload = {
                    id: id,
                    name: document.getElementById('companyName').value.trim(),
                    client_name: document.getElementById('companyClientName').value.trim(),
                    client_email: document.getElementById('companyClientEmail').value.trim(),
                    color: document.getElementById('companyColor').value
                };
                const action = id ? 'update_company' : 'create_company';

                try {
                    const res = await fetch(`${API_BASE}?api_action=${action}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const json = await res.json();
                    if (!json.success) throw new Error(json.error || 'Operation failed');

                    closeModal('companyModal');
                    showToast(json.message || 'Company saved!', 'success');
                    await loadCompaniesAndProjects();
                    const mModal = document.getElementById('manageListModal');
                    if (mModal && mModal.classList.contains('open')) {
                        const searchVal = document.getElementById('manageListSearchInput')?.value || '';
                        renderManageListModalContent(searchVal);
                    }
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        }

        // --- MODAL HANDLERS: PROJECT & CURRENCY ---
        function handleProjectModalCurrencyChange(curr) {
            const sym = getCurrencySymbol(curr);
            const label = document.getElementById('projectHourlyRateLabel');
            const input = document.getElementById('projectHourlyRate');
            if (!input) return;

            input.step = 'any';
            input.min = '0';

            if (sym === 'تومان') {
                if (label) label.textContent = 'Default Hourly Rate (تومان / ساعت) *';
                input.placeholder = 'e.g. 500000';
                if (parseFloat(input.value) <= 100) input.value = '500000';
            } else if (sym === '€') {
                if (label) label.textContent = 'Default Hourly Rate (€ / hr) *';
                input.placeholder = 'e.g. 50.00';
                if (parseFloat(input.value) > 1000) input.value = '50';
            } else {
                if (label) label.textContent = 'Default Hourly Rate ($ / hr) *';
                input.placeholder = 'e.g. 65.00';
                if (parseFloat(input.value) > 1000) input.value = '65';
            }
        }

        function openCreateProjectModal(companyId = null) {
            document.getElementById('projectModalTitle').textContent = 'Create Project';
            document.getElementById('projectId').value = '';

            const compSelect = document.getElementById('projectCompanySelect');
            if (compSelect) {
                if (compSelect.options.length === 0 && currentTreeData && currentTreeData.length > 0) {
                    compSelect.innerHTML = currentTreeData.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
                }
                if (companyId) {
                    compSelect.value = companyId;
                } else if (compSelect.options.length > 0 && !compSelect.value) {
                    compSelect.selectedIndex = 0;
                }
            }

            document.getElementById('projectTitle').value = '';
            const currEl = document.getElementById('projectCurrency');
            if (currEl) {
                currEl.value = '$';
                handleProjectModalCurrencyChange('$');
            }
            document.getElementById('projectHourlyRate').value = '50';
            document.getElementById('projectStatus').value = 'in_progress';
            document.getElementById('projectDescription').value = '';
            openModal('projectModal');
        }

        function openEditProjectModal(projectId) {
            let proj = null;
            let compId = null;
            for (const c of currentTreeData) {
                const found = (c.projects || []).find(p => p.id == projectId);
                if (found) {
                    proj = found;
                    compId = c.id;
                    break;
                }
            }
            if (!proj && activeProjectData && activeProjectData.project && activeProjectData.project.id == projectId) {
                proj = activeProjectData.project;
                compId = proj.company_id;
            }
            if (!proj) return;

            document.getElementById('projectModalTitle').textContent = 'Edit Project';
            document.getElementById('projectId').value = proj.id;
            
            const compSelect = document.getElementById('projectCompanySelect');
            if (compSelect) {
                if (compSelect.options.length === 0 && currentTreeData && currentTreeData.length > 0) {
                    compSelect.innerHTML = currentTreeData.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
                }
                compSelect.value = compId;
            }

            document.getElementById('projectTitle').value = proj.title || '';

            const pCurr = proj.currency || '$';
            const currEl = document.getElementById('projectCurrency');
            if (currEl) {
                currEl.value = getCurrencySymbol(pCurr) === 'تومان' ? 'تومان' : (getCurrencySymbol(pCurr) === '€' ? '€' : '$');
                handleProjectModalCurrencyChange(currEl.value);
            }

            document.getElementById('projectHourlyRate').value = proj.hourly_rate || (getCurrencySymbol(pCurr) === 'تومان' ? '500000' : '50');
            document.getElementById('projectStatus').value = proj.status || 'in_progress';
            document.getElementById('projectDescription').value = proj.description || '';
            openModal('projectModal');
        }

        // --- QUICK SET CURRENCY & RATE MODAL HANDLERS ---
        function openSetRateModal(curr = null) {
            if (!activeProjectData || !activeProjectData.project) {
                showToast('Please select a project first.', 'error');
                return;
            }
            const p = activeProjectData.project;
            const targetCurr = curr || p.currency || '$';

            const selectEl = document.getElementById('quickCurrencySelect');
            if (selectEl) {
                selectEl.value = getCurrencySymbol(targetCurr) === 'تومان' ? 'تومان' : (getCurrencySymbol(targetCurr) === '€' ? '€' : '$');
            }

            const rateInput = document.getElementById('quickHourlyRateInput');
            let rate = parseFloat(p.hourly_rate) || 0;

            const oldSym = getCurrencySymbol(p.currency);
            const newSym = getCurrencySymbol(targetCurr);
            if (oldSym !== newSym) {
                if (newSym === 'تومان' && rate < 1000) {
                    rate = 500000;
                } else if ((newSym === '$' || newSym === '€') && rate > 1000) {
                    rate = 50;
                }
            }

            if (rateInput) {
                rateInput.step = 'any';
                rateInput.min = '0';
                rateInput.value = rate;
            }

            handleQuickCurrencyChange(selectEl ? selectEl.value : targetCurr);
            openModal('setCurrencyRateModal');
        }

        function handleCurrencySelect(curr) {
            openSetRateModal(curr);
        }

        function handleQuickCurrencyChange(curr) {
            const sym = getCurrencySymbol(curr);
            const labelEl = document.getElementById('quickHourlyRateLabel');
            const inputEl = document.getElementById('quickHourlyRateInput');
            const helpEl = document.getElementById('quickRateHelpText');

            if (inputEl) {
                inputEl.step = 'any';
                inputEl.min = '0';
            }

            if (sym === 'تومان') {
                if (labelEl) labelEl.textContent = 'Hourly Rate (تومان / ساعت) *';
                if (inputEl) {
                    inputEl.placeholder = 'e.g. 500000';
                    if (parseFloat(inputEl.value) <= 100) inputEl.value = 500000;
                }
                if (helpEl) helpEl.textContent = 'Enter hourly rate in Iranian Toman (e.g. 500,000 تومان).';
            } else if (sym === '€') {
                if (labelEl) labelEl.textContent = 'Hourly Rate (€ / hr) *';
                if (inputEl) {
                    inputEl.placeholder = 'e.g. 50.00';
                    if (parseFloat(inputEl.value) > 1000) inputEl.value = 50;
                }
                if (helpEl) helpEl.textContent = 'Enter hourly rate in Euros (e.g. 50.00 €).';
            } else {
                if (labelEl) labelEl.textContent = 'Hourly Rate ($ / hr) *';
                if (inputEl) {
                    inputEl.placeholder = 'e.g. 65.00';
                    if (parseFloat(inputEl.value) > 1000) inputEl.value = 65;
                }
                if (helpEl) helpEl.textContent = 'Enter hourly rate in US Dollars (e.g. 65.00 $).';
            }
        }

        const setCurrencyRateForm = document.getElementById('setCurrencyRateForm');
        if (setCurrencyRateForm) {
            setCurrencyRateForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!activeProjectId) return;

                const currency = document.getElementById('quickCurrencySelect').value;
                const hourlyRate = parseFloat(document.getElementById('quickHourlyRateInput').value) || 0;
                const applyToTasks = document.getElementById('quickApplyToExistingTasks').checked;
                const btn = document.getElementById('btnSaveQuickCurrency');
                if (btn) btn.disabled = true;

                try {
                    const res = await fetch(`${API_BASE}?api_action=update_project_currency`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            id: activeProjectId,
                            currency: currency,
                            hourly_rate: hourlyRate,
                            apply_to_tasks: applyToTasks
                        })
                    });
                    const json = await res.json();
                    if (!json.success) throw new Error(json.error || 'Failed to update currency and price');

                    closeModal('setCurrencyRateModal');
                    showToast(json.message || 'Currency and price updated successfully!', 'success');
                    await selectProject(activeProjectId);
                    await loadCompaniesAndProjects(activeProjectId);
                } catch (err) {
                    showToast(err.message, 'error');
                } finally {
                    if (btn) btn.disabled = false;
                }
            });
        }

        async function deleteProject(projectId, projectTitle) {
            const titleStr = projectTitle || 'this project';
            if (!confirm(`Are you sure you want to delete project "${titleStr}"?\n\nAll tasks logged under this project will also be deleted!`)) {
                return;
            }
            try {
                const res = await fetch(`${API_BASE}?api_action=delete_project`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: projectId })
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Failed to delete project');

                showToast(json.message || 'Project deleted successfully.', 'success');
                if (activeProjectId == projectId) {
                    activeProjectId = null;
                    activeProjectData = null;
                    document.getElementById('projectEmptyState').style.display = 'block';
                    document.getElementById('projectHeroCard').style.display = 'none';
                    document.getElementById('tasksListSection').style.display = 'none';
                }
                await loadCompaniesAndProjects();
                const mModal = document.getElementById('manageListModal');
                if (mModal && mModal.classList.contains('open')) {
                    const searchVal = document.getElementById('manageListSearchInput')?.value || '';
                    renderManageListModalContent(searchVal);
                }
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        const btnEditProj = document.getElementById('btnEditProject');
        if (btnEditProj) {
            btnEditProj.addEventListener('click', () => {
                if (activeProjectId) openEditProjectModal(activeProjectId);
            });
        }

        const projectForm = document.getElementById('projectForm');
        if (projectForm) {
            projectForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const id = document.getElementById('projectId').value;
                const payload = {
                    id: id,
                    company_id: document.getElementById('projectCompanySelect').value,
                    title: document.getElementById('projectTitle').value.trim(),
                    currency: document.getElementById('projectCurrency') ? document.getElementById('projectCurrency').value : '$',
                    hourly_rate: document.getElementById('projectHourlyRate').value,
                    status: document.getElementById('projectStatus').value,
                    description: document.getElementById('projectDescription').value.trim()
                };
                const action = id ? 'update_project' : 'create_project';

                try {
                    const res = await fetch(`${API_BASE}?api_action=${action}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const json = await res.json();
                    if (!json.success) throw new Error(json.error || 'Operation failed');

                    closeModal('projectModal');
                    showToast(json.message || 'Project saved!', 'success');
                    await loadCompaniesAndProjects(id || json.id);
                    const mModal = document.getElementById('manageListModal');
                    if (mModal && mModal.classList.contains('open')) {
                        const searchVal = document.getElementById('manageListSearchInput')?.value || '';
                        renderManageListModalContent(searchVal);
                    }
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        }

        // Project Dashboard Delete Button listener
        const btnDelProj = document.getElementById('btnDeleteProject');
        if (btnDelProj) {
            btnDelProj.addEventListener('click', () => {
                if (!activeProjectId) return;
                const title = activeProjectData?.project?.title || 'this project';
                deleteProject(activeProjectId, title);
            });
        }

        // --- MANAGE COMPANIES & PROJECTS LIST MODAL HANDLERS ---
        function openManageListModal() {
            const searchInput = document.getElementById('manageListSearchInput');
            if (searchInput) searchInput.value = '';
            renderManageListModalContent('');
            openModal('manageListModal');
        }

        function filterManageModalList(val) {
            renderManageListModalContent(val);
        }

        function renderManageListModalContent(search = '') {
            const container = document.getElementById('manageListContainer');
            const summaryEl = document.getElementById('manageListSummaryText');
            if (!container) return;

            const term = (search || '').trim().toLowerCase();
            let totalCompanies = 0;
            let totalProjects = 0;

            const filteredCompanies = currentTreeData.filter(c => {
                if (!term) return true;
                const compMatch = (c.name || '').toLowerCase().includes(term) || (c.client_name || '').toLowerCase().includes(term);
                const projMatch = (c.projects || []).some(p => (p.title || '').toLowerCase().includes(term));
                return compMatch || projMatch;
            });

            if (!currentTreeData || currentTreeData.length === 0) {
                container.innerHTML = `
                    <div style="text-align:center; padding:44px 20px; color:var(--text-muted); background:var(--bg-card); border-radius:12px; border:2px dashed var(--border-color); margin:12px 0;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:12px;"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                        <h4 style="font-size:1.1rem; color:var(--text-dark); margin-bottom:6px; font-weight:700;">No Companies or Projects</h4>
                        <p style="font-size:0.86rem; max-width:440px; margin:0 auto 18px; line-height:1.5;">All companies have been removed. You can start fresh by creating a company or load sample demo data.</p>
                        <div style="display:flex; justify-content:center; gap:10px; flex-wrap:wrap;">
                            <button type="button" class="btn btn-primary btn-sm" onclick="openCreateCompanyModal()">+ Create New Company</button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="seedDemoData()">📥 Import Demo Data</button>
                        </div>
                    </div>
                `;
                if (summaryEl) summaryEl.textContent = '0 Companies, 0 Projects';
                return;
            }

            if (filteredCompanies.length === 0) {
                container.innerHTML = `
                    <div style="text-align:center; padding:36px 20px; color:var(--text-muted);">
                        <p style="font-size:0.95rem; margin-bottom:12px;">No companies or projects found matching "${escapeHtml(search)}".</p>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openCreateCompanyModal()">+ Create New Company</button>
                    </div>
                `;
                if (summaryEl) summaryEl.textContent = '0 Companies, 0 Projects';
                return;
            }

            let html = '';
            filteredCompanies.forEach(c => {
                totalCompanies++;
                let projects = c.projects || [];
                if (term) {
                    const compMatch = (c.name || '').toLowerCase().includes(term) || (c.client_name || '').toLowerCase().includes(term);
                    if (!compMatch) {
                        projects = projects.filter(p => (p.title || '').toLowerCase().includes(term));
                    }
                }
                totalProjects += projects.length;

                let projectsHtml = '';
                if (projects.length === 0) {
                    projectsHtml = `
                        <div style="padding:12px 20px 12px 32px; color:var(--text-muted); font-size:0.84rem; display:flex; align-items:center; gap:8px;">
                            <span>No projects under this company yet.</span>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="openCreateProjectModal(${c.id})" style="padding:2px 8px; font-size:0.8rem; color:var(--brand-primary);">+ Add Project</button>
                        </div>
                    `;
                } else {
                    projectsHtml = `
                        <ul class="manage-projects-list">
                            ${projects.map(p => {
                                const statusBadge = p.status === 'completed' 
                                    ? '<span style="background:#ecfdf5; color:#047857; font-size:0.72rem; padding:2px 8px; border-radius:12px; font-weight:700;">Completed</span>'
                                    : '<span style="background:#eff6ff; color:#1d4ed8; font-size:0.72rem; padding:2px 8px; border-radius:12px; font-weight:700;">In Progress</span>';

                                return `
                                    <li class="manage-project-item">
                                        <div class="manage-project-main">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#1e3b4f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                                            <div>
                                                <span class="manage-project-title">${escapeHtml(p.title)}</span>
                                                <div class="manage-project-meta">
                                                    <span>Rate: <strong>${formatRate(p.hourly_rate, p.currency)}</strong></span>
                                                    <span>&bull;</span>
                                                    <span>${p.task_count || 0} tasks (${parseFloat(p.total_hours || 0).toFixed(1)}h)</span>
                                                    <span>&bull;</span>
                                                    <span style="font-weight:700; color:var(--brand-primary);">${formatPrice(p.total_price || 0, p.currency)}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            ${statusBadge}
                                            <div class="manage-project-actions">
                                                <button type="button" class="btn-action-icon" title="View Project in Workspace" onclick="closeModal('manageListModal'); selectProject(${p.id});">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                </button>
                                                <button type="button" class="btn-action-icon" title="Edit Project" onclick="openEditProjectModal(${p.id})">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                                </button>
                                                <button type="button" class="btn-action-icon danger" title="Delete Project" onclick="deleteProject(${p.id}, '${escapeJsString(p.title)}')">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </li>
                                `;
                            }).join('')}
                        </ul>
                    `;
                }

                html += `
                    <div class="manage-company-card">
                        <div class="manage-company-header">
                            <div class="manage-company-info">
                                <span class="company-dot" style="background-color: ${c.color || '#12466f'};"></span>
                                <span class="manage-company-title">${escapeHtml(c.name)}</span>
                                ${c.client_name ? `<span class="manage-company-sub">(Client: ${escapeHtml(c.client_name)})</span>` : ''}
                            </div>
                            <div class="manage-company-actions">
                                <span class="badge-count" style="margin-right:4px;" title="${c.projects.length} Projects">${c.projects.length} Proj</span>
                                <button type="button" class="btn btn-ghost btn-sm" style="padding:4px 8px; font-size:0.8rem;" onclick="openCreateProjectModal(${c.id})" title="Add new project to ${escapeHtml(c.name)}">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                    <span>+ Project</span>
                                </button>
                                <button type="button" class="btn-action-icon" onclick="openEditCompanyModal(${c.id})" title="Edit Company Details">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                </button>
                                <button type="button" class="btn-action-icon danger" onclick="deleteCompany(${c.id}, '${escapeJsString(c.name)}')" title="Delete Company and all associated projects/tasks">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                </button>
                            </div>
                        </div>
                        ${projectsHtml}
                    </div>
                `;
            });

            container.innerHTML = html;
            if (summaryEl) {
                summaryEl.textContent = `Total: ${totalCompanies} Companies, ${totalProjects} Projects`;
            }
        }

        async function deleteAllCompanies() {
            if (!confirm("Are you sure you want to delete ALL companies, projects, and tasks?\n\nWARNING: This will permanently wipe all freelance records!")) {
                return;
            }
            try {
                const res = await fetch(`${API_BASE}?api_action=delete_all_companies`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Failed to delete all companies');

                showToast(json.message || 'All companies and projects have been deleted.', 'success');
                activeProjectId = null;
                activeProjectData = null;
                document.getElementById('projectEmptyState').style.display = 'block';
                document.getElementById('projectHeroCard').style.display = 'none';
                document.getElementById('tasksListSection').style.display = 'none';
                await loadCompaniesAndProjects();
                renderManageListModalContent('');
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        async function seedDemoData() {
            if (!confirm("Load sample demo companies, projects, and tasks?")) {
                return;
            }
            try {
                const res = await fetch(`${API_BASE}?api_action=seed_demo_data`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Failed to load demo data');

                showToast(json.message || 'Demo data loaded successfully!', 'success');
                await loadCompaniesAndProjects();
                renderManageListModalContent('');
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // --- MODAL HANDLERS: TASK ---
        const btnCreateTask = document.getElementById('btnCreateTask');
        if (btnCreateTask) {
            btnCreateTask.addEventListener('click', openCreateTaskModal);
        }

        function openCreateTaskModal() {
            if (!activeProjectData || !activeProjectData.project) {
                showToast('Please select a project first.', 'error');
                return;
            }
            const curr = activeProjectData.project.currency || '$';
            const sym = getCurrencySymbol(curr);
            const rateLabel = document.getElementById('taskPricePerHourLabel');
            const rateInput = document.getElementById('taskPricePerHour');

            if (rateLabel) {
                if (sym === 'تومان') {
                    rateLabel.textContent = 'Price Per Hour (تومان / ساعت) *';
                } else if (sym === '€') {
                    rateLabel.textContent = 'Price Per Hour (€ / hr) *';
                } else {
                    rateLabel.textContent = 'Price Per Hour ($ / hr) *';
                }
            }
            if (rateInput) {
                rateInput.step = 'any';
                rateInput.min = '0';
                rateInput.placeholder = sym === 'تومان' ? 'e.g. 500000' : 'e.g. 50.00';
                rateInput.value = activeProjectData.project.hourly_rate || (sym === 'تومان' ? '500000' : '50');
            }

            document.getElementById('taskModalTitle').textContent = 'Create New Task';
            document.getElementById('taskId').value = '';
            document.getElementById('taskTitle').value = '';
            document.getElementById('taskDate').value = new Date().toISOString().split('T')[0];
            document.getElementById('taskStartTime').value = '09:00';
            document.getElementById('taskEndTime').value = '13:00';
            document.getElementById('taskStatus').value = 'completed';
            document.getElementById('taskDescription').value = '';

            updateTaskCalculations();
            openModal('taskModal');
        }

        function openEditTaskModal(taskId) {
            if (!activeProjectData) return;
            const task = activeProjectData.tasks.find(t => t.id === taskId);
            if (!task) return;

            const curr = activeProjectData.project?.currency || '$';
            const sym = getCurrencySymbol(curr);
            const rateLabel = document.getElementById('taskPricePerHourLabel');
            const rateInput = document.getElementById('taskPricePerHour');

            if (rateLabel) {
                if (sym === 'تومان') {
                    rateLabel.textContent = 'Price Per Hour (تومان / ساعت) *';
                } else if (sym === '€') {
                    rateLabel.textContent = 'Price Per Hour (€ / hr) *';
                } else {
                    rateLabel.textContent = 'Price Per Hour ($ / hr) *';
                }
            }
            if (rateInput) {
                rateInput.step = 'any';
                rateInput.min = '0';
                rateInput.placeholder = sym === 'تومان' ? 'e.g. 500000' : 'e.g. 50.00';
                rateInput.value = task.price_per_hour;
            }

            document.getElementById('taskModalTitle').textContent = 'Edit Task';
            document.getElementById('taskId').value = task.id;
            document.getElementById('taskTitle').value = task.title;
            document.getElementById('taskDate').value = task.task_date;
            document.getElementById('taskStartTime').value = task.start_time.substring(0, 5);
            document.getElementById('taskEndTime').value = task.end_time.substring(0, 5);
            document.getElementById('taskStatus').value = task.status;
            document.getElementById('taskDescription').value = task.description || '';

            updateTaskCalculations();
            openModal('taskModal');
        }

        const taskForm = document.getElementById('taskForm');
        if (taskForm) {
            taskForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const id = document.getElementById('taskId').value;
                const payload = {
                    id: id,
                    project_id: activeProjectId,
                    title: document.getElementById('taskTitle').value.trim(),
                    task_date: document.getElementById('taskDate').value,
                    start_time: document.getElementById('taskStartTime').value,
                    end_time: document.getElementById('taskEndTime').value,
                    price_per_hour: document.getElementById('taskPricePerHour').value,
                    status: document.getElementById('taskStatus').value,
                    description: document.getElementById('taskDescription').value.trim()
                };
                const action = id ? 'update_task' : 'create_task';

                try {
                    const res = await fetch(`${API_BASE}?api_action=${action}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const json = await res.json();
                    if (!json.success) throw new Error(json.error || 'Failed to save task');

                    closeModal('taskModal');
                    showToast(json.message || 'Task saved successfully!', 'success');
                    selectProject(activeProjectId);
                    loadCompaniesAndProjects(activeProjectId);
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        }

        async function deleteTask(taskId) {
            if (!confirm('Are you sure you want to delete this task?')) return;

            try {
                const res = await fetch(`${API_BASE}?api_action=delete_task`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: taskId })
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Failed to delete task');

                showToast('Task deleted.', 'success');
                selectProject(activeProjectId);
                loadCompaniesAndProjects(activeProjectId);
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // --- SHARE LINK MODAL ---
        const btnShare = document.getElementById('btnShareProject');
        if (btnShare) {
            btnShare.addEventListener('click', () => {
                if (!activeProjectData || !activeProjectData.project) return;
                const p = activeProjectData.project;
                const origin = window.location.origin + window.location.pathname;

                const projectUrl = `${origin}?share=${p.share_token}`;
                const companyUrl = `${origin}?share_company=${p.company_share_token}`;

                document.getElementById('shareProjectUrlInput').value = projectUrl;
                document.getElementById('shareProjectPreviewBtn').href = projectUrl;

                document.getElementById('shareCompanyUrlInput').value = companyUrl;
                document.getElementById('shareCompanyPreviewBtn').href = companyUrl;

                openModal('shareModal');
            });
        }

        function copyShareLink(inputId) {
            const input = document.getElementById(inputId);
            if (!input) return;
            input.select();
            input.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(input.value).then(() => {
                showToast('Share link copied to clipboard!', 'success');
            }).catch(() => {
                document.execCommand('copy');
                showToast('Share link copied!', 'success');
            });
        }

        // --- LOGIN FORM ---
        const loginForm = document.getElementById('standaloneLoginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const u = document.getElementById('loginUser').value.trim();
                const p = document.getElementById('loginPass').value;

                try {
                    const res = await fetch(`${API_BASE}?api_action=login`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ username: u, password: p })
                    });
                    const json = await res.json();
                    if (!json.success) throw new Error(json.error || 'Login failed');

                    showToast('Login successful! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        }

        // --- PUBLIC SHARE MODE LOADER (If accessed with ?share= or ?share_company=) ---
        <?php if ($isPublicMode): ?>
        async function loadPublicShareView() {
            const urlParams = new URLSearchParams(window.location.search);
            const projectToken = urlParams.get('share');
            const companyToken = urlParams.get('share_company');

            try {
                if (projectToken) {
                    const res = await fetch(`${API_BASE}?api_action=get_shared_project&token=${encodeURIComponent(projectToken)}`);
                    const json = await res.json();
                    if (!json.success) throw new Error(json.error || 'Could not load shared project.');

                    const pCurr = json.project.currency || '$';
                    document.getElementById('sharedTitle').textContent = json.project.title;
                    document.getElementById('sharedSubtitle').textContent = `Company: ${json.project.company_name} • Rate: ${formatRate(json.project.hourly_rate, pCurr)}`;
                    document.getElementById('sharedDescription').textContent = json.project.description || 'Project timesheet summary.';
                    document.getElementById('sharedTotalHours').textContent = `${json.summary.total_hours.toFixed(2)} hrs`;
                    document.getElementById('sharedTotalPrice').textContent = formatPrice(json.summary.total_price, pCurr);

                    const tbody = document.getElementById('sharedTasksTbody');
                    if (json.tasks.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">No tasks recorded for this project yet.</td></tr>`;
                    } else {
                        tbody.innerHTML = json.tasks.map(t => `
                            <tr>
                                <td>
                                    <strong style="color:var(--brand-dark); font-size:0.94rem;">${escapeHtml(t.title)}</strong>
                                    <div style="font-size:0.82rem; color:#475569; margin-top:4px;">${escapeHtml(t.description || '')}</div>
                                </td>
                                <td style="font-family:'JetBrains Mono', monospace; font-size:0.82rem; color:var(--text-muted);">${formatDate(t.task_date)}</td>
                                <td style="font-family:'JetBrains Mono', monospace; font-size:0.82rem; color:var(--text-muted);">${formatTime(t.start_time)} &ndash; ${formatTime(t.end_time)}</td>
                                <td style="font-family:'JetBrains Mono', monospace; font-size:0.84rem;">${formatRate(t.price_per_hour, pCurr)}</td>
                                <td style="font-family:'JetBrains Mono', monospace; font-weight:700; color:var(--brand-primary);">${parseFloat(t.duration_hours).toFixed(2)}h</td>
                                <td style="font-family:'JetBrains Mono', monospace; font-weight:800; color:var(--brand-primary); text-align:right; font-size:0.94rem;">${formatPrice(t.total_price, pCurr)}</td>
                            </tr>
                        `).join('');
                    }
                } else if (companyToken) {
                    const res = await fetch(`${API_BASE}?api_action=get_shared_company&token=${encodeURIComponent(companyToken)}`);
                    const json = await res.json();
                    if (!json.success) throw new Error(json.error || 'Could not load company share report.');

                    const currencies = [...new Set(json.projects.map(p => p.currency || '$'))];
                    let totalSummaryStr = '';
                    if (currencies.length <= 1) {
                        totalSummaryStr = formatPrice(json.summary.overall_price, currencies[0] || '$');
                    } else {
                        const byCurr = {};
                        json.projects.forEach(p => {
                            const c = p.currency || '$';
                            byCurr[c] = (byCurr[c] || 0) + (parseFloat(p.total_price) || 0);
                        });
                        totalSummaryStr = Object.entries(byCurr).map(([c, sum]) => formatPrice(sum, c)).join(' + ');
                    }

                    document.getElementById('sharedTitle').textContent = `${json.company.name} — All Projects Timesheet`;
                    document.getElementById('sharedSubtitle').textContent = `Total Projects: ${json.summary.total_projects} • Client: ${json.company.client_name || 'N/A'}`;
                    document.getElementById('sharedDescription').textContent = `Consolidated timesheet and financial breakdown across all projects for ${json.company.name}.`;
                    document.getElementById('sharedTotalHours').textContent = `${json.summary.overall_hours.toFixed(2)} hrs`;
                    document.getElementById('sharedTotalPrice').textContent = totalSummaryStr;

                    const tbody = document.getElementById('sharedTasksTbody');
                    let rowsHtml = '';
                    json.projects.forEach(p => {
                        const pCurr = p.currency || '$';
                        rowsHtml += `
                            <tr style="background:#f1f5f9;">
                                <td colspan="6" style="padding:10px 16px; font-weight:800; color:var(--brand-dark); font-size:0.92rem;">
                                    Project: ${escapeHtml(p.title)} (Subtotal: ${formatPrice(p.total_price, pCurr)} &bull; ${p.total_hours.toFixed(2)} hrs)
                                </td>
                            </tr>
                        `;
                        if (p.tasks.length === 0) {
                            rowsHtml += `<tr><td colspan="6" style="padding:12px 16px; color:var(--text-muted); font-size:0.84rem;">No tasks logged.</td></tr>`;
                        } else {
                            p.tasks.forEach(t => {
                                rowsHtml += `
                                    <tr>
                                        <td>
                                            <strong style="color:var(--brand-dark); font-size:0.92rem;">${escapeHtml(t.title)}</strong>
                                            <div style="font-size:0.82rem; color:#475569; margin-top:3px;">${escapeHtml(t.description || '')}</div>
                                        </td>
                                        <td style="font-family:'JetBrains Mono', monospace; font-size:0.82rem; color:var(--text-muted);">${formatDate(t.task_date)}</td>
                                        <td style="font-family:'JetBrains Mono', monospace; font-size:0.82rem; color:var(--text-muted);">${formatTime(t.start_time)} &ndash; ${formatTime(t.end_time)}</td>
                                        <td style="font-family:'JetBrains Mono', monospace; font-size:0.84rem;">${formatRate(t.price_per_hour, pCurr)}</td>
                                        <td style="font-family:'JetBrains Mono', monospace; font-weight:700; color:var(--brand-primary);">${parseFloat(t.duration_hours).toFixed(2)}h</td>
                                        <td style="font-family:'JetBrains Mono', monospace; font-weight:800; color:var(--brand-primary); text-align:right;">${formatPrice(t.total_price, pCurr)}</td>
                                    </tr>
                                `;
                            });
                        }
                    });
                    tbody.innerHTML = rowsHtml;
                }
            } catch (err) {
                document.getElementById('publicShareContainer').innerHTML = `
                    <div style="text-align:center; padding:50px 20px;">
                        <h3 style="color:var(--danger); font-size:1.3rem;">Error Loading Shared Timesheet</h3>
                        <p style="color:var(--text-muted); margin-top:8px;">${escapeHtml(err.message)}</p>
                    </div>
                `;
            }
        }
        window.addEventListener('DOMContentLoaded', loadPublicShareView);
        <?php else: ?>
        // Initialize Authenticated View
        window.addEventListener('DOMContentLoaded', () => {
            loadCompaniesAndProjects();
        });
        <?php endif; ?>

        // Helper utilities
        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>"']/g, m => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[m]));
        }

        function formatDate(dateStr) {
            if (!dateStr) return '';
            try {
                const d = new Date(dateStr + 'T00:00:00');
                return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            } catch (e) {
                return dateStr;
            }
        }

        function formatTime(timeStr) {
            if (!timeStr) return '';
            try {
                const parts = timeStr.split(':');
                let h = parseInt(parts[0], 10);
                const m = parts[1];
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12;
                h = h ? h : 12;
                return `${h}:${m} ${ampm}`;
            } catch (e) {
                return timeStr;
            }
        }

        // Floating Quick Navigation Menu Controls
        const floatFab = document.getElementById('floatMenuFab');
        const floatPanel = document.getElementById('floatMenuPanel');
        const floatBackdrop = document.getElementById('floatMenuBackdrop');
        const floatClose = document.getElementById('floatMenuCloseBtn');

        function toggleFloatMenu(forceState) {
            if (!floatPanel) return;
            const shouldOpen = typeof forceState === 'boolean' ? forceState : !floatPanel.classList.contains('active');
            if (shouldOpen) {
                floatPanel.classList.add('active');
                if (floatBackdrop) floatBackdrop.classList.add('active');
                if (floatFab) floatFab.classList.add('active');
            } else {
                floatPanel.classList.remove('active');
                if (floatBackdrop) floatBackdrop.classList.remove('active');
                if (floatFab) floatFab.classList.remove('active');
            }
        }

        if (floatFab) {
            floatFab.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleFloatMenu();
            });
        }
        if (floatClose) {
            floatClose.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleFloatMenu(false);
            });
        }
        if (floatBackdrop) {
            floatBackdrop.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleFloatMenu(false);
            });
        }
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && floatPanel && floatPanel.classList.contains('active')) {
                toggleFloatMenu(false);
            }
        });

        // Global Scroll-to-Top Listener for Platform Footer
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-footer-top');
            if (btn) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
                const main = document.querySelector('.freelance-main-content');
                if (main) {
                    main.scrollTo({ top: 0, behavior: 'smooth' });
                }
            }
        });
    </script>
</body>
</html>
