<?php
namespace App\Middleware;

use App\Core\CSRF;
use App\Core\Logger;
use App\Core\View;

/**
 * CSRF Protection Middleware
 * Validates cryptographic tokens for state-modifying requests (POST/PUT/DELETE).
 */
class CsrfMiddleware
{
    public static function handle(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            if (!CSRF::validateRequest()) {
                Logger::audit('CSRF_VALIDATION_FAILURE', 'request', $_SERVER['REQUEST_URI'] ?? '', [
                    'method' => $method
                ]);

                http_response_code(403);
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)) {
                    View::json(['error' => 'Security token invalid or expired. Please refresh the page and try again.'], 403);
                } else {
                    View::render('errors/403', [
                        'title' => 'Security Token Expired',
                        'message' => 'Your security token was invalid or expired. Please return to the previous page, refresh, and submit again.'
                    ]);
                }
                exit;
            }
        }
    }
}
