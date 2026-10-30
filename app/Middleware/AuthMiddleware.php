<?php
namespace App\Middleware;

use App\Core\Auth;
use App\Core\Session;
use App\Core\View;

/**
 * Authentication Gatekeeper Middleware
 * Enforces session existence, account active status, and mandatory password reset.
 */
class AuthMiddleware
{
    public static function handle(): void
    {
        if (!Auth::isLoggedIn() || !Auth::user()) {
            Session::setFlash('warning', 'Please sign in with your institutional credentials to continue.');
            header('Location: ' . View::url('/login'));
            exit;
        }

        // Check if mandatory password change is required
        $currentUri = $_SERVER['REQUEST_URI'] ?? '';
        $changePasswordUrl = View::url('/change-password');
        $logoutUrl = View::url('/logout');

        if (Auth::mustChangePassword()) {
            // Allow access only to change password and logout
            if (strpos($currentUri, '/change-password') === false && strpos($currentUri, '/logout') === false) {
                Session::setFlash('warning', 'You must change your temporary password before accessing the system.');
                header('Location: ' . $changePasswordUrl);
                exit;
            }
        }
    }
}
