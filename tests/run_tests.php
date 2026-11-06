<?php
/**
 * VerityVote - Automated Test Suite Runner
 */

if (PHP_SAPI !== 'cli') {
    die("Test runner must be executed from CLI.\n");
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

require_once __DIR__ . '/Unit/HelperTest.php';
require_once __DIR__ . '/Integration/AuthIntegrationTest.php';
require_once __DIR__ . '/Integration/ElectionLifecycleTest.php';
require_once __DIR__ . '/Integration/VotingConcurrencyTest.php';
require_once __DIR__ . '/Integration/ThreePortalsAuthTest.php';
require_once __DIR__ . '/Integration/TestAccountsAuthTest.php';
require_once __DIR__ . '/Integration/HttpJourneyTest.php';
require_once __DIR__ . '/Integration/CsrfPortalLifecycleTest.php';
require_once __DIR__ . '/Integration/MasterSecurityTest.php';
require_once __DIR__ . '/Integration/BackupRestoreTest.php';

use Tests\Unit\HelperTest;
use Tests\Integration\AuthIntegrationTest;
use Tests\Integration\ElectionLifecycleTest;
use Tests\Integration\VotingConcurrencyTest;
use Tests\Integration\ThreePortalsAuthTest;
use Tests\Integration\TestAccountsAuthTest;
use Tests\Integration\HttpJourneyTest;
use Tests\Integration\CsrfPortalLifecycleTest;
use Tests\Integration\MasterSecurityTest;
use Tests\Integration\BackupRestoreTest;

echo "========================================================\n";
echo " VerityVote - Complete Automated Test Suite\n";
echo "========================================================\n\n";

$suites = [
    'Helper & Security Unit Tests' => HelperTest::class,
    'Authentication & Session Integration Tests' => AuthIntegrationTest::class,
    'Election Lifecycle & Lock Tests' => ElectionLifecycleTest::class,
    'Voting Engine & Concurrency Tests' => VotingConcurrencyTest::class,
    'Three Portals & Role Boundary Integration Tests' => ThreePortalsAuthTest::class,
    'Three Test Accounts (Shubham) Authentication Tests' => TestAccountsAuthTest::class,
    'HTTP & Browser Real-World Journey Tests' => HttpJourneyTest::class,
    'CSRF & Portal Session Lifecycle Tests' => CsrfPortalLifecycleTest::class,
    'Master Security Hardening & Concurrency Tests (Phase 9)' => MasterSecurityTest::class,
    'Database Backup & Recovery Tests (Phase 10)' => BackupRestoreTest::class,
];

$totalPassed = 0;
$totalFailed = 0;

foreach ($suites as $suiteName => $className) {
    echo "--- [ {$suiteName} ] ---\n";
    try {
        $suiteResults = $className::run();
        foreach ($suiteResults as $test) {
            if ($test['pass']) {
                $totalPassed++;
                echo "  [PASS] {$test['name']}\n";
            } else {
                $totalFailed++;
                echo "  [FAIL] {$test['name']}\n";
            }
        }
    } catch (\Throwable $e) {
        $totalFailed++;
        echo "  [EXCEPTION] {$e->getMessage()}\n";
        echo "  Trace: " . $e->getTraceAsString() . "\n";
    }
    echo "\n";
}

echo "========================================================\n";
echo "Test Execution Summary: {$totalPassed} Passed | {$totalFailed} Failed\n";
echo "========================================================\n";

exit($totalFailed > 0 ? 1 : 0);
