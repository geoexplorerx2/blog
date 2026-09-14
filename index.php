<?php

require_once __DIR__ . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

if (isset($_GET['page']) && $_GET['page'] === 'profile') {
    require_once __DIR__ . '/profile.php';
    exit;
}

require_once __DIR__ . '/footer_component.php';

use App\Controllers\QuestionController;
use App\Core\JsonSanitizer;
use App\Core\MarkdownParser;

// Global helper backward compatibility
if (!function_exists('renderMarkdown')) {
    function renderMarkdown(string $text): string
    {
        return MarkdownParser::parse($text);
    }
}

if (!function_exists('sanitizeJson')) {
    function sanitizeJson(string $input): string
    {
        return JsonSanitizer::sanitize($input);
    }
}

$controller = new QuestionController();
$controller->handle();
