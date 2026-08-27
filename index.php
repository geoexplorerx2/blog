<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['page']) && $_GET['page'] === 'profile') {
    require_once __DIR__ . '/profile.php';
    exit;
}
/**
 * Computer Science & Software Development Knowledge Repository - Single File Application
 * 
 * OOP, MySQL (PDO), Blue Scientific Conference Design
 * Supports categories, JSON import, and Admin Authentication.
 * Accepts both strict JSON and JavaScript-like object literals (unquoted keys, trailing semicolons).
 * 
 * Database: q_db (username: root, password: empty)
 * Table: questions (id INT AUTO_INCREMENT PRIMARY KEY, question TEXT, answer TEXT, category VARCHAR(100))
 * Table: users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE, password VARCHAR(255), created_at TIMESTAMP)
 */

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

    public static function isAvailable(): bool
    {
        return self::getConnection() !== null;
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

        // Ensure default admin exists
        $stmt = $db->prepare("SELECT id, password FROM users WHERE username = :username");
        $stmt->execute([':username' => 'admin']);
        $user = $stmt->fetch();

        if (!$user) {
            $insert = $db->prepare("INSERT INTO users (username, password) VALUES (:username, :password)");
            $insert->execute([
                ':username' => 'admin',
                ':password' => password_hash('Fa@90904030', PASSWORD_DEFAULT)
            ]);
        }
    }

    public static function login(string $username, string $password): bool
    {
        self::ensureUserTableExists();
        $db = Database::getConnection();
        if ($db === null) return false;

        $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = :username");
        $stmt->execute([':username' => trim($username)]);
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

require_once __DIR__ . '/footer_component.php';

class Question
{
    public function __construct(
        public int $id,
        public string $question,
        public string $answer,
        public ?string $category = null
    ) {}
}

class QuestionRepository
{
    private ?PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
        if ($this->db !== null) {
            $this->ensureTableExists();
        }
    }

    private function ensureTableExists(): void
    {
        if ($this->db === null) return;
        $sql = "CREATE TABLE IF NOT EXISTS questions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            question TEXT NOT NULL,
            answer TEXT NOT NULL,
            category VARCHAR(100) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->db->exec($sql);
    }

    public function getAll(): array
    {
        if ($this->db === null) return [];
        try {
            $stmt = $this->db->query('SELECT id, question, answer, category FROM questions ORDER BY id');
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
        if ($this->db === null) return [];
        try {
            $stmt = $this->db->query('SELECT DISTINCT COALESCE(NULLIF(category, ""), "General") AS cat FROM questions ORDER BY cat');
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function importQuestions(array $questions): int
    {
        if ($this->db === null) {
            throw new RuntimeException('Database connection not available.');
        }
        if (empty($questions)) return 0;
        $stmt = $this->db->prepare('INSERT INTO questions (question, answer, category) VALUES (:question, :answer, :category)');
        $this->db->beginTransaction();
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
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $inserted;
    }

    public function getById(int $id): ?Question
    {
        if ($this->db === null) return null;
        try {
            $stmt = $this->db->prepare('SELECT id, question, answer, category FROM questions WHERE id = :id');
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
        if ($this->db === null) {
            throw new RuntimeException('Database connection not available.');
        }
        $cat = $category !== null && trim($category) !== '' ? trim($category) : 'General';
        $stmt = $this->db->prepare('INSERT INTO questions (question, answer, category) VALUES (:question, :answer, :category)');
        $stmt->execute([
            ':question' => trim($question),
            ':answer'   => trim($answer),
            ':category' => $cat,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateQuestion(int $id, string $question, string $answer, ?string $category): bool
    {
        if ($this->db === null) {
            throw new RuntimeException('Database connection not available.');
        }
        $cat = $category !== null && trim($category) !== '' ? trim($category) : 'General';
        $stmt = $this->db->prepare('UPDATE questions SET question = :question, answer = :answer, category = :category WHERE id = :id');
        return $stmt->execute([
            ':id'       => $id,
            ':question' => trim($question),
            ':answer'   => trim($answer),
            ':category' => $cat,
        ]);
    }

    public function deleteQuestion(int $id): bool
    {
        if ($this->db === null) {
            throw new RuntimeException('Database connection not available.');
        }
        $stmt = $this->db->prepare('DELETE FROM questions WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function countAll(): int
    {
        if ($this->db === null) return 0;
        try {
            return (int)$this->db->query('SELECT COUNT(*) FROM questions')->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    public function clearAll(): void
    {
        if ($this->db === null) return;
        $this->db->exec('TRUNCATE TABLE questions');
    }
}

/**
 * Convert JavaScript-like object literal (unquoted keys, single quotes, comments, trailing semicolons/commas) to valid JSON.
 */
function sanitizeJson(string $input): string
{
    // First, try standard json_decode directly
    $trimmed = trim($input);
    $decoded = json_decode($trimmed, true);
    if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
        return $trimmed;
    }

    // Strip leading UTF-8 BOM if present
    $input = preg_replace('/^\xEF\xBB\xBF/', '', $input);

    $length = strlen($input);
    $output = '';
    $i = 0;
    
    // State machine
    $inString = false; // false, '"', or "'"
    $escape = false;
    
    while ($i < $length) {
        $char = $input[$i];
        
        if ($inString !== false) {
            if ($escape) {
                if ($inString === "'" && $char === "'") {
                    // Escaped single quote inside single-quoted string -> literal single quote
                    $output .= "'";
                } elseif ($inString === "'" && $char === '"') {
                    // Escaped double quote inside single-quoted string -> escaped \"
                    $output .= '\"';
                } elseif ($inString === '"' && $char === "'") {
                    // \' inside double quote is invalid in standard JSON -> just '
                    $output .= "'";
                } else {
                    $output .= '\\' . $char;
                }
                $escape = false;
            } elseif ($char === '\\') {
                $escape = true;
            } elseif ($char === $inString) {
                // Closing string
                $output .= '"';
                $inString = false;
            } else {
                // Unescaped character inside string
                if ($inString === "'" && $char === '"') {
                    $output .= '\"';
                } elseif ($char === "\n") {
                    $output .= '\n';
                } elseif ($char === "\r") {
                    $output .= '\r';
                } elseif ($char === "\t") {
                    $output .= '\t';
                } else {
                    $output .= $char;
                }
            }
            $i++;
            continue;
        }
        
        // Outside of string
        // Check for comments
        if ($char === '/' && $i + 1 < $length) {
            $next = $input[$i + 1];
            if ($next === '/') {
                // Single line comment: skip until newline
                $i += 2;
                while ($i < $length && $input[$i] !== "\n" && $input[$i] !== "\r") {
                    $i++;
                }
                continue;
            } elseif ($next === '*') {
                // Multi-line comment: skip until */
                $i += 2;
                while ($i + 1 < $length && !($input[$i] === '*' && $input[$i + 1] === '/')) {
                    $i++;
                }
                $i += 2;
                continue;
            }
        }
        
        // Check for strings start
        if ($char === '"' || $char === "'") {
            $inString = $char;
            $output .= '"';
            $i++;
            continue;
        }
        
        // Check for unquoted identifier (e.g. key in { key: ... })
        if (preg_match('/^[a-zA-Z_$][a-zA-Z0-9_$]*/', substr($input, $i), $matches)) {
            $identifier = $matches[0];
            $identifierLen = strlen($identifier);
            $afterPos = $i + $identifierLen;
            
            // Check what follows whitespace
            while ($afterPos < $length && ctype_space($input[$afterPos])) {
                $afterPos++;
            }
            
            // If it's a variable declaration keyword
            if (in_array(strtolower($identifier), ['const', 'var', 'let', 'export', 'default'], true)) {
                $i += $identifierLen;
                continue;
            }
            
            if ($afterPos < $length && ($input[$afterPos] === ':' || $input[$afterPos] === '=')) {
                // It's an object key
                $output .= '"' . $identifier . '"';
                $i += $identifierLen;
                continue;
            }
            
            // Handle boolean / null literals
            if (in_array(strtolower($identifier), ['true', 'false', 'null'], true)) {
                $output .= strtolower($identifier);
                $i += $identifierLen;
                continue;
            }
            
            $output .= $identifier;
            $i += $identifierLen;
            continue;
        }
        
        // Semicolons outside strings
        if ($char === ';') {
            $output .= ' ';
            $i++;
            continue;
        }
        
        // Assignment operator outside strings -> colon
        if ($char === '=') {
            $output .= ':';
            $i++;
            continue;
        }
        
        $output .= $char;
        $i++;
    }
    
    // Clean trailing commas before } or ]
    $output = preg_replace('/,\s*([\}\]])/', '$1', $output);
    $output = trim($output);
    
    // If output is of the form "data": [...] or "questions": [...] not enclosed in {}, wrap it
    if (preg_match('/^"[a-zA-Z0-9_$]+"\s*:\s*[{\[]/', $output) && (!str_starts_with($output, '{') || !str_ends_with($output, '}'))) {
        $output = '{' . $output . '}';
    }
    
    return $output;
}

// Handle Direct GET Logout (e.g. index.php?action=logout or index.php?api_action=logout)
if ((isset($_GET['action']) && $_GET['action'] === 'logout') || (isset($_GET['api_action']) && $_GET['api_action'] === 'logout' && $_SERVER['REQUEST_METHOD'] === 'GET')) {
    Auth::logout();
    header('Location: index.php');
    exit;
}

// ---------------------------------------------------------------------
// AJAX / JSON API Handling (Login, Logout, Check Auth, Edit, Delete, Add)
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['api_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $repository = new QuestionRepository();
    Auth::ensureUserTableExists();

    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput, true);
    if (!is_array($inputData)) {
        $inputData = $_POST;
    }

    $action = $_GET['api_action'];

    try {
        if ($action === 'login') {
            $username = trim($inputData['username'] ?? '');
            $password = (string)($inputData['password'] ?? '');
            if ($username === '' || $password === '') {
                echo json_encode(['success' => false, 'error' => 'Username and password are required.']);
                exit;
            }
            if (Auth::login($username, $password)) {
                echo json_encode(['success' => true, 'username' => Auth::getCurrentUser()]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid username or password.']);
            }
            exit;
        }

        if ($action === 'logout') {
            Auth::logout();
            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'check_auth') {
            echo json_encode([
                'success' => true,
                'authenticated' => Auth::isLoggedIn(),
                'username' => Auth::getCurrentUser()
            ]);
            exit;
        }

        // Actions below require authentication
        if (!Auth::isLoggedIn()) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'Authentication required. Please log in as administrator to create, edit, or delete questions.',
                'require_login' => true
            ]);
            exit;
        }

        if ($action === 'edit') {
            $id = (int)($inputData['id'] ?? 0);
            $question = trim($inputData['question'] ?? '');
            $answer = trim($inputData['answer'] ?? '');
            $category = trim($inputData['category'] ?? '');
            if ($id <= 0 || $question === '' || $answer === '') {
                echo json_encode(['success' => false, 'error' => 'Question and answer cannot be empty.']);
                exit;
            }
            $repository->updateQuestion($id, $question, $answer, $category ?: 'General');
            echo json_encode([
                'success' => true,
                'id' => $id,
                'question' => $question,
                'answer' => $answer,
                'category' => $category ?: 'General'
            ]);
            exit;
        } elseif ($action === 'delete') {
            $id = (int)($inputData['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid question ID.']);
                exit;
            }
            $repository->deleteQuestion($id);
            echo json_encode(['success' => true, 'id' => $id]);
            exit;
        } elseif ($action === 'add') {
            $question = trim($inputData['question'] ?? '');
            $answer = trim($inputData['answer'] ?? '');
            $category = trim($inputData['category'] ?? '');
            if ($question === '' || $answer === '') {
                echo json_encode(['success' => false, 'error' => 'Question and answer cannot be empty.']);
                exit;
            }
            $newId = $repository->addQuestion($question, $answer, $category ?: 'General');
            echo json_encode([
                'success' => true,
                'id' => $newId,
                'question' => $question,
                'answer' => $answer,
                'category' => $category ?: 'General'
            ]);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => 'Unknown API action.']);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// ---------------------------------------------------------------------
// Export handling
// ---------------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'json') {
    $repository = new QuestionRepository();
    $exportCategory = isset($_GET['category']) && trim($_GET['category']) !== '' ? trim($_GET['category']) : null;
    
    if ($exportCategory && strcasecmp($exportCategory, 'all') !== 0) {
        $exportQuestions = $repository->getByCategory($exportCategory);
        $fileCatSlug = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($exportCategory));
    } else {
        $exportQuestions = $repository->getAll();
        $fileCatSlug = 'all';
    }

    $exportData = array_map(fn($q) => [
        'id' => (string)$q->id,
        'question' => $q->question,
        'answer' => $q->answer,
        'category' => $q->category ?? 'General',
    ], $exportQuestions);

    $jsonOutput = json_encode(['data' => $exportData], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="questions-' . $fileCatSlug . '-' . date('Y-m-d') . '.json"');
    header('Content-Length: ' . strlen($jsonOutput));
    header('Pragma: no-cache');
    header('Expires: 0');
    echo $jsonOutput;
    exit;
}

// ---------------------------------------------------------------------
// Import handling
// ---------------------------------------------------------------------
$importMessage = '';
$importSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['jsonfile'])) {
    if (!Auth::isLoggedIn()) {
        $importMessage = 'Authentication required. Please log in as admin before importing files.';
        $importSuccess = false;
    } else {
        $repository = new QuestionRepository();
        if ($_FILES['jsonfile']['error'] !== UPLOAD_ERR_OK) {
            $importMessage = 'File upload error. Please try again.';
        } else {
            $fileTmp = $_FILES['jsonfile']['tmp_name'];
            $fileContent = file_get_contents($fileTmp);
            if ($fileContent === false) {
                $importMessage = 'Could not read the uploaded file.';
            } else {
                $sanitizedJson = sanitizeJson($fileContent);
                $decoded = json_decode($sanitizedJson, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $importMessage = 'Invalid JSON file: ' . json_last_error_msg();
                } else {
                    $questions = [];
                    if (is_array($decoded)) {
                        if (isset($decoded['data']) && is_array($decoded['data'])) {
                            $questions = $decoded['data'];
                        } else {
                            $questions = $decoded;
                        }
                    } else {
                        $importMessage = 'JSON must be an array of questions or an object with a "data" array.';
                    }
                    if (!empty($questions)) {
                        try {
                            if (!empty($_POST['replace_existing'])) {
                                $repository->clearAll();
                            }
                            $inserted = $repository->importQuestions($questions);
                            $importMessage = "Successfully imported $inserted questions into the database.";
                            $importSuccess = true;
                        } catch (Exception $e) {
                            $importMessage = 'Import failed: ' . $e->getMessage();
                        }
                    } else {
                        $importMessage = 'No valid question entries found in the file.';
                    }
                }
            }
        }
    }
}

// ---------------------------------------------------------------------
// Fetch data for display
// ---------------------------------------------------------------------
$repository = new QuestionRepository();
$totalQuestionsCount = $repository->countAll();
$selectedCategory = $_GET['category'] ?? null;

if ($selectedCategory) {
    $questions = $repository->getByCategory($selectedCategory);
} else {
    $questions = [];
}
$categories = $repository->getCategories();

$questionsJson = json_encode(array_map(fn($q) => [
    'id' => $q->id,
    'question' => $q->question,
    'answer' => $q->answer,
    'category' => $q->category,
], $selectedCategory ? $questions : []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $selectedCategory ? htmlspecialchars($selectedCategory) . ' – CS & Software Development Knowledge Repository' : 'Computer Science & Software Development Knowledge Repository'; ?></title>
    <link rel="stylesheet" href="assets/fonts.css">
    <link rel="stylesheet" href="assets/prism.min.css">
    <style>
        :root {
            --navy-900: #0a2540;
            --navy-800: #0d3557;
            --navy-700: #12466f;
            --blue-600: #1a5f8a;
            --blue-500: #2178ae;
            --blue-200: #b8d8ee;
            --blue-100: #e0eef8;
            --blue-50: #f0f7fc;
            --text-dark: #102a3a;
            --text-muted: #5b7385;
            --bg: #f5f9fc;
            --card-bg: #ffffff;
            --border: #d5e3ee;
            --shadow: 0 2px 8px rgba(10,37,64,0.06);
            --shadow-hover: 0 4px 16px rgba(10,37,64,0.12);
            --radius: 10px;
            --font-heading: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            --font-body: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            --font-mono: 'JetBrains Mono', 'Fira Code', 'Consolas', monospace;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font-body);
            background: var(--bg);
            color: var(--text-dark);
            padding: 0;
            line-height: 1.65;
            font-size: 0.98rem;
            letter-spacing: -0.011em;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .conference-header {
            background: linear-gradient(135deg, var(--navy-900) 0%, var(--navy-700) 50%, var(--blue-600) 100%);
            color: white;
            padding: 2rem 1rem;
            text-align: center;
            border-bottom: 4px solid var(--blue-500);
            position: relative;
            overflow: hidden;
        }
        .conference-header::after {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(circle at 20% 30%, rgba(255,255,255,0.08) 0%, transparent 60%);
            pointer-events: none;
        }
        .conference-header h1 {
            font-family: var(--font-heading);
            font-size: 2.1rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            line-height: 1.25;
            margin-bottom: 0.4rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            max-width: 850px;
            margin-left: auto;
            margin-right: auto;
        }
        .conference-header p {
            font-size: 1rem;
            color: var(--blue-200);
            max-width: 650px;
            margin: 0 auto;
        }
        .container {
            max-width: 1280px;
            width: 100%;
            margin: 0 auto;
            padding: 1.5rem 1rem;
            flex: 1;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 1rem;
            color: var(--blue-600);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
        }
        .back-link:hover {
            color: var(--navy-800);
            text-decoration: underline;
        }
        .data-management-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .data-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .data-card h3 {
            font-family: var(--font-heading);
            color: var(--navy-800);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.15rem;
        }
        .data-card p {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        .data-card form {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .file-input-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }
        .file-input-group input[type="file"] {
            flex: 1 1 180px;
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--blue-50);
            font-size: 0.85rem;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: var(--navy-800);
            cursor: pointer;
            user-select: none;
        }
        .checkbox-label input[type="checkbox"] {
            accent-color: var(--blue-500);
            cursor: pointer;
        }
        .db-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.25rem 0.65rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            background: var(--blue-100);
            color: var(--navy-800);
            border: 1px solid var(--blue-200);
            margin-bottom: 0.75rem;
            align-self: flex-start;
        }
        .message {
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        .message.success {
            background: #e6f7e6;
            color: #1e7a1e;
            border: 1px solid #b2e0b2;
        }
        .message.error {
            background: #fdeaea;
            color: #c0392b;
            border: 1px solid #f5c6c6;
        }
        .controls {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            align-items: center;
            background: var(--card-bg);
            padding: 0.75rem;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }
        .search-box {
            flex: 1 1 260px;
            position: relative;
        }
        .search-box input {
            width: 100%;
            padding: 0.7rem 1rem 0.7rem 2.4rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.95rem;
            background: var(--blue-50);
            color: var(--text-dark);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .search-box input:focus {
            outline: none;
            border-color: var(--blue-500);
            box-shadow: 0 0 0 3px rgba(33,120,174,0.15);
            background: white;
        }
        .search-box svg {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }
        .btn {
            padding: 0.7rem 1.2rem;
            border: 1px solid var(--blue-500);
            border-radius: 6px;
            background: white;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--blue-600);
            transition: background 0.2s, color 0.2s, border-color 0.2s, transform 0.1s;
            white-space: nowrap;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }
        .btn:hover {
            background: var(--blue-100);
            border-color: var(--navy-700);
            color: var(--navy-900);
        }
        .btn.primary {
            background: var(--navy-700);
            color: white;
            border-color: var(--navy-700);
        }
        .btn.primary:hover {
            background: var(--navy-900);
            border-color: var(--navy-900);
        }
        .btn.export-btn {
            background: var(--blue-600);
            color: white;
            border-color: var(--blue-600);
        }
        .btn.export-btn:hover {
            background: var(--blue-500);
            border-color: var(--blue-500);
        }
        .stats {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-left: auto;
            font-weight: 500;
        }
        .qa-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .qa-item {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .qa-item:hover {
            border-color: var(--blue-500);
            box-shadow: var(--shadow-hover);
        }
        .qa-question {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 1rem 1.25rem;
            cursor: pointer;
            user-select: none;
            background: linear-gradient(to right, var(--blue-50), white);
            gap: 0.75rem;
        }
        .qa-header-left {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            flex: 1 1 auto;
            min-width: 0;
            width: 100%;
        }
        .qa-id-badge {
            font-size: 0.75rem;
            font-weight: 700;
            background: var(--blue-100);
            color: var(--navy-800);
            border: 1px solid var(--blue-200);
            padding: 0.25rem 0.55rem;
            border-radius: 4px;
            white-space: nowrap;
            margin-top: 0.15rem;
            flex-shrink: 0;
        }
        .qa-question h3 {
            font-family: var(--font-heading);
            font-size: 1.05rem;
            font-weight: 600;
            margin: 0;
            color: var(--navy-800);
            flex: 1 1 auto;
            min-width: 0;
            width: 100%;
            line-height: 1.45;
        }
        .qa-header-right {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-shrink: 0;
            margin-top: 0.15rem;
        }
        .qa-actions {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        .action-btn {
            background: transparent;
            border: 1px solid transparent;
            padding: 0.35rem;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            transition: all 0.2s ease;
        }
        .action-btn:hover {
            background: white;
            border-color: var(--border);
        }
        .action-btn.edit-btn:hover {
            color: var(--blue-500);
            border-color: var(--blue-200);
            background: var(--blue-50);
        }
        .action-btn.delete-btn:hover {
            color: #c0392b;
            border-color: #f5c6c6;
            background: #fdeaea;
        }
        .qa-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease;
            padding: 0 1.25rem;
            border-top: 1px solid transparent;
        }
        .qa-item.open .qa-answer {
            max-height: 5000px;
            border-top-color: var(--border);
            padding: 1.1rem 1.25rem;
        }
        .item-answer-text {
            color: #1e3b4f;
            font-size: 0.95rem;
            line-height: 1.6;
            width: 100%;
        }
        .qa-answer p {
            color: #1e3b4f;
            white-space: pre-wrap;
            word-break: break-word;
            font-size: 0.95rem;
        }
        .no-results {
            text-align: center;
            padding: 2.5rem;
            color: var(--text-muted);
            font-style: italic;
            background: var(--card-bg);
            border-radius: var(--radius);
            border: 1px solid var(--border);
        }
        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .category-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow);
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
            text-decoration: none;
            color: var(--navy-800);
            display: block;
        }
        .category-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
            border-color: var(--blue-500);
        }
        .category-card h2 {
            font-family: var(--font-heading);
            font-size: 1.3rem;
            margin-bottom: 0.3rem;
            color: var(--blue-600);
        }
        .category-card p {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        .conference-footer {
            background: var(--navy-900);
            color: var(--blue-200);
            text-align: center;
            padding: 1rem;
            font-size: 0.85rem;
            border-top: 3px solid var(--blue-500);
        }
        .btn.add-btn {
            background: #1b6d3e;
            color: white;
            border-color: #1b6d3e;
        }
        .btn.add-btn:hover {
            background: #14532d;
            border-color: #14532d;
        }
        .qa-header-left {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            flex: 1 1 auto;
            min-width: 0;
            width: 100%;
        }
        .qa-id-badge {
            font-size: 0.75rem;
            font-weight: 700;
            background: var(--blue-100);
            color: var(--navy-800);
            border: 1px solid var(--blue-200);
            padding: 0.25rem 0.55rem;
            border-radius: 4px;
            white-space: nowrap;
            margin-top: 0.15rem;
            flex-shrink: 0;
        }
        .qa-header-right {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-shrink: 0;
            margin-top: 0.15rem;
        }
        .qa-actions {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        .action-btn {
            background: transparent;
            border: 1px solid transparent;
            padding: 0.35rem;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            transition: all 0.2s ease;
        }
        .action-btn:hover {
            background: white;
            border-color: var(--border);
        }
        .action-btn.edit-btn:hover {
            color: var(--blue-500);
            border-color: var(--blue-200);
            background: var(--blue-50);
        }
        .action-btn.delete-btn:hover {
            color: #c0392b;
            border-color: #f5c6c6;
            background: #fdeaea;
        }
        .qa-footer-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 0.75rem;
            border-top: 1px dashed var(--border);
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .qa-category-tag {
            background: var(--blue-50);
            color: var(--navy-700);
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid var(--blue-200);
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(10, 37, 64, 0.6);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1rem;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .modal-card {
            background: white;
            border-radius: var(--radius);
            width: 100%;
            max-width: 620px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border: 1px solid var(--border);
            overflow: hidden;
            transform: translateY(20px);
            transition: transform 0.25s ease;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }
        .modal-overlay.active .modal-card {
            transform: translateY(0);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 1.5rem;
            background: var(--navy-900);
            color: white;
        }
        .modal-header h3 {
            font-family: var(--font-heading);
            font-size: 1.2rem;
            margin: 0;
        }
        .modal-close {
            background: none;
            border: none;
            color: var(--blue-200);
            font-size: 1.6rem;
            cursor: pointer;
            line-height: 1;
            padding: 0;
            transition: color 0.2s;
        }
        .modal-close:hover {
            color: white;
        }
        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            flex: 1;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--navy-800);
            margin-bottom: 0.4rem;
        }
        .form-group label .required {
            color: #c0392b;
        }
        .form-group input[type="text"],
        .form-group input[type="password"],
        .form-group textarea {
            width: 100%;
            padding: 0.7rem 0.9rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-family: var(--font-body);
            font-size: 0.95rem;
            background: var(--blue-50);
            color: var(--text-dark);
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }
        .form-group input[type="text"]:focus,
        .form-group input[type="password"]:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--blue-500);
            box-shadow: 0 0 0 3px rgba(33,120,174,0.15);
            background: white;
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            background: var(--bg);
            border-top: 1px solid var(--border);
        }

        /* Auth Bar & Modals */
        .auth-bar-top {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 0.6rem;
            max-width: 1280px;
            margin: 0 auto 0.75rem auto;
            position: relative;
            z-index: 2;
        }
        .auth-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #ffffff;
            padding: 0.3rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            backdrop-filter: blur(4px);
        }
        .auth-pill.logged-in::before {
            content: "";
            width: 7px;
            height: 7px;
            background: #4ade80;
            border-radius: 50%;
            display: inline-block;
        }
        .auth-btn-action {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.35);
            color: #ffffff;
            padding: 0.35rem 0.85rem;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .auth-btn-action:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: #ffffff;
            color: #ffffff;
            transform: translateY(-1px);
        }
        .auth-btn-action.logout:hover {
            background: rgba(231, 76, 60, 0.85);
            border-color: rgba(231, 76, 60, 1);
        }
        .auth-alert {
            padding: 0.65rem 0.9rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            background: #fdeaea;
            color: #c0392b;
            border: 1px solid #f5c6c6;
            line-height: 1.4;
        }
        .modal-auth-card {
            max-width: 440px !important;
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            z-index: 2000;
            pointer-events: none;
        }
        .toast {
            background: var(--navy-900);
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            pointer-events: auto;
            animation: slideInToast 0.3s ease forwards;
            border-left: 4px solid var(--blue-500);
        }
        .toast.toast-success { border-left-color: #27ae60; }
        .toast.toast-error { border-left-color: #e74c3c; }
        @keyframes slideInToast {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Code Block Styling */
        pre[class*="language-"], pre {
            display: block !important;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
            margin: 0.5rem 0 !important;
            padding: 0.85rem 1.1rem !important;
            border-radius: 8px !important;
            font-size: 0.9rem !important;
            line-height: 1.55 !important;
            background: #1e293b !important;
            border: 1px solid #334155 !important;
            position: relative !important;
            overflow-x: auto !important;
            text-align: left !important;
        }
        code[class*="language-"], pre code {
            font-family: var(--font-mono), 'Consolas', 'Fira Code', 'Monaco', monospace !important;
            font-size: 0.9rem !important;
            display: block !important;
            padding-right: 4.5rem !important;
            font-weight: 400 !important;
            text-shadow: none !important;
            white-space: pre !important;
            word-spacing: normal !important;
            word-break: normal !important;
            tab-size: 4 !important;
        }
        code.inline-code {
            background: #e2e8f0;
            color: #0f172a;
            padding: 0.15rem 0.45rem;
            border-radius: 4px;
            font-family: var(--font-mono), 'Consolas', 'Fira Code', monospace;
            font-size: 0.88em;
            border: 1px solid #cbd5e1;
            font-weight: normal;
        }
        .copy-code-btn {
            position: absolute !important;
            top: 0.65rem !important;
            right: 0.75rem !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            height: 28px !important;
            box-sizing: border-box !important;
            background: rgba(255, 255, 255, 0.12) !important;
            border: 1px solid rgba(255, 255, 255, 0.22) !important;
            color: #cbd5e1 !important;
            padding: 0 0.65rem !important;
            border-radius: 4px !important;
            font-size: 0.75rem !important;
            font-weight: 500 !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            user-select: none !important;
            z-index: 10 !important;
            font-family: var(--font-body) !important;
            line-height: 1 !important;
        }
        .copy-code-btn:hover {
            background: rgba(255, 255, 255, 0.25) !important;
            border-color: rgba(255, 255, 255, 0.4) !important;
            color: #ffffff !important;
        }

        /* -------------------------------------------------------------
           Responsive Design & Media Queries
           ------------------------------------------------------------- */
        @media (max-width: 1024px) {
            .container {
                padding: 1.25rem 1rem;
            }
            .data-management-grid {
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
            }
        }

        @media (max-width: 768px) {
            .conference-header {
                padding: 1.75rem 1rem 1.25rem 1rem;
            }
            .conference-header h1 {
                font-size: 1.65rem;
                line-height: 1.3;
            }
            .conference-header p {
                font-size: 0.92rem;
            }
            .data-management-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            .controls {
                gap: 0.6rem;
                padding: 0.65rem;
            }
            .search-box {
                flex: 1 1 100%;
                width: 100%;
            }
            .stats {
                width: 100%;
                text-align: right;
                margin-left: 0;
                order: 10;
                padding-top: 0.25rem;
            }
            .qa-question {
                padding: 0.85rem 1rem;
                gap: 0.5rem;
            }
            .item-question-text {
                font-size: 0.98rem;
                line-height: 1.4;
            }
            .qa-answer {
                padding: 0 1rem;
            }
            .qa-item.open .qa-answer {
                padding: 1rem;
            }
            .category-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 0.85rem;
            }
            .category-card {
                padding: 1.25rem 1rem;
            }
            .toast-container {
                left: 1rem;
                right: 1rem;
                bottom: 1rem;
            }
            .toast {
                width: 100%;
                box-sizing: border-box;
                justify-content: center;
                font-size: 0.85rem;
            }
        }

        @media (max-width: 520px) {
            .conference-header {
                padding: 1.25rem 0.75rem 1rem 0.75rem;
            }
            .conference-header h1 {
                font-size: 1.35rem;
                letter-spacing: -0.02em;
            }
            .conference-header p {
                font-size: 0.85rem;
            }
            .auth-bar-top {
                justify-content: center;
                flex-wrap: wrap;
                margin-bottom: 0.5rem;
            }
            .auth-pill, .auth-btn-action {
                font-size: 0.75rem;
                padding: 0.25rem 0.65rem;
            }
            .container {
                padding: 1rem 0.6rem;
            }
            .controls {
                flex-direction: column;
                align-items: stretch;
                padding: 0.6rem;
            }
            .controls .btn {
                width: 100%;
                padding: 0.6rem 0.8rem;
                font-size: 0.85rem;
            }
            .stats {
                text-align: center;
                font-size: 0.8rem;
            }
            .category-grid {
                grid-template-columns: 1fr;
            }
            .qa-question {
                padding: 0.75rem 0.85rem;
            }
            .qa-id-badge {
                font-size: 0.7rem;
                padding: 0.15rem 0.4rem;
            }
            .item-question-text {
                font-size: 0.92rem;
            }
            .action-btn {
                padding: 0.4rem;
            }
            .action-btn svg {
                width: 14px;
                height: 14px;
            }
            pre[class*="language-"], pre {
                padding: 0.75rem 0.85rem !important;
                font-size: 0.82rem !important;
                border-radius: 6px !important;
            }
            code[class*="language-"], pre code {
                font-size: 0.82rem !important;
                padding-right: 3.5rem !important;
            }
            .copy-code-btn {
                top: 0.5rem !important;
                right: 0.5rem !important;
                height: 24px !important;
                padding: 0 0.5rem !important;
                font-size: 0.7rem !important;
            }
            .modal-card {
                max-width: 100% !important;
                border-radius: 8px !important;
            }
            .modal-header {
                padding: 1rem !important;
            }
            .modal-body {
                padding: 1rem 0.85rem !important;
            }
            .modal-footer {
                padding: 0.75rem 0.85rem !important;
                flex-direction: column-reverse;
                gap: 0.5rem;
            }
            .modal-footer .btn {
                width: 100%;
            }
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
            background: linear-gradient(135deg, var(--navy-900) 0%, var(--blue-500) 100%);
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
            letter-spacing: -0.01em;
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
            gap: 0.5rem;
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

        .float-menu-badge.active-user {
            background: #dcfce7;
            color: #166534;
        }

        /* Close button with zero background */
        .modal-close, .close-btn, #floatMenuCloseBtn, #modalCloseBtn, #loginModalCloseBtn {
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

        .modal-close:hover, .close-btn:hover, #floatMenuCloseBtn:hover, #modalCloseBtn:hover, #loginModalCloseBtn:hover {
            background: transparent !important;
            background-color: transparent !important;
            opacity: 1;
            transform: scale(1.15);
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
    </style>
</head>
<body>
<div class="conference-header">
    <?php if ($selectedCategory): ?>
        <h1><?php echo htmlspecialchars($selectedCategory); ?></h1>
        <p style="font-style: italic; opacity: 0.95; font-size: 1.02rem;">“The mind that opens to a new idea never returns to its original size.”</p>
        <div style="font-size: 0.85rem; color: #93c5fd; font-weight: 600; margin-top: 0.35rem; letter-spacing: 0.04em;">— Albert Einstein</div>
    <?php else: ?>
        <h1>Computer Science &amp; Software Development Knowledge Repository</h1>
        <p style="font-style: italic; opacity: 0.95; font-size: 1.05rem;">“The mind that opens to a new idea never returns to its original size.”</p>
        <div style="font-size: 0.85rem; color: #93c5fd; font-weight: 600; margin-top: 0.35rem; letter-spacing: 0.04em;">— Albert Einstein</div>
    <?php endif; ?>
</div>

<div class="container">
    <?php if ($importMessage): ?>
        <div class="message <?php echo $importSuccess ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($importMessage); ?>
        </div>
    <?php endif; ?>

    <?php if ($selectedCategory): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
            <a href="index.php" class="back-link" style="margin-bottom: 0;">&larr; Back to All Categories</a>
            <?php if (Auth::isLoggedIn()): ?>
            <a href="index.php?export=json&category=<?php echo urlencode($selectedCategory); ?>" class="btn export-btn" style="padding: 0.4rem 0.9rem; font-size: 0.85rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Export "<?php echo htmlspecialchars($selectedCategory); ?>" JSON
            </a>
            <?php endif; ?>
        </div>
        <div class="controls">
            <div class="search-box">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" id="searchInput" placeholder="Search in <?php echo htmlspecialchars($selectedCategory); ?>..." autocomplete="off">
            </div>
            <?php if (Auth::isLoggedIn()): ?>
            <button class="btn add-btn" id="addQuestionBtn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Add Question
            </button>
            <?php endif; ?>
            <button class="btn primary" id="expandAllBtn">Expand All</button>
            <button class="btn" id="collapseAllBtn">Collapse All</button>
            <span class="stats" id="stats"></span>
        </div>
        <div class="qa-list" id="qaList"></div>
        <div class="no-results" id="noResults" style="display:none;">No matching questions found in this category.</div>
    <?php else: ?>
        <?php if (Auth::isLoggedIn()): ?>
        <div class="data-management-grid">
            <div class="data-card">
                <div>
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        Import File to Database
                    </h3>
                    <p>Upload a JSON or text file with question entries. Supports strict JSON and JavaScript-like object literals.</p>
                </div>
                <form method="post" enctype="multipart/form-data">
                    <div class="file-input-group">
                        <input type="file" name="jsonfile" accept=".json,.txt" required>
                        <button type="submit" class="btn primary" style="padding: 0.55rem 1rem;">Import</button>
                    </div>
                    <label class="checkbox-label">
                        <input type="checkbox" name="replace_existing" value="1">
                        Replace / overwrite existing questions in database
                    </label>
                </form>
            </div>

            <div class="data-card">
                <div>
                    <div class="db-badge">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
                        Database Status: <?php echo $totalQuestionsCount; ?> Questions | <?php echo count($categories); ?> Categories
                    </div>
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        Export Database to File
                    </h3>
                    <p>Export and download all stored questions and answers as a structured, formatted JSON file.</p>
                </div>
                <div>
                    <a href="index.php?export=json" class="btn export-btn" style="width: 100%; justify-content: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        Download All Questions (JSON)
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($categories)): ?>
            <div class="category-grid">
                <?php foreach ($categories as $cat): ?>
                    <a href="index.php?category=<?php echo urlencode($cat); ?>" class="category-card">
                        <h2><?php echo htmlspecialchars($cat); ?></h2>
                        <p>Explore questions</p>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-results">No questions in the database yet. Please import a JSON file.</div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Admin Login Modal -->
<div class="modal-overlay" id="loginModal" role="dialog" aria-modal="true" aria-labelledby="loginModalTitle">
    <div class="modal-card modal-auth-card">
        <div class="modal-header">
            <h3 id="loginModalTitle" style="display: flex; align-items: center; gap: 0.5rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                Administrator Login
            </h3>
            <button type="button" class="modal-close" id="loginModalCloseBtn" aria-label="Close modal">&times;</button>
        </div>
        <form id="loginForm">
            <div class="modal-body">
                <div id="loginAlert" class="auth-alert" style="display:none;"></div>
                <div class="form-group">
                    <label for="loginUsername">Username <span class="required">*</span></label>
                    <input type="text" id="loginUsername" required placeholder="Enter username (e.g. admin)" autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="loginPassword">Password <span class="required">*</span></label>
                    <div style="position: relative;">
                        <input type="password" id="loginPassword" required placeholder="Enter password" autocomplete="current-password" style="padding-right: 2.5rem;">
                        <button type="button" id="togglePasswordBtn" style="position: absolute; right: 0.6rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-muted); padding: 0.2rem;" title="Show/hide password">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" id="loginModalCancelBtn">Cancel</button>
                <button type="submit" class="btn primary" id="loginSubmitBtn">Log In</button>
            </div>
        </form>
    </div>
</div>

<!-- Add / Edit Question Modal -->
<div class="modal-overlay" id="qaModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="modalTitle">Edit Question</h3>
            <button type="button" class="modal-close" id="modalCloseBtn" aria-label="Close modal">&times;</button>
        </div>
        <form id="qaForm">
            <div class="modal-body">
                <input type="hidden" id="formQuestionId" value="">
                <div class="form-group">
                    <label for="formQuestion">Question <span class="required">*</span></label>
                    <textarea id="formQuestion" rows="3" required placeholder="Enter question text..."></textarea>
                </div>
                <div class="form-group">
                    <label for="formAnswer">Answer <span class="required">*</span></label>
                    <textarea id="formAnswer" rows="6" required placeholder="Enter answer details..."></textarea>
                </div>
                <div class="form-group">
                    <label for="formCategory">Category</label>
                    <input type="text" id="formCategory" placeholder="e.g. GraphQL, JavaScript, General" list="categoryOptions">
                    <datalist id="categoryOptions">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" id="modalCancelBtn">Cancel</button>
                <button type="submit" class="btn primary" id="modalSaveBtn">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<script src="assets/prism.min.js"></script>
<script>
    let isAuthenticated = <?php echo Auth::isLoggedIn() ? 'true' : 'false'; ?>;
    let currentUser = <?php echo json_encode(Auth::getCurrentUser()); ?>;
    const currentCategoryName = <?php echo json_encode($selectedCategory); ?>;
    const apiEndpoint = <?php echo json_encode($_SERVER['SCRIPT_NAME'] ?? 'index.php'); ?>;
    let qaData = <?php echo $questionsJson ?? '[]'; ?>;
    let pendingAction = null;

    // Toast Container & Function
    const toastContainer = document.getElementById('toastContainer');
    function showToast(message, type = 'success') {
        if (!toastContainer) return;
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `<span>${escapeHtml(message)}</span>`;
        toastContainer.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // -------------------------------------------------------------
    // Auth Modal Logic
    // -------------------------------------------------------------
    const loginModal = document.getElementById('loginModal');
    const loginForm = document.getElementById('loginForm');
    const loginUsername = document.getElementById('loginUsername');
    const loginPassword = document.getElementById('loginPassword');
    const loginAlert = document.getElementById('loginAlert');
    const loginSubmitBtn = document.getElementById('loginSubmitBtn');
    const loginModalCloseBtn = document.getElementById('loginModalCloseBtn');
    const loginModalCancelBtn = document.getElementById('loginModalCancelBtn');
    const togglePasswordBtn = document.getElementById('togglePasswordBtn');
    const headerLoginBtn = document.getElementById('headerLoginBtn');
    const headerLogoutBtn = document.getElementById('headerLogoutBtn');

    function openLoginModal(actionCallback = null, hintMessage = '') {
        pendingAction = actionCallback;
        if (loginAlert) {
            if (hintMessage) {
                loginAlert.textContent = hintMessage;
                loginAlert.style.display = 'block';
            } else {
                loginAlert.style.display = 'none';
            }
        }
        if (loginPassword) loginPassword.value = '';
        if (loginModal) loginModal.classList.add('active');
        setTimeout(() => {
            if (loginUsername) {
                if (!loginUsername.value) loginUsername.focus();
                else if (loginPassword) loginPassword.focus();
            }
        }, 50);
    }

    function closeLoginModal() {
        if (loginModal) loginModal.classList.remove('active');
        pendingAction = null;
    }

    if (loginModalCloseBtn) loginModalCloseBtn.addEventListener('click', closeLoginModal);
    if (loginModalCancelBtn) loginModalCancelBtn.addEventListener('click', closeLoginModal);
    if (loginModal) {
        loginModal.addEventListener('click', (e) => {
            if (e.target === loginModal) closeLoginModal();
        });
    }

    if (togglePasswordBtn && loginPassword) {
        togglePasswordBtn.addEventListener('click', () => {
            const isPassword = loginPassword.type === 'password';
            loginPassword.type = isPassword ? 'text' : 'password';
        });
    }

    if (headerLoginBtn) {
        headerLoginBtn.addEventListener('click', () => {
            openLoginModal(null, 'Enter administrator credentials to create, edit, or delete questions.');
        });
    }

    if (headerLogoutBtn) {
        headerLogoutBtn.addEventListener('click', async () => {
            try {
                const res = await fetch(`${apiEndpoint}?api_action=logout`, { method: 'POST' });
                if (res.ok) {
                    window.location.reload();
                }
            } catch (e) {
                window.location.reload();
            }
        });
    }

    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = loginUsername ? loginUsername.value.trim() : '';
            const password = loginPassword ? loginPassword.value : '';

            if (!username || !password) {
                if (loginAlert) {
                    loginAlert.textContent = 'Please enter both username and password.';
                    loginAlert.style.display = 'block';
                }
                return;
            }

            if (loginSubmitBtn) {
                loginSubmitBtn.disabled = true;
                loginSubmitBtn.textContent = 'Verifying...';
            }

            try {
                const response = await fetch(`${apiEndpoint}?api_action=login`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });
                const result = await response.json();

                if (result && result.success) {
                    isAuthenticated = true;
                    currentUser = result.username;
                    showToast(`Authenticated as ${result.username}`, 'success');
                    closeLoginModal();
                    
                    if (typeof pendingAction === 'function') {
                        const cb = pendingAction;
                        pendingAction = null;
                        cb();
                    } else {
                        window.location.reload();
                    }
                } else {
                    if (loginAlert) {
                        loginAlert.textContent = (result && result.error) || 'Invalid username or password.';
                        loginAlert.style.display = 'block';
                    }
                }
            } catch (err) {
                if (loginAlert) {
                    loginAlert.textContent = err.message || 'Connection error while logging in.';
                    loginAlert.style.display = 'block';
                }
            } finally {
                if (loginSubmitBtn) {
                    loginSubmitBtn.disabled = false;
                    loginSubmitBtn.textContent = 'Log In';
                }
            }
        });
    }

    // -------------------------------------------------------------
    // Question & Category Interaction Logic (If category view active)
    // -------------------------------------------------------------
    const qaList = document.getElementById('qaList');
    const searchInput = document.getElementById('searchInput');
    const noResults = document.getElementById('noResults');
    const expandAllBtn = document.getElementById('expandAllBtn');
    const collapseAllBtn = document.getElementById('collapseAllBtn');
    const addQuestionBtn = document.getElementById('addQuestionBtn');
    const stats = document.getElementById('stats');

    // Question Modal elements
    const qaModal = document.getElementById('qaModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalCloseBtn = document.getElementById('modalCloseBtn');
    const modalCancelBtn = document.getElementById('modalCancelBtn');
    const qaForm = document.getElementById('qaForm');
    const formQuestionId = document.getElementById('formQuestionId');
    const formQuestion = document.getElementById('formQuestion');
    const formAnswer = document.getElementById('formAnswer');
    const formCategory = document.getElementById('formCategory');
    const modalSaveBtn = document.getElementById('modalSaveBtn');

    function copyCodeBlock(btn, event) {
        if (event) {
            event.stopPropagation();
        }
        const pre = btn.parentElement;
        const codeEl = pre ? pre.querySelector('code') : null;
        if (!codeEl) return;
        navigator.clipboard.writeText(codeEl.textContent).then(() => {
            const originalText = btn.textContent;
            btn.textContent = 'Copied!';
            setTimeout(() => { btn.textContent = originalText; }, 1500);
        }).catch(() => {
            btn.textContent = 'Copied!';
            setTimeout(() => { btn.textContent = 'Copy'; }, 1500);
        });
    }

    function formatContent(text) {
        if (!text) return '';

        const codeBlockRegex = /```([a-zA-Z0-9_+\-]*)\s*([\s\S]*?)```/g;
        let formatted = text.replace(codeBlockRegex, (match, lang, code) => {
            let language = (lang.trim() || 'cpp').toLowerCase();
            if (language === 'c++') language = 'cpp';
            if (language === 'js') language = 'javascript';
            if (language === 'ts') language = 'typescript';
            if (language === 'py') language = 'python';
            const escapedCode = escapeHtml(code.trim());
            return `__CODEBLOCK_START__<pre class="language-${language}"><button type="button" class="copy-code-btn" onclick="copyCodeBlock(this, event)">Copy</button><code class="language-${language}">${escapedCode}</code></pre>__CODEBLOCK_END__`;
        });

        const parts = formatted.split(/(__CODEBLOCK_START__[\s\S]*?__CODEBLOCK_END__)/);
        for (let i = 0; i < parts.length; i++) {
            if (parts[i].startsWith('__CODEBLOCK_START__')) {
                parts[i] = parts[i].replace('__CODEBLOCK_START__', '').replace('__CODEBLOCK_END__', '');
            } else {
                parts[i] = escapeHtml(parts[i]);
                parts[i] = parts[i].replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
                parts[i] = parts[i].replace(/^####\s+(.*)$/gm, '<h4 style="margin:0.85rem 0 0.35rem; color:var(--navy-800); font-size:0.95rem; font-weight:600;">$1</h4>');
                parts[i] = parts[i].replace(/^###\s+(.*)$/gm, '<h3 style="margin:1rem 0 0.45rem; color:var(--navy-900); font-size:1.05rem; font-weight:700;">$1</h3>');
                parts[i] = parts[i].replace(/`([^`]+)`/g, (m, code) => `<code class="inline-code">${code}</code>`);
                parts[i] = parts[i].replace(/\n/g, '<br>');
            }
        }
        return parts.join('');
    }

    function triggerHighlighting(container) {
        if (window.Prism && container) {
            container.querySelectorAll('pre code').forEach((block) => {
                Prism.highlightElement(block);
            });
        }
    }

    function updateStats(count) {
        if (stats) stats.textContent = `${count} question${count !== 1 ? 's' : ''}`;
    }

    function renderList(items) {
        if (!qaList) return;
        qaList.innerHTML = '';
        if (!items || items.length === 0) {
            if (noResults) noResults.style.display = 'block';
            qaList.style.display = 'none';
            updateStats(0);
            return;
        }

        if (noResults) noResults.style.display = 'none';
        qaList.style.display = 'flex';

        items.forEach((item, index) => {
            if (!item) return;
            const div = document.createElement('div');
            div.className = 'qa-item';
            div.dataset.id = item.id;
            div.dataset.index = index;

            div.innerHTML = `
                <div class="qa-question" role="button" tabindex="0" aria-expanded="false">
                    <div class="qa-header-left">
                        <span class="qa-id-badge">${index + 1}</span>
                        <h3 class="item-question-text">${formatContent(item.question || '')}</h3>
                    </div>
                    <div class="qa-header-right">
                        ${isAuthenticated ? `
                        <div class="qa-actions">
                            <button type="button" class="action-btn edit-btn" title="Edit Question" data-id="${item.id}" aria-label="Edit Question">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                            </button>
                            <button type="button" class="action-btn delete-btn" title="Delete Question" data-id="${item.id}" aria-label="Delete Question">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                            </button>
                        </div>
                        ` : ''}
                    </div>
                </div>
                <div class="qa-answer">
                    <div class="item-answer-text">${formatContent(item.answer || '')}</div>
                </div>
            `;
            qaList.appendChild(div);
        });
        updateStats(items.length);
        triggerHighlighting(qaList);
    }

    function filterQuestions(query) {
        const q = (query || '').toLowerCase().trim();
        if (!q) return qaData || [];
        return (qaData || []).filter(item => {
            return (item.question || '').toLowerCase().includes(q) || (item.answer || '').toLowerCase().includes(q);
        });
    }

    function toggleItem(itemElement) {
        if (!itemElement) return;
        itemElement.classList.toggle('open');
        const isOpen = itemElement.classList.contains('open');
        const qBtn = itemElement.querySelector('.qa-question');
        if (qBtn) qBtn.setAttribute('aria-expanded', isOpen);
        if (isOpen) triggerHighlighting(itemElement);
    }

    function expandAll() {
        document.querySelectorAll('.qa-item').forEach(el => {
            el.classList.add('open');
            el.querySelector('.qa-question').setAttribute('aria-expanded', 'true');
        });
        triggerHighlighting(qaList);
    }

    function collapseAll() {
        document.querySelectorAll('.qa-item').forEach(el => {
            el.classList.remove('open');
            el.querySelector('.qa-question').setAttribute('aria-expanded', 'false');
        });
    }

    function openModal(mode, data = {}) {
        if (!qaModal) return;
        if (mode === 'edit') {
            modalTitle.textContent = 'Edit Question';
            formQuestionId.value = data.id || '';
            formQuestion.value = data.question || '';
            formAnswer.value = data.answer || '';
            formCategory.value = data.category || currentCategoryName || 'General';
            modalSaveBtn.textContent = 'Save Changes';
        } else {
            modalTitle.textContent = 'Add New Question';
            formQuestionId.value = '';
            formQuestion.value = '';
            formAnswer.value = '';
            formCategory.value = currentCategoryName || 'General';
            modalSaveBtn.textContent = 'Add Question';
        }
        qaModal.classList.add('active');
        setTimeout(() => formQuestion.focus(), 50);
    }

    function closeModal() {
        if (qaModal) qaModal.classList.remove('active');
    }

    if (modalCloseBtn) modalCloseBtn.addEventListener('click', closeModal);
    if (modalCancelBtn) modalCancelBtn.addEventListener('click', closeModal);
    if (qaModal) {
        qaModal.addEventListener('click', (e) => {
            if (e.target === qaModal) closeModal();
        });
    }

    if (addQuestionBtn) {
        addQuestionBtn.addEventListener('click', () => {
            if (!isAuthenticated) {
                openLoginModal(() => openModal('add'), 'Administrator authentication required to add new questions.');
                return;
            }
            openModal('add');
        });
    }

    if (qaForm) {
        qaForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = formQuestionId.value ? parseInt(formQuestionId.value, 10) : null;
            const mode = id ? 'edit' : 'add';
            const payload = {
                id: id,
                question: formQuestion.value.trim(),
                answer: formAnswer.value.trim(),
                category: formCategory.value.trim() || currentCategoryName || 'General'
            };

            if (!payload.question || !payload.answer) {
                showToast('Question and answer cannot be empty.', 'error');
                return;
            }

            modalSaveBtn.disabled = true;
            modalSaveBtn.textContent = 'Saving...';

            try {
                const response = await fetch(`${apiEndpoint}?api_action=${mode}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                if (response.status === 401) {
                    showToast('Administrator login required.', 'error');
                    openLoginModal(() => {
                        if (qaForm) qaForm.dispatchEvent(new Event('submit'));
                    }, 'Your session expired. Please log in again to save.');
                    return;
                }

                const result = await response.json();

                if (result && result.success) {
                    if (mode === 'edit') {
                        const idx = (qaData || []).findIndex(item => item && item.id == id);
                        if (idx !== -1) {
                            qaData[idx] = { ...qaData[idx], ...payload };
                        }
                        const domCard = document.querySelector(`.qa-item[data-id="${id}"]`);
                        if (domCard) {
                            domCard.querySelector('.item-question-text').innerHTML = formatContent(payload.question);
                            domCard.querySelector('.item-answer-text').innerHTML = formatContent(payload.answer);
                            triggerHighlighting(domCard);
                        }
                        showToast('Updated successfully!', 'success');
                    } else {
                        const newItem = { id: result.id, ...payload };
                        qaData.unshift(newItem);
                        renderList(filterQuestions(searchInput.value));
                        showToast('Added successfully!', 'success');
                    }
                    closeModal();
                } else {
                    showToast((result && result.error) || 'Failed to save.', 'error');
                }
            } catch (err) {
                showToast('Network error.', 'error');
            } finally {
                modalSaveBtn.disabled = false;
                modalSaveBtn.textContent = mode === 'edit' ? 'Save Changes' : 'Add Question';
            }
        });
    }

    async function deleteQuestion(id) {
        if (!confirm('Are you sure?')) return;
        try {
            const response = await fetch(`${apiEndpoint}?api_action=delete`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });

            if (response.status === 401) {
                showToast('Administrator login required.', 'error');
                openLoginModal(() => deleteQuestion(id), 'Please log in to delete this question.');
                return;
            }

            const result = await response.json();
            if (result && result.success) {
                qaData = qaData.filter(item => item.id != id);
                const domCard = document.querySelector(`.qa-item[data-id="${id}"]`);
                if (domCard) domCard.remove();
                updateStats(qaData.length);
                showToast('Deleted successfully.', 'success');
            } else {
                showToast((result && result.error) || 'Failed to delete.', 'error');
            }
        } catch (err) {
            showToast('Network error.', 'error');
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', (e) => renderList(filterQuestions(e.target.value)));
    }

    if (qaList) {
        qaList.addEventListener('click', (e) => {
            const editBtn = e.target.closest('.edit-btn');
            if (editBtn) {
                e.stopPropagation();
                const id = parseInt(editBtn.dataset.id, 10);
                const item = (qaData || []).find(q => q.id == id);
                if (item) {
                    if (!isAuthenticated) {
                        openLoginModal(() => openModal('edit', item), 'Administrator authentication required to edit questions.');
                        return;
                    }
                    openModal('edit', item);
                }
                return;
            }
            const deleteBtn = e.target.closest('.delete-btn');
            if (deleteBtn) {
                e.stopPropagation();
                const id = parseInt(deleteBtn.dataset.id, 10);
                if (!isAuthenticated) {
                    openLoginModal(() => deleteQuestion(id), 'Administrator authentication required to delete questions.');
                    return;
                }
                deleteQuestion(id);
                return;
            }
            if (e.target.closest('.copy-code-btn')) return;
            const questionDiv = e.target.closest('.qa-question');
            if (questionDiv) toggleItem(questionDiv.parentElement);
        });
    }

    if (expandAllBtn) expandAllBtn.addEventListener('click', expandAll);
    if (collapseAllBtn) collapseAllBtn.addEventListener('click', collapseAll);

    if (qaData && Array.isArray(qaData) && qaList) {
        renderList(qaData);
    }
</script>

<?php renderGlobalFooter(); ?>

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

        <!-- 3. Pages & Profile Navigation -->
        <div class="float-menu-divider"></div>
        <div class="float-menu-group-label">Pages &amp; Profile</div>
        <a href="projects.php" class="float-menu-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
            <span>Projects Showcase</span>
            <span class="float-menu-badge" style="background:#e0f2fe; color:#0369a1;">Portfolio</span>
        </a>
        <a href="profile.php" class="float-menu-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
            <span>Resume &amp; Profile</span>
            <span class="float-menu-badge" style="background:#e0f2fe; color:#0369a1;">CV</span>
        </a>
        <a href="index.php" class="float-menu-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            <span>All Categories Overview</span>
        </a>

        <div class="float-menu-divider"></div>
        <div class="float-menu-group-label">Quick Actions</div>

        <?php if ($selectedCategory): ?>
            <?php if (Auth::isLoggedIn()): ?>
            <button type="button" class="float-menu-item" id="floatAddQuestionBtn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                <span>Add New Question</span>
            </button>
            <?php endif; ?>
            <button type="button" class="float-menu-item" id="floatSearchBtn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <span>Search Questions</span>
            </button>
            <button type="button" class="float-menu-item" id="floatExpandAllBtn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="7 13 12 18 17 13"></polyline><polyline points="7 6 12 11 17 6"></polyline></svg>
                <span>Expand All</span>
            </button>
            <button type="button" class="float-menu-item" id="floatCollapseAllBtn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 11 12 6 7 11"></polyline><polyline points="17 18 12 13 7 18"></polyline></svg>
                <span>Collapse All</span>
            </button>
            <?php if (Auth::isLoggedIn()): ?>
            <a href="index.php?export=json&category=<?php echo urlencode($selectedCategory); ?>" class="float-menu-item">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                <span>Export JSON</span>
            </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (Auth::isLoggedIn()): ?>
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
    // Initialize Float Menu Elements safely
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

        const floatAddQ = document.getElementById('floatAddQuestionBtn');
        if (floatAddQ) {
            floatAddQ.onclick = function() {
                toggleFloatMenu(false);
                const mainAdd = document.getElementById('addQuestionBtn');
                if (mainAdd) mainAdd.click();
            };
        }

        const floatSearch = document.getElementById('floatSearchBtn');
        if (floatSearch) {
            floatSearch.onclick = function() {
                toggleFloatMenu(false);
                const sInput = document.getElementById('searchInput');
                if (sInput) {
                    sInput.focus();
                    sInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            };
        }

        const floatExpAll = document.getElementById('floatExpandAllBtn');
        if (floatExpAll) {
            floatExpAll.onclick = function() {
                toggleFloatMenu(false);
                const mainExp = document.getElementById('expandAllBtn');
                if (mainExp) mainExp.click();
            };
        }

        const floatCollAll = document.getElementById('floatCollapseAllBtn');
        if (floatCollAll) {
            floatCollAll.onclick = function() {
                toggleFloatMenu(false);
                const mainColl = document.getElementById('collapseAllBtn');
                if (mainColl) mainColl.click();
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
                if (typeof openLoginModal === 'function') openLoginModal();
            };
        }

        const floatLogout = document.getElementById('floatHeaderLogoutBtn');
        if (floatLogout) {
            floatLogout.onclick = async function(e) {
                e.preventDefault();
                toggleFloatMenu(false);
                try {
                    await fetch('index.php?api_action=logout', { method: 'POST' });
                } catch (err) {}
                window.location.href = 'index.php';
            };
        }
    })();
</script>
</body>
</html>
