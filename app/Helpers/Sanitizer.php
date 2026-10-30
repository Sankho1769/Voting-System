<?php
namespace App\Helpers;

/**
 * Output and Input Sanitization Helper
 * Defends against XSS and control character injection.
 */
class Sanitizer
{
    /**
     * Escape HTML output safely
     */
    public static function e(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Clean and normalize institutional ID (Roll Number or Employee ID)
     * Preserves leading zeros and uppercase standard
     */
    public static function cleanIdentifier(string $id): string
    {
        $id = trim($id);
        // Remove characters that aren't alphanumeric, dash, slash, or underscore
        $id = preg_replace('/[^A-Za-z0-9\-\/_]/', '', $id);
        return strtoupper($id);
    }

    /**
     * Clean general text input
     */
    public static function cleanText(string $text): string
    {
        return trim(preg_replace('/\r\n|\r/', "\n", $text));
    }
}
