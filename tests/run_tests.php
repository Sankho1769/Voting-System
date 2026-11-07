<?php
/**
 * VerityVote - Complete Automated Test Suite Runner
 * 
 * Executes all unit, integration, role boundary, HTTP journey, CSRF lifecycle,
 * concurrency, and backup-recovery test suites inside an isolated disposable test
 * database ('verityvote_test_suite'). Strictly safeguards the primary database ('verityvote_db').
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    die("Test runner must be executed from CLI.\n");
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

require_once __DIR__ . '/Support/TestDatabaseManager.php';
require_once __DIR__ . '/Support/TestFixtureFactory.php';

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
require_once __DIR__ . '/Integration/FailureResilienceTest.php';
require_once __DIR__ . '/Integration/ApacheLoggingSecurityTest.php';

use Tests\Support\TestDatabaseManager;
use Tests\Support\TestFixtureFactory;
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
use Tests\Integration\FailureResilienceTest;
use Tests\Integration\ApacheLoggingSecurityTest;

echo "========================================================\n";
echo " VerityVote - Complete Automated Test Suite\n";
echo "========================================================\n\n";

echo "1. Initializing isolated disposable test database...\n";

$testDb = null;
try {
    $testDb = TestDatabaseManager::setup();
    echo "   [OK] Connected to disposable test database: '" . TestDatabaseManager::TEST_DB_NAME . "'\n";
    echo "   [OK] Schema and all versioned migrations applied.\n";
    
    // Provision reusable dynamic fixtures
    $fixtures = TestFixtureFactory::createSuiteFixtures($testDb);
    echo "   [OK] Isolated test fixtures provisioned (Visible ID: {$fixtures['visible_id']})\n\n";
} catch (\Throwable $setupErr) {
    echo "   [CRITICAL SETUP FAILURE] " . $setupErr->getMessage() . "\n";
    TestDatabaseManager::teardown();
    exit(1);
}

$suites = [
    'Helper & Security Unit Tests' => HelperTest::class,
    'Authentication & Session Integration Tests' => AuthIntegrationTest::class,
    'Election Lifecycle & Lock Tests' => ElectionLifecycleTest::class,
    'Voting Engine & Concurrency Tests' => VotingConcurrencyTest::class,
    'Three Portals & Role Boundary Integration Tests' => ThreePortalsAuthTest::class,
    'Three Test Accounts Authentication Tests' => TestAccountsAuthTest::class,
    'HTTP & Browser Real-World Journey Tests' => HttpJourneyTest::class,
    'CSRF & Portal Session Lifecycle Tests' => CsrfPortalLifecycleTest::class,
    'MasterSecurityTest (Phase 9)' => MasterSecurityTest::class,
    'Database Backup & Recovery Tests (Phase 10)' => BackupRestoreTest::class,
    'Failure Resilience & Fault Recovery Tests (Phase 12)' => FailureResilienceTest::class,
    'Apache Logging & Credential Leakage Prevention Tests (Phase 13)' => ApacheLoggingSecurityTest::class,
];

$totalPassed = 0;
$totalFailed = 0;
$suiteCounts = [];

try {
    foreach ($suites as $suiteName => $className) {
        echo "--- [ {$suiteName} ] ---\n";
        $suitePassed = 0;
        $suiteFailed = 0;

        try {
            $suiteResults = $className::run();
            foreach ($suiteResults as $test) {
                if ($test['pass']) {
                    $totalPassed++;
                    $suitePassed++;
                    echo "  [PASS] {$test['name']}\n";
                } else {
                    $totalFailed++;
                    $suiteFailed++;
                    echo "  [FAIL] {$test['name']}\n";
                }
            }
        } catch (\Throwable $e) {
            $totalFailed++;
            $suiteFailed++;
            echo "  [EXCEPTION] {$e->getMessage()}\n";
            echo "  Trace: " . $e->getTraceAsString() . "\n";
        }
        $suiteCounts[$suiteName] = ['passed' => $suitePassed, 'failed' => $suiteFailed];
        echo "\n";
    }
} finally {
    echo "========================================================\n";
    echo "Tearing down disposable test database...\n";
    TestDatabaseManager::teardown();
    echo "[OK] Disposable database '" . TestDatabaseManager::TEST_DB_NAME . "' dropped cleanly.\n";

    // Verify primary database
    $primaryCheck = TestDatabaseManager::verifyPrimaryDatabase();
    if ($primaryCheck['healthy']) {
        echo "[OK] " . $primaryCheck['message'] . "\n";
    } else {
        echo "[WARNING] Primary database check: " . $primaryCheck['message'] . "\n";
    }
    echo "========================================================\n\n";
}

echo "========================================================\n";
echo "Suite Summary:\n";
foreach ($suiteCounts as $name => $c) {
    printf("  %-50s : %3d Passed | %2d Failed\n", $name, $c['passed'], $c['failed']);
}
echo "--------------------------------------------------------\n";
echo "Test Execution Summary: {$totalPassed} Passed | {$totalFailed} Failed | 0 Skipped\n";
echo "========================================================\n";

exit($totalFailed > 0 ? 1 : 0);
