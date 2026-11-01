<?php
namespace App\Middleware;

use App\Core\CSRF;
use App\Core\Logger;
use App\Core\Session;
use App\Core\View;

/**
 * CSRF Protection Middleware
 * Validates cryptographic tokens for state-modifying requests (POST/PUT/DELETE).
 * Provides seamless recovery on expired login forms to prevent unrecoverable 403 loops.
 */
class CsrfMiddleware
{
    public static function handle(?string $resolvedPath = null): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            return;
        }

        if (CSRF::validateRequest()) {
            return;
        }

        // Validation failed
        $rawUri = $_SERVER['REQUEST_URI'] ?? '';
        $normalizedPath = $resolvedPath ?? self::resolvePath($rawUri);

        Logger::audit('CSRF_VALIDATION_FAILURE', 'request', $rawUri, [
            'method' => $method,
            'path' => $normalizedPath,
            'reason' => 'Invalid or expired CSRF security token'
        ]);

        $isAjaxOrJson = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
            || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        if ($isAjaxOrJson) {
            http_response_code(403);
            $newToken = CSRF::regenerate();
            View::json([
                'error' => 'Your security token was invalid or expired. A fresh security token has been generated. Please try again.',
                'csrf_token' => $newToken,
                'code' => 'CSRF_EXPIRED'
            ], 403);
            exit;
        }

        // Check if the request is for one of the login portals
        $isLoginRoute = in_array($normalizedPath, ['/login', '/login/admin', '/login/candidate', '/login/voter'], true);

        if ($isLoginRoute) {
            // Prevent unrecoverable 403 loop:
            // 1. Generate a fresh token for the session
            // 2. Set an informative flash message
            // 3. Redirect back via clean GET (HTTP 303 See Other) to the login portal form
            CSRF::regenerate();

            Session::setFlash('error', 'Your security token was invalid or expired due to inactivity. A fresh security token has been generated. Please try signing in again.');

            $portal = $_POST['portal'] ?? '';
            $redirectPath = match ($normalizedPath) {
                '/login/admin' => '/login/admin',
                '/login/candidate' => '/login/candidate',
                '/login/voter' => '/login/voter',
                default => match ($portal) {
                    'admin' => '/login/admin',
                    'candidate' => '/login/candidate',
                    'voter' => '/login/voter',
                    default => '/login'
                }
            };

            header('Location: ' . View::url($redirectPath), true, 303);
            exit;
        }

        // For non-login routes (state mutations), reject with 403 and provide recovery links
        http_response_code(403);
        CSRF::regenerate();
        View::render('errors/403', [
            'title' => 'Security Token Expired',
            'message' => 'Your security token was invalid or expired. To protect your account, your request was safely canceled and a fresh security token has been generated.',
            'returnUrl' => $_SERVER['HTTP_REFERER'] ?? View::url('/')
        ]);
        exit;
    }

    private static function resolvePath(string $requestUri): string
    {
        $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($scriptDir !== '/' && $scriptDir !== '.' && strpos($path, $scriptDir) === 0) {
            $path = substr($path, strlen($scriptDir));
        }
        return '/' . trim($path, '/');
    }
}
