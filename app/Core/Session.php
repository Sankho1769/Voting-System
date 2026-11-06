<?php
namespace App\Core;

/**
 * Secure Session Manager
 * Enforces idle and absolute timeouts, session regeneration, and secure cookie parameters.
 */
class Session
{
    private static bool $started = false;

    public static function start(array $config = []): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            self::checkTimeouts($config);
            return;
        }

        $configFile = dirname(__DIR__, 2) . '/config/config.php';
        if (!file_exists($configFile)) {
            $configFile = dirname(__DIR__, 2) . '/config/config.example.php';
        }
        $appConfig = require $configFile;

        // Ensure timezone is standardized across application
        if (isset($appConfig['app']['timezone'])) {
            date_default_timezone_set($appConfig['app']['timezone']);
        }

        if (empty($config)) {
            $config = $appConfig['session'] ?? [];
        }

        $cookieName = $config['cookie_name'] ?? 'VERITYVOTE_SESSID';
        $lifetime = $config['cookie_lifetime'] ?? 0;
        $path = $config['cookie_path'] ?? '/';
        $domain = $config['cookie_domain'] ?? '';
        $isHttps = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
        );
        $secure = isset($config['cookie_secure']) ? (bool)$config['cookie_secure'] : $isHttps;
        $httponly = (bool)($config['cookie_httponly'] ?? true);
        $samesite = $config['cookie_samesite'] ?? 'Lax';
        $idleTimeout = (int)($config['idle_timeout'] ?? 1800);
        $absoluteTimeout = (int)($config['absolute_timeout'] ?? 14400);

        if (!headers_sent()) {
            @ini_set('session.use_strict_mode', '1');
            @ini_set('session.use_cookies', '1');
            @ini_set('session.use_only_cookies', '1');
            @ini_set('session.gc_maxlifetime', (string)max($idleTimeout, $absoluteTimeout));

            session_name($cookieName);

            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite
            ]);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        self::$started = true;

        if (!isset($_SESSION['_created_at'])) {
            $_SESSION['_created_at'] = time();
        }
        self::checkTimeouts($config);
        $_SESSION['_last_activity'] = time();

        // Ensure session has a ready CSRF token upon initialization
        if (!isset($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token']) || strlen($_SESSION['_csrf_token']) < 32) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    private static function checkTimeouts(array $config): void
    {
        $idleTimeout = $config['idle_timeout'] ?? 1800; // 30 mins
        $absoluteTimeout = $config['absolute_timeout'] ?? 14400; // 4 hours
        $now = time();

        // Check idle timeout
        if (isset($_SESSION['_last_activity']) && ($now - $_SESSION['_last_activity'] > $idleTimeout)) {
            self::destroy();
            self::start($config);
            self::setFlash('warning', 'Your session timed out due to inactivity. Please sign in again.');
            return;
        }

        // Check absolute timeout
        if (isset($_SESSION['_created_at']) && ($now - $_SESSION['_created_at'] > $absoluteTimeout)) {
            self::destroy();
            self::start($config);
            self::setFlash('warning', 'Session expired. Please sign in again.');
            return;
        }
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['_created_at'] = time();
            $_SESSION['_last_activity'] = time();
        }
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function setFlash(string $type, string $message): void
    {
        self::start();
        if (!isset($_SESSION['_flash'])) {
            $_SESSION['_flash'] = [];
        }
        $_SESSION['_flash'][$type][] = $message;
    }

    public static function getFlashes(): array
    {
        self::start();
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flashes;
    }

    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }
            session_destroy();
            self::$started = false;
        }
    }
}
