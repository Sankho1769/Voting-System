<?php
namespace App\Middleware;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\View;

/**
 * Voter Access Gatekeeper Middleware
 * Verifies that the authenticated session holds collegiate voting privileges.
 */
class VoterMiddleware
{
    public static function handle(): void
    {
        AuthMiddleware::handle();

        if (!Auth::isVoter()) {
            Logger::audit(
                'UNAUTHORIZED_VOTER_ACCESS_ATTEMPT',
                'route',
                $_SERVER['REQUEST_URI'] ?? '',
                ['user_id' => Auth::id(), 'user_role' => Auth::role()]
            );

            http_response_code(403);
            View::render('errors/403', [
                'title' => 'Voter Access Required',
                'message' => 'Administrator and non-voter accounts cannot access the collegiate voting portal directly.'
            ]);
            exit;
        }
    }
}
