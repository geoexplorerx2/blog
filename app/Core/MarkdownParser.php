<?php

namespace App\Core;

use Parsedown;

class MarkdownParser
{
    private static ?Parsedown $parsedown = null;

    public static function getParsedown(): Parsedown
    {
        if (self::$parsedown === null) {
            $parsedownFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'Parsedown.php';
            if (file_exists($parsedownFile) && !class_exists('Parsedown')) {
                require_once $parsedownFile;
            }
            self::$parsedown = new Parsedown();
            self::$parsedown->setBreaksEnabled(true);
            self::$parsedown->setMarkupEscaped(true);
        }
        return self::$parsedown;
    }

    public static function parse(string $text): string
    {
        $parsedown = self::getParsedown();
        $html = $parsedown->text($text);

        $html = preg_replace_callback(
            '~<pre><code class="language-([^"]+)">~',
            function ($m) {
                return '<pre class="language-' . $m[1] . '"><button type="button" class="copy-code-btn" onclick="copyCodeBlock(this, event)">Copy</button><code class="language-' . $m[1] . '">';
            },
            $html
        );

        return $html;
    }
}
