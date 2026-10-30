<?php
namespace App\Core;

/**
 * Cross-Site Request Forgery (CSRF) Defense
 * Generates and validates cryptographically secure CSRF tokens per session.
 */
class CSRF
{
    private const SESSION_KEY = '_csrf_token';

    public static function getToken(): string
    {
        Session::start();
        $token = Session::get(self::SESSION_KEY);
        if (!$token || !is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }
        return $token;
    }

    public static function regenerate(): string
    {
        Session::start();
        $token = bin2hex(random_bytes(32));
        Session::set(self::SESSION_KEY, $token);
        return $token;
    }

    public static function verify(?string $submittedToken): bool
    {
        Session::start();
        $stored = Session::get(self::SESSION_KEY);
        if (!is_string($stored) || !is_string($submittedToken) || empty($submittedToken)) {
            return false;
        }
        return hash_equals($stored, $submittedToken);
    }

    public static function field(): string
    {
        $token = htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    public static function validateRequest(): bool
    {
        $token = $_POST['csrf_token'] 
            ?? $_SERVER['HTTP_X_CSRF_TOKEN'] 
            ?? null;
        return self::verify($token);
    }
}
