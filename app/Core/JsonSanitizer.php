<?php

namespace App\Core;

class JsonSanitizer
{
    /**
     * Convert JavaScript-like object literal (unquoted keys, single quotes, comments, trailing semicolons/commas) to valid JSON.
     */
    public static function sanitize(string $input): string
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
}
