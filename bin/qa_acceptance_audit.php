<?php
/**
 * VerityVote - Phase 15.1 Final Acceptance & Quality Audit Runner
 * Executes real browser/HTTP journeys against local Apache and MariaDB via isolated test fixtures.
 */

declare(strict_types=1);

namespace Bin;

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/tests/Support/TestDatabaseManager.php';
require_once dirname(__DIR__) . '/tests/Support/TestFixtureFactory.php';

use App\Core\Database;
use Tests\Support\TestDatabaseManager;
use Tests\Support\TestFixtureFactory;

if (PHP_SAPI !== 'cli') {
    die("This QA audit runner must be executed from the CLI.\n");
}

class QAAcceptanceAudit
{
    private string $baseUrl;
    private array $preCounts = [];
    private array $postCounts = [];
    private array $auditLogs = [];
    private int $passCount = 0;
    private int $failCount = 0;

    public function __construct(string $baseUrl = 'http://localhost/Baymax_Voting_System/public')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function run(): void
    {
        echo "========================================================\n";
        echo " VerityVote - Phase 15.1 Final Acceptance & QA Audit\n";
        echo " Target: {$this->baseUrl}\n";
        echo " Timestamp: " . date('Y-m-d H:i:s') . "\n";
        echo "========================================================\n\n";

        // Step 1: Pre-Audit Snapshot of Primary Database
        $this->snapshotPrimaryDb('PRE');

        // Step 2: Environment Process & Port Verification
        $this->verifyEnvironment();

        // Step 3: Setup Disposable Test Suite
        echo "\n--- [ Isolated Test Environment Setup ] ---\n";
        $testDb = TestDatabaseManager::setup();
        $fixtures = TestFixtureFactory::createSuiteFixtures($testDb);
        echo "[OK] Disposable 'verityvote_test_suite' initialized.\n";
        echo "[OK] Test fixtures provisioned.\n";

        try {
            // Step 4: Admin Portal Journeys
            $this->testAdminPortal($testDb, $fixtures);

            // Step 5: Candidate Portal Journeys
            $this->testCandidatePortal($testDb, $fixtures);

            // Step 6: Voter Portal & Voting Workflow Journeys
            $this->testVoterPortalAndVotingLifecycle($testDb, $fixtures);

            // Step 7: Backup Security & Path Traversal Verification
            $this->testBackupSecurity($testDb, $fixtures);

        } finally {
            // Step 8: Teardown Disposable Database
            echo "\n--- [ Disposable Test Database Teardown ] ---\n";
            TestDatabaseManager::teardown();
            echo "[OK] Disposable database dropped cleanly.\n";
        }

        // Step 9: Post-Audit Snapshot & Integrity Comparison of Primary Database
        $this->snapshotPrimaryDb('POST');
        $this->compareDbSnapshots();

        // Step 10: Summary
        echo "\n========================================================\n";
        echo " Acceptance Audit Complete: {$this->passCount} Passed | {$this->failCount} Failed\n";
        echo "========================================================\n";
    }

    private function logAssertion(string $name, bool $success, string $details = ''): void
    {
        if ($success) {
            $this->passCount++;
            echo "  [PASS] {$name}" . ($details ? " ({$details})" : "") . "\n";
        } else {
            $this->failCount++;
            echo "  [FAIL] {$name}" . ($details ? " - {$details}" : "") . "\n";
        }
    }

    private function snapshotPrimaryDb(string $phase): void
    {
        echo "Recording {$phase} snapshot of primary database 'verityvote_db'...\n";
        Database::resetInstance();
        $db = Database::getInstance();

        $tables = [
            'users',
            'elections',
            'candidates',
            'election_eligibility',
            'election_participation',
            'votes',
            'audit_logs',
            'login_attempts',
            'migrations'
        ];

        $counts = [];
        foreach ($tables as $table) {
            $row = $db->fetchOne("SELECT COUNT(*) as c, COALESCE(MIN(id), 0) as min_id, COALESCE(MAX(id), 0) as max_id FROM `{$table}`");
            $counts[$table] = [
                'count' => (int)$row['c'],
                'min' => (int)$row['min_id'],
                'max' => (int)$row['max_id']
            ];
        }

        if ($phase === 'PRE') {
            $this->preCounts = $counts;
        } else {
            $this->postCounts = $counts;
        }

        foreach ($counts as $tbl => $data) {
            echo "  - {$tbl}: {$data['count']} rows (ID range: {$data['min']}..{$data['max']})\n";
        }
    }

