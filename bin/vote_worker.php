<?php
/**
 * VerityVote - Concurrency Worker CLI Tool
 * Used exclusively for stress and concurrency verification testing.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Access denied.\n");
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Repositories\VotingRepository;

if ($argc < 4) {
    echo json_encode(['status' => 'error', 'message' => 'Missing arguments: election_id, candidate_id, user_id required']);
    exit(1);
}

$electionId = (int)$argv[1];
$candidateId = (int)$argv[2];
$userId = (int)$argv[3];

$configFile = dirname(__DIR__) . '/config/config.php';
if (!file_exists($configFile)) {
    $configFile = dirname(__DIR__) . '/config/config.example.php';
}
$config = require $configFile;
$secret = $config['security']['encryption_key'] ?? 'verityvote_hmac_secret_key';

$db = Database::createConnection();
$repo = new VotingRepository($db);

try {
    $receipt = $repo->recordBallot($electionId, $candidateId, $userId, $secret);
    echo json_encode(['status' => 'success', 'receipt' => $receipt]);
    exit(0);
} catch (\Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit(1);
}
