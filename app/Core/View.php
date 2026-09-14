<?php

namespace App\Core;

use RuntimeException;

class View
{
    private static string $viewsPath = '';

    public static function setViewsPath(string $path): void
    {
        self::$viewsPath = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
    }

    public static function getViewsPath(): string
    {
        if (empty(self::$viewsPath)) {
            self::$viewsPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR;
        }
        return self::$viewsPath;
    }

    public static function render(string $viewPath, array $data = []): void
    {
        $file = self::getViewsPath() . ltrim($viewPath, '/\\');
        if (!str_ends_with($file, '.php')) {
            $file .= '.php';
        }

        if (!file_exists($file)) {
            throw new RuntimeException("View file not found: $file");
        }

        extract($data);
        require $file;
    }

    public static function fetch(string $viewPath, array $data = []): string
    {
        ob_start();
        self::render($viewPath, $data);
        return ob_get_clean();
    }
}