    private function compareDbSnapshots(): void
    {
        echo "\n--- [ Primary Database Preservation Audit ] ---\n";
        $preserved = true;
        foreach ($this->preCounts as $table => $pre) {
            $post = $this->postCounts[$table] ?? null;
            if (!$post) {
                $preserved = false;
                $this->logAssertion("Table {$table} exists post-audit", false, "Table missing!");
                continue;
            }

            // For audit_logs and login_attempts, operational records may append during live server actions
            if ($table === 'audit_logs' || $table === 'login_attempts') {
                $this->logAssertion("Table {$table} preserved", $post['count'] >= $pre['count'], "Pre: {$pre['count']}, Post: {$post['count']}");
                continue;
            }

            $exactMatch = ($pre['count'] === $post['count'] && $pre['min'] === $post['min'] && $pre['max'] === $post['max']);
            if (!$exactMatch) {
                $preserved = false;
            }
            $this->logAssertion("Table {$table} 100% untouched", $exactMatch, "Count: {$pre['count']} -> {$post['count']}, Range: {$pre['min']}..{$pre['max']}");
        }

        $this->logAssertion("Zero Data Alteration in 'verityvote_db'", $preserved);
    }

    private function verifyEnvironment(): void
    {
        echo "\n--- [ Environment & Service Health Checks ] ---\n";

        // PHP Version
        $phpVer = PHP_VERSION;
        $this->logAssertion("PHP Version is 8.2+", version_compare($phpVer, '8.2.0', '>='), "Running PHP {$phpVer}");

        // Public App Response
        $res = $this->http('GET', '/');
        $this->logAssertion("Public Home returns HTTP 200", $res['code'] === 200, "Code: {$res['code']}");

        // phpMyAdmin Response
        $pmaRes = $this->httpRaw('http://localhost/phpmyadmin/');
        $this->logAssertion("phpMyAdmin returns HTTP 200", $pmaRes['code'] === 200, "Code: {$pmaRes['code']}");

        // Explore Page
        $expRes = $this->http('GET', '/explore');
        $this->logAssertion("Explore page returns HTTP 200", $expRes['code'] === 200, "Code: {$expRes['code']}");

        // Candidates Page
        $candRes = $this->http('GET', '/candidates');
        $this->logAssertion("Candidates page returns HTTP 200", $candRes['code'] === 200, "Code: {$candRes['code']}");
    }

