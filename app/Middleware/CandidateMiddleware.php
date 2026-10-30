<?php
namespace App\Middleware;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\View;

/**
 * Candidate Access Gatekeeper Middleware
 * Verifies that the authenticated user possesses an authorized candidate profile.
 */
class CandidateMiddleware
{
    public static function handle(): void
    {
        AuthMiddleware::handle();

        if (!Auth::isCandidate()) {
            Logger::audit(
                'UNAUTHORIZED_CANDIDATE_ACCESS_ATTEMPT',
                'route',
                $_SERVER['REQUEST_URI'] ?? '',
                ['user_id' => Auth::id(), 'user_role' => Auth::role()]
            );

            http_response_code(403);
            View::render('errors/403', [
                'title' => 'Candidate Access Required',
                'message' => 'You do not hold an authorized candidate profile for this election cycle. If you believe this is in error, please contact the election commission.'
            ]);
            exit;
        }
    }
}
