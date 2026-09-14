<?php

require_once __DIR__ . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Controllers\FooterController;
use App\Services\AuthService;
use App\Services\FooterService;

if (!class_exists('Database')) {
    class Database
    {
        public static function getConnection(): ?\PDO
        {
            return \App\Core\Database::getConnection();
        }
    }
}

if (!class_exists('Auth')) {
    class Auth
    {
        public static function ensureUserTableExists(): void
        {
            (new AuthService())->ensureUserTableExists();
        }

        public static function login(string $username, string $password): bool
        {
            return (new AuthService())->login($username, $password);
        }

        public static function logout(): void
        {
            (new AuthService())->logout();
        }

        public static function isLoggedIn(): bool
        {
            return (new AuthService())->isLoggedIn();
        }

        public static function getCurrentUser(): ?string
        {
            return (new AuthService())->getCurrentUser();
        }
    }
}

if (!class_exists('FooterManager')) {
    class FooterManager
    {
        public static function ensureTableExists(): void
        {
            (new FooterService())->getRepository()->ensureTableExists();
        }

        public static function getSettings(): array
        {
            return (new FooterService())->getSettingsArray();
        }

        public static function getDefaults(): array
        {
            $repo = (new FooterService())->getRepository();
            if ($repo instanceof \App\Repositories\FooterRepository) {
                return $repo->getDefaultData();
            }
            return [];
        }
    }
}

// -------------------------------------------------------------
// Shared API Action: save_footer
// -------------------------------------------------------------
if (isset($_GET['api_action']) && $_GET['api_action'] === 'save_footer') {
    $controller = new FooterController();
    $controller->handleApi();
}

if (!function_exists('renderGlobalFooter')) {
    function renderGlobalFooter(): void
    {
        FooterController::renderComponent();
    }
}