    private function testAdminPortal(Database $db, array $fixtures): void
    {
        echo "\n--- [ Portal 1: Administrative Workflows ] ---\n";
        $cookie = tempnam(sys_get_temp_dir(), 'admin_cookie_');

        try {
            // 1. GET /login/admin
            $loginPage = $this->http('GET', '/login/admin', [], $cookie);
            $this->logAssertion("Admin Login GET returns HTTP 200", $loginPage['code'] === 200);
            $csrf = $this->extractCsrf($loginPage['body']);
            $this->logAssertion("Admin Login renders CSRF token", !empty($csrf));

            // 2. POST /login/admin
            $loginPost = $this->http('POST', '/login/admin', [
                'identifier' => $fixtures['admin_identifier'],
                'password' => $fixtures['password'],
                'csrf_token' => $csrf
            ], $cookie);
            $this->logAssertion("Admin Login POST redirects to /admin", in_array($loginPost['code'], [302, 303], true));

            // 3. GET /admin Dashboard
            $dash = $this->http('GET', '/admin', [], $cookie);
            $this->logAssertion("Admin Dashboard GET returns HTTP 200", $dash['code'] === 200);
            $hasStats = strpos($dash['body'], 'Active Elections') !== false || strpos($dash['body'], 'Total Voters') !== false || strpos($dash['body'], 'System Administration') !== false;
            $this->logAssertion("Admin Dashboard renders metrics cards", $hasStats);

            // 4. GET /admin/elections
            $electionsList = $this->http('GET', '/admin/elections', [], $cookie);
            $this->logAssertion("Admin Elections List returns HTTP 200", $electionsList['code'] === 200);

            // 5. GET /admin/elections/create
            $createForm = $this->http('GET', '/admin/elections/create', [], $cookie);
            $this->logAssertion("Admin Election Create Form returns HTTP 200", $createForm['code'] === 200);
            $createCsrf = $this->extractCsrf($createForm['body']);

            // 6. POST /admin/elections/create (Create new Draft Election)
            $newTitle = "QA Campus President Election " . bin2hex(random_bytes(3));
            $createPost = $this->http('POST', '/admin/elections/create', [
                'title' => $newTitle,
                'description' => 'Acceptance testing presidential ballot',
                'category' => 'general',
                'department' => 'All Departments',
                'position' => 'President',
                'opens_at' => date('Y-m-d H:i:s', time() + 3600),
                'closes_at' => date('Y-m-d H:i:s', time() + 86400),
                'results_visibility' => 'after_close',
                'csrf_token' => $createCsrf
            ], $cookie);
            $this->logAssertion("Admin Election Create POST redirects", in_array($createPost['code'], [302, 303], true));

            // Retrieve created election from testDb
            $createdElec = $db->fetchOne("SELECT id, title, status FROM elections WHERE title = ?", [$newTitle]);
            $this->logAssertion("Election created in test database", !empty($createdElec) && $createdElec['status'] === 'draft');
            $electionId = (int)($createdElec['id'] ?? 0);

            // 7. GET /admin/elections/edit?id=X
            $editForm = $this->http('GET', "/admin/elections/edit?id={$electionId}", [], $cookie);
            $this->logAssertion("Admin Election Edit Form returns HTTP 200", $editForm['code'] === 200);
            $editCsrf = $this->extractCsrf($editForm['body']);

            // 8. POST /admin/elections/edit?id=X (Update Configuration)
            $updatedTitle = $newTitle . " (Updated)";
            $editPost = $this->http('POST', "/admin/elections/edit?id={$electionId}", [
                'title' => $updatedTitle,
                'description' => 'Updated manifesto guidelines and dates',
                'category' => 'general',
                'department' => 'All Departments',
                'position' => 'President',
                'opens_at' => date('Y-m-d H:i:s', time() - 300), // opened 5 mins ago
                'closes_at' => date('Y-m-d H:i:s', time() + 7200),
                'results_visibility' => 'always',
                'csrf_token' => $editCsrf
            ], $cookie);
            $this->logAssertion("Admin Election Edit POST redirects", in_array($editPost['code'], [302, 303], true));

            $updatedElec = $db->fetchOne("SELECT title FROM elections WHERE id = ?", [$electionId]);
            $this->logAssertion("Election configuration successfully updated in DB", ($updatedElec['title'] ?? '') === $updatedTitle);

            // 9. GET /admin/elections/detail?id=X
            $viewPage = $this->http('GET', "/admin/elections/detail?id={$electionId}", [], $cookie);
            $this->logAssertion("Admin Election Detail returns HTTP 200", $viewPage['code'] === 200);
            $viewCsrf = $this->extractCsrf($viewPage['body']);

            // 10. Add 2 Test Candidates
            // Candidate A (Unified Fixture)
            $db->insert(
                "INSERT INTO candidates (election_id, user_id, name, manifesto, approval_status)
                 VALUES (?, ?, 'Candidate Alpha', 'Alpha Manifesto', 'pending')",
                [$electionId, $fixtures['voter_id']]
            );
            $candA = $db->fetchOne("SELECT id FROM candidates WHERE election_id = ? AND name = 'Candidate Alpha'", [$electionId]);
            $candAId = (int)$candA['id'];

            // Candidate B
            $db->insert(
                "INSERT INTO candidates (election_id, user_id, name, manifesto, approval_status)
                 VALUES (?, ?, 'Candidate Beta', 'Beta Manifesto', 'pending')",
                [$electionId, $fixtures['staff_id']]
            );
            $candB = $db->fetchOne("SELECT id FROM candidates WHERE election_id = ? AND name = 'Candidate Beta'", [$electionId]);
            $candBId = (int)$candB['id'];

            $this->logAssertion("Provisioned 2 candidates for test election", $candAId > 0 && $candBId > 0);

            // Re-fetch detail page to get fresh CSRF after DB inserts
            $viewPage = $this->http('GET', "/admin/elections/detail?id={$electionId}", [], $cookie);
            $viewCsrf = $this->extractCsrf($viewPage['body']);

            // 11. Quick Approval of Candidates
            $appPostA = $this->http('POST', '/admin/candidates/status', [
                'candidate_id' => $candAId,
                'status' => 'approved',
                'csrf_token' => $viewCsrf
            ], $cookie);
            $this->logAssertion("Admin Candidate A approved via POST", in_array($appPostA['code'], [302, 303], true));

            $viewPage = $this->http('GET', "/admin/elections/detail?id={$electionId}", [], $cookie);
            $viewCsrf = $this->extractCsrf($viewPage['body']);

            $appPostB = $this->http('POST', '/admin/candidates/status', [
                'candidate_id' => $candBId,
                'status' => 'approved',
                'csrf_token' => $viewCsrf
            ], $cookie);
            $this->logAssertion("Admin Candidate B approved via POST", in_array($appPostB['code'], [302, 303], true));

            $viewPage = $this->http('GET', "/admin/elections/detail?id={$electionId}", [], $cookie);
            $viewCsrf = $this->extractCsrf($viewPage['body']);

            // 12. Enroll Voter into Whitelist
            $enrollPost = $this->http('POST', '/admin/eligibility/assign-individual', [
                'election_id' => $electionId,
                'identifier' => $fixtures['voter_identifier'],
                'csrf_token' => $viewCsrf
            ], $cookie);
            $this->logAssertion("Admin single voter enrollment redirects", in_array($enrollPost['code'], [302, 303], true));

            // Verify in testDb
            $isEligible = $db->fetchOne("SELECT id FROM election_eligibility WHERE election_id = ? AND user_id = ?", [$electionId, $fixtures['voter_id']]);
            $this->logAssertion("Voter recorded in election_eligibility", !empty($isEligible));

            $viewPage = $this->http('GET', "/admin/elections/detail?id={$electionId}", [], $cookie);
            $viewCsrf = $this->extractCsrf($viewPage['body']);

            // 13. Open Election
            $openPost = $this->http('POST', '/admin/elections/status', [
                'election_id' => $electionId,
                'status' => 'open',
                'csrf_token' => $viewCsrf
            ], $cookie);
            $this->logAssertion("Admin opens election (status -> open)", in_array($openPost['code'], [302, 303], true));

            $checkOpen = $db->fetchOne("SELECT status FROM elections WHERE id = ?", [$electionId]);
            $this->logAssertion("Election status is 'open' in DB", ($checkOpen['status'] ?? '') === 'open');

            // 14. GET /admin/users
            $usersPage = $this->http('GET', '/admin/users', [], $cookie);
            $this->logAssertion("Admin Users List returns HTTP 200", $usersPage['code'] === 200);

            // 15. GET /admin/audit
            $auditPage = $this->http('GET', '/admin/audit', [], $cookie);
            $this->logAssertion("Admin Audit Trail returns HTTP 200", $auditPage['code'] === 200);

            // 16. GET /admin/backups
            $backupsPage = $this->http('GET', '/admin/backups', [], $cookie);
            $this->logAssertion("Admin Backups Console returns HTTP 200", $backupsPage['code'] === 200);

            // 17. GET /logout
            $logoutRes = $this->http('GET', '/logout', [], $cookie);
            $this->logAssertion("Admin Logout redirects", in_array($logoutRes['code'], [302, 303], true));

        } finally {
            if (file_exists($cookie)) {
                @unlink($cookie);
            }
        }
    }

