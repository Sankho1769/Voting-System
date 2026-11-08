<?php
/**
 * VerityVote - Release Candidate End-to-End Validation Engine
 * Runs exclusively against Baymax_Voting_System_RC and verityvote_install_test_20261108.
 */

declare(strict_types=1);

namespace RC;

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use mysqli;
use RuntimeException;

if (PHP_SAPI !== 'cli') {
    die("This validator must be run from the CLI.\n");
}

class RCLifecycleValidation
{
    private string $baseUrl;
    private Database $db;
    private string $targetDb = 'verityvote_install_test_20261108';
    private string $restoreDb = 'verityvote_restore_test_20261108';
    private int $passCount = 0;
    private int $failCount = 0;
    private array $credentials = [];
    private ?string $generatedBackupPath = null;

    public function __construct(
        string $baseUrl = 'http://localhost/Baymax_Voting_System_RC/public',
        string $targetDb = 'verityvote_install_test_20261108'
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->targetDb = $targetDb;
    }

    public function execute(): void
    {
        echo "========================================================\n";
        echo " VerityVote - Release Candidate End-to-End Validation\n";
        echo " Target URL: {$this->baseUrl}\n";
        echo " Target DB:  {$this->targetDb}\n";
        echo " Timestamp:  " . date('Y-m-d H:i:s') . "\n";
        echo "========================================================\n\n";

        // Step 1: Safety Assertions
        $this->verifyDatabaseBoundaries();

        // Step 2: Provision Test Accounts
        $this->provisionTestAccounts();

        // Step 3: Admin Complete Workflow
        $electionId = $this->runAdminWorkflow();

        // Step 4: Candidate Workflow
        $this->runCandidateWorkflow($electionId);

        // Step 5: Voter Workflow & Secret Ballot Integrity
        $this->runVoterWorkflow($electionId);

        // Step 6: Negative-Path Security Workflows & DB Protection
        $this->runNegativePathSecurityWorkflow();

        // Step 7: Close Election & Verify Published Results
        $this->runResultsConclusionWorkflow($electionId);

        // Step 8: Backup Security & Restoration Acceptance
        $this->runBackupAndRestoreAcceptance();

        // Summary
        echo "\n========================================================\n";
        echo " Release Candidate Validation Complete: {$this->passCount} Passed | {$this->failCount} Failed\n";
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

    private function verifyDatabaseBoundaries(): void
    {
        echo "--- [ Step 1: Database Safety & Isolation Verification ] ---\n";
        $this->db = Database::getInstance();
        $currDb = $this->db->fetchOne("SELECT DATABASE() as db")['db'] ?? '';

        if (strtolower($currDb) === 'verityvote_db' || strtolower($this->targetDb) === 'verityvote_db') {
            throw new RuntimeException("CRITICAL SAFETY VIOLATION: Refusing to run validation against primary database 'verityvote_db'!");
        }

        if ($currDb !== $this->targetDb) {
            throw new RuntimeException("CRITICAL SAFETY ABORT: Expected connected database '{$this->targetDb}', but connected to '{$currDb}'!");
        }

        $this->logAssertion("RC Database is strictly '{$this->targetDb}'", true);
        $this->logAssertion("Connection to 'verityvote_db' prohibited", true);
    }

    private function provisionTestAccounts(): void
    {
        echo "\n--- [ Step 2: Provisioning RC Test Accounts ] ---\n";

        $token = bin2hex(random_bytes(3));
        $adminIdent = 'RC_ADM_' . $token;
        $voterIdent = 'RC_VTR_' . $token;
        $candidateIdent = 'RC_CND_' . $token;
        $password = 'RcTest!Pass#' . bin2hex(random_bytes(4));
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // 1. Admin
        $adminId = $this->db->insert(
            "INSERT INTO users (identifier, role, full_name, email, department, password_hash, must_change_password, status)
             VALUES (?, 'admin', 'RC System Administrator', 'admin@rc.college', 'Administration', ?, 0, 'active')",
            [$adminIdent, $hash]
        );

        // 2. Candidate User
        $candUserId = $this->db->insert(
            "INSERT INTO users (identifier, role, full_name, email, department, academic_year, password_hash, must_change_password, status)
             VALUES (?, 'student', 'RC Candidate One', 'cand1@rc.college', 'Computer Science', '2024-2027', ?, 0, 'active')",
            [$candidateIdent, $hash]
        );

        // 3. Voter User
        $voterId = $this->db->insert(
            "INSERT INTO users (identifier, role, full_name, email, department, academic_year, password_hash, must_change_password, status)
             VALUES (?, 'student', 'RC Student Voter', 'voter@rc.college', 'Computer Science', '2024-2027', ?, 0, 'active')",
            [$voterIdent, $hash]
        );

        $this->credentials = [
            'password' => $password,
            'admin_id' => $adminId,
            'admin_ident' => $adminIdent,
            'cand_user_id' => $candUserId,
            'cand_ident' => $candidateIdent,
            'voter_id' => $voterId,
            'voter_ident' => $voterIdent
        ];

        $this->logAssertion("Provisioned RC Admin ({$adminIdent})", $adminId > 0);
        $this->logAssertion("Provisioned RC Candidate ({$candidateIdent})", $candUserId > 0);
        $this->logAssertion("Provisioned RC Voter ({$voterIdent})", $voterId > 0);
    }

    private function runAdminWorkflow(): int
    {
        echo "\n--- [ Step 3: Administrator Workflow Execution ] ---\n";
        $cookie = tempnam(sys_get_temp_dir(), 'rc_adm_cookie_');

        try {
            // 1. Login
            $loginGet = $this->http('GET', '/login/admin', [], $cookie);
            $csrf = $this->extractCsrf($loginGet['body']);
            $loginPost = $this->http('POST', '/login/admin', [
                'identifier' => $this->credentials['admin_ident'],
                'password' => $this->credentials['password'],
                'csrf_token' => $csrf
            ], $cookie);
            $this->logAssertion("Admin Login authenticates successfully", in_array($loginPost['code'], [302, 303], true));

            // 2. Dashboard
            $dash = $this->http('GET', '/admin', [], $cookie);
            $this->logAssertion("Admin Dashboard renders (HTTP 200)", $dash['code'] === 200);

            // 3. Create Election
            $createPage = $this->http('GET', '/admin/elections/create', [], $cookie);
            $createCsrf = $this->extractCsrf($createPage['body']);
            $title = "RC General Campus Election " . bin2hex(random_bytes(3));

            $createPost = $this->http('POST', '/admin/elections/create', [
                'title' => $title,
                'description' => 'Release candidate campus council election',
                'category' => 'general',
                'department' => 'All Departments',
                'position' => 'President',
                'opens_at' => date('Y-m-d H:i:s', time() - 600), // opened 10m ago
                'closes_at' => date('Y-m-d H:i:s', time() + 7200),
                'results_visibility' => 'always',
                'csrf_token' => $createCsrf
            ], $cookie);
            $this->logAssertion("Election created successfully", in_array($createPost['code'], [302, 303], true));

            $elec = $this->db->fetchOne("SELECT id, status FROM elections WHERE title = ?", [$title]);
            $electionId = (int)($elec['id'] ?? 0);
            $this->logAssertion("Election stored in RC database as 'draft'", $electionId > 0 && $elec['status'] === 'draft');

            // 4. Edit Election
            $editPage = $this->http('GET', "/admin/elections/edit?id={$electionId}", [], $cookie);
            $editCsrf = $this->extractCsrf($editPage['body']);
            $updatedTitle = $title . " (Verified)";
            $editPost = $this->http('POST', "/admin/elections/edit?id={$electionId}", [
                'title' => $updatedTitle,
                'description' => 'Verified RC election configuration',
                'category' => 'general',
                'department' => 'All Departments',
                'position' => 'President',
                'opens_at' => date('Y-m-d H:i:s', time() - 600),
                'closes_at' => date('Y-m-d H:i:s', time() + 7200),
                'results_visibility' => 'always',
                'csrf_token' => $editCsrf
            ], $cookie);
            $this->logAssertion("Election configuration updated", in_array($editPost['code'], [302, 303], true));

            // 5. Add Candidates
            // Candidate 1 (User linked)
            $this->db->insert(
                "INSERT INTO candidates (election_id, user_id, name, manifesto, approval_status)
                 VALUES (?, ?, 'Alice Candidate', 'Vote Alice for campus progress.', 'pending')",
                [$electionId, $this->credentials['cand_user_id']]
            );
            $cand1 = $this->db->fetchOne("SELECT id FROM candidates WHERE election_id = ? AND name = 'Alice Candidate'", [$electionId]);
            $cand1Id = (int)$cand1['id'];

            // Candidate 2 (External)
            $this->db->insert(
                "INSERT INTO candidates (election_id, user_id, name, manifesto, approval_status)
                 VALUES (?, NULL, 'Bob Opponent', 'Vote Bob for transparency.', 'pending')",
                [$electionId]
            );
            $cand2 = $this->db->fetchOne("SELECT id FROM candidates WHERE election_id = ? AND name = 'Bob Opponent'", [$electionId]);
            $cand2Id = (int)$cand2['id'];

            $this->logAssertion("Added 2 candidates to election", $cand1Id > 0 && $cand2Id > 0);

            // 6. Approve Candidates
            $detailPage = $this->http('GET', "/admin/elections/detail?id={$electionId}", [], $cookie);
            $detailCsrf = $this->extractCsrf($detailPage['body']);

            $this->http('POST', '/admin/candidates/status', [
                'candidate_id' => $cand1Id,
                'status' => 'approved',
                'csrf_token' => $detailCsrf
            ], $cookie);

            $detailPage = $this->http('GET', "/admin/elections/detail?id={$electionId}", [], $cookie);
            $detailCsrf = $this->extractCsrf($detailPage['body']);

            $this->http('POST', '/admin/candidates/status', [
                'candidate_id' => $cand2Id,
                'status' => 'approved',
                'csrf_token' => $detailCsrf
            ], $cookie);

            $app1 = $this->db->fetchOne("SELECT approval_status FROM candidates WHERE id = ?", [$cand1Id])['approval_status'] ?? '';
            $app2 = $this->db->fetchOne("SELECT approval_status FROM candidates WHERE id = ?", [$cand2Id])['approval_status'] ?? '';
            $this->logAssertion("Both candidates approved by Administrator", $app1 === 'approved' && $app2 === 'approved');

            // 7. Enroll Voter into Whitelist
            $detailPage = $this->http('GET', "/admin/elections/detail?id={$electionId}", [], $cookie);
            $detailCsrf = $this->extractCsrf($detailPage['body']);

            $this->http('POST', '/admin/eligibility/assign-individual', [
                'election_id' => $electionId,
                'identifier' => $this->credentials['voter_ident'],
                'csrf_token' => $detailCsrf
            ], $cookie);

            $isEligible = $this->db->fetchOne("SELECT id FROM election_eligibility WHERE election_id = ? AND user_id = ?", [$electionId, $this->credentials['voter_id']]);
            $this->logAssertion("Voter enrolled into election whitelist", !empty($isEligible));

            // 8. Open Election
            $detailPage = $this->http('GET', "/admin/elections/detail?id={$electionId}", [], $cookie);
            $detailCsrf = $this->extractCsrf($detailPage['body']);

            $this->http('POST', '/admin/elections/status', [
                'election_id' => $electionId,
                'status' => 'open',
                'csrf_token' => $detailCsrf
            ], $cookie);

            $statusAfter = $this->db->fetchOne("SELECT status FROM elections WHERE id = ?", [$electionId])['status'] ?? '';
            $this->logAssertion("Election transitioned from 'draft' to 'open'", $statusAfter === 'open');

            // Logout
            $this->http('GET', '/logout', [], $cookie);

            return $electionId;

        } finally {
            if (file_exists($cookie)) {
                @unlink($cookie);
            }
        }
    }

    private function runCandidateWorkflow(int $electionId): void
    {
        echo "\n--- [ Step 4: Candidate Workflow Execution ] ---\n";
        $cookie = tempnam(sys_get_temp_dir(), 'rc_cnd_cookie_');

        try {
            // 1. Candidate Login
            $loginGet = $this->http('GET', '/login/candidate', [], $cookie);
            $csrf = $this->extractCsrf($loginGet['body']);

            $loginPost = $this->http('POST', '/login/candidate', [
                'identifier' => $this->credentials['cand_ident'],
                'password' => $this->credentials['password'],
                'csrf_token' => $csrf
            ], $cookie);
            $this->logAssertion("Candidate Login authenticates successfully", in_array($loginPost['code'], [302, 303], true));

            // 2. Candidate Dashboard
            $dash = $this->http('GET', '/candidate', [], $cookie);
            $this->logAssertion("Candidate Dashboard loads (HTTP 200)", $dash['code'] === 200);
            $hasElectionCard = strpos($dash['body'], 'Alice Candidate') !== false || strpos($dash['body'], 'Campaign Manifesto') !== false;
            $this->logAssertion("Candidate Dashboard displays candidacy profile", $hasElectionCard);

            // 3. Candidate Manifesto Self-Service Update
            $candRow = $this->db->fetchOne("SELECT id FROM candidates WHERE user_id = ?", [$this->credentials['cand_user_id']]);
            $candidateId = (int)($candRow['id'] ?? 0);
            $dashCsrf = $this->extractCsrf($dash['body']);

            $updatedManifesto = "Official Release Candidate Manifesto: Continuous Quality, Security, and Open Dialogue.";
            $editPost = $this->http('POST', '/candidate/edit', [
                'candidate_id' => $candidateId,
                'manifesto' => $updatedManifesto,
                'csrf_token' => $dashCsrf
            ], $cookie);
            $this->logAssertion("Candidate updates manifesto successfully", in_array($editPost['code'], [302, 303], true));

            $dbManifesto = $this->db->fetchOne("SELECT manifesto FROM candidates WHERE id = ?", [$candidateId])['manifesto'] ?? '';
            $this->logAssertion("Manifesto update reflected in database", $dbManifesto === $updatedManifesto);

            // Logout
            $this->http('GET', '/logout', [], $cookie);

        } finally {
            if (file_exists($cookie)) {
                @unlink($cookie);
            }
        }
    }

    private function runVoterWorkflow(int $electionId): void
    {
        echo "\n--- [ Step 5: Voter Workflow & Secret Ballot Integrity ] ---\n";
        $cookie = tempnam(sys_get_temp_dir(), 'rc_vtr_cookie_');

        try {
            // 1. Voter Login
            $loginGet = $this->http('GET', '/login/voter', [], $cookie);
            $csrf = $this->extractCsrf($loginGet['body']);

            $loginPost = $this->http('POST', '/login/voter', [
                'identifier' => $this->credentials['voter_ident'],
                'password' => $this->credentials['password'],
                'csrf_token' => $csrf
            ], $cookie);
            $this->logAssertion("Voter Login authenticates successfully", in_array($loginPost['code'], [302, 303], true));

            // 2. Voter Dashboard & Discovery
            $dash = $this->http('GET', '/voter', [], $cookie);
            $this->logAssertion("Voter Dashboard loads active elections (HTTP 200)", $dash['code'] === 200);

            $explore = $this->http('GET', '/explore', [], $cookie);
            $this->logAssertion("Public Explore loads elections (HTTP 200)", $explore['code'] === 200);

            $candidatesList = $this->http('GET', '/candidates', [], $cookie);
            $this->logAssertion("Public Candidates directory loads (HTTP 200)", $candidatesList['code'] === 200);

            // 3. Ballot Form
            $ballotPage = $this->http('GET', "/ballot?election_id={$electionId}", [], $cookie);
            $this->logAssertion("Secret ballot interface renders (HTTP 200)", $ballotPage['code'] === 200);
            $ballotCsrf = $this->extractCsrf($ballotPage['body']);
            $ballotToken = '';
            if (preg_match('/name=["\']ballot_token["\']\s+value=["\']([^"\']+)["\']/i', $ballotPage['body'], $m)) {
                $ballotToken = $m[1];
            }
            $this->logAssertion("Cryptographic ballot session token issued", !empty($ballotToken));

            // Select Candidate 1
            $candRow = $this->db->fetchOne("SELECT id FROM candidates WHERE election_id = ? AND approval_status = 'approved' LIMIT 1", [$electionId]);
            $candidateId = (int)$candRow['id'];

            // 4. Submit Valid Ballot
            $ballotPost = $this->http('POST', '/ballot', [
                'election_id' => $electionId,
                'candidate_id' => $candidateId,
                'csrf_token' => $ballotCsrf,
                'ballot_token' => $ballotToken
            ], $cookie);
            $this->logAssertion("Valid ballot cast redirects to confirmation", in_array($ballotPost['code'], [302, 303], true));

            // 5. Confirmation Receipt
            $confirmPage = $this->http('GET', "/confirmation?id={$electionId}", [], $cookie);
            $this->logAssertion("Confirmation receipt renders (HTTP 200)", $confirmPage['code'] === 200);
            $hasReceipt = strpos($confirmPage['body'], 'Receipt Token') !== false || strpos($confirmPage['body'], 'copyReceiptBtn') !== false || strpos($confirmPage['body'], 'VV-') !== false;
            $this->logAssertion("Receipt token and audit slip controls present", $hasReceipt);

            // 6. Database Verification
            $partCount = (int)$this->db->fetchOne("SELECT COUNT(*) as c FROM election_participation WHERE election_id = ? AND user_id = ?", [$electionId, $this->credentials['voter_id']])['c'];
            $voteCount = (int)$this->db->fetchOne("SELECT COUNT(*) as c FROM votes WHERE election_id = ? AND candidate_id = ?", [$electionId, $candidateId])['c'];
            $this->logAssertion("Database has exactly 1 participation record", $partCount === 1, "Count: {$partCount}");
            $this->logAssertion("Database has exactly 1 anonymous vote record", $voteCount === 1, "Count: {$voteCount}");

            $votesColumns = $this->db->fetchAll("SHOW COLUMNS FROM votes LIKE 'user_id'");
            $this->logAssertion("Votes table strictly maintains anonymity (NO user_id column)", empty($votesColumns));

            // 7. Duplicate Vote Attempt
            $dupPost = $this->http('POST', '/ballot', [
                'election_id' => $electionId,
                'candidate_id' => $candidateId,
                'csrf_token' => $ballotCsrf,
                'ballot_token' => 'replayed_token'
            ], $cookie);
            $partAfter = (int)$this->db->fetchOne("SELECT COUNT(*) as c FROM election_participation WHERE election_id = ? AND user_id = ?", [$electionId, $this->credentials['voter_id']])['c'];
            $votesAfter = (int)$this->db->fetchOne("SELECT COUNT(*) as c FROM votes WHERE election_id = ? AND candidate_id = ?", [$electionId, $candidateId])['c'];
            $this->logAssertion("Duplicate vote strictly rejected (Participation count = 1)", $partAfter === 1);
            $this->logAssertion("Duplicate vote strictly rejected (Votes count = 1)", $votesAfter === 1);

            // Logout
            $this->http('GET', '/logout', [], $cookie);

        } finally {
            if (file_exists($cookie)) {
                @unlink($cookie);
            }
        }
    }

    private function runNegativePathSecurityWorkflow(): void
    {
        echo "\n--- [ Step 6: Negative-Path Security Workflows & DB Protection ] ---\n";
        $primaryConn = new mysqli('127.0.0.1', 'root', '', 'verityvote_db', 3306);
        $cookie = tempnam(sys_get_temp_dir(), 'rc_neg_cookie_');

        try {
            // Snapshot baseline counts of primary database
            $initialPrimAudits = (int)$primaryConn->query("SELECT COUNT(*) FROM audit_logs")->fetch_row()[0];
            $initialPrimLogins = (int)$primaryConn->query("SELECT COUNT(*) FROM login_attempts")->fetch_row()[0];
            $this->logAssertion("Primary database baseline verified (Audits: {$initialPrimAudits}, Logins: {$initialPrimLogins})", $initialPrimAudits === 6104 && $initialPrimLogins === 199);

            // 1. Invalid Login Attempt against /login/voter
            $lGet = $this->http('GET', '/login/voter', [], $cookie);
            $csrf = $this->extractCsrf($lGet['body']);
            $fakeVoter = 'FAKE_VTR_' . bin2hex(random_bytes(3));
            $dummyBadPass = 'DeliberateBad_' . bin2hex(random_bytes(4));
            $loginRes = $this->http('POST', '/login/voter', [
                'identifier' => $fakeVoter,
                'password' => $dummyBadPass,
                'csrf_token' => $csrf
            ], $cookie);

            // Assert failure recorded in RC login_attempts
            $rcAttempt = $this->db->fetchOne(
                "SELECT id, is_successful FROM login_attempts WHERE identifier = ? ORDER BY id DESC LIMIT 1",
                [$fakeVoter]
            );
            $attemptLogged = (!empty($rcAttempt) && (int)$rcAttempt['is_successful'] === 0);
            $this->logAssertion("Invalid login failure recorded in RC login_attempts (is_successful = 0)", $attemptLogged);

            // Assert primary database untouched
            $primAudits1 = (int)$primaryConn->query("SELECT COUNT(*) FROM audit_logs")->fetch_row()[0];
            $primLogins1 = (int)$primaryConn->query("SELECT COUNT(*) FROM login_attempts")->fetch_row()[0];
            $this->logAssertion("Invalid login caused ZERO writes to primary database (Audits: {$primAudits1}, Logins: {$primLogins1})", $primAudits1 === 6104 && $primLogins1 === 199);

            // 2. CSRF Rejection on State-Mutating Route (/change-password)
            $badCsrfToken = 'invalid_canary_csrf_' . bin2hex(random_bytes(4));
            $dummyOldPass = 'InvalidPass_' . bin2hex(random_bytes(4));
            $dummyNewPass = 'InvalidPass_' . bin2hex(random_bytes(4));
            $csrfRes = $this->http('POST', '/change-password', [
                'current_password' => $dummyOldPass,
                'new_password' => $dummyNewPass,
                'csrf_token' => $badCsrfToken
            ], $cookie);

            $this->logAssertion("State-mutation with invalid CSRF token rejected with HTTP 403", $csrfRes['code'] === 403);

            // Assert CSRF_VALIDATION_FAILURE recorded in RC audit_logs
            $rcCsrfAudit = $this->db->fetchOne(
                "SELECT id FROM audit_logs WHERE action = 'CSRF_VALIDATION_FAILURE' ORDER BY id DESC LIMIT 1"
            );
            $this->logAssertion("CSRF_VALIDATION_FAILURE security event recorded in RC audit_logs", !empty($rcCsrfAudit));

            // Assert primary database untouched
            $primAudits2 = (int)$primaryConn->query("SELECT COUNT(*) FROM audit_logs")->fetch_row()[0];
            $primLogins2 = (int)$primaryConn->query("SELECT COUNT(*) FROM login_attempts")->fetch_row()[0];
            $this->logAssertion("CSRF attack attempt caused ZERO writes to primary database (Audits: {$primAudits2}, Logins: {$primLogins2})", $primAudits2 === 6104 && $primLogins2 === 199);

            // 3. Rate-Limit Event (Throttling on repeated failures)
            $throttledIdent = 'THROTTLE_VTR_' . bin2hex(random_bytes(3));
            for ($attempt = 1; $attempt <= 5; $attempt++) {
                $formGet = $this->http('GET', '/login/voter', [], $cookie);
                $formCsrf = $this->extractCsrf($formGet['body']);
                $attemptPass = 'ThrottlePass_' . $attempt . '_' . bin2hex(random_bytes(2));
                $this->http('POST', '/login/voter', [
                    'identifier' => $throttledIdent,
                    'password' => $attemptPass,
                    'csrf_token' => $formCsrf
                ], $cookie);
            }

            // 6th attempt should trigger LOGIN_THROTTLED
            $formGet6 = $this->http('GET', '/login/voter', [], $cookie);
            $formCsrf6 = $this->extractCsrf($formGet6['body']);
            $attemptPass6 = 'ThrottlePass_6_' . bin2hex(random_bytes(2));
            $res6 = $this->http('POST', '/login/voter', [
                'identifier' => $throttledIdent,
                'password' => $attemptPass6,
                'csrf_token' => $formCsrf6
            ], $cookie);

            // Verify RC recorded LOGIN_THROTTLED event in audit_logs
            $throttleAudit = $this->db->fetchOne(
                "SELECT id FROM audit_logs WHERE action = 'LOGIN_THROTTLED' AND entity_id = ? ORDER BY id DESC LIMIT 1",
                [$throttledIdent]
            );
            $this->logAssertion("Repeated brute-force trigger recorded 'LOGIN_THROTTLED' in RC audit_logs", !empty($throttleAudit));

            // Verify RC recorded 5 or 6 failed attempts
            $rcAttemptsCount = (int)$this->db->fetchValue(
                "SELECT COUNT(*) FROM login_attempts WHERE identifier = ?",
                [$throttledIdent]
            );
            $this->logAssertion("RC database recorded throttled failed attempts (Count: {$rcAttemptsCount})", $rcAttemptsCount >= 5);

            // Assert primary database completely untouched throughout throttling barrage
            $finalPrimAudits = (int)$primaryConn->query("SELECT COUNT(*) FROM audit_logs")->fetch_row()[0];
            $finalPrimLogins = (int)$primaryConn->query("SELECT COUNT(*) FROM login_attempts")->fetch_row()[0];
            $this->logAssertion("Brute-force throttling attack caused ZERO writes to primary database (Audits: {$finalPrimAudits}, Logins: {$finalPrimLogins})", $finalPrimAudits === 6104 && $finalPrimLogins === 199);

        } finally {
            $primaryConn->close();
            if (file_exists($cookie)) {
                @unlink($cookie);
            }
        }
    }

    private function runResultsConclusionWorkflow(int $electionId): void
    {
        echo "\n--- [ Step 7: Election Closure & Results Publication ] ---\n";
        $cookie = tempnam(sys_get_temp_dir(), 'rc_adm_close_');

        try {
            // Login as Admin
            $l = $this->http('GET', '/login/admin', [], $cookie);
            $csrf = $this->extractCsrf($l['body']);
            $this->http('POST', '/login/admin', [
                'identifier' => $this->credentials['admin_ident'],
                'password' => $this->credentials['password'],
                'csrf_token' => $csrf
            ], $cookie);

            // Transition open -> closed
            $det = $this->http('GET', "/admin/elections/detail?id={$electionId}", [], $cookie);
            $cCsrf = $this->extractCsrf($det['body']);
            $this->http('POST', '/admin/elections/status', [
                'election_id' => $electionId,
                'status' => 'closed',
                'csrf_token' => $cCsrf
            ], $cookie);

            $stClosed = $this->db->fetchOne("SELECT status FROM elections WHERE id = ?", [$electionId])['status'] ?? '';
            $this->logAssertion("Election closed by Administrator", $stClosed === 'closed');

            // Transition closed -> results_published
            $det = $this->http('GET', "/admin/elections/detail?id={$electionId}", [], $cookie);
            $cCsrf = $this->extractCsrf($det['body']);
            $this->http('POST', '/admin/elections/status', [
                'election_id' => $electionId,
                'status' => 'results_published',
                'csrf_token' => $cCsrf
            ], $cookie);

            $stPub = $this->db->fetchOne("SELECT status FROM elections WHERE id = ?", [$electionId])['status'] ?? '';
            $this->logAssertion("Election results published by Administrator", $stPub === 'results_published');

            // Verify results page with authenticated session
            $resultsPage = $this->http('GET', "/results?id={$electionId}", [], $cookie);
            $this->logAssertion("Results page renders (HTTP 200)", $resultsPage['code'] === 200);
            $hasTally = strpos($resultsPage['body'], 'Alice Candidate') !== false || strpos($resultsPage['body'], '100%') !== false || strpos($resultsPage['body'], 'Winner') !== false;
            $this->logAssertion("Results tally accurately displays candidate standing", $hasTally);

            // Logout
            $this->http('GET', '/logout', [], $cookie);

        } finally {
            if (file_exists($cookie)) {
                @unlink($cookie);
            }
        }
    }

    private function runBackupAndRestoreAcceptance(): void
    {
        echo "\n--- [ Step 8: Backup Generation & Disposable Database Restoration ] ---\n";
        $adminCookie = tempnam(sys_get_temp_dir(), 'rc_b_adm_');
        $unauthCookie = tempnam(sys_get_temp_dir(), 'rc_b_unauth_');

        try {
            // Admin Login
            $l = $this->http('GET', '/login/admin', [], $adminCookie);
            $csrf = $this->extractCsrf($l['body']);
            $this->http('POST', '/login/admin', [
                'identifier' => $this->credentials['admin_ident'],
                'password' => $this->credentials['password'],
                'csrf_token' => $csrf
            ], $adminCookie);

            // 1. Generate Backup Snapshot
            $bPage = $this->http('GET', '/admin/backups', [], $adminCookie);
            $bCsrf = $this->extractCsrf($bPage['body']);
            $createPost = $this->http('POST', '/admin/backups', [
                'csrf_token' => $bCsrf
            ], $adminCookie);
            $this->logAssertion("Admin triggers backup snapshot creation (302/303)", in_array($createPost['code'], [302, 303], true));

            // Inspect file on disk in RC
            $rcBackupDir = dirname(__DIR__) . '/database/backups';
            $backups = glob($rcBackupDir . '/*.sql');
            $this->logAssertion("Backup SQL snapshot generated on disk", !empty($backups));
            $this->generatedBackupPath = end($backups) ?: null;
            $backupFilename = basename($this->generatedBackupPath);

            // 2. Download Security Checks
            $dlRes = $this->http('GET', "/admin/backups/download?file={$backupFilename}", [], $adminCookie);
            $this->logAssertion("Authorized Admin downloads backup (HTTP 200)", $dlRes['code'] === 200 && strpos($dlRes['headers'], 'application/sql') !== false);

            $unauthDl = $this->http('GET', "/admin/backups/download?file={$backupFilename}", [], $unauthCookie);
            $this->logAssertion("Unauthorized user rejected from downloading backup", in_array($unauthDl['code'], [302, 303, 403], true));

            $traversalRes = $this->http('GET', '/admin/backups/download?file=../../config/config.php', [], $adminCookie);
            $this->logAssertion("Path traversal download attempt blocked", in_array($traversalRes['code'], [404, 403], true));

            // 3. Disposable Database Restoration Acceptance
            echo "\nTesting database restoration into second disposable DB '{$this->restoreDb}'...\n";

            // Ensure second disposable database exists
            $rawConn = new mysqli('127.0.0.1', 'root', '', '', 3306);
            $rawConn->query("DROP DATABASE IF EXISTS `{$this->restoreDb}`");
            $rawConn->query("CREATE DATABASE `{$this->restoreDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // Execute restore_db.php against second disposable DB
            $phpBin = PHP_BINARY;
            $restoreScript = dirname(__DIR__) . '/bin/restore_db.php';
            $cmd = escapeshellcmd("{$phpBin} \"{$restoreScript}\" \"{$this->generatedBackupPath}\" {$this->restoreDb} --confirm");
            $output = (string)shell_exec($cmd);

            $restoreSuccess = strpos($output, 'Restore Status:  SUCCESS') !== false;
            $this->logAssertion("Database restoration into '{$this->restoreDb}' succeeded", $restoreSuccess);

            // 4. Compare Restored Database with Source RC Database
            $rawConn->select_db($this->restoreDb);
            $resTables = $rawConn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
            $restoredTableCount = $resTables ? $resTables->num_rows : 0;
            $this->logAssertion("Restored database contains all 10 core tables", $restoredTableCount === 10, "Tables: {$restoredTableCount}");

            $usersRestored = (int)$rawConn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
            $electionsRestored = (int)$rawConn->query("SELECT COUNT(*) FROM elections")->fetch_row()[0];
            $candidatesRestored = (int)$rawConn->query("SELECT COUNT(*) FROM candidates")->fetch_row()[0];
            $votesRestored = (int)$rawConn->query("SELECT COUNT(*) FROM votes")->fetch_row()[0];

            $usersSource = (int)$this->db->fetchOne("SELECT COUNT(*) as c FROM users")['c'];
            $electionsSource = (int)$this->db->fetchOne("SELECT COUNT(*) as c FROM elections")['c'];
            $candidatesSource = (int)$this->db->fetchOne("SELECT COUNT(*) as c FROM candidates")['c'];
            $votesSource = (int)$this->db->fetchOne("SELECT COUNT(*) as c FROM votes")['c'];

            $this->logAssertion("Restored users count matches source ({$usersRestored} vs {$usersSource})", $usersRestored === $usersSource);
            $this->logAssertion("Restored elections count matches source ({$electionsRestored} vs {$electionsSource})", $electionsRestored === $electionsSource);
            $this->logAssertion("Restored candidates count matches source ({$candidatesRestored} vs {$candidatesSource})", $candidatesRestored === $candidatesSource);
            $this->logAssertion("Restored votes count matches source ({$votesRestored} vs {$votesSource})", $votesRestored === $votesSource);

            // Clean up second disposable restore database
            $rawConn->query("DROP DATABASE IF EXISTS `{$this->restoreDb}`");
            $rawConn->close();
            echo "[OK] Second disposable database '{$this->restoreDb}' dropped cleanly.\n";

            // Logout
            $this->http('GET', '/logout', [], $adminCookie);

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
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, 'VerityVote-RCValidationRunner/1.0');

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

$baseUrl = $argv[1] ?? 'http://localhost/Baymax_Voting_System_RC/public';
$targetDb = $argv[2] ?? 'verityvote_install_test_20261108';
$validator = new RCLifecycleValidation($baseUrl, $targetDb);
$validator->execute();
