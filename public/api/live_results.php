<?php
/**
 * VerityVote - Live Results Polling API Endpoint
 * Enforces authentication and election visibility policies independently of the frontend.
 */

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Session;
use App\Core\View;
use App\Services\ResultsService;

Session::start();

if (!Auth::isLoggedIn() || !Auth::user()) {
    View::json(['error' => 'Authentication required.'], 401);
}

$electionId = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;
if ($electionId <= 0) {
    View::json(['error' => 'Invalid election ID.'], 400);
}

$resultsService = new ResultsService();
$res = $resultsService->getResults($electionId, Auth::user());

View::json($res);