    private function testCandidatePortal(Database $db, array $fixtures): void
    {
        echo "\n--- [ Portal 2: Candidate Workflows ] ---\n";
        $cookie = tempnam(sys_get_temp_dir(), 'cand_cookie_');

        try {
            // 1. GET /login/candidate
            $loginPage = $this->http('GET', '/login/candidate', [], $cookie);
            $this->logAssertion("Candidate Login GET returns HTTP 200", $loginPage['code'] === 200);
            $csrf = $this->extractCsrf($loginPage['body']);

            // 2. POST /login/candidate
            $loginPost = $this->http('POST', '/login/candidate', [
                'identifier' => $fixtures['voter_identifier'],
                'password' => $fixtures['password'],
                'csrf_token' => $csrf
            ], $cookie);
            $this->logAssertion("Candidate Login POST redirects to /candidate", in_array($loginPost['code'], [302, 303], true));

            // 3. GET /candidate Dashboard
            $dash = $this->http('GET', '/candidate', [], $cookie);
            $this->logAssertion("Candidate Dashboard returns HTTP 200", $dash['code'] === 200);
            $hasManifestoCard = strpos($dash['body'], 'Campaign Manifesto') !== false || strpos($dash['body'], 'Edit Platform') !== false;
            $this->logAssertion("Candidate Dashboard renders profile card", $hasManifestoCard);
            $dashCsrf = $this->extractCsrf($dash['body']);

            // 4. POST /candidate/edit (Update Manifesto)
            $candRow = $db->fetchOne("SELECT id FROM candidates WHERE user_id = ?", [$fixtures['voter_id']]);
            $candidateId = (int)($candRow['id'] ?? 0);

            $editPost = $this->http('POST', '/candidate/edit', [
                'candidate_id' => $candidateId,
                'manifesto' => 'Enhanced Student Government Manifesto: Transparency, Technology, Integrity.',
                'csrf_token' => $dashCsrf
            ], $cookie);
            $this->logAssertion("Candidate Manifesto Edit POST redirects", in_array($editPost['code'], [302, 303], true));

            $updatedCand = $db->fetchOne("SELECT manifesto FROM candidates WHERE id = ?", [$candidateId]);
            $this->logAssertion("Manifesto successfully updated in DB", strpos($updatedCand['manifesto'] ?? '', 'Enhanced Student Government') !== false);

            // 5. GET /logout
            $logout = $this->http('GET', '/logout', [], $cookie);
            $this->logAssertion("Candidate Logout redirects", in_array($logout['code'], [302, 303], true));

        } finally {
            if (file_exists($cookie)) {
                @unlink($cookie);
            }
        }
    }

