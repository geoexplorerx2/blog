<?php
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

    public static function ensureProjectsTableExists(): void
    {
        $db = Database::getConnection();
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

// Handle Direct GET Logout (e.g. projects.php?action=logout or projects.php?api_action=logout)
if ((isset($_GET['action']) && $_GET['action'] === 'logout') || (isset($_GET['api_action']) && $_GET['api_action'] === 'logout' && $_SERVER['REQUEST_METHOD'] === 'GET')) {
    Auth::logout();
    header('Location: projects.php');
    exit;
}

require_once __DIR__ . '/footer_component.php';

Auth::ensureUserTableExists();
Auth::ensureProjectsTableExists();

// -------------------------------------------------------------
// API Actions
// -------------------------------------------------------------
if (isset($_GET['api_action'])) {
    ob_start();
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['api_action'];
    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput, true) ?? $_POST;

    if ($action === 'login') {
        $user = trim($inputData['username'] ?? '');
        $pass = $inputData['password'] ?? '';
        ob_clean();
        if (Auth::login($user, $pass)) {
            echo json_encode(['success' => true, 'username' => Auth::getCurrentUser()]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid username or password.']);
        }
        exit;
    }

    if ($action === 'logout') {
        Auth::logout();
        ob_clean();
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'check_auth') {
        ob_clean();
        echo json_encode([
            'authenticated' => Auth::isLoggedIn(),
            'username' => Auth::getCurrentUser()
        ]);
        exit;
    }

    if ($action === 'get_projects') {
        $db = Database::getConnection();
        if ($db === null) {
            ob_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
            exit;
        }
        $stmt = $db->query("SELECT * FROM projects ORDER BY featured DESC, id DESC");
        $projects = $stmt->fetchAll();
        foreach ($projects as &$p) {
            $p['technologies'] = !empty($p['technologies']) ? json_decode($p['technologies'], true) : [];
            $p['highlights'] = !empty($p['highlights']) ? json_decode($p['highlights'], true) : [];
        }
        ob_clean();
        echo json_encode(['success' => true, 'data' => $projects]);
        exit;
    }

    if ($action === 'save_project') {
        if (!Auth::isLoggedIn()) {
            ob_clean();
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Administrator authentication required.', 'require_login' => true]);
            exit;
        }

        $db = Database::getConnection();
        if ($db === null) {
            ob_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
            exit;
        }

        $id = !empty($inputData['id']) ? (int)$inputData['id'] : null;
        $title = trim($inputData['title'] ?? '');
        $description = trim($inputData['description'] ?? '');
        $projectUrl = trim($inputData['project_url'] ?? '');
        $githubUrl = trim($inputData['github_url'] ?? '');
        $company = trim($inputData['company'] ?? '');
        $duration = trim($inputData['duration'] ?? '');
        $role = trim($inputData['role'] ?? '');
        $featured = !empty($inputData['featured']) ? 1 : 0;

        if (empty($title) || empty($description)) {
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Project Title and Description are required.']);
            exit;
        }

        // Parse technologies
        $technologies = [];
        if (isset($inputData['technologies'])) {
            if (is_array($inputData['technologies'])) {
                $technologies = $inputData['technologies'];
            } elseif (is_string($inputData['technologies'])) {
                $rawTech = trim($inputData['technologies']);
                if (str_starts_with($rawTech, '[')) {
                    $technologies = json_decode($rawTech, true) ?: [];
                } else {
                    $technologies = array_values(array_filter(array_map('trim', explode(',', $rawTech))));
                }
            }
        }
        $techJson = json_encode($technologies, JSON_UNESCAPED_UNICODE);

        // Parse highlights
        $highlights = [];
        if (isset($inputData['highlights'])) {
            if (is_array($inputData['highlights'])) {
                $highlights = $inputData['highlights'];
            } elseif (is_string($inputData['highlights'])) {
                $lines = explode("\n", str_replace("\r", "", $inputData['highlights']));
                foreach ($lines as $line) {
                    $clean = trim(ltrim(trim($line), "-*•"));
                    if (!empty($clean)) $highlights[] = $clean;
                }
            }
        }
        $highlightsJson = json_encode($highlights, JSON_UNESCAPED_UNICODE);

        try {
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
                    ':project_url' => $projectUrl,
                    ':github_url' => $githubUrl,
                    ':company' => $company,
                    ':duration' => $duration,
                    ':role' => $role,
                    ':technologies' => $techJson,
                    ':highlights' => $highlightsJson,
                    ':featured' => $featured
                ]);
                $msg = 'Project updated successfully!';
            } else {
                $stmt = $db->prepare("INSERT INTO projects (
                    title, description, project_url, github_url, company, duration, role, technologies, highlights, featured
                ) VALUES (
                    :title, :description, :project_url, :github_url, :company, :duration, :role, :technologies, :highlights, :featured
                )");
                $stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':project_url' => $projectUrl,
                    ':github_url' => $githubUrl,
                    ':company' => $company,
                    ':duration' => $duration,
                    ':role' => $role,
                    ':technologies' => $techJson,
                    ':highlights' => $highlightsJson,
                    ':featured' => $featured
                ]);
                $id = (int)$db->lastInsertId();
                $msg = 'Project created successfully!';
            }

            ob_clean();
            echo json_encode(['success' => true, 'id' => $id, 'message' => $msg]);
        } catch (Exception $e) {
            ob_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete_project') {
        if (!Auth::isLoggedIn()) {
            ob_clean();
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Administrator authentication required.', 'require_login' => true]);
            exit;
        }

        $id = !empty($inputData['id']) ? (int)$inputData['id'] : null;
        if (!$id) {
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid Project ID.']);
            exit;
        }

        $db = Database::getConnection();
        if ($db === null) {
            ob_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
            exit;
        }

        try {
            $stmt = $db->prepare("DELETE FROM projects WHERE id = :id");
            $stmt->execute([':id' => $id]);
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Project deleted successfully.']);
        } catch (Exception $e) {
            ob_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// Fetch Projects for Initial Server Render
$db = Database::getConnection();
$initialProjects = [];
if ($db !== null) {
    $stmt = $db->query("SELECT * FROM projects ORDER BY featured DESC, id DESC");
    $initialProjects = $stmt->fetchAll();
    foreach ($initialProjects as &$p) {
        $p['technologies'] = !empty($p['technologies']) ? json_decode($p['technologies'], true) : [];
        $p['highlights'] = !empty($p['highlights']) ? json_decode($p['highlights'], true) : [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Featured Projects &amp; Portfolio | Farshad Nabizade</title>
    <link rel="icon" type="image/png" href="https://uploads.neginsafareh-academy.ir/files/favicon_20260907_085955_27bd31cd.png">
    <style>
        :root {
            --navy-900: #0a2540;
            --navy-800: #103557;
            --navy-700: #1b4970;
            --blue-600: #2178ae;
            --blue-500: #2b8ac4;
            --blue-200: #bae6fd;
            --blue-50: #f0f9ff;
            --bg: #f8fafc;
            --surface: #ffffff;
            --border: #e2e8f0;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --font-main: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            height: auto;
            min-height: 100%;
            overflow-y: auto;
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-main);
            background-color: var(--bg);
            color: var(--text-dark);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* Top Header */
        .projects-header {
            background: linear-gradient(135deg, var(--navy-900) 0%, var(--navy-800) 50%, var(--blue-600) 100%);
            color: white;
            padding: 2.75rem 1.5rem 3rem 1.5rem;
            text-align: center;
            position: relative;
            box-shadow: 0 4px 20px rgba(10, 37, 64, 0.15);
        }

        .projects-header h1 {
            font-size: 2.2rem;
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: 0.5rem;
        }

        .projects-header p {
            color: #dbeafe;
            font-size: 1.05rem;
            max-width: 650px;
            margin: 0 auto;
        }

        .container {
            max-width: 1150px;
            margin: -1.75rem auto 3rem auto;
            padding: 0 1.25rem;
            position: relative;
            z-index: 10;
        }

        /* Controls & Search Bar */
        .controls-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.1rem 1.25rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
            margin-bottom: 1.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }

        .search-row {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 250px;
        }

        .search-box input {
            width: 100%;
            padding: 0.65rem 1rem 0.65rem 2.4rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            font-size: 0.92rem;
            outline: none;
            font-family: inherit;
            transition: all 0.2s ease;
        }

        .search-box input:focus {
            border-color: var(--blue-500);
            box-shadow: 0 0 0 3px rgba(33, 120, 174, 0.15);
        }

        .search-box svg {
            position: absolute;
            left: 0.8rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .tech-filter-bar {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-btn {
            background: var(--bg);
            border: 1px solid var(--border);
            color: var(--text-muted);
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            font-family: inherit;
        }

        .filter-btn:hover {
            border-color: var(--blue-500);
            color: var(--blue-600);
            background: var(--blue-50);
        }

        .filter-btn.active {
            background: var(--navy-900);
            color: white;
            border-color: var(--navy-900);
        }

        .btn-add-project {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #10b981;
            color: white;
            border: none;
            padding: 0.65rem 1.1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-add-project:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        }

        /* Projects Grid */
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(520px, 1fr));
            gap: 1.5rem;
        }

        .project-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-left: 4px solid var(--blue-600);
            border-radius: 14px;
            padding: 1.5rem;
            box-shadow: 0 4px 16px rgba(10, 37, 64, 0.04);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            position: relative;
        }

        .project-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 28px rgba(10, 37, 64, 0.08);
            border-color: var(--blue-200);
            border-left-color: var(--navy-900);
        }

        .project-meta-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .project-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--navy-900);
            letter-spacing: -0.015em;
            line-height: 1.35;
        }

        .project-details-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .detail-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: var(--bg);
            border: 1px solid var(--border);
            padding: 0.25rem 0.65rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--navy-700);
        }

        .detail-badge.company {
            background: #e0f2fe;
            color: #0369a1;
            border-color: #bae6fd;
        }

        .project-desc {
            font-size: 0.94rem;
            color: #334155;
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .highlights-list {
            list-style: none;
            margin-bottom: 1.25rem;
            padding-left: 0;
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }

        .highlights-list li {
            font-size: 0.88rem;
            color: #475569;
            position: relative;
            padding-left: 1.25rem;
            line-height: 1.5;
        }

        .highlights-list li::before {
            content: "•";
            position: absolute;
            left: 0.3rem;
            color: var(--blue-500);
            font-size: 1.1rem;
            line-height: 1;
            top: 0.1rem;
        }

        .tech-tags-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-bottom: 1.25rem;
            padding-top: 0.75rem;
            border-top: 1px dashed var(--border);
        }

        .tech-pill {
            background: #f1f5f9;
            color: #334155;
            font-size: 0.78rem;
            font-weight: 600;
            padding: 0.22rem 0.6rem;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }

        .project-actions-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 0.85rem;
            border-top: 1px solid var(--border);
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .project-links {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .btn-link-action {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.4rem 0.85rem;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--border);
            color: var(--navy-800);
            background: white;
            transition: all 0.15s ease;
        }

        .btn-link-action:hover {
            border-color: var(--blue-500);
            color: var(--blue-600);
            background: var(--blue-50);
        }

        .btn-link-action.demo {
            background: var(--navy-900);
            color: white;
            border-color: var(--navy-900);
        }

        .btn-link-action.demo:hover {
            background: var(--navy-800);
        }

        .admin-item-controls {
            display: flex;
            gap: 0.4rem;
            align-items: center;
            margin-left: auto;
        }

        .btn-icon-control {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: transparent;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            gap: 0.3rem;
            font-family: inherit;
        }

        .btn-icon-control.edit {
            color: var(--blue-600);
            border-color: var(--blue-200);
            background: var(--blue-50);
        }

        .btn-icon-control.edit:hover {
            background: #e0f2fe;
            border-color: var(--blue-500);
        }

        .btn-icon-control.delete {
            color: #dc2626;
            border-color: #fecaca;
            background: #fef2f2;
        }

        .btn-icon-control.delete:hover {
            background: #fee2e2;
            border-color: #ef4444;
        }

        .no-projects-msg {
            text-align: center;
            padding: 3rem 1.5rem;
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border);
            color: var(--text-muted);
            grid-column: 1 / -1;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(10, 37, 64, 0.6);
            backdrop-filter: blur(4px);
            z-index: 1300;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            padding: 1rem;
        }

        .modal-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }

        .modal-card {
            background: white;
            border-radius: 16px;
            width: 100%;
            max-width: 680px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
            transform: translateY(15px);
            transition: transform 0.2s ease;
            overflow: hidden;
        }

        .modal-overlay.active .modal-card {
            transform: translateY(0);
        }

        .modal-header {
            padding: 1.1rem 1.5rem;
            background: var(--navy-900);
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            max-height: calc(90vh - 130px);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .form-group label {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--navy-800);
        }

        .form-group input[type="text"],
        .form-group input[type="url"],
        .form-group input[type="password"],
        .form-group textarea {
            width: 100%;
            padding: 0.6rem 0.85rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            font-size: 0.92rem;
            font-family: inherit;
            outline: none;
            transition: border-color 0.15s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--blue-500);
            box-shadow: 0 0 0 3px rgba(33, 120, 174, 0.15);
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            background: #f8fafc;
        }

        /* Close Button Rule: ZERO Background */
        .modal-close, .close-btn, #floatMenuCloseBtn, #closeProjectModalBtn, #closeLoginModalBtn, #closeDeleteModalBtn {
            background: transparent !important;
            background-color: transparent !important;
            border: none !important;
            box-shadow: none !important;
            outline: none !important;
            cursor: pointer;
            font-size: 1.4rem;
            line-height: 1;
            padding: 0.2rem 0.4rem;
            color: inherit;
            transition: transform 0.15s ease, opacity 0.15s ease;
            opacity: 0.85;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover, .close-btn:hover, #floatMenuCloseBtn:hover, #closeProjectModalBtn:hover, #closeLoginModalBtn:hover, #closeDeleteModalBtn:hover {
            background: transparent !important;
            background-color: transparent !important;
            opacity: 1;
            transform: scale(1.15);
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            bottom: 1.75rem;
            left: 1.75rem;
            z-index: 1500;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            pointer-events: none;
        }

        .toast {
            background: var(--navy-900);
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
            font-size: 0.9rem;
            font-weight: 600;
            border-left: 4px solid var(--blue-500);
            animation: toastIn 0.25s ease;
            pointer-events: auto;
        }

        .toast.success { border-left-color: #10b981; }
        .toast.error { border-left-color: #ef4444; }

        @keyframes toastIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Floating Menu System */
        .float-menu-fab {
            position: fixed;
            bottom: 1.75rem;
            right: 1.75rem;
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--navy-800) 0%, var(--blue-600) 100%);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.35);
            box-shadow: 0 6px 20px rgba(10, 37, 64, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1200;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            outline: none;
        }

        .float-menu-fab:hover {
            transform: scale(1.08) translateY(-2px);
            box-shadow: 0 8px 26px rgba(10, 37, 64, 0.45);
        }

        .float-menu-fab.active {
            transform: rotate(90deg);
            background: var(--navy-900);
            border-color: var(--blue-200);
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
            border: 1px solid var(--border);
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
            background: linear-gradient(135deg, var(--navy-900) 0%, var(--navy-800) 100%);
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
            color: var(--text-dark);
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
            background: var(--blue-50);
            color: var(--blue-600);
            transform: translateX(3px);
        }

        .float-menu-item svg {
            color: var(--blue-500);
            flex-shrink: 0;
        }

        .float-menu-divider {
            height: 1px;
            background: var(--border);
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

        /* Premium Global Footer */
        .app-global-footer {
            background: linear-gradient(180deg, #0a2540 0%, #06182a 100%);
            color: #94a3b8;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            margin-top: 4rem;
            padding: 3.5rem 1.5rem 1.5rem 1.5rem;
            position: relative;
            font-size: 0.9rem;
        }

        .footer-inner-container {
            max-width: 1150px;
            margin: 0 auto;
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
            background: linear-gradient(135deg, var(--blue-600) 0%, var(--blue-500) 100%);
            color: white;
            font-weight: 800;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(33, 120, 174, 0.35);
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
            background: var(--blue-500);
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

        /* Responsive fixes */
        @media (max-width: 768px) {
            .projects-header {
                padding: 2rem 1rem 2.5rem 1rem;
            }
            .projects-header h1 {
                font-size: 1.7rem;
            }
            .projects-header p {
                font-size: 0.95rem;
            }
            .container {
                padding: 0 1rem;
            }
            .projects-grid {
                grid-template-columns: 1fr;
            }
            .form-grid-2 {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            .controls-card {
                padding: 1rem;
            }
        }

        @media (max-width: 480px) {
            .projects-header {
                padding: 1.5rem 1rem 2rem 1rem;
            }
            .projects-header h1 {
                font-size: 1.4rem;
            }
            .projects-header p {
                font-size: 0.88rem;
            }
            .search-box {
                min-width: 100%;
            }
            .btn-add-project {
                width: 100%;
                justify-content: center;
            }
            .project-card {
                padding: 1.15rem;
            }
            .project-title {
                font-size: 1.15rem;
            }
            .project-actions-row {
                flex-direction: column;
                align-items: stretch;
            }
            .project-links {
                flex-wrap: wrap;
            }
            .admin-item-controls {
                margin-left: 0;
                flex-wrap: wrap;
            }
            .modal-body {
                padding: 1.1rem;
            }
            .modal-header {
                padding: 0.9rem 1.1rem;
            }
            .modal-footer {
                padding: 0.9rem 1.1rem;
                flex-wrap: wrap;
            }
            .modal-footer .btn-link-action,
            .modal-footer .btn-add-project {
                flex: 1;
                justify-content: center;
            }
            .toast-container {
                left: 1rem;
                right: 1rem;
                bottom: 1rem;
            }
            .float-menu-fab {
                bottom: 1rem;
                right: 1rem;
            }
            .float-menu-panel {
                bottom: 5rem;
                right: 1rem;
                left: 1rem;
                width: auto;
            }
        }
    </style>
</head>
<body>

<div class="projects-header">
    <h1>Featured Projects &amp; Portfolio</h1>
    <p style="font-style: italic; opacity: 0.95; font-size: 1.05rem; letter-spacing: 0.01em;">“Somewhere, something incredible is waiting to be known.”</p>
    <div style="font-size: 0.85rem; color: #93c5fd; font-weight: 600; margin-top: 0.4rem; letter-spacing: 0.04em;">— Carl Edward Sagan</div>
</div>

<div class="container">
    <!-- Controls Bar -->
    <div class="controls-card">
        <div class="search-row">
            <div class="search-box">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" id="projectSearchInput" placeholder="Search projects by name, company, role, or description...">
            </div>
            <?php if (Auth::isLoggedIn()): ?>
            <button type="button" class="btn-add-project" id="addProjectBtn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                <span>Add Project</span>
            </button>
            <?php endif; ?>
        </div>

        <div class="tech-filter-bar" id="techFilterBar">
            <button type="button" class="filter-btn active" data-tech="all">All Projects</button>
            <button type="button" class="filter-btn" data-tech="Next.js">Next.js</button>
            <button type="button" class="filter-btn" data-tech="React">React</button>
            <button type="button" class="filter-btn" data-tech="TypeScript">TypeScript</button>
            <button type="button" class="filter-btn" data-tech="Laravel">Laravel</button>
            <button type="button" class="filter-btn" data-tech="PHP">PHP</button>
            <button type="button" class="filter-btn" data-tech="Tailwind CSS">Tailwind CSS</button>
            <button type="button" class="filter-btn" data-tech="Redux Toolkit">Redux Toolkit</button>
            <button type="button" class="filter-btn" data-tech="Redis">Redis</button>
            <button type="button" class="filter-btn" data-tech="MySQL">MySQL</button>
        </div>
    </div>

    <!-- Projects Grid Container -->
    <div class="projects-grid" id="projectsGrid">
        <!-- Rendered dynamically via JavaScript -->
    </div>
</div>

<!-- Add / Edit Project Modal -->
<div class="modal-overlay" id="projectModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="projectModalTitle">Add New Project</h3>
            <button type="button" class="close-btn" id="closeProjectModalBtn">&times;</button>
        </div>
        <form id="projectForm">
            <div class="modal-body">
                <input type="hidden" id="p_id" value="">

                <div class="form-group">
                    <label for="p_title">Project Title <span style="color:#ef4444;">*</span></label>
                    <input type="text" id="p_title" required placeholder="e.g. BazarGah Marketplace Platform">
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="p_company">Company / Client</label>
                        <input type="text" id="p_company" placeholder="e.g. Gareno Pargas">
                    </div>
                    <div class="form-group">
                        <label for="p_duration">Duration / Period</label>
                        <input type="text" id="p_duration" placeholder="e.g. June 2025 - Present (1 yr 2 mos)">
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="p_role">Your Role / Responsibility</label>
                        <input type="text" id="p_role" placeholder="e.g. Lead Frontend Developer">
                    </div>
                    <div class="form-group">
                        <label for="p_featured" style="display:flex; align-items:center; gap:0.5rem; margin-top:1.5rem; cursor:pointer;">
                            <input type="checkbox" id="p_featured" value="1" style="width:18px; height:18px; cursor:pointer;">
                            <span>Feature this project prominently</span>
                        </label>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="p_projectUrl">Live Project Demo Link</label>
                        <input type="url" id="p_projectUrl" placeholder="https://example.com">
                    </div>
                    <div class="form-group">
                        <label for="p_githubUrl">GitHub Repository Link</label>
                        <input type="url" id="p_githubUrl" placeholder="https://github.com/username/project">
                    </div>
                </div>

                <div class="form-group">
                    <label for="p_description">Project Overview &amp; Description <span style="color:#ef4444;">*</span></label>
                    <textarea id="p_description" rows="3" required placeholder="Describe the goal, architectural foundation, and impact of the project..."></textarea>
                </div>

                <div class="form-group">
                    <label for="p_technologies">Technologies Used (Comma-separated)</label>
                    <input type="text" id="p_technologies" placeholder="Next.js, TypeScript, Tailwind CSS, Redux Toolkit, REST APIs">
                </div>

                <div class="form-group">
                    <label for="p_highlights">Key Deliverables &amp; Achievements (One per line)</label>
                    <textarea id="p_highlights" rows="4" placeholder="• Designed modular component architecture&#10;• Reduced latency by 45% with Redis caching&#10;• Integrated automated payment verification"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-link-action" id="cancelProjectModalBtn">Cancel</button>
                <button type="submit" class="btn-add-project" id="saveProjectBtn">Save Project</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-card" style="max-width: 440px;">
        <div class="modal-header" style="background: #dc2626;">
            <h3>Confirm Deletion</h3>
            <button type="button" class="close-btn" id="closeDeleteModalBtn">&times;</button>
        </div>
        <div class="modal-body" style="padding: 1.5rem; text-align: center;">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 1rem auto;"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
            <h4 style="font-size: 1.15rem; color: var(--navy-900); margin-bottom: 0.5rem;" id="deleteProjectTitle">Delete Project?</h4>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Are you sure you want to permanently delete this project? This action cannot be undone.</p>
        </div>
        <div class="modal-footer" style="justify-content: center;">
            <button type="button" class="btn-link-action" id="cancelDeleteBtn">Cancel</button>
            <button type="button" class="btn-add-project" id="confirmDeleteBtn" style="background:#dc2626;">Delete Permanently</button>
        </div>
    </div>
</div>

<!-- Admin Login Modal -->
<div class="modal-overlay" id="loginModal">
    <div class="modal-card" style="max-width: 420px;">
        <div class="modal-header">
            <h3 style="display:flex; align-items:center; gap:0.5rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                Administrator Login
            </h3>
            <button type="button" class="close-btn" id="closeLoginModalBtn">&times;</button>
        </div>
        <form id="loginForm">
            <div class="modal-body">
                <div class="form-group">
                    <label for="loginUsername">Username <span style="color:#ef4444;">*</span></label>
                    <input type="text" id="loginUsername" required placeholder="admin">
                </div>
                <div class="form-group">
                    <label for="loginPassword">Password <span style="color:#ef4444;">*</span></label>
                    <input type="password" id="loginPassword" required placeholder="••••••••">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-link-action" id="cancelLoginBtn">Cancel</button>
                <button type="submit" class="btn-add-project" id="loginSubmitBtn">Log In</button>
            </div>
        </form>
    </div>
</div>

<?php renderGlobalFooter(); ?>

<!-- Toast Notifications -->
<div class="toast-container" id="toastContainer"></div>

<!-- Floating Menu Widget -->
<div class="float-menu-backdrop" id="floatMenuBackdrop"></div>

<button type="button" class="float-menu-fab" id="floatMenuFab" title="Open Quick Navigation Menu" aria-label="Open Floating Menu">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
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
        <button type="button" class="close-btn" id="floatMenuCloseBtn" style="color: white; font-size: 1.25rem;">&times;</button>
    </div>

    <div class="float-menu-body">
        <!-- 1. Admin Status Section (FIRST) -->
        <div class="float-menu-group-label">Admin Status</div>
        <?php if (Auth::isLoggedIn()): ?>
            <div style="display:flex; align-items:center; gap:0.5rem; padding:0.55rem 0.75rem; background:rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.3); border-radius:8px; margin:0 0.15rem;">
                <span style="width:8px; height:8px; border-radius:50%; background:#22c55e; box-shadow:0 0 6px #22c55e; flex-shrink:0;"></span>
                <span style="font-size:0.82rem; font-weight:600; color:#15803d;">Admin: <strong><?php echo htmlspecialchars(Auth::getCurrentUser() ?? 'admin'); ?></strong></span>
                <span class="float-menu-badge" style="background:#dcfce7; color:#166534; margin-left:auto;">Online</span>
            </div>
        <?php else: ?>
            <div style="display:flex; align-items:center; gap:0.5rem; padding:0.55rem 0.75rem; background:rgba(100,116,139,0.08); border:1px solid rgba(100,116,139,0.2); border-radius:8px; margin:0 0.15rem;">
                <span style="width:8px; height:8px; border-radius:50%; background:#94a3b8; flex-shrink:0;"></span>
                <span style="font-size:0.82rem; font-weight:500; color:#475569;">Session: <strong>Guest (Read Only)</strong></span>
            </div>
        <?php endif; ?>

        <!-- 2. Authentication Section -->
        <div class="float-menu-divider"></div>
        <div class="float-menu-group-label">Authentication</div>
        <?php if (Auth::isLoggedIn()): ?>
            <button type="button" class="float-menu-item" id="floatHeaderLogoutBtn" style="color:#dc2626;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#ef4444;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                <span>Logout (Admin)</span>
                <span class="float-menu-badge" style="background:#fee2e2; color:#b91c1c;">Exit</span>
            </button>
        <?php else: ?>
            <button type="button" class="float-menu-item" id="floatHeaderLoginBtn" style="color:#0284c7;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#0284c7;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                <span>Admin Login</span>
                <span class="float-menu-badge" style="background:#e0f2fe; color:#0369a1;">Sign In</span>
            </button>
        <?php endif; ?>

        <!-- 3. Navigation Links -->
        <div class="float-menu-divider"></div>
        <div class="float-menu-group-label">Pages &amp; Navigation</div>
        <a href="projects.php" class="float-menu-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
            <span>Projects Showcase</span>
            <span class="float-menu-badge" style="background:#e0f2fe; color:#0369a1;">Active</span>
        </a>
        <a href="profile.php" class="float-menu-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
            <span>Resume &amp; Profile</span>
            <span class="float-menu-badge" style="background:#e0f2fe; color:#0369a1;">CV</span>
        </a>
        <a href="index.php" class="float-menu-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            <span>Questions Base</span>
        </a>

        <!-- 4. Quick Actions -->
        <div class="float-menu-divider"></div>
        <div class="float-menu-group-label">Quick Actions</div>
        <?php if (Auth::isLoggedIn()): ?>
        <button type="button" class="float-menu-item" id="floatAddProjectBtn">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
            <span>Add New Project</span>
        </button>
        <button type="button" class="float-menu-item" id="floatEditFooterBtn">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
            <span>Edit Footer Sections</span>
            <span class="float-menu-badge" style="background:#e0f2fe; color:#0369a1;">Config</span>
        </button>
        <?php endif; ?>
        <button type="button" class="float-menu-item" id="floatPrintBtn">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            <span>Print Page</span>
        </button>
        <button type="button" class="float-menu-item" id="floatScrollTopBtn">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>
            <span>Back to Top</span>
        </button>
    </div>
</div>

<script>
    let allProjects = <?php echo json_encode($initialProjects, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> || [];
    let activeFilter = 'all';
    let deleteTargetId = null;
    const isAuthenticated = <?php echo Auth::isLoggedIn() ? 'true' : 'false'; ?>;

    const projectsGrid = document.getElementById('projectsGrid');
    const searchInput = document.getElementById('projectSearchInput');
    const techFilterBar = document.getElementById('techFilterBar');
    const toastContainer = document.getElementById('toastContainer');

    // Modals
    const projectModal = document.getElementById('projectModal');
    const projectForm = document.getElementById('projectForm');
    const projectModalTitle = document.getElementById('projectModalTitle');
    const addProjectBtn = document.getElementById('addProjectBtn');
    const closeProjectModalBtn = document.getElementById('closeProjectModalBtn');
    const cancelProjectModalBtn = document.getElementById('cancelProjectModalBtn');
    const saveProjectBtn = document.getElementById('saveProjectBtn');

    const deleteModal = document.getElementById('deleteModal');
    const deleteProjectTitle = document.getElementById('deleteProjectTitle');
    const closeDeleteModalBtn = document.getElementById('closeDeleteModalBtn');
    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    const loginModal = document.getElementById('loginModal');
    const loginForm = document.getElementById('loginForm');
    const closeLoginModalBtn = document.getElementById('closeLoginModalBtn');
    const cancelLoginBtn = document.getElementById('cancelLoginBtn');

    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        toastContainer.appendChild(toast);
        setTimeout(() => toast.remove(), 3500);
    }

    function renderProjects(projects) {
        if (!projects || projects.length === 0) {
            projectsGrid.innerHTML = `
                <div class="no-projects-msg">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 0.75rem auto; color:#94a3b8;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <h3 style="font-size:1.1rem; color:var(--navy-900); margin-bottom:0.25rem;">No Projects Found</h3>
                    <p style="font-size:0.9rem;">Try adjusting your search query or technology filter.</p>
                </div>`;
            return;
        }

        projectsGrid.innerHTML = projects.map(p => {
            const techList = Array.isArray(p.technologies) ? p.technologies : [];
            const highlightsList = Array.isArray(p.highlights) ? p.highlights : [];

            return `
                <div class="project-card" data-id="${p.id}">
                    <div>
                        <div class="project-meta-top">
                            <h2 class="project-title">${escapeHtml(p.title)}</h2>
                        </div>

                        <div class="project-details-bar">
                            ${p.company ? `<span class="detail-badge company"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>${escapeHtml(p.company)}</span>` : ''}
                            ${p.role ? `<span class="detail-badge"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>${escapeHtml(p.role)}</span>` : ''}
                            ${p.duration ? `<span class="detail-badge"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>${escapeHtml(p.duration)}</span>` : ''}
                        </div>

                        <p class="project-desc">${escapeHtml(p.description)}</p>

                        ${highlightsList.length > 0 ? `
                            <ul class="highlights-list">
                                ${highlightsList.map(h => `<li>${escapeHtml(h)}</li>`).join('')}
                            </ul>
                        ` : ''}
                    </div>

                    <div>
                        ${techList.length > 0 ? `
                            <div class="tech-tags-container">
                                ${techList.map(t => `<span class="tech-pill">${escapeHtml(t)}</span>`).join('')}
                            </div>
                        ` : ''}

                        <div class="project-actions-row">
                            <div class="project-links">
                                ${p.project_url ? `
                                    <a href="${escapeHtml(p.project_url)}" target="_blank" rel="noopener noreferrer" class="btn-link-action demo">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                        <span>Live Demo</span>
                                    </a>
                                ` : ''}
                                ${p.github_url ? `
                                    <a href="${escapeHtml(p.github_url)}" target="_blank" rel="noopener noreferrer" class="btn-link-action">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"></path></svg>
                                        <span>GitHub</span>
                                    </a>
                                ` : ''}
                            </div>

                            ${isAuthenticated ? `
                            <div class="admin-item-controls">
                                <button type="button" class="btn-icon-control edit edit-btn" data-id="${p.id}">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    <span>Edit</span>
                                </button>
                                <button type="button" class="btn-icon-control delete delete-btn" data-id="${p.id}">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    <span>Delete</span>
                                </button>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function filterProjects() {
        const query = searchInput.value.toLowerCase().trim();
        const filtered = allProjects.filter(p => {
            const matchesQuery = !query || 
                (p.title && p.title.toLowerCase().includes(query)) ||
                (p.description && p.description.toLowerCase().includes(query)) ||
                (p.company && p.company.toLowerCase().includes(query)) ||
                (p.role && p.role.toLowerCase().includes(query)) ||
                (Array.isArray(p.technologies) && p.technologies.some(t => t.toLowerCase().includes(query)));

            const matchesTech = activeFilter === 'all' || 
                (Array.isArray(p.technologies) && p.technologies.some(t => t.toLowerCase() === activeFilter.toLowerCase()));

            return matchesQuery && matchesTech;
        });

        renderProjects(filtered);
    }

    // Search & Filter Listeners
    if (searchInput) searchInput.addEventListener('input', filterProjects);
    if (techFilterBar) {
        techFilterBar.addEventListener('click', (e) => {
            const btn = e.target.closest('.filter-btn');
            if (btn) {
                techFilterBar.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                activeFilter = btn.dataset.tech;
                filterProjects();
            }
        });
    }

    // Modal Handlers
    function openProjectModal(project = null) {
        if (project) {
            projectModalTitle.textContent = 'Edit Project';
            document.getElementById('p_id').value = project.id;
            document.getElementById('p_title').value = project.title || '';
            document.getElementById('p_company').value = project.company || '';
            document.getElementById('p_duration').value = project.duration || '';
            document.getElementById('p_role').value = project.role || '';
            document.getElementById('p_featured').checked = project.featured == 1;
            document.getElementById('p_projectUrl').value = project.project_url || '';
            document.getElementById('p_githubUrl').value = project.github_url || '';
            document.getElementById('p_description').value = project.description || '';
            document.getElementById('p_technologies').value = Array.isArray(project.technologies) ? project.technologies.join(', ') : '';
            document.getElementById('p_highlights').value = Array.isArray(project.highlights) ? project.highlights.join('\n') : '';
        } else {
            projectModalTitle.textContent = 'Add New Project';
            projectForm.reset();
            document.getElementById('p_id').value = '';
        }
        projectModal.classList.add('active');
        setTimeout(() => document.getElementById('p_title').focus(), 50);
    }

    function closeProjectModal() {
        projectModal.classList.remove('active');
    }

    if (addProjectBtn) {
        addProjectBtn.addEventListener('click', () => {
            if (!isAuthenticated) {
                openLoginModal(() => openProjectModal(), 'Administrator login required to add projects.');
                return;
            }
            openProjectModal();
        });
    }

    if (closeProjectModalBtn) closeProjectModalBtn.addEventListener('click', closeProjectModal);
    if (cancelProjectModalBtn) cancelProjectModalBtn.addEventListener('click', closeProjectModal);
    if (projectModal) {
        projectModal.addEventListener('click', (e) => {
            if (e.target === projectModal) closeProjectModal();
        });
    }

    // Projects Grid Edit / Delete delegation
    if (projectsGrid) {
        projectsGrid.addEventListener('click', (e) => {
            const editBtn = e.target.closest('.edit-btn');
            if (editBtn) {
                const id = parseInt(editBtn.dataset.id, 10);
                const proj = allProjects.find(p => p.id == id);
                if (proj) {
                    if (!isAuthenticated) {
                        openLoginModal(() => openProjectModal(proj), 'Administrator login required to edit projects.');
                        return;
                    }
                    openProjectModal(proj);
                }
                return;
            }

            const deleteBtn = e.target.closest('.delete-btn');
            if (deleteBtn) {
                const id = parseInt(deleteBtn.dataset.id, 10);
                const proj = allProjects.find(p => p.id == id);
                if (proj) {
                    if (!isAuthenticated) {
                        openLoginModal(() => openDeleteModal(proj), 'Administrator login required to delete projects.');
                        return;
                    }
                    openDeleteModal(proj);
                }
            }
        });
    }

    function openDeleteModal(project) {
        deleteTargetId = project.id;
        deleteProjectTitle.textContent = `Delete "${project.title}"?`;
        deleteModal.classList.add('active');
    }

    function closeDeleteModal() {
        deleteModal.classList.remove('active');
        deleteTargetId = null;
    }

    if (closeDeleteModalBtn) closeDeleteModalBtn.addEventListener('click', closeDeleteModal);
    if (cancelDeleteBtn) cancelDeleteBtn.addEventListener('click', closeDeleteModal);

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', async () => {
            if (!deleteTargetId) return;
            confirmDeleteBtn.disabled = true;
            confirmDeleteBtn.textContent = 'Deleting...';

            try {
                const res = await fetch('projects.php?api_action=delete_project', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: deleteTargetId })
                });

                const text = await res.text();
                let result = null;
                try { result = JSON.parse(text); } catch (e) { throw new Error('Invalid server response'); }

                if (result && result.success) {
                    allProjects = allProjects.filter(p => p.id != deleteTargetId);
                    filterProjects();
                    closeDeleteModal();
                    showToast('Project deleted successfully.', 'success');
                } else {
                    showToast((result && result.error) || 'Failed to delete project.', 'error');
                }
            } catch (err) {
                showToast(err.message || 'Network error.', 'error');
            } finally {
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.textContent = 'Delete Permanently';
            }
        });
    }

    // Save Project Form Submission
    if (projectForm) {
        projectForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('p_id').value ? parseInt(document.getElementById('p_id').value, 10) : null;
            const payload = {
                id: id,
                title: document.getElementById('p_title').value.trim(),
                company: document.getElementById('p_company').value.trim(),
                duration: document.getElementById('p_duration').value.trim(),
                role: document.getElementById('p_role').value.trim(),
                featured: document.getElementById('p_featured').checked ? 1 : 0,
                project_url: document.getElementById('p_projectUrl').value.trim(),
                github_url: document.getElementById('p_githubUrl').value.trim(),
                description: document.getElementById('p_description').value.trim(),
                technologies: document.getElementById('p_technologies').value.trim(),
                highlights: document.getElementById('p_highlights').value.trim()
            };

            saveProjectBtn.disabled = true;
            saveProjectBtn.textContent = 'Saving...';

            try {
                const res = await fetch('projects.php?api_action=save_project', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const text = await res.text();
                let result = null;
                try { result = JSON.parse(text); } catch (e) { throw new Error('Server returned invalid response.'); }

                if (res.status === 401 || (result && result.require_login)) {
                    showToast('Admin session required. Please log in.', 'error');
                    openLoginModal(() => projectForm.dispatchEvent(new Event('submit')));
                    return;
                }

                if (result && result.success) {
                    showToast(result.message || 'Saved successfully!', 'success');
                    closeProjectModal();
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    showToast((result && result.error) || 'Failed to save project.', 'error');
                }
            } catch (err) {
                showToast(err.message || 'Error saving project.', 'error');
            } finally {
                saveProjectBtn.disabled = false;
                saveProjectBtn.textContent = 'Save Project';
            }
        });
    }

    // Login Modal Handlers
    let postLoginCallback = null;
    function openLoginModal(onSuccess = null) {
        postLoginCallback = onSuccess;
        loginModal.classList.add('active');
        setTimeout(() => document.getElementById('loginUsername').focus(), 50);
    }

    function closeLoginModal() {
        loginModal.classList.remove('active');
    }

    if (closeLoginModalBtn) closeLoginModalBtn.addEventListener('click', closeLoginModal);
    if (cancelLoginBtn) cancelLoginBtn.addEventListener('click', closeLoginModal);

    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const u = document.getElementById('loginUsername').value.trim();
            const p = document.getElementById('loginPassword').value;

            try {
                const res = await fetch('projects.php?api_action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username: u, password: p })
                });
                const result = await res.json();
                if (result.success) {
                    showToast(`Logged in as ${result.username}`, 'success');
                    closeLoginModal();
                    if (typeof postLoginCallback === 'function') {
                        const cb = postLoginCallback;
                        postLoginCallback = null;
                        cb();
                    } else {
                        setTimeout(() => window.location.reload(), 600);
                    }
                } else {
                    showToast(result.error || 'Login failed', 'error');
                }
            } catch (err) {
                showToast('Login error', 'error');
            }
        });
    }

    // Float Menu Logic
    (function() {
        const floatFab = document.getElementById('floatMenuFab');
        const floatPanel = document.getElementById('floatMenuPanel');
        const floatBackdrop = document.getElementById('floatMenuBackdrop');
        const floatClose = document.getElementById('floatMenuCloseBtn');

        function toggleFloatMenu(show = null) {
            if (!floatPanel || !floatBackdrop || !floatFab) return;
            const willOpen = show !== null ? show : !floatPanel.classList.contains('active');
            if (willOpen) {
                floatPanel.classList.add('active');
                floatBackdrop.classList.add('active');
                floatFab.classList.add('active');
            } else {
                floatPanel.classList.remove('active');
                floatBackdrop.classList.remove('active');
                floatFab.classList.remove('active');
            }
        }

        if (floatFab) {
            floatFab.onclick = function(e) {
                e.stopPropagation();
                toggleFloatMenu();
            };
        }
        if (floatClose) {
            floatClose.onclick = function(e) {
                e.stopPropagation();
                toggleFloatMenu(false);
            };
        }
        if (floatBackdrop) {
            floatBackdrop.onclick = function(e) {
                e.stopPropagation();
                toggleFloatMenu(false);
            };
        }

        const floatAddProj = document.getElementById('floatAddProjectBtn');
        if (floatAddProj) {
            floatAddProj.onclick = function() {
                toggleFloatMenu(false);
                if (addProjectBtn) addProjectBtn.click();
            };
        }

        const floatEditFooter = document.getElementById('floatEditFooterBtn');
        if (floatEditFooter) {
            floatEditFooter.onclick = function() {
                toggleFloatMenu(false);
                const mainEditFooter = document.getElementById('editFooterTriggerBtn');
                if (mainEditFooter) mainEditFooter.click();
            };
        }

        const floatPrint = document.getElementById('floatPrintBtn');
        if (floatPrint) {
            floatPrint.onclick = function() {
                toggleFloatMenu(false);
                window.print();
            };
        }

        const floatTop = document.getElementById('floatScrollTopBtn');
        if (floatTop) {
            floatTop.onclick = function() {
                toggleFloatMenu(false);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            };
        }

        const floatLogin = document.getElementById('floatHeaderLoginBtn');
        if (floatLogin) {
            floatLogin.onclick = function() {
                toggleFloatMenu(false);
                openLoginModal();
            };
        }

        const floatLogout = document.getElementById('floatHeaderLogoutBtn');
        if (floatLogout) {
            floatLogout.onclick = async function(e) {
                e.preventDefault();
                toggleFloatMenu(false);
                try {
                    await fetch('projects.php?api_action=logout', { method: 'POST' });
                } catch (err) {}
                window.location.href = 'projects.php';
            };
        }
    })();

    // Initial Render
    renderProjects(allProjects);
</script>
</body>
</html>
