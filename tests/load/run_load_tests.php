<?php
/**
 * VerityVote - Complete Load & Concurrency Benchmark Runner
 * Executes all 5 Phase 12 load testing scenarios in disposable test database.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    die("Load test runner must be executed from CLI.\n");
}

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/Support/TestDatabaseManager.php';
require_once dirname(__DIR__) . '/Support/TestFixtureFactory.php';

require_once __DIR__ . '/DiscoveryLoadTest.php';
require_once __DIR__ . '/AuthLoadTest.php';
require_once __DIR__ . '/VotingBurstLoadTest.php';
require_once __DIR__ . '/SameVoterAttackTest.php';
require_once __DIR__ . '/MixedWorkloadLoadTest.php';

use Tests\Support\TestDatabaseManager;
use Tests\Support\TestFixtureFactory;
use Tests\Load\DiscoveryLoadTest;
use Tests\Load\AuthLoadTest;
use Tests\Load\VotingBurstLoadTest;
use Tests\Load\SameVoterAttackTest;
use Tests\Load\MixedWorkloadLoadTest;

echo "========================================================\n";
echo " VerityVote - Phase 12 Load & Concurrency Test Runner\n";
echo " Host: " . php_uname('n') . " | PHP: " . PHP_VERSION . "\n";
echo " Time: " . date('Y-m-d H:i:s') . "\n";
echo "========================================================\n\n";

echo "1. Initializing isolated disposable test database...\n";
$testDb = null;
try {
    $testDb = TestDatabaseManager::setup();
    echo "   [OK] Connected to disposable test database: '" . TestDatabaseManager::TEST_DB_NAME . "'\n";
    TestFixtureFactory::createSuiteFixtures($testDb);
    echo "   [OK] Base fixtures loaded.\n\n";
} catch (\Throwable $e) {
    echo "   [FAIL] Could not setup test database: " . $e->getMessage() . "\n";
    TestDatabaseManager::teardown();
    exit(1);
}

$suites = [
    'Scenario 1: Discovery & Read Subsystem Load' => DiscoveryLoadTest::class,
    'Scenario 2: Authentication & Portal Security Load' => AuthLoadTest::class,
    'Scenario 3: Voting Burst Concurrency Load' => VotingBurstLoadTest::class,
    'Scenario 4: Same-Voter 25 & 100 Concurrency Attack' => SameVoterAttackTest::class,
    'Scenario 5: Mixed Workload (70/20/10) Production Simulation' => MixedWorkloadLoadTest::class
];

$totalPassed = 0;
$totalFailed = 0;
$benchmarkMetrics = [];

try {
    foreach ($suites as $suiteName => $className) {
        echo "--- [ {$suiteName} ] ---\n";
        $tests = $className::run();
        foreach ($tests as $t) {
            if ($t['pass']) {
                $totalPassed++;
                echo "  [PASS] {$t['name']}\n";
            } else {
                $totalFailed++;
                echo "  [FAIL] {$t['name']}\n";
            }
            if (isset($t['metrics'])) {
                $benchmarkMetrics[$t['name']] = $t['metrics'];
            }
        }
        echo "\n";
    }
} finally {
    echo "Tearing down disposable test database...\n";
    TestDatabaseManager::teardown();
    echo "[OK] Teardown complete. Main database is pristine.\n\n";
}

echo "========================================================\n";
echo " Load Testing Summary:\n";
echo " Passed: {$totalPassed} | Failed: {$totalFailed}\n";
echo "========================================================\n";

// Save metrics to storage/logs/load_benchmark_results.json
$metricsFile = dirname(__DIR__, 2) . '/storage/logs/load_benchmark_results.json';
file_put_contents($metricsFile, json_encode([
    'timestamp' => date('c'),
    'passed' => $totalPassed,
    'failed' => $totalFailed,
    'metrics' => $benchmarkMetrics
], JSON_PRETTY_PRINT));

exit($totalFailed === 0 ? 0 : 1);
