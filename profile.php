<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$dbname = 'q_db';
$username = 'root';
$password = 'root';

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

// Handle Direct GET Logout (e.g. profile.php?action=logout or profile.php?api_action=logout)
if ((isset($_GET['action']) && $_GET['action'] === 'logout') || (isset($_GET['api_action']) && $_GET['api_action'] === 'logout' && $_SERVER['REQUEST_METHOD'] === 'GET')) {
    Auth::logout();
    header('Location: profile.php');
    exit;
}

require_once __DIR__ . '/footer_component.php';

Auth::ensureUserTableExists();

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

    if ($action === 'save_resume') {
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

        try {
            $stmt = $db->query("SELECT * FROM profile ORDER BY id DESC LIMIT 1");
            $existing = $stmt->fetch() ?: [];

            $fullName = trim($inputData['full_name'] ?? ($existing['full_name'] ?? 'Farshad Nabizade'));
            $headline = trim($inputData['headline'] ?? ($existing['headline'] ?? 'Full-Stack Web Developer'));
            $email = trim($inputData['email'] ?? ($existing['email'] ?? ''));
            $phone = trim($inputData['phone'] ?? ($existing['phone'] ?? ''));
            $address = trim($inputData['address'] ?? ($existing['address'] ?? ''));
            $age = isset($inputData['age']) && is_numeric($inputData['age']) ? (int)$inputData['age'] : (int)($existing['age'] ?? 34);
            $gender = trim($inputData['gender'] ?? ($existing['gender'] ?? 'Male'));
            $maritalStatus = trim($inputData['marital_status'] ?? ($existing['marital_status'] ?? 'Single'));
            $militaryStatus = trim($inputData['military_status'] ?? ($existing['military_status'] ?? 'Exempted'));
            $workExpYears = isset($inputData['work_experience_years']) && is_numeric($inputData['work_experience_years']) ? (int)$inputData['work_experience_years'] : (int)($existing['work_experience_years'] ?? 7);
            $salaryExpectation = trim($inputData['salary_expectation'] ?? ($existing['salary_expectation'] ?? '45 - 60 Million Tomans'));
            $eduDegree = trim($inputData['education_degree'] ?? ($existing['education_degree'] ?? 'Bachelor: Computer Engineering'));
            $eduSchool = trim($inputData['education_school'] ?? ($existing['education_school'] ?? 'IAU'));
            $eduYears = trim($inputData['education_years'] ?? ($existing['education_years'] ?? '2016 - 2019'));
            $eduGpa = trim($inputData['education_gpa'] ?? ($existing['education_gpa'] ?? '16.4'));
            $aboutMe = trim($inputData['about_me'] ?? ($existing['about_me'] ?? ''));
            $github = trim($inputData['github'] ?? ($existing['github'] ?? ''));
            $linkedin = trim($inputData['linkedin'] ?? ($existing['linkedin'] ?? ''));

            // Safe JSON handling to prevent undefined array key warnings
            if (isset($inputData['skills'])) {
                $skills = is_array($inputData['skills']) ? json_encode($inputData['skills'], JSON_UNESCAPED_UNICODE) : (string)$inputData['skills'];
            } else {
                $skills = $existing['skills'] ?? '{}';
            }

            if (isset($inputData['experiences'])) {
                $experiences = is_array($inputData['experiences']) ? json_encode($inputData['experiences'], JSON_UNESCAPED_UNICODE) : (string)$inputData['experiences'];
            } else {
                $experiences = $existing['experiences'] ?? '[]';
            }

            if (isset($inputData['courses'])) {
                $courses = is_array($inputData['courses']) ? json_encode($inputData['courses'], JSON_UNESCAPED_UNICODE) : (string)$inputData['courses'];
            } else {
                $courses = $existing['courses'] ?? '[]';
            }

            if (isset($inputData['languages'])) {
                $languages = is_array($inputData['languages']) ? json_encode($inputData['languages'], JSON_UNESCAPED_UNICODE) : (string)$inputData['languages'];
            } else {
                $languages = $existing['languages'] ?? '[]';
            }

            if (isset($inputData['software'])) {
                $software = is_array($inputData['software']) ? json_encode($inputData['software'], JSON_UNESCAPED_UNICODE) : (string)$inputData['software'];
            } else {
                $software = $existing['software'] ?? '[]';
            }

            if (!empty($existing['id'])) {
                $update = $db->prepare("UPDATE profile SET
                    full_name = :full_name,
                    headline = :headline,
                    email = :email,
                    phone = :phone,
                    address = :address,
                    age = :age,
                    gender = :gender,
                    marital_status = :marital_status,
                    military_status = :military_status,
                    work_experience_years = :work_experience_years,
                    salary_expectation = :salary_expectation,
                    education_degree = :education_degree,
                    education_school = :education_school,
                    education_years = :education_years,
                    education_gpa = :education_gpa,
                    about_me = :about_me,
                    github = :github,
                    linkedin = :linkedin,
                    skills = :skills,
                    experiences = :experiences,
                    courses = :courses,
                    languages = :languages,
                    software = :software
                    WHERE id = :id");
                
                $update->execute([
                    ':id' => $existing['id'],
                    ':full_name' => $fullName,
                    ':headline' => $headline,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':address' => $address,
                    ':age' => $age,
                    ':gender' => $gender,
                    ':marital_status' => $maritalStatus,
                    ':military_status' => $militaryStatus,
                    ':work_experience_years' => $workExpYears,
                    ':salary_expectation' => $salaryExpectation,
                    ':education_degree' => $eduDegree,
                    ':education_school' => $eduSchool,
                    ':education_years' => $eduYears,
                    ':education_gpa' => $eduGpa,
                    ':about_me' => $aboutMe,
                    ':github' => $github,
                    ':linkedin' => $linkedin,
                    ':skills' => $skills,
                    ':experiences' => $experiences,
                    ':courses' => $courses,
                    ':languages' => $languages,
                    ':software' => $software
                ]);
            } else {
                $insert = $db->prepare("INSERT INTO profile (
                    full_name, headline, email, phone, address, age, gender,
                    marital_status, military_status, work_experience_years, salary_expectation,
                    education_degree, education_school, education_years, education_gpa,
                    about_me, github, linkedin, skills, experiences, courses, languages, software
                ) VALUES (
                    :full_name, :headline, :email, :phone, :address, :age, :gender,
                    :marital_status, :military_status, :work_experience_years, :salary_expectation,
                    :education_degree, :education_school, :education_years, :education_gpa,
                    :about_me, :github, :linkedin, :skills, :experiences, :courses, :languages, :software
                )");

                $insert->execute([
                    ':full_name' => $fullName,
                    ':headline' => $headline,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':address' => $address,
                    ':age' => $age,
                    ':gender' => $gender,
                    ':marital_status' => $maritalStatus,
                    ':military_status' => $militaryStatus,
                    ':work_experience_years' => $workExpYears,
                    ':salary_expectation' => $salaryExpectation,
                    ':education_degree' => $eduDegree,
                    ':education_school' => $eduSchool,
                    ':education_years' => $eduYears,
                    ':education_gpa' => $eduGpa,
                    ':about_me' => $aboutMe,
                    ':github' => $github,
                    ':linkedin' => $linkedin,
                    ':skills' => $skills,
                    ':experiences' => $experiences,
                    ':courses' => $courses,
                    ':languages' => $languages,
                    ':software' => $software
                ]);
            }

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Resume updated successfully!']);
        } catch (Exception $e) {
            ob_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// -------------------------------------------------------------
// Fetch Profile Data
// -------------------------------------------------------------
$db = Database::getConnection();
$profile = null;
if ($db) {
    $stmt = $db->query("SELECT * FROM profile ORDER BY id DESC LIMIT 1");
    $profile = $stmt->fetch();
}

if ($profile) {
    $skills = json_decode($profile['skills'] ?? '{}', true) ?: [];
    $experiences = json_decode($profile['experiences'] ?? '[]', true) ?: [];
    $courses = json_decode($profile['courses'] ?? '[]', true) ?: [];
    $languages = json_decode($profile['languages'] ?? '[]', true) ?: [];
    $software = json_decode($profile['software'] ?? '[]', true) ?: [];
} else {
    $skills = [];
    $experiences = [];
    $courses = [];
    $languages = [];
    $software = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($profile['full_name'] ?? 'Farshad Nabizade'); ?> – Resume</title>
    <link rel="icon" type="image/png" href="https://uploads.neginsafareh-academy.ir/files/favicon_20260907_085955_27bd31cd.png">
    <link rel="stylesheet" href="assets/fonts.css">
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
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --border: #e2e8f0;
            --shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
            --shadow-hover: 0 8px 24px rgba(15, 23, 42, 0.09);
            --radius: 12px;
            --font-heading: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-body: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        html {
            height: auto;
            min-height: 100%;
            overflow-y: auto;
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-body);
            background: var(--bg);
            color: var(--text-dark);
            line-height: 1.65;
            font-size: 0.98rem;
            letter-spacing: -0.011em;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
            min-height: 100vh;
            height: auto;
            overflow-y: visible;
            display: flex;
            flex-direction: column;
        }

        /* Top Header */
        .profile-nav-header {
            background: linear-gradient(135deg, var(--navy-900) 0%, var(--navy-700) 50%, var(--blue-600) 100%);
            color: white;
            padding: 1.1rem 1rem;
            border-bottom: 3px solid var(--blue-500);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .profile-nav-container {
            max-width: 1180px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .brand-link {
            color: white;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.25rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            letter-spacing: -0.02em;
        }

        .nav-sections-links {
            display: flex;
            align-items: center;
            gap: 1rem;
            list-style: none;
        }

        .nav-sections-links a {
            color: var(--blue-200);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            transition: color 0.15s ease;
        }

        .nav-sections-links a:hover {
            color: white;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-nav {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.45rem 0.95rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .btn-nav.primary {
            background: var(--blue-500);
            color: white;
            border-color: var(--blue-500);
        }
        .btn-nav.primary:hover {
            background: var(--blue-600);
        }

        .btn-nav.edit-resume-btn {
            background: #10b981;
            color: white;
            border-color: #10b981;
        }
        .btn-nav.edit-resume-btn:hover {
            background: #059669;
        }

        .btn-nav.outline {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border-color: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(4px);
        }
        .btn-nav.outline:hover {
            background: rgba(255, 255, 255, 0.28);
            border-color: white;
        }

        /* Container */
        .profile-container {
            max-width: 1180px;
            width: 100%;
            margin: 2rem auto;
            padding: 0 1rem;
            flex: 1 0 auto;
            display: flex;
            flex-direction: column;
            gap: 1.75rem;
        }

        /* Hero / Identity Card */
        .hero-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 2rem;
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 2rem;
            align-items: center;
            position: relative;
        }

        .hero-card::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; height: 5px;
            background: linear-gradient(90deg, var(--navy-700), var(--blue-500), #38bdf8);
            border-top-left-radius: var(--radius);
            border-top-right-radius: var(--radius);
        }

        .hero-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--navy-800), var(--blue-600));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 700;
            box-shadow: 0 4px 14px rgba(33, 120, 174, 0.25);
            border: 4px solid white;
            outline: 2px solid var(--blue-200);
            flex-shrink: 0;
        }

        .hero-info h1 {
            font-size: 1.85rem;
            font-weight: 700;
            color: var(--navy-900);
            margin-bottom: 0.35rem;
            letter-spacing: -0.02em;
        }

        .hero-headline {
            font-size: 1.05rem;
            font-weight: 500;
            color: var(--blue-600);
            margin-bottom: 1.15rem;
        }

        .hero-meta-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
        }

        .meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: var(--blue-50);
            border: 1px solid var(--blue-200);
            color: var(--navy-800);
            padding: 0.3rem 0.75rem;
            border-radius: 20px;
            font-size: 0.83rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .meta-pill:hover {
            background: var(--blue-100);
            border-color: var(--blue-500);
        }

        .meta-pill svg {
            color: var(--blue-500);
            flex-shrink: 0;
        }

        /* Sections Grid */
        .section-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.75rem;
            scroll-margin-top: 5rem;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
        }

        .section-header-left {
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        .section-header-left svg {
            color: var(--blue-500);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--navy-900);
            letter-spacing: -0.015em;
        }

        .about-text {
            font-size: 0.96rem;
            line-height: 1.75;
            color: #334155;
            text-align: justify;
        }

        /* Experience Timeline */
        .timeline {
            position: relative;
            padding-left: 1.75rem;
        }

        .timeline::before {
            content: "";
            position: absolute;
            top: 0.5rem;
            bottom: 0.5rem;
            left: 6px;
            width: 2px;
            background: #cbd5e1;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 2rem;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-dot {
            position: absolute;
            left: -1.75rem;
            top: 0.35rem;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: var(--blue-500);
            border: 3px solid white;
            box-shadow: 0 0 0 2px var(--blue-200);
        }

        .timeline-content {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.25rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .timeline-content:hover {
            border-color: var(--blue-200);
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }

        .timeline-role {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--navy-800);
            margin-bottom: 0.25rem;
        }

        .timeline-company {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 0.85rem;
        }

        .company-name {
            font-weight: 600;
            color: var(--blue-600);
            font-size: 0.92rem;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .period-badge {
            background: #e2e8f0;
            color: #475569;
            padding: 0.2rem 0.55rem;
            border-radius: 4px;
            font-size: 0.78rem;
            font-weight: 600;
        }

        .timeline-bullets {
            list-style-type: none;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .timeline-bullets li {
            position: relative;
            padding-left: 1.25rem;
            font-size: 0.92rem;
            color: #334155;
            line-height: 1.55;
        }

        .timeline-bullets li::before {
            content: "•";
            position: absolute;
            left: 0.3rem;
            top: 0;
            color: var(--blue-500);
            font-size: 1.1rem;
            line-height: 1.4;
        }

        /* Skills Matrix */
        .skills-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.25rem;
        }

        .skill-group-card {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.1rem;
        }

        .skill-group-title {
            font-size: 0.92rem;
            font-weight: 700;
            color: var(--navy-800);
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .skill-tags-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
        }

        .skill-tag {
            background: white;
            border: 1px solid #cbd5e1;
            color: #1e293b;
            padding: 0.25rem 0.6rem;
            border-radius: 4px;
            font-size: 0.82rem;
            font-weight: 500;
            transition: all 0.15s ease;
        }

        .skill-tag:hover {
            border-color: var(--blue-500);
            background: var(--blue-50);
            color: var(--navy-900);
        }

        /* Education & Courses Grid */
        .edu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1rem;
        }

        .edu-item-card {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.1rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .edu-item-title {
            font-size: 0.98rem;
            font-weight: 700;
            color: var(--navy-900);
            margin-bottom: 0.25rem;
        }

        .edu-item-inst {
            font-size: 0.88rem;
            color: var(--blue-600);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .edu-item-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: var(--text-muted);
            border-top: 1px dashed #cbd5e1;
            padding-top: 0.5rem;
            margin-top: 0.5rem;
        }

        /* 2 Column Details Layout */
        .details-two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        /* Modals & Forms */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(10, 37, 64, 0.65);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            backdrop-filter: blur(4px);
            padding: 1rem;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            width: 100%;
            max-width: 820px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            overflow: hidden;
            animation: modalFadeIn 0.25s ease-out;
        }

        .modal-auth-card {
            max-width: 440px !important;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.96) translateY(-10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(to right, var(--blue-50), white);
            flex-shrink: 0;
        }

        .modal-header h3 {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--navy-900);
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
            line-height: 1;
        }

        .close-btn:hover { color: var(--navy-900); }

        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            max-height: calc(90vh - 140px);
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            background: var(--bg);
            border-top: 1px solid var(--border);
            flex-shrink: 0;
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
            font-weight: 600;
            color: var(--navy-800);
        }

        .form-group input, .form-group textarea {
            padding: 0.65rem 0.85rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-family: var(--font-body);
            font-size: 0.92rem;
            background: var(--blue-50);
            color: var(--text-dark);
        }

        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--blue-500);
            box-shadow: 0 0 0 3px rgba(33,120,174,0.15);
            background: white;
        }

        .form-group textarea.code-area {
            font-family: var(--font-mono);
            font-size: 0.85rem;
            line-height: 1.45;
        }

        /* Toast */
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
            border-left: 4px solid #10b981;
            pointer-events: auto;
        }

        .toast.toast-error { border-left-color: #ef4444; }

        /* Footer */
        .profile-footer {
            background: var(--navy-900);
            color: var(--blue-200);
            text-align: center;
            padding: 1.5rem 1rem;
            font-size: 0.85rem;
            margin-top: auto;
            border-top: 3px solid var(--blue-500);
            flex-shrink: 0;
        }

        /* Responsive Breakpoints */
        @media (max-width: 860px) {
            .nav-sections-links {
                display: none;
            }
            .hero-card {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 1.25rem;
            }
            .hero-avatar {
                margin: 0 auto;
            }
            .hero-meta-grid {
                justify-content: center;
            }
            .details-two-col {
                grid-template-columns: 1fr;
            }
            .form-grid-2 {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {
            .profile-nav-container {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            .nav-actions {
                justify-content: center;
            }
            .hero-card {
                padding: 1.25rem 1rem;
            }
            .hero-info h1 {
                font-size: 1.5rem;
            }
            .section-card {
                padding: 1.25rem 1rem;
            }
            .timeline {
                padding-left: 1.25rem;
            }
            .timeline-dot {
                left: -1.25rem;
            }
            .skills-grid {
                grid-template-columns: 1fr;
            }
            .toast-container {
                left: 1rem;
                right: 1rem;
                bottom: 1rem;
            }
            .modal-card {
                max-height: 95vh;
            }
            .modal-body {
                padding: 1rem;
                max-height: calc(95vh - 130px);
            }
            .modal-footer {
                flex-direction: column-reverse;
                gap: 0.5rem;
            }
            .modal-footer .btn-nav {
                width: 100%;
                justify-content: center;
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
        .modal-close, .close-btn, #floatMenuCloseBtn, #closeEditModalBtn, #closeLoginModalBtn {
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

        .modal-close:hover, .close-btn:hover, #floatMenuCloseBtn:hover, #closeEditModalBtn:hover, #closeLoginModalBtn:hover {
            background: transparent !important;
            background-color: transparent !important;
            opacity: 1;
            transform: scale(1.15);
        }

        @media (max-width: 520px) {
            .float-menu-fab {
                bottom: 1.25rem;
                right: 1.25rem;
                width: 48px;
                height: 48px;
            }
            .float-menu-panel {
                bottom: 4.75rem;
                right: 1.25rem;
                left: 1.25rem;
                width: auto;
            }
        }

        @media print {
            .profile-nav-header, .profile-footer, .app-global-footer, .btn-nav, .modal-overlay, .nav-sections-links, .float-menu-fab, .float-menu-panel, .float-menu-backdrop {
                display: none !important;
            }
            body, .profile-container {
                background: white !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .hero-card, .section-card, .timeline-content {
                box-shadow: none !important;
                border: 1px solid #ccc !important;
            }
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

<header class="profile-nav-header">
    <div class="profile-nav-container">
        <a href="profile.php" class="brand-link">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            <span>Resume</span>
        </a>

        <ul class="nav-sections-links">
            <li><a href="#about">About</a></li>
            <li><a href="#skills">Skills</a></li>
            <li><a href="#experience">Experience</a></li>
            <li><a href="#education">Education</a></li>
        </ul>
    </div>
</header>

<main class="profile-container">

    <!-- Hero / Identity Card -->
    <section class="hero-card" id="about">
        <div class="hero-avatar">
            FN
        </div>
        <div class="hero-info">
            <h1><?php echo htmlspecialchars($profile['full_name'] ?? 'Farshad Nabizade'); ?></h1>
            <div class="hero-headline"><?php echo htmlspecialchars($profile['headline'] ?? 'Full-Stack Web Developer'); ?></div>
            
            <div class="hero-meta-grid">
                <span class="meta-pill">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    <?php echo htmlspecialchars($profile['address'] ?? 'Tehran, Iran'); ?>
                </span>
                <span class="meta-pill">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                    <?php echo htmlspecialchars($profile['work_experience_years'] ?? 7); ?>+ Years Experience
                </span>
                <a href="mailto:<?php echo htmlspecialchars($profile['email'] ?? 'geoexplorerx2@gmail.com'); ?>" class="meta-pill">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    <?php echo htmlspecialchars($profile['email'] ?? 'geoexplorerx2@gmail.com'); ?>
                </a>
                <a href="tel:<?php echo htmlspecialchars($profile['phone'] ?? '09218124475'); ?>" class="meta-pill">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                    <?php echo htmlspecialchars($profile['phone'] ?? '09218124475'); ?>
                </a>
                <span class="meta-pill">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
                    Age: <?php echo htmlspecialchars($profile['age'] ?? 34); ?> Years
                </span>
                <span class="meta-pill">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                    Salary: <?php echo htmlspecialchars($profile['salary_expectation'] ?? '45 - 60M Tomans'); ?>
                </span>
            </div>
        </div>
    </section>

    <!-- About Me -->
    <section class="section-card">
        <div class="section-header">
            <div class="section-header-left">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <h2 class="section-title">Executive Summary &amp; About Me</h2>
            </div>
        </div>
        <p class="about-text">
            <?php echo nl2br(htmlspecialchars($profile['about_me'] ?? '')); ?>
        </p>
    </section>

    <!-- Technical Skills Matrix -->
    <section class="section-card" id="skills">
        <div class="section-header">
            <div class="section-header-left">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
                <h2 class="section-title">Technical Skills &amp; Domain Expertise</h2>
            </div>
        </div>
        <div class="skills-grid">
            <?php if (is_array($skills)): ?>
                <?php foreach ($skills as $category => $skillList): ?>
                    <div class="skill-group-card">
                        <div class="skill-group-title">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                            <?php echo htmlspecialchars((string)$category); ?>
                        </div>
                        <div class="skill-tags-wrapper">
                            <?php if (is_array($skillList)): ?>
                                <?php foreach ($skillList as $skill): ?>
                                    <span class="skill-tag"><?php echo htmlspecialchars((string)$skill); ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Work Experience Timeline -->
    <section class="section-card" id="experience">
        <div class="section-header">
            <div class="section-header-left">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                <h2 class="section-title">Professional Work Experience</h2>
            </div>
        </div>
        <div class="timeline">
            <?php if (is_array($experiences)): ?>
                <?php foreach ($experiences as $exp): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <div class="timeline-role"><?php echo htmlspecialchars($exp['role'] ?? 'Developer'); ?></div>
                            <div class="timeline-company">
                                <span class="company-name">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                    <?php echo htmlspecialchars($exp['company'] ?? ''); ?><?php if (!empty($exp['location'])): ?> (<?php echo htmlspecialchars($exp['location']); ?>)<?php endif; ?>
                                </span>
                                <?php if (!empty($exp['period'])): ?>
                                    <span class="period-badge"><?php echo htmlspecialchars($exp['period']); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($exp['responsibilities']) && is_array($exp['responsibilities'])): ?>
                                <ul class="timeline-bullets">
                                    <?php foreach ($exp['responsibilities'] as $resp): ?>
                                        <li><?php echo htmlspecialchars((string)$resp); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- 2 Column Details: Education & Certifications, Languages & Software -->
    <div class="details-two-col" id="education">
        <!-- Education & Certifications -->
        <section class="section-card">
            <div class="section-header">
                <div class="section-header-left">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
                    <h2 class="section-title">Education &amp; Certifications</h2>
                </div>
            </div>
            <div class="edu-grid">
                <!-- Academic Degree -->
                <div class="edu-item-card">
                    <div>
                        <div class="edu-item-title"><?php echo htmlspecialchars($profile['education_degree'] ?? 'Bachelor: Computer Engineering'); ?></div>
                        <div class="edu-item-inst"><?php echo htmlspecialchars($profile['education_school'] ?? 'IAU'); ?></div>
                    </div>
                    <div class="edu-item-meta">
                        <span>Period: <?php echo htmlspecialchars($profile['education_years'] ?? '2016 - 2019'); ?></span>
                        <span>GPA: <strong><?php echo htmlspecialchars($profile['education_gpa'] ?? '16.4'); ?></strong></span>
                    </div>
                </div>

                <!-- Training Courses -->
                <?php if (is_array($courses)): ?>
                    <?php foreach ($courses as $c): ?>
                        <div class="edu-item-card">
                            <div>
                                <div class="edu-item-title"><?php echo htmlspecialchars($c['title'] ?? 'Training'); ?><?php if (!empty($c['title'])): ?> Certification<?php endif; ?></div>
                                <div class="edu-item-inst">Institute: <?php echo htmlspecialchars($c['institute'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="edu-item-meta">
                                <?php if (!empty($c['duration'])): ?><span>Duration: <?php echo htmlspecialchars($c['duration']); ?></span><?php endif; ?>
                                <?php if (!empty($c['year'])): ?><span>Year: <?php echo htmlspecialchars($c['year']); ?></span><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Languages & Software Competencies -->
        <section class="section-card">
            <div class="section-header">
                <div class="section-header-left">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1 4-10z"></path></svg>
                    <h2 class="section-title">Languages &amp; Software</h2>
                </div>
            </div>
            
            <div style="margin-bottom: 1.25rem;">
                <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--navy-800); margin-bottom: 0.5rem;">Languages</h3>
                <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
                    <?php foreach ($languages as $l): ?>
                        <div class="skill-tag" style="padding: 0.4rem 0.8rem; font-size: 0.88rem; background: var(--bg);">
                            <strong><?php echo htmlspecialchars($l['language']); ?>:</strong>
                            <span style="color: var(--blue-600);"><?php echo htmlspecialchars($l['proficiency']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--navy-800); margin-bottom: 0.5rem;">Productivity Software</h3>
                <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
                    <?php foreach ($software as $s): ?>
                        <div class="skill-tag" style="padding: 0.4rem 0.8rem; font-size: 0.88rem; background: var(--bg);">
                            <strong><?php echo htmlspecialchars($s['name']); ?>:</strong>
                            <span style="color: var(--blue-600);"><?php echo htmlspecialchars($s['proficiency']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </div>

</main>

<!-- Edit Resume Modal -->
<div class="modal-overlay" id="editResumeModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Edit Resume Information</h3>
            <button type="button" class="close-btn" id="closeEditModalBtn">&times;</button>
        </div>
        <form id="editResumeForm">
            <div class="modal-body">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="f_fullName">Full Name</label>
                        <input type="text" id="f_fullName" value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="f_headline">Headline / Title</label>
                        <input type="text" id="f_headline" value="<?php echo htmlspecialchars($profile['headline'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="f_email">Email</label>
                        <input type="email" id="f_email" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="f_phone">Phone / Mobile</label>
                        <input type="text" id="f_phone" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="f_address">Location Address</label>
                        <input type="text" id="f_address" value="<?php echo htmlspecialchars($profile['address'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="f_salary">Salary Expectation</label>
                        <input type="text" id="f_salary" value="<?php echo htmlspecialchars($profile['salary_expectation'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="f_age">Age</label>
                        <input type="number" id="f_age" value="<?php echo htmlspecialchars($profile['age'] ?? 34); ?>">
                    </div>
                    <div class="form-group">
                        <label for="f_expYears">Work Experience (Years)</label>
                        <input type="number" id="f_expYears" value="<?php echo htmlspecialchars($profile['work_experience_years'] ?? 7); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="f_aboutMe">Executive Summary &amp; About Me</label>
                    <textarea id="f_aboutMe" rows="4"><?php echo htmlspecialchars($profile['about_me'] ?? ''); ?></textarea>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="f_eduDegree">Degree</label>
                        <input type="text" id="f_eduDegree" value="<?php echo htmlspecialchars($profile['education_degree'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="f_eduSchool">University / Institute</label>
                        <input type="text" id="f_eduSchool" value="<?php echo htmlspecialchars($profile['education_school'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="f_eduYears">Education Period</label>
                        <input type="text" id="f_eduYears" value="<?php echo htmlspecialchars($profile['education_years'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="f_eduGpa">GPA</label>
                        <input type="text" id="f_eduGpa" value="<?php echo htmlspecialchars($profile['education_gpa'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="f_skillsJson">Technical Skills Matrix (JSON Format)</label>
                    <textarea id="f_skillsJson" class="code-area" rows="6"><?php echo htmlspecialchars(json_encode($skills, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="f_expJson">Work Experience Timeline (JSON Format)</label>
                    <textarea id="f_expJson" class="code-area" rows="8"><?php echo htmlspecialchars(json_encode($experiences, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-nav outline" id="cancelEditModalBtn" style="color: var(--navy-800); border-color: var(--border);">Cancel</button>
                <button type="submit" class="btn-nav primary" id="saveResumeBtn">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Admin Login Modal -->
<div class="modal-overlay" id="loginModal">
    <div class="modal-card modal-auth-card">
        <div class="modal-header">
            <h3>Administrator Authentication</h3>
            <button type="button" class="close-btn" id="closeLoginModalBtn">&times;</button>
        </div>
        <form id="loginForm">
            <div class="modal-body">
                <div id="loginAlert" style="display:none; background:#fee2e2; color:#b91c1c; padding:0.6rem; border-radius:6px; font-size:0.85rem;"></div>
                <div class="form-group">
                    <label for="loginUsername">Username</label>
                    <input type="text" id="loginUsername" placeholder="Enter username" value="admin" required>
                </div>
                <div class="form-group">
                    <label for="loginPassword">Password</label>
                    <input type="password" id="loginPassword" placeholder="Enter password" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-nav outline" id="cancelLoginModalBtn" style="color: var(--navy-800); border-color: var(--border);">Cancel</button>
                <button type="submit" class="btn-nav primary" id="submitLoginBtn">Log In</button>
            </div>
        </form>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<?php renderGlobalFooter(); ?>

<script>
    let isAuthenticated = <?php echo Auth::isLoggedIn() ? 'true' : 'false'; ?>;
    let pendingAction = null;

    const toastContainer = document.getElementById('toastContainer');
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast ${type === 'error' ? 'toast-error' : ''}`;
        toast.textContent = message;
        toastContainer.appendChild(toast);
        setTimeout(() => toast.remove(), 3500);
    }

    // Login Modal
    const loginModal = document.getElementById('loginModal');
    const loginForm = document.getElementById('loginForm');
    const loginUsername = document.getElementById('loginUsername');
    const loginPassword = document.getElementById('loginPassword');
    const loginAlert = document.getElementById('loginAlert');
    const closeLoginModalBtn = document.getElementById('closeLoginModalBtn');
    const cancelLoginModalBtn = document.getElementById('cancelLoginModalBtn');

    function openLoginModal(actionCallback = null) {
        pendingAction = actionCallback;
        loginAlert.style.display = 'none';
        loginPassword.value = '';
        loginModal.classList.add('active');
        setTimeout(() => loginPassword.focus(), 50);
    }

    function closeLoginModal() {
        loginModal.classList.remove('active');
        pendingAction = null;
    }

    if (closeLoginModalBtn) closeLoginModalBtn.addEventListener('click', closeLoginModal);
    if (cancelLoginModalBtn) cancelLoginModalBtn.addEventListener('click', closeLoginModal);

    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = loginUsername.value.trim();
            const password = loginPassword.value;

            try {
                const res = await fetch('profile.php?api_action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });
                const result = await res.json();
                if (result.success) {
                    isAuthenticated = true;
                    showToast('Authenticated as Admin', 'success');
                    closeLoginModal();
                    if (typeof pendingAction === 'function') {
                        const cb = pendingAction;
                        pendingAction = null;
                        cb();
                    }
                } else {
                    loginAlert.textContent = result.error || 'Authentication failed.';
                    loginAlert.style.display = 'block';
                }
            } catch (err) {
                loginAlert.textContent = 'Connection error.';
                loginAlert.style.display = 'block';
            }
        });
    }

    // Edit Resume Modal
    const editResumeBtn = document.getElementById('editResumeBtn');
    const editResumeModal = document.getElementById('editResumeModal');
    const editResumeForm = document.getElementById('editResumeForm');
    const closeEditModalBtn = document.getElementById('closeEditModalBtn');
    const cancelEditModalBtn = document.getElementById('cancelEditModalBtn');
    const saveResumeBtn = document.getElementById('saveResumeBtn');

    function openEditModal() {
        editResumeModal.classList.add('active');
    }

    function closeEditModal() {
        editResumeModal.classList.remove('active');
    }

    if (editResumeBtn) {
        editResumeBtn.addEventListener('click', () => {
            if (!isAuthenticated) {
                openLoginModal(() => openEditModal());
                return;
            }
            openEditModal();
        });
    }

    if (closeEditModalBtn) closeEditModalBtn.addEventListener('click', closeEditModal);
    if (cancelEditModalBtn) cancelEditModalBtn.addEventListener('click', closeEditModal);

    if (editResumeForm) {
        editResumeForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            let skillsParsed = {};
            let expParsed = [];
            try {
                skillsParsed = JSON.parse(document.getElementById('f_skillsJson').value);
            } catch (e) {
                showToast('Invalid Skills JSON formatting.', 'error');
                return;
            }

            try {
                expParsed = JSON.parse(document.getElementById('f_expJson').value);
            } catch (e) {
                showToast('Invalid Experience JSON formatting.', 'error');
                return;
            }

            const payload = {
                full_name: document.getElementById('f_fullName').value.trim(),
                headline: document.getElementById('f_headline').value.trim(),
                email: document.getElementById('f_email').value.trim(),
                phone: document.getElementById('f_phone').value.trim(),
                address: document.getElementById('f_address').value.trim(),
                salary_expectation: document.getElementById('f_salary').value.trim(),
                age: parseInt(document.getElementById('f_age').value, 10),
                work_experience_years: parseInt(document.getElementById('f_expYears').value, 10),
                about_me: document.getElementById('f_aboutMe').value.trim(),
                education_degree: document.getElementById('f_eduDegree').value.trim(),
                education_school: document.getElementById('f_eduSchool').value.trim(),
                education_years: document.getElementById('f_eduYears').value.trim(),
                education_gpa: document.getElementById('f_eduGpa').value.trim(),
                skills: skillsParsed,
                experiences: expParsed
            };

            saveResumeBtn.disabled = true;
            saveResumeBtn.textContent = 'Saving...';

            try {
                const res = await fetch('profile.php?api_action=save_resume', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const text = await res.text();
                let result = null;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    console.error('Server response:', text);
                    throw new Error('Server returned invalid response.');
                }

                if (res.status === 401 || (result && result.require_login)) {
                    showToast('Admin session required. Please log in.', 'error');
                    openLoginModal(() => {
                        editResumeForm.dispatchEvent(new Event('submit'));
                    }, 'Admin authentication required to save resume changes.');
                    return;
                }

                if (result && result.success) {
                    showToast('Resume updated successfully!', 'success');
                    closeEditModal();
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    showToast((result && result.error) || 'Failed to save resume.', 'error');
                }
            } catch (err) {
                console.error(err);
                showToast(err.message || 'Error saving resume.', 'error');
            } finally {
                saveResumeBtn.disabled = false;
                saveResumeBtn.textContent = 'Save Changes';
            }
        });
    }
</script>

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

        <!-- 3. Resume Actions (from cv-section.png) -->
        <div class="float-menu-divider"></div>
        <div class="float-menu-group-label">Navigation &amp; Actions</div>
        <a href="projects.php" class="float-menu-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
            <span>Projects Showcase</span>
            <span class="float-menu-badge" style="background:#e0f2fe; color:#0369a1;">Portfolio</span>
        </a>
        <a href="index.php" class="float-menu-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            <span>Questions Base</span>
        </a>
        <?php if (Auth::isLoggedIn()): ?>
        <button type="button" class="float-menu-item" id="floatEditResumeBtn" style="color:#059669;">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#10b981;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
            <span>Edit Resume</span>
            <span class="float-menu-badge" style="background:#d1fae5; color:#065f46;">Admin</span>
        </button>
        <button type="button" class="float-menu-item" id="floatEditFooterBtn">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
            <span>Edit Footer Sections</span>
            <span class="float-menu-badge" style="background:#e0f2fe; color:#0369a1;">Config</span>
        </button>
        <?php endif; ?>
        <button type="button" class="float-menu-item" id="floatPrintBtn" style="color:#0369a1;">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#0284c7;"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            <span>Print / PDF</span>
            <span class="float-menu-badge" style="background:#e0f2fe; color:#0369a1;">Export</span>
        </button>

        <!-- 4. Section Shortcuts (from cv-section.png) -->
        <div class="float-menu-divider"></div>
        <div class="float-menu-group-label">Section Jump</div>
        <a href="#about" class="float-menu-item" id="shortcutAbout">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            <span>About</span>
        </a>
        <a href="#skills" class="float-menu-item" id="shortcutSkills">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
            <span>Skills</span>
        </a>
        <a href="#experience" class="float-menu-item" id="shortcutExperience">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
            <span>Experience</span>
        </a>
        <a href="#education" class="float-menu-item" id="shortcutEducation">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
            <span>Education</span>
        </a>

        <!-- 5. Controls -->
        <div class="float-menu-divider"></div>
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

        // Section Shortcuts
        ['shortcutAbout', 'shortcutSkills', 'shortcutExperience', 'shortcutEducation'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.onclick = () => toggleFloatMenu(false);
        });

        const floatEdit = document.getElementById('floatEditResumeBtn');
        if (floatEdit) {
            floatEdit.onclick = function() {
                toggleFloatMenu(false);
                if (!isAuthenticated) {
                    if (typeof openLoginModal === 'function') {
                        openLoginModal(() => {
                            if (typeof openEditModal === 'function') openEditModal();
                        });
                    }
                    return;
                }
                if (typeof openEditModal === 'function') openEditModal();
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
                    await fetch('profile.php?api_action=logout', { method: 'POST' });
                } catch (err) {}
                window.location.href = 'profile.php';
            };
        }
    })();
</script>
</body>
</html>
