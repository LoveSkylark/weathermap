<?php

namespace App\Plugins\Weathermap\Services;

/**
 * EditorSanitizerService - Input sanitization for editor operations
 * 
 * Extracted from lib/editor/EditorFunctions.php
 * Provides consistent input cleaning to prevent XSS and injection attacks
 */
class EditorSanitizerService
{
    /**
     * Clean up URI to protect against XSS (from Cacti)
     */
    public function sanitizeUri(string $str): string
    {
        static $drop_char_match = [' ', '^', '$', '<', '>', '`', '\'', '"', '|', '+', '[', ']', '{', '}', ';', '!', '%'];
        static $drop_char_replace = ['', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];

        return str_replace($drop_char_match, $drop_char_replace, urldecode($str));
    }

    /**
     * Loose sanitizer for general strings (remove HTML tags)
     */
    public function sanitizeString(string $str): string
    {
        return str_replace(['<', '>'], ['', ''], urldecode($str));
    }

    /**
     * Remove spaces from names (Nodes, Links, Scales)
     */
    public function sanitizeName(string $str): string
    {
        return str_replace(' ', '', $str);
    }

    /**
     * Sanitize selected element (NODE: or LINK:)
     */
    public function sanitizeSelected(string $str): string
    {
        $res = urldecode($str);

        if (!preg_match("/^(LINK|NODE):/", $res)) {
            return '';
        }
        return $this->sanitizeName($res);
    }

    /**
     * Sanitize filename with allowed extensions
     */
    public function sanitizeFile(string $filename, array $allowed_exts = []): string
    {
        $filename = $this->sanitizeUri($filename);

        if ($filename === '') {
            return '';
        }

        $ok = false;
        foreach ($allowed_exts as $ext) {
            $match = '.' . $ext;
            if (substr($filename, -strlen($match), strlen($match)) === $match) {
                $ok = true;
                break;
            }
        }

        return $ok ? $filename : '';
    }

    /**
     * Sanitize configuration filename (.conf files only)
     */
    public function sanitizeConffile(string $filename): string
    {
        $filename = $this->sanitizeUri($filename);

        // Must end in .conf
        if (substr($filename, -5, 5) !== '.conf') {
            return '';
        }

        // Prevent directory traversal (CVE-2013-3739)
        if (strpos($filename, '/') !== false) {
            return '';
        }

        return $filename;
    }

    /**
     * Handle legacy PHP magic quotes if enabled
     */
    public function fixGpcString(string $input): string
    {
        if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
            $input = stripslashes($input);
        }
        return $input;
    }
}
