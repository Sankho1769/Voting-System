<?php
namespace App\Core;

/**
 * Cross-Site Request Forgery (CSRF) Defense
 * Generates and validates cryptographically secure CSRF tokens per session.
 */
class CSRF
{
    private const SESSION_KEY = '_csrf_token';

    /**
     * Retrieve the active CSRF token for the current session,
     * generating one if none exists.
     */
    public static function getToken(): string
    {
        Session::start();
        $token = Session::get(self::SESSION_KEY);
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }
        return $token;
    }

    /**
     * Forcefully regenerate a new CSRF token for the current session.
     * Use upon privilege elevation (login, logout) or after token expiration.
     */
    public static function regenerate(): string
    {
        Session::start();
        $token = bin2hex(random_bytes(32));
        Session::set(self::SESSION_KEY, $token);
        return $token;
    }

    /**
     * Verify a submitted token against the stored session token.
     */
    public static function verify(?string $submittedToken): bool
    {
        Session::start();
        $stored = Session::get(self::SESSION_KEY);
        if (!is_string($stored) || !is_string($submittedToken) || empty($submittedToken)) {
            return false;
        }

        $submittedToken = trim($submittedToken);
        if (strlen($submittedToken) < 32 || strlen($stored) < 32) {
            return false;
        }

        return hash_equals($stored, $submittedToken);
    }

    /**
     * Output a pre-formatted hidden HTML form input field.
     */
    public static function field(): string
    {
        $token = htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    /**
     * Extract token from POST payload, JSON body, or HTTP headers and validate.
     */
    public static function validateRequest(): bool
    {
        $token = $_POST['csrf_token'] 
            ?? $_SERVER['HTTP_X_CSRF_TOKEN'] 
            ?? $_SERVER['HTTP_X_XSRF_TOKEN']
            ?? null;

        if ($token === null && !empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            if (!empty($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['csrf_token'])) {
                    $token = (string)$decoded['csrf_token'];
                }
            }
        }

        return self::verify($token);
    }
}
