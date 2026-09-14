<?php

require_once __DIR__ . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

require_once __DIR__ . '/footer_component.php';

use App\Controllers\FreelanceController;
use App\Services\FreelanceService;

class FreelanceManager
{
    public static function ensureTables(): void
    {
        (new FreelanceService())->getRepository()->ensureTableExists();
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function calculateDurationAndPrice(string $startDate, string $endDate, string $startTime, string $endTime, float $hourlyRate): array
    {
        return (new FreelanceService())->calculateDurationAndPrice($startDate, $endDate, $startTime, $endTime, $hourlyRate);
    }

    public static function seedInitialData(\PDO $db): void
    {
        (new FreelanceService())->seedDemoData();
    }
}

$controller = new FreelanceController();
$controller->handle();
