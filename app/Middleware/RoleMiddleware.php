<?php
namespace App\Middleware;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\View;

/**
 * Role-Based Access Control (RBAC) Middleware
 * Verifies the authenticated user holds the required institutional role.
 */
class RoleMiddleware
{
    public static function handle(string ...$allowedRoles): void
    {
        AuthMiddleware::handle();

        if (!Auth::hasRole(...$allowedRoles)) {
            Logger::audit(
                'UNAUTHORIZED_ACCESS_ATTEMPT',
                'route',
                $_SERVER['REQUEST_URI'] ?? '',
                ['required_roles' => $allowedRoles, 'user_role' => Auth::role()]
            );

            http_response_code(403);
            View::render('errors/403', [
                'title' => 'Access Forbidden',
                'message' => 'You do not hold the required authorization for this section.'
            ]);
            exit;
        }
    }
}