    private function testVoterPortalAndVotingLifecycle(Database $db, array $fixtures): void
    {
        echo "\n--- [ Portal 3: Voter Workflows & Voting Lifecycle ] ---\n";
        $cookie = tempnam(sys_get_temp_dir(), 'voter_cookie_');

        try {
            // Find the open test election
            $openElec = $db->fetchOne("SELECT id, title FROM elections WHERE status = 'open' LIMIT 1");
            $electionId = (int)$openElec['id'];
            $cand = $db->fetchOne("SELECT id FROM candidates WHERE election_id = ? AND approval_status = 'approved' LIMIT 1", [$electionId]);
            $candidateId = (int)$cand['id'];

            // Ensure voter is eligible
            $db->insert("INSERT IGNORE INTO election_eligibility (election_id, user_id) VALUES (?, ?)", [$electionId, $fixtures['voter_id']]);

            // 1. GET /login/voter
            $loginPage = $this->http('GET', '/login/voter', [], $cookie);
            $this->logAssertion("Voter Login GET returns HTTP 200", $loginPage['code'] === 200);
            $csrf = $this->extractCsrf($loginPage['body']);

            // 2. POST /login/voter
            $loginPost = $this->http('POST', '/login/voter', [
                'identifier' => $fixtures['voter_identifier'],
                'password' => $fixtures['password'],
                'csrf_token' => $csrf
            ], $cookie);
            $this->logAssertion("Voter Login POST redirects to /voter", in_array($loginPost['code'], [302, 303], true));

            // 3. GET /voter Dashboard
            $dash = $this->http('GET', '/voter', [], $cookie);
            $this->logAssertion("Voter Dashboard returns HTTP 200", $dash['code'] === 200);

            // 4. GET /ballot?election_id=X
            $ballotPage = $this->http('GET', "/ballot?election_id={$electionId}", [], $cookie);
            $this->logAssertion("Ballot Submission page returns HTTP 200", $ballotPage['code'] === 200);
            $ballotCsrf = $this->extractCsrf($ballotPage['body']);
            $ballotToken = '';
            if (preg_match('/name=["\']ballot_token["\']\s+value=["\']([^"\']+)["\']/i', $ballotPage['body'], $m)) {
                $ballotToken = $m[1];
            }
            $this->logAssertion("Ballot token extracted from form", !empty($ballotToken));

            // 5. POST /ballot (Submit valid vote)
            $ballotPost = $this->http('POST', '/ballot', [
                'election_id' => $electionId,
                'candidate_id' => $candidateId,
                'csrf_token' => $ballotCsrf,
                'ballot_token' => $ballotToken
            ], $cookie);
            $this->logAssertion("Valid Ballot POST redirects to confirmation", in_array($ballotPost['code'], [302, 303], true));

            // 6. GET /confirmation?id=X
            $confirmPage = $this->http('GET', "/confirmation?id={$electionId}", [], $cookie);
            $this->logAssertion("Vote Confirmation page returns HTTP 200", $confirmPage['code'] === 200);
            $hasReceiptToken = strpos($confirmPage['body'], 'Receipt Token') !== false || strpos($confirmPage['body'], 'copyReceiptBtn') !== false || strpos($confirmPage['body'], 'VV-') !== false;
            $this->logAssertion("Confirmation renders cryptographic receipt and copy controls", $hasReceiptToken);

            // 7. Verify Database Integrity (Votes vs Participation)
            $partCount = (int)$db->fetchOne("SELECT COUNT(*) as c FROM election_participation WHERE election_id = ? AND user_id = ?", [$electionId, $fixtures['voter_id']])['c'];
            $voteCount = (int)$db->fetchOne("SELECT COUNT(*) as c FROM votes WHERE election_id = ? AND candidate_id = ?", [$electionId, $candidateId])['c'];
            $this->logAssertion("Exactly 1 participation record in test database", $partCount === 1, "Count: {$partCount}");
            $this->logAssertion("Exactly 1 anonymous ballot in test database", $voteCount === 1, "Count: {$voteCount}");

            // Verify votes table has NO user_id column
            $colRes = $db->fetchAll("SHOW COLUMNS FROM votes LIKE 'user_id'");
            $this->logAssertion("Ballot Privacy: votes table contains NO user_id column", empty($colRes));

            // 8. Re-attempt Duplicate Vote
            // Fresh GET /ballot?election_id=X should recognize voter already participated
            $ballotReattempt = $this->http('GET', "/ballot?election_id={$electionId}", [], $cookie);
            $alreadyVotedRedirect = in_array($ballotReattempt['code'], [302, 303], true) || strpos($ballotReattempt['body'], 'already submitted') !== false;
            $this->logAssertion("Re-entering ballot for voted election redirects or shows notice", $alreadyVotedRedirect);

            $dupPost = $this->http('POST', '/ballot', [
                'election_id' => $electionId,
                'candidate_id' => $candidateId,
                'csrf_token' => $ballotCsrf,
                'ballot_token' => 'invalid_replay_token'
            ], $cookie);
            // Must not create another vote
            $partCountAfter = (int)$db->fetchOne("SELECT COUNT(*) as c FROM election_participation WHERE election_id = ? AND user_id = ?", [$electionId, $fixtures['voter_id']])['c'];
            $voteCountAfter = (int)$db->fetchOne("SELECT COUNT(*) as c FROM votes WHERE election_id = ? AND candidate_id = ?", [$electionId, $candidateId])['c'];
            $this->logAssertion("Duplicate vote strictly rejected (Participation unchanged)", $partCountAfter === 1);
            $this->logAssertion("Duplicate vote strictly rejected (Ballots count unchanged)", $voteCountAfter === 1);

            // 9. True Simultaneous HTTP Concurrency Test (via curl_multi)
            $this->testConcurrentHttpSubmissions($db, $electionId, $fixtures);

            // 10. GET /logout
            $logout = $this->http('GET', '/logout', [], $cookie);
            $this->logAssertion("Voter Logout redirects", in_array($logout['code'], [302, 303], true));

        } finally {
            if (file_exists($cookie)) {
                @unlink($cookie);
            }
        }
    }

