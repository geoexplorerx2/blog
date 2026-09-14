<?php

namespace App\Core;

class Response
{
    public static function json(array $data, int $statusCode = 200): void
    {
        if (ob_get_level() > 0) {
            ob_clean();
        }
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirect(string $url, int $statusCode = 302): void
    {
        http_response_code($statusCode);
        header("Location: $url");
        exit;
    }

    public static function download(string $content, string $filename, string $contentType = 'application/json; charset=utf-8'): void
    {
        if (ob_get_level() > 0) {
            ob_clean();
        }
        header("Content-Type: $contentType");
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $content;
        exit;
    }

    public static function error(string $message, int $statusCode = 400, array $extra = []): void
    {
        self::json(array_merge(['success' => false, 'error' => $message], $extra), $statusCode);
    }

    public static function success(array $data = [], int $statusCode = 200): void
    {
        self::json(array_merge(['success' => true], $data), $statusCode);
    }
}
