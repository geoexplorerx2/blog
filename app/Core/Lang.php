<?php

namespace App\Core {

    class Lang
    {
        private static ?array $strings = null;

        /**
         * Load all localization strings from the central language file.
         */
        public static function load(): array
        {
            if (self::$strings === null) {
                $path = dirname(__DIR__, 2) . '/lang/strings.php';
                self::$strings = file_exists($path) ? (include $path) : [];
            }
            return self::$strings;
        }

        /**
         * Get a localized string by key with optional default and parameter replacement.
         */
        public static function get(string $key, ?string $default = null, array $replace = []): string
        {
            $strings = self::load();
            $text = $strings[$key] ?? ($default !== null ? $default : $key);

            if (!empty($replace)) {
                foreach ($replace as $placeholder => $value) {
                    $text = str_replace(':' . $placeholder, (string)$value, $text);
                }
            }

            return $text;
        }

        /**
         * Return the complete dictionary for frontend serialization.
         */
        public static function all(): array
        {
            return self::load();
        }
    }
}

namespace {

    // Global helpers for clean, DRY string resolution across all views & controllers
    if (!function_exists('__')) {
        function __(string $key, ?string $default = null, array $replace = []): string
        {
            return \App\Core\Lang::get($key, $default, $replace);
        }
    }

    if (!function_exists('trans')) {
        function trans(string $key, ?string $default = null, array $replace = []): string
        {
            return \App\Core\Lang::get($key, $default, $replace);
        }
    }
}