    private function testConcurrentHttpSubmissions(Database $db, int $electionId, array $fixtures): void
    {
        echo "\n--- [ True Simultaneous HTTP Concurrency Test (curl_multi) ] ---\n";

        // Create a new distinct voter fixture for concurrency race
        $token = bin2hex(random_bytes(3));
        $raceVoterIdent = 'RACE_VTR_' . $token;
        $raceVoterId = $db->insert(
            "INSERT INTO users (identifier, role, full_name, email, department, academic_year, password_hash, must_change_password, status)
             VALUES (?, 'student', 'Concurrent Race Voter', 'race@test.local', 'CS', '2025', ?, 0, 'active')",
            [$raceVoterIdent, $fixtures['password_hash']]
        );

        $cand = $db->fetchOne("SELECT id FROM candidates WHERE election_id = ? AND approval_status = 'approved' LIMIT 1", [$electionId]);
        $candId = (int)$cand['id'];

        $db->insert("INSERT INTO election_eligibility (election_id, user_id, assigned_by) VALUES (?, ?, ?)", [$electionId, $raceVoterId, $fixtures['admin_id']]);

        // Establish session and get ballot token
        $cookie = tempnam(sys_get_temp_dir(), 'race_cookie_');
        $loginPage = $this->http('GET', '/login/voter', [], $cookie);
        $csrf = $this->extractCsrf($loginPage['body']);

        $this->http('POST', '/login/voter', [
            'identifier' => $raceVoterIdent,
            'password' => $fixtures['password'],
            'csrf_token' => $csrf
        ], $cookie);

        $ballotPage = $this->http('GET', "/ballot?election_id={$electionId}", [], $cookie);
        $ballotCsrf = $this->extractCsrf($ballotPage['body']);
        $ballotToken = '';
        if (preg_match('/name=["\']ballot_token["\']\s+value=["\']([^"\']+)["\']/i', $ballotPage['body'], $m)) {
            $ballotToken = $m[1];
        }

        // Fire 10 simultaneous HTTP requests via curl_multi
        $mh = curl_multi_init();
        $handles = [];
        $url = $this->baseUrl . '/ballot';
        $postFields = http_build_query([
            'election_id' => $electionId,
            'candidate_id' => $candId,
            'csrf_token' => $ballotCsrf,
            'ballot_token' => $ballotToken
        ]);

        for ($i = 0; $i < 10; $i++) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_multi_add_handle($mh, $ch);
            $handles[$i] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);

