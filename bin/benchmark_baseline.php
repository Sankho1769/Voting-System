<?php
/**
 * VerityVote - Performance Baseline Benchmark Script
 * Measures latency, percentiles (p50, p95, p99), throughput (RPS), memory, and DB query duration
 * across core application journeys. Repeatable and deterministic.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\ElectionService;
use App\Services\CandidateService;
use App\Services\VotingService;
use App\Services\ResultsService;
use App\Services\AuthService;
use App\Repositories\ElectionRepository;
use App\Repositories\CandidateRepository;
use App\Repositories\EligibilityRepository;
use App\Repositories\VotingRepository;
use App\Repositories\UserRepository;
use App\Repositories\AuditRepository;

$baseUrl = 'http://localhost/Baymax_Voting_System/public';
$cookieFile = tempnam(sys_get_temp_dir(), 'bench_cookie_');

// Helper to make HTTP requests and time them
function measureHttp(string $url, string $method = 'GET', array $postData = [], ?string $cookie = null): array
{
    $start = microtime(true);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    if ($cookie) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }
    $response = curl_exec($ch);
    $durationMs = (microtime(true) - $start) * 1000.0;
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'duration_ms' => $durationMs,
        'status' => $httpCode,
        'success' => ($httpCode >= 200 && $httpCode < 400),
        'error' => $error,
        'body_length' => is_string($response) ? strlen($response) : 0
    ];
}

// Calculate percentiles
function calcStats(array $latencies): array
{
    if (empty($latencies)) {
        return ['avg' => 0, 'p50' => 0, 'p95' => 0, 'p99' => 0, 'min' => 0, 'max' => 0];
    }
    sort($latencies);
    $count = count($latencies);
    $sum = array_sum($latencies);
    $avg = $sum / $count;
    $min = $latencies[0];
    $max = $latencies[$count - 1];

    $p50Index = min($count - 1, (int)floor($count * 0.50));
    $p95Index = min($count - 1, (int)floor($count * 0.95));
    $p99Index = min($count - 1, (int)floor($count * 0.99));

    return [
        'count' => $count,
        'avg' => round($avg, 2),
        'min' => round($min, 2),
        'p50' => round($latencies[$p50Index], 2),
        'p95' => round($latencies[$p95Index], 2),
        'p99' => round($latencies[$p99Index], 2),
        'max' => round($max, 2)
    ];
}

echo "========================================================\n";
echo " VerityVote - Comprehensive Baseline Benchmark\n";
echo " Host: " . php_uname('n') . " (" . PHP_OS . ")\n";
echo " PHP:  " . PHP_VERSION . " | MariaDB 10.4 | Apache 2.4\n";
echo " Time: " . date('Y-m-d H:i:s') . "\n";
echo "========================================================\n\n";

$db = Database::getInstance();
$electionRepo = new ElectionRepository($db);
$candidateRepo = new CandidateRepository($db);
$votingRepo = new VotingRepository($db);
$resultsService = new ResultsService($electionRepo, $votingRepo);

// Find active sample election
$sampleElection = ($electionRepo->getAll(['status' => 'open']))[0] ?? ($electionRepo->getAll())[0] ?? null;
$electionId = $sampleElection['id'] ?? 1;

$iterations = 50;
$benchmarkResults = [];

// Benchmark Journey Definitions
$journeys = [
    'Homepage (/)' => [
        'url' => $baseUrl . '/',
        'method' => 'GET'
    ],
    'Election Discovery (/explore)' => [
        'url' => $baseUrl . '/explore',
        'method' => 'GET'
    ],
    'Election Discovery Filtered (/explore?category=student_council)' => [
        'url' => $baseUrl . '/explore?category=student_council',
        'method' => 'GET'
    ],
    'Election Detail (/election?id=...)' => [
        'url' => $baseUrl . '/election?id=' . $electionId,
        'method' => 'GET'
    ],
    'Candidate Directory (/candidates)' => [
        'url' => $baseUrl . '/candidates',
        'method' => 'GET'
    ],
    'Login Hub (/login)' => [
        'url' => $baseUrl . '/login',
        'method' => 'GET'
    ],
    'Voter Login Portal (/login/voter)' => [
        'url' => $baseUrl . '/login/voter',
        'method' => 'GET'
    ],
    'Admin Login Portal (/login/admin)' => [
        'url' => $baseUrl . '/login/admin',
        'method' => 'GET'
    ]
];

echo "1. Measuring HTTP Application Journeys ({$iterations} iterations each)...\n";
echo str_repeat('-', 75) . "\n";
printf("%-40s | %6s | %6s | %6s | %6s | %6s\n", "Journey", "Avg ms", "p50 ms", "p95 ms", "p99 ms", "RPS");
echo str_repeat('-', 75) . "\n";

foreach ($journeys as $name => $spec) {
    $latencies = [];
    $errors = 0;
    $tStart = microtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $res = measureHttp($spec['url'], $spec['method'], [], $cookieFile);
        $latencies[] = $res['duration_ms'];
        if (!$res['success']) {
            $errors++;
        }
    }

    $tElapsed = microtime(true) - $tStart;
    $rps = round($iterations / max(0.001, $tElapsed), 1);
    $stats = calcStats($latencies);
    $stats['rps'] = $rps;
    $stats['error_rate'] = round(($errors / $iterations) * 100, 1);
    $benchmarkResults[$name] = $stats;

    printf("%-40s | %6.1f | %6.1f | %6.1f | %6.1f | %6.1f\n", $name, $stats['avg'], $stats['p50'], $stats['p95'], $stats['p99'], $rps);
}

// 2. Measure Core Database Query Latencies
echo "\n2. Measuring Core Database Query Latencies (100 iterations each)...\n";
echo str_repeat('-', 75) . "\n";
printf("%-45s | %8s | %8s | %8s\n", "Query Operation", "Avg ms", "p50 ms", "p95 ms");
echo str_repeat('-', 75) . "\n";

$dbQueries = [
    'ElectionRepository::getAll (Full scan/sort)' => function() use ($electionRepo) {
        return $electionRepo->getAll();
    },
    'ElectionRepository::getAll (status: open)' => function() use ($electionRepo) {
        return $electionRepo->getAll(['status' => 'open']);
    },
    'ElectionRepository::getStats (COUNT aggregations)' => function() use ($electionRepo) {
        return $electionRepo->getStats();
    },
    'CandidateRepository::getByElectionId' => function() use ($candidateRepo, $electionId) {
        return $candidateRepo->getByElectionId($electionId);
    },
    'ResultsService::getResults' => function() use ($resultsService, $electionId) {
        return $resultsService->getResults($electionId);
    },
    'AuditRepository::getRecent (Pagination & Sort)' => function() use ($db) {
        return $db->fetchAll("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 50");
    }
];

$dbStatsResults = [];
foreach ($dbQueries as $name => $fn) {
    $latencies = [];
    for ($i = 0; $i < 100; $i++) {
        $qStart = microtime(true);
        $fn();
        $latencies[] = (microtime(true) - $qStart) * 1000.0;
    }
    $stats = calcStats($latencies);
    $dbStatsResults[$name] = $stats;
    printf("%-45s | %8.2f | %8.2f | %8.2f\n", $name, $stats['avg'], $stats['p50'], $stats['p95']);
}

// 3. Measure System Resources
$memPeak = memory_get_peak_usage(true);
$memKb = round($memPeak / 1024, 2);

echo "\nSystem Resource Baseline:\n";
echo "  Peak Memory Usage: {$memKb} KB (" . round($memKb / 1024, 2) . " MB)\n";
echo "  DB Active Connections: 1 (Persistent/Singleton)\n";

// Save results to JSON file for automated reporting and documentation
$outputFile = dirname(__DIR__) . '/storage/logs/benchmark_baseline.json';
file_put_contents($outputFile, json_encode([
    'timestamp' => date('c'),
    'environment' => [
        'php_version' => PHP_VERSION,
        'os' => PHP_OS . ' ' . php_uname('r'),
        'web_server' => 'Apache 2.4.58 (XAMPP)',
        'database' => 'MariaDB 10.4.32'
    ],
    'journeys' => $benchmarkResults,
    'database_queries' => $dbStatsResults,
    'resources' => [
        'peak_memory_kb' => $memKb,
        'connections' => 1
    ]
], JSON_PRETTY_PRINT));

echo "\n[OK] Baseline metrics saved to: {$outputFile}\n";
@unlink($cookieFile);
