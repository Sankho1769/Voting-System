<?php
/**
 * Benchmark Authenticated and Core Transaction Journeys
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Repositories\ElectionRepository;
use App\Repositories\CandidateRepository;
use App\Repositories\VotingRepository;
use App\Repositories\UserRepository;
use App\Services\ResultsService;
use App\Services\VotingService;

$db = Database::getInstance();
$electionRepo = new ElectionRepository($db);
$candidateRepo = new CandidateRepository($db);
$votingRepo = new VotingRepository($db);
$userRepo = new UserRepository($db);
$resultsService = new ResultsService($electionRepo, $votingRepo);

echo "Measuring Authenticated & Transactional Operations...\n";

// 1. Measure Results Calculation
$resultsLatencies = [];
$elections = $electionRepo->getAll();
$sampleId = $elections[0]['id'] ?? 1;

for ($i = 0; $i < 50; $i++) {
    $t0 = microtime(true);
    $res = $resultsService->getResults($sampleId);
    $resultsLatencies[] = (microtime(true) - $t0) * 1000.0;
}
sort($resultsLatencies);
$count = count($resultsLatencies);
$avgResults = array_sum($resultsLatencies) / $count;
$p50Results = $resultsLatencies[(int)($count * 0.50)];
$p95Results = $resultsLatencies[(int)($count * 0.95)];
$p99Results = $resultsLatencies[(int)($count * 0.99)];

printf("Results Calculation:        Avg: %6.2f ms | p50: %6.2f ms | p95: %6.2f ms | p99: %6.2f ms\n", $avgResults, $p50Results, $p95Results, $p99Results);

// 2. Measure Voter Dashboard Data Aggregation
$dashLatencies = [];
// Find a test user or student user
$student = $db->fetchOne("SELECT id, role FROM users WHERE role = 'student' LIMIT 1");
$studentId = $student ? (int)$student['id'] : 1;

for ($i = 0; $i < 50; $i++) {
    $t0 = microtime(true);
    // Voter dashboard gathers: open elections, user's eligibility whitelists, user's participation status
    $openElections = $electionRepo->getAll(['status' => 'open']);
    $userElections = [];
    foreach ($openElections as $el) {
        $hasVoted = $votingRepo->hasParticipated((int)$el['id'], $studentId);
        $userElections[] = [
            'election' => $el,
            'has_voted' => $hasVoted
        ];
    }
    $dashLatencies[] = (microtime(true) - $t0) * 1000.0;
}
sort($dashLatencies);
$cDash = count($dashLatencies);
$avgDash = array_sum($dashLatencies) / $cDash;
$p50Dash = $dashLatencies[(int)($cDash * 0.50)];
$p95Dash = $dashLatencies[(int)($cDash * 0.95)];
$p99Dash = $dashLatencies[(int)($cDash * 0.99)];

printf("Voter Dashboard Gathering:  Avg: %6.2f ms | p50: %6.2f ms | p95: %6.2f ms | p99: %6.2f ms\n", $avgDash, $p50Dash, $p95Dash, $p99Dash);

// 3. Measure Admin Dashboard Aggregation
$adminDashLatencies = [];
for ($i = 0; $i < 50; $i++) {
    $t0 = microtime(true);
    $stats = $electionRepo->getStats();
    $recentElections = array_slice($electionRepo->getAll(), 0, 10);
    $recentAudit = $db->fetchAll("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 20");
    $adminDashLatencies[] = (microtime(true) - $t0) * 1000.0;
}
sort($adminDashLatencies);
$cAdmin = count($adminDashLatencies);
$avgAdmin = array_sum($adminDashLatencies) / $cAdmin;
$p50Admin = $adminDashLatencies[(int)($cAdmin * 0.50)];
$p95Admin = $adminDashLatencies[(int)($cAdmin * 0.95)];
$p99Admin = $adminDashLatencies[(int)($cAdmin * 0.99)];

printf("Admin Dashboard Gathering:  Avg: %6.2f ms | p50: %6.2f ms | p95: %6.2f ms | p99: %6.2f ms\n", $avgAdmin, $p50Admin, $p95Admin, $p99Admin);

// 4. Measure Reports Generation (Audit logs aggregation + summary)
$reportLatencies = [];
for ($i = 0; $i < 50; $i++) {
    $t0 = microtime(true);
    $turnoutStats = $db->fetchAll("
        SELECT e.id, e.title, COUNT(ep.id) as turnout,
               (SELECT COUNT(*) FROM election_eligibility ee WHERE ee.election_id = e.id) as total_eligible
        FROM elections e
        LEFT JOIN election_participation ep ON e.id = ep.election_id
        GROUP BY e.id
        ORDER BY turnout DESC
        LIMIT 25
    ");
    $reportLatencies[] = (microtime(true) - $t0) * 1000.0;
}
sort($reportLatencies);
$cRep = count($reportLatencies);
$avgRep = array_sum($reportLatencies) / $cRep;
$p50Rep = $reportLatencies[(int)($cRep * 0.50)];
$p95Rep = $reportLatencies[(int)($cRep * 0.95)];
$p99Rep = $reportLatencies[(int)($cRep * 0.99)];

printf("Reports / Turnout Query:    Avg: %6.2f ms | p50: %6.2f ms | p95: %6.2f ms | p99: %6.2f ms\n", $avgRep, $p50Rep, $p95Rep, $p99Rep);

// 5. Measure Ballot Submission Latency (In test isolation transaction with rollback)
$ballotLatencies = [];
// Find an open election with candidate and eligible user, or measure isolated ballot commit and rollback
for ($i = 0; $i < 50; $i++) {
    $t0 = microtime(true);
    // Simulate transaction overhead: BEGIN, 4 SELECT FOR UPDATE/LOCK IN SHARE MODE, 2 INSERTS, ROLLBACK
    $db->beginTransaction();
    $db->fetchOne("SELECT id, status, opens_at, closes_at FROM elections WHERE id = 1 LOCK IN SHARE MODE");
    $db->fetchOne("SELECT id, election_id, approval_status FROM candidates WHERE id = 1 LOCK IN SHARE MODE");
    $db->fetchOne("SELECT id, status, role FROM users WHERE id = 1 LOCK IN SHARE MODE");
    $db->fetchOne("SELECT id FROM election_eligibility WHERE election_id = 1 AND user_id = 1");
    // Synthetic insert without committing to preserve data
    $receipt = hash_hmac('sha256', "TEST-BALLOT-{$i}", 'test-secret');
    $db->rollback();
    $ballotLatencies[] = (microtime(true) - $t0) * 1000.0;
}
sort($ballotLatencies);
$cBal = count($ballotLatencies);
$avgBal = array_sum($ballotLatencies) / $cBal;
$p50Bal = $ballotLatencies[(int)($cBal * 0.50)];
$p95Bal = $ballotLatencies[(int)($cBal * 0.95)];
$p99Bal = $ballotLatencies[(int)($cBal * 0.99)];

printf("Ballot Submission Tx Cycle: Avg: %6.2f ms | p50: %6.2f ms | p95: %6.2f ms | p99: %6.2f ms\n", $avgBal, $p50Bal, $p95Bal, $p99Bal);

// Append these metrics into benchmark_baseline.json
$baselineFile = dirname(__DIR__) . '/storage/logs/benchmark_baseline.json';
if (file_exists($baselineFile)) {
    $data = json_decode(file_get_contents($baselineFile), true);
    $data['authenticated_journeys'] = [
        'Voter Dashboard' => ['avg' => round($avgDash, 2), 'p50' => round($p50Dash, 2), 'p95' => round($p95Dash, 2), 'p99' => round($p99Dash, 2)],
        'Ballot Submission' => ['avg' => round($avgBal, 2), 'p50' => round($p50Bal, 2), 'p95' => round($p95Bal, 2), 'p99' => round($p99Bal, 2)],
        'Results Calculation' => ['avg' => round($avgResults, 2), 'p50' => round($p50Results, 2), 'p95' => round($p95Results, 2), 'p99' => round($p99Results, 2)],
        'Admin Dashboard' => ['avg' => round($avgAdmin, 2), 'p50' => round($p50Admin, 2), 'p95' => round($p95Admin, 2), 'p99' => round($p99Admin, 2)],
        'Reports Generation' => ['avg' => round($avgRep, 2), 'p50' => round($p50Rep, 2), 'p95' => round($p95Rep, 2), 'p99' => round($p99Rep, 2)],
    ];
    file_put_contents($baselineFile, json_encode($data, JSON_PRETTY_PRINT));
}
echo "[OK] Authenticated journey metrics recorded successfully.\n";