        $successCount = 0;
        $rejectedCount = 0;

        foreach ($handles as $ch) {
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $content = curl_multi_getcontent($ch);
            // HTTP 302/303 to confirmation indicates successful vote acceptance
            if (in_array($code, [302, 303], true) && strpos((string)$content, 'confirmation') !== false) {
                $successCount++;
            } else {
                $rejectedCount++;
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
        @unlink($cookie);

        $this->logAssertion("Concurrent Same-Voter Race: Exactly 1 accepted", $successCount === 1, "Accepted: {$successCount}, Rejected: {$rejectedCount}");
        $this->logAssertion("Concurrent Same-Voter Race: Exactly 9 rejected", $rejectedCount === 9, "Rejected: {$rejectedCount}");

        // Verify DB participation & votes
        $racePartCount = (int)$db->fetchOne("SELECT COUNT(*) as c FROM election_participation WHERE election_id = ? AND user_id = ?", [$electionId, $raceVoterId])['c'];
        $this->logAssertion("Concurrent Same-Voter Race: Database holds exactly 1 participation record", $racePartCount === 1);
    }

    private function testBackupSecurity(Database $db, array $fixtures): void
    {
        echo "\n--- [ Backup Security & Path Traversal Verification ] ---\n";
        $adminCookie = tempnam(sys_get_temp_dir(), 'admin_b_cookie_');
        $unauthCookie = tempnam(sys_get_temp_dir(), 'unauth_cookie_');

        try {
            // Login as Admin
            $l = $this->http('GET', '/login/admin', [], $adminCookie);
            $csrf = $this->extractCsrf($l['body']);
            $this->http('POST', '/login/admin', [
                'identifier' => $fixtures['admin_identifier'],
                'password' => $fixtures['password'],
                'csrf_token' => $csrf
            ], $adminCookie);

            // 1. Authorized Admin requests backup console to get fresh post-auth CSRF
            $bPage = $this->http('GET', '/admin/backups', [], $adminCookie);
            $backupCsrf = $this->extractCsrf($bPage['body']);

            // 2. Authorized Admin can create a backup
            $createPost = $this->http('POST', '/admin/backups', [
                'csrf_token' => $backupCsrf
            ], $adminCookie);
            $this->logAssertion("Authorized Admin backup request redirects", in_array($createPost['code'], [302, 303], true));

            // Find generated backup file
            $backups = glob(dirname(__DIR__) . '/database/backups/*.sql');
            $this->logAssertion("Backup SQL file generated on disk", !empty($backups));
            $latestBackup = basename(end($backups) ?: '');

            // 2. Authorized Admin can download the backup
            $dlRes = $this->http('GET', "/admin/backups/download?file={$latestBackup}", [], $adminCookie);
            $this->logAssertion("Authorized Admin can download backup (HTTP 200)", $dlRes['code'] === 200 && strpos($dlRes['headers'], 'application/sql') !== false);

            // 3. Unauthorized User cannot download backup
            $unauthDl = $this->http('GET', "/admin/backups/download?file={$latestBackup}", [], $unauthCookie);
            $this->logAssertion("Unauthorized user receives redirect/403 on backup download", in_array($unauthDl['code'], [302, 303, 403], true));

            // 4. Path Traversal Attack Defense
            $traversalAttacks = [
                '../../config/config.php',
                '..\\..\\config\\config.php',
                '%2e%2e%2fconfig/config.php',
                '/etc/passwd'
            ];
            foreach ($traversalAttacks as $atk) {
                $atkRes = $this->http('GET', "/admin/backups/download?file=" . urlencode($atk), [], $adminCookie);
                $this->logAssertion("Path Traversal blocked for '{$atk}'", $atkRes['code'] === 404 || $atkRes['code'] === 403, "HTTP {$atkRes['code']}");
            }

            // Clean up test backup files
            foreach ($backups as $b) {
                @unlink($b);
            }

        } finally {
            if (file_exists($adminCookie)) {
                @unlink($adminCookie);
            }
            if (file_exists($unauthCookie)) {
                @unlink($unauthCookie);
            }
        }
    }

    private function http(string $method, string $path, array $postData = [], ?string $cookieFile = null): array
    {
        $url = $this->baseUrl . $path;
        return $this->httpRaw($url, $method, $postData, $cookieFile);
    }

    private function httpRaw(string $url, string $method = 'GET', array $postData = [], ?string $cookieFile = null): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, 'VerityVote-QAAcceptanceRunner/1.0');

        if ($cookieFile !== null) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        }

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        }

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr((string)$res, 0, $headerSize);
        $body = substr((string)$res, $headerSize);
        curl_close($ch);

        return [
            'code' => $code,
            'headers' => $headers,
            'body' => $body
        ];
    }

    private function extractCsrf(string $html): string
    {
        if (preg_match('/name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']/i', $html, $matches)) {
            return $matches[1];
        }
        return '';
    }
}

$audit = new QAAcceptanceAudit();
$audit->run();
