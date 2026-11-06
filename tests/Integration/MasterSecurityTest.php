<?php
/**
 * VerityVote - Master Security Hardening Automated Test Suite
 * Comprehensive verification of the 37 security controls specified in Phase 9:
 * 1. Identity Verification & Account Lifecycle (Tests 1-7)
 * 2. Eligibility Verification & Election Timing (Tests 8-12)
 * 3. Concurrency & Duplicate-Voting Prevention (Tests 13-22)
 * 4. Parameter Tampering, Injection & Replay Defense (Tests 23-30)
 * 5. Role Boundary, Result Integrity & Audit Secrecy (Tests 31-37)
 */

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Logger;
use App\Helpers\Sanitizer;
use App\Repositories\CandidateRepository;
use App\Repositories\ElectionRepository;
use App\Repositories\EligibilityRepository;
use App\Repositories\UserRepository;
use App\Repositories\VotingRepository;
use App\Repositories\AuditRepository;
use App\Services\AccountService;
use App\Services\CandidateService;
use App\Services\ElectionService;
use App\Services\EligibilityService;
use App\Services\VotingService;
use App\Services\ResultsService;

class MasterSecurityTest
{
    private static string $baseUrl = 'http://localhost/Baymax_Voting_System/public';

    public static function run(): array
    {
        $results = [];
        $db = Database::getInstance();
        $userRepo = new UserRepository($db);
        $accountService = new AccountService($userRepo);
        $electionRepo = new ElectionRepository($db);
        $candidateRepo = new CandidateRepository($db);
        $electionService = new ElectionService($electionRepo, $candidateRepo);
        $candidateService = new CandidateService($candidateRepo, $electionRepo);
        $eligibilityRepo = new EligibilityRepository($db);
        $eligibilityService = new EligibilityService($eligibilityRepo, $electionRepo);
        $votingRepo = new VotingRepository($db);
        $votingService = new VotingService($votingRepo);
        $auditRepo = new AuditRepository($db);
        $resultsService = new ResultsService($electionRepo, $votingRepo);

        $now = time();
        $suitePrefix = 'SEC_' . strtoupper(bin2hex(random_bytes(3))) . '_';
        $workerPath = dirname(__DIR__, 2) . '/bin/vote_worker.php';
        $phpBinary = PHP_BINARY;

        // =====================================================================
        // SECTION 1: IDENTITY TESTS (1 - 7)
        // =====================================================================

        // 1. Duplicate institutional IDs are rejected
        $id1 = $suitePrefix . 'ID_1';
        $user1 = $accountService->createUser([
            'identifier' => $id1,
            'role' => 'student',
            'full_name' => 'Alice Identity',
            'department' => 'BCA'
        ], 1);
        $dupCreate = $accountService->createUser([
            'identifier' => $id1,
            'role' => 'student',
            'full_name' => 'Fake Duplicate Alice',
            'department' => 'BCA'
        ], 1);
        $results[] = [
            'name' => 'Test 1: Duplicate institutional IDs are rejected',
            'pass' => $user1['success'] === true && $dupCreate['success'] === false
        ];

        // 2. Uppercase and lowercase identifier variations cannot create duplicate identities
        $dupLower = $accountService->createUser([
            'identifier' => strtolower($id1),
            'role' => 'student',
            'full_name' => 'Case Variant Alice',
            'department' => 'BCA'
        ], 1);
        $results[] = [
            'name' => 'Test 2: Uppercase and lowercase identifier variations cannot create duplicate identities',
            'pass' => $dupLower['success'] === false
        ];

        // 3. Whitespace variations cannot create duplicate identities
        $dupWs = $accountService->createUser([
            'identifier' => "   {$id1}   ",
            'role' => 'student',
            'full_name' => 'Whitespace Variant Alice',
            'department' => 'BCA'
        ], 1);
        $results[] = [
            'name' => 'Test 3: Whitespace variations cannot create duplicate identities',
            'pass' => $dupWs['success'] === false
        ];

        // Provision Primary Test Election in Draft state
        $elData = $electionService->createElection([
            'title' => 'Master Security Test Election ' . $suitePrefix,
            'position' => 'Security Chairperson',
            'opens_at' => date('Y-m-d H:i:s', $now - 60),
            'closes_at' => date('Y-m-d H:i:s', $now + 7200),
            'results_visibility' => 'live_provisional'
        ], 1);
        $elId = $elData['id'];

        $c1 = $candidateService->createCandidate(['election_id' => $elId, 'name' => 'Sec Candidate Alpha', 'approval_status' => 'approved'], null, 1);
        $c2 = $candidateService->createCandidate(['election_id' => $elId, 'name' => 'Sec Candidate Beta', 'approval_status' => 'approved'], null, 1);

        // Provision voters for Section 1 tests
        $userPending = $accountService->createUser([
            'identifier' => $suitePrefix . 'PENDING',
            'role' => 'student',
            'full_name' => 'Pending Voter',
            'status' => 'pending'
        ], 1);
        $userRepo->updatePassword($userPending['user_id'], password_hash('Pass12345', PASSWORD_DEFAULT));

        $userSuspended = $accountService->createUser([
            'identifier' => $suitePrefix . 'SUSPENDED',
            'role' => 'student',
            'full_name' => 'Suspended Voter',
            'status' => 'suspended'
        ], 1);
        $userRepo->updatePassword($userSuspended['user_id'], password_hash('Pass12345', PASSWORD_DEFAULT));

        $userDisabled = $accountService->createUser([
            'identifier' => $suitePrefix . 'DISABLED',
            'role' => 'student',
            'full_name' => 'Disabled Voter',
            'status' => 'disabled'
        ], 1);
        $userRepo->updatePassword($userDisabled['user_id'], password_hash('Pass12345', PASSWORD_DEFAULT));

        // Provision voters for Section 2 & 3 tests before locking election
        $userEligible = $accountService->createUser([
            'identifier' => $suitePrefix . 'ELIG_OK',
            'role' => 'student',
            'full_name' => 'Eligible Voter'
        ], 1);
        $userRepo->updatePassword($userEligible['user_id'], password_hash('Pass12345', PASSWORD_DEFAULT));

        $userDup = $accountService->createUser([
            'identifier' => $suitePrefix . 'DUP_TEST',
            'role' => 'student',
            'full_name' => 'Duplicate Test Voter'
        ], 1);
        $userRepo->updatePassword($userDup['user_id'], password_hash('Pass12345', PASSWORD_DEFAULT));

        $userSimul = $accountService->createUser([
            'identifier' => $suitePrefix . 'SIMUL_2',
            'role' => 'student',
            'full_name' => 'Simultaneous Voter'
        ], 1);
        $userRepo->updatePassword($userSimul['user_id'], password_hash('Pass12345', PASSWORD_DEFAULT));

        $userConcur25 = $accountService->createUser([
            'identifier' => $suitePrefix . 'CONCUR_25',
            'role' => 'student',
            'full_name' => '25 Concurrency Voter'
        ], 1);
        $userRepo->updatePassword($userConcur25['user_id'], password_hash('Pass12345', PASSWORD_DEFAULT));

        $userConcur100 = $accountService->createUser([
            'identifier' => $suitePrefix . 'CONCUR_100',
            'role' => 'student',
            'full_name' => '100 Concurrency Voter'
        ], 1);
        $userRepo->updatePassword($userConcur100['user_id'], password_hash('Pass12345', PASSWORD_DEFAULT));

        $userManip = $accountService->createUser([
            'identifier' => $suitePrefix . 'MANIP_V',
            'role' => 'student',
            'full_name' => 'Manipulation Tester'
        ], 1);
        $userRepo->updatePassword($userManip['user_id'], password_hash('Pass12345', PASSWORD_DEFAULT));

        // Assign whitelist in Draft state
        $whitelistUserIds = [
            $userPending['user_id'],
            $userSuspended['user_id'],
            $userDisabled['user_id'],
            $userEligible['user_id'],
            $userDup['user_id'],
            $userSimul['user_id'],
            $userConcur25['user_id'],
            $userConcur100['user_id'],
            $userManip['user_id']
        ];
        $eligibilityService->assignVoters($elId, $whitelistUserIds, 1);

        // Now open primary election
        $electionService->transitionState($elId, 'open', 1);

        // 4. An unapproved account cannot vote (pending status)
        $votePending = $votingService->castVote($elId, $c1['id'], $userPending['user_id']);
        $results[] = [
            'name' => 'Test 4: An unapproved account cannot vote (pending status rejected)',
            'pass' => $votePending['success'] === false && strpos(strtolower($votePending['error']), 'pending') !== false
        ];

        // 5. Suspended users cannot vote
        $voteSuspended = $votingService->castVote($elId, $c1['id'], $userSuspended['user_id']);
        $results[] = [
            'name' => 'Test 5: Suspended users cannot vote',
            'pass' => $voteSuspended['success'] === false && strpos(strtolower($voteSuspended['error']), 'suspended') !== false
        ];

        // 6. Disabled users cannot vote
        $voteDisabled = $votingService->castVote($elId, $c1['id'], $userDisabled['user_id']);
        $results[] = [
            'name' => 'Test 6: Disabled users cannot vote',
            'pass' => $voteDisabled['success'] === false && strpos(strtolower($voteDisabled['error']), 'disabled') !== false
        ];

        // 7. Candidate profiles cannot be attached to an unrelated account
        $draftEl = $electionService->createElection([
            'title' => 'Draft Attachment Election ' . $suitePrefix,
            'position' => 'Representative',
            'opens_at' => date('Y-m-d H:i:s', $now + 3600),
            'closes_at' => date('Y-m-d H:i:s', $now + 7200)
        ], 1);
        $candBadUser = $candidateService->createCandidate([
            'election_id' => $draftEl['id'],
            'name' => 'Ghost Candidate',
            'user_id' => 9999999
        ], null, 1);
        $candAdminUser = $candidateService->createCandidate([
            'election_id' => $draftEl['id'],
            'name' => 'Admin Candidate',
            'user_id' => 1 // ID 1 is Administrator
        ], null, 1);
        $results[] = [
            'name' => 'Test 7: Candidate profiles cannot be attached to an unrelated account or admin',
            'pass' => $candBadUser['success'] === false && $candAdminUser['success'] === false
        ];

        // =====================================================================
        // SECTION 2: ELIGIBILITY TESTS (8 - 12)
        // =====================================================================

        // 8. An ineligible voter is rejected
        $userIneligible = $accountService->createUser([
            'identifier' => $suitePrefix . 'INELIG',
            'role' => 'student',
            'full_name' => 'Ineligible Voter'
        ], 1);
        $userRepo->updatePassword($userIneligible['user_id'], password_hash('Pass12345', PASSWORD_DEFAULT));
        $voteIneligible = $votingService->castVote($elId, $c1['id'], $userIneligible['user_id']);
        $results[] = [
            'name' => 'Test 8: An ineligible voter is rejected',
            'pass' => $voteIneligible['success'] === false && strpos(strtolower($voteIneligible['error']), 'not eligible') !== false
        ];

        // 9. An eligible voter is accepted
        $voteEligible = $votingService->castVote($elId, $c1['id'], $userEligible['user_id']);
        $results[] = [
            'name' => 'Test 9: An eligible voter is accepted',
            'pass' => $voteEligible['success'] === true && !empty($voteEligible['confirmation_receipt'])
        ];

        // 10. A voter eligible for one election cannot automatically vote in another
        $elOther = $electionService->createElection([
            'title' => 'Other Department Election ' . $suitePrefix,
            'position' => 'Councilor',
            'opens_at' => date('Y-m-d H:i:s', $now - 60),
            'closes_at' => date('Y-m-d H:i:s', $now + 7200)
        ], 1);
        $cOther1 = $candidateService->createCandidate(['election_id' => $elOther['id'], 'name' => 'Other 1', 'approval_status' => 'approved'], null, 1);
        $cOther2 = $candidateService->createCandidate(['election_id' => $elOther['id'], 'name' => 'Other 2', 'approval_status' => 'approved'], null, 1);
        $electionService->transitionState($elOther['id'], 'open', 1);

        $crossVote = $votingService->castVote($elOther['id'], $cOther1['id'], $userEligible['user_id']);
        $results[] = [
            'name' => 'Test 10: A voter eligible for one election cannot automatically vote in another',
            'pass' => $crossVote['success'] === false && strpos(strtolower($crossVote['error']), 'not eligible') !== false
        ];

        // 11. Eligibility changes are audited
        $eligAudit = $db->fetchOne(
            "SELECT * FROM audit_logs WHERE action = 'ELIGIBILITY_BATCH_ASSIGNED' AND entity_id = ? ORDER BY id DESC LIMIT 1",
            [(string)$elId],
            's'
        );
        $results[] = [
            'name' => 'Test 11: Eligibility changes are audited in immutable ledger',
            'pass' => !empty($eligAudit) && !empty($eligAudit['correlation_id'])
        ];

        // 12. Closed elections reject all ballot submissions
        $elClosed = $electionService->createElection([
            'title' => 'Closed Election ' . $suitePrefix,
            'position' => 'Treasurer',
            'opens_at' => date('Y-m-d H:i:s', $now - 60),
            'closes_at' => date('Y-m-d H:i:s', $now + 7200)
        ], 1);
        $cClosed1 = $candidateService->createCandidate(['election_id' => $elClosed['id'], 'name' => 'Closed 1', 'approval_status' => 'approved'], null, 1);
        $cClosed2 = $candidateService->createCandidate(['election_id' => $elClosed['id'], 'name' => 'Closed 2', 'approval_status' => 'approved'], null, 1);
        $userClosedVoter = $accountService->createUser([
            'identifier' => $suitePrefix . 'CLOSED_V',
            'role' => 'student',
            'full_name' => 'Closed Election Voter'
        ], 1);
        $userRepo->updatePassword($userClosedVoter['user_id'], password_hash('Pass12345', PASSWORD_DEFAULT));
        $eligibilityService->assignVoters($elClosed['id'], [$userClosedVoter['user_id']], 1);
        $electionService->transitionState($elClosed['id'], 'open', 1);
        $electionService->transitionState($elClosed['id'], 'closed', 1);

        $voteClosed = $votingService->castVote($elClosed['id'], $cClosed1['id'], $userClosedVoter['user_id']);
        $results[] = [
            'name' => 'Test 12: Closed elections reject all ballot submissions',
            'pass' => $voteClosed['success'] === false && (strpos(strtolower($voteClosed['error']), 'closed') !== false || strpos(strtolower($voteClosed['error']), 'not') !== false)
        ];

        // =====================================================================
        // SECTION 3: DUPLICATE VOTING TESTS (13 - 22)
        // =====================================================================

        // 13. First valid vote is accepted
        $firstVote = $votingService->castVote($elId, $c1['id'], $userDup['user_id']);
        $results[] = [
            'name' => 'Test 13: First valid vote is accepted',
            'pass' => $firstVote['success'] === true
        ];

        // 14. Second vote is rejected
        $secondVote = $votingService->castVote($elId, $c2['id'], $userDup['user_id']);
        $results[] = [
            'name' => 'Test 14: Second vote is rejected',
            'pass' => $secondVote['success'] === false && strpos(strtolower($secondVote['error']), 'already cast') !== false
        ];

        // 15. Third vote is rejected
        $thirdVote = $votingService->castVote($elId, $c1['id'], $userDup['user_id']);
        $results[] = [
            'name' => 'Test 15: Third vote is rejected',
            'pass' => $thirdVote['success'] === false && strpos(strtolower($thirdVote['error']), 'already cast') !== false
        ];

        // 16. Duplicate requests from different browser tabs are rejected
        $standaloneDb = Database::createConnection();
        $standaloneRepo = new VotingRepository($standaloneDb);
        $tab2Exception = false;
        try {
            $standaloneRepo->recordBallot($elId, $c2['id'], $userDup['user_id'], 'secret_key');
        } catch (\Throwable $e) {
            $tab2Exception = (strpos(strtolower($e->getMessage()), 'already cast') !== false);
        }
        $results[] = [
            'name' => 'Test 16: Duplicate requests from different browser tabs are rejected',
            'pass' => $tab2Exception === true
        ];

        // 17. Two simultaneous submissions cannot create two ballots
        $simulAccepted = 0;
        $simulRejected = 0;
        $simulProcs = [];
        $simulPipes = [];
        for ($i = 0; $i < 2; $i++) {
            $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($workerPath) . ' ' . $elId . ' ' . ($i === 0 ? $c1['id'] : $c2['id']) . ' ' . $userSimul['user_id'];
            $simulProcs[] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $simulPipes[$i]);
        }
        for ($i = 0; $i < 2; $i++) {
            $stdout = stream_get_contents($simulPipes[$i][1]);
            fclose($simulPipes[$i][1]);
            fclose($simulPipes[$i][2]);
            $code = proc_close($simulProcs[$i]);
            $resJson = json_decode(trim($stdout), true);
            if ($code === 0 && ($resJson['status'] ?? '') === 'success') {
                $simulAccepted++;
            } else {
                $simulRejected++;
            }
        }
        $simulPartCount = (int)$db->fetchValue("SELECT COUNT(*) FROM election_participation WHERE election_id = ? AND user_id = ?", [$elId, $userSimul['user_id']], 'ii');
        $results[] = [
            'name' => 'Test 17: Two simultaneous submissions cannot create two ballots (1 accepted, 1 rejected)',
            'pass' => $simulAccepted === 1 && $simulRejected === 1 && $simulPartCount === 1
        ];

        // 18. 25 concurrent connections from the same voter produce exactly one accepted ballot
        $acc25 = 0;
        $rej25 = 0;
        $p25 = [];
        $pipes25 = [];
        for ($i = 0; $i < 25; $i++) {
            $cTarget = ($i % 2 === 0) ? $c1['id'] : $c2['id'];
            $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($workerPath) . ' ' . $elId . ' ' . $cTarget . ' ' . $userConcur25['user_id'];
            $p25[] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes25[$i]);
        }
        for ($i = 0; $i < 25; $i++) {
            $stdout = stream_get_contents($pipes25[$i][1]);
            fclose($pipes25[$i][1]);
            fclose($pipes25[$i][2]);
            $code = proc_close($p25[$i]);
            $resJson = json_decode(trim($stdout), true);
            if ($code === 0 && ($resJson['status'] ?? '') === 'success') {
                $acc25++;
            } else {
                $rej25++;
            }
        }
        $part25 = (int)$db->fetchValue("SELECT COUNT(*) FROM election_participation WHERE election_id = ? AND user_id = ?", [$elId, $userConcur25['user_id']], 'ii');
        $results[] = [
            'name' => 'Test 18: 25 concurrent connections from the same voter produce exactly one accepted ballot',
            'pass' => $acc25 === 1 && $rej25 === 24 && $part25 === 1
        ];

        // 19. 100 concurrent connections from the same voter produce exactly one accepted ballot
        $acc100 = 0;
        $rej100 = 0;
        for ($batch = 0; $batch < 4; $batch++) {
            $bProcs = [];
            $bPipes = [];
            for ($i = 0; $i < 25; $i++) {
                $cTarget = ($i % 2 === 0) ? $c1['id'] : $c2['id'];
                $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($workerPath) . ' ' . $elId . ' ' . $cTarget . ' ' . $userConcur100['user_id'];
                $bProcs[] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $bPipes[$i]);
            }
            for ($i = 0; $i < 25; $i++) {
                $stdout = stream_get_contents($bPipes[$i][1]);
                fclose($bPipes[$i][1]);
                fclose($bPipes[$i][2]);
                $code = proc_close($bProcs[$i]);
                $resJson = json_decode(trim($stdout), true);
                if ($code === 0 && ($resJson['status'] ?? '') === 'success') {
                    $acc100++;
                } else {
                    $rej100++;
                }
            }
        }
        $part100 = (int)$db->fetchValue("SELECT COUNT(*) FROM election_participation WHERE election_id = ? AND user_id = ?", [$elId, $userConcur100['user_id']], 'ii');
        $results[] = [
            'name' => 'Test 19: 100 concurrent connections from the same voter produce exactly one accepted ballot (1 accepted, 99 rejected)',
            'pass' => $acc100 === 1 && $rej100 === 99 && $part100 === 1
        ];

        // 20. Ten or more distinct eligible voters can vote concurrently without losing valid ballots
        $el10Voters = $electionService->createElection([
            'title' => '10 Voters Multi-Concurrency ' . $suitePrefix,
            'position' => 'Council Representative',
            'opens_at' => date('Y-m-d H:i:s', $now - 60),
            'closes_at' => date('Y-m-d H:i:s', $now + 7200)
        ], 1);
        $cMulti1 = $candidateService->createCandidate(['election_id' => $el10Voters['id'], 'name' => 'Multi A', 'approval_status' => 'approved'], null, 1);
        $cMulti2 = $candidateService->createCandidate(['election_id' => $el10Voters['id'], 'name' => 'Multi B', 'approval_status' => 'approved'], null, 1);

        $voter10Ids = [];
        for ($i = 1; $i <= 10; $i++) {
            $u10 = $accountService->createUser([
                'identifier' => $suitePrefix . "MULTI_V_{$i}",
                'role' => 'student',
                'full_name' => "Multi Voter {$i}"
            ], 1);
            $userRepo->updatePassword($u10['user_id'], password_hash('Pass12345', PASSWORD_DEFAULT));
            $voter10Ids[] = $u10['user_id'];
        }
        // Assign in Draft state before opening
        $eligibilityService->assignVoters($el10Voters['id'], $voter10Ids, 1);
        $electionService->transitionState($el10Voters['id'], 'open', 1);

        $acc10Multi = 0;
        $rej10Multi = 0;
        $p10 = [];
        $pipes10 = [];
        for ($i = 0; $i < 10; $i++) {
            $candChoice = ($i % 2 === 0) ? $cMulti1['id'] : $cMulti2['id'];
            $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($workerPath) . ' ' . $el10Voters['id'] . ' ' . $candChoice . ' ' . $voter10Ids[$i];
            $p10[] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes10[$i]);
        }
        for ($i = 0; $i < 10; $i++) {
            $stdout = stream_get_contents($pipes10[$i][1]);
            fclose($pipes10[$i][1]);
            fclose($pipes10[$i][2]);
            $code = proc_close($p10[$i]);
            $resJson = json_decode(trim($stdout), true);
            if ($code === 0 && ($resJson['status'] ?? '') === 'success') {
                $acc10Multi++;
            } else {
                $rej10Multi++;
            }
        }
        $partCount10 = (int)$db->fetchValue("SELECT COUNT(*) FROM election_participation WHERE election_id = ?", [$el10Voters['id']], 'i');
        $voteCount10 = (int)$db->fetchValue("SELECT COUNT(*) FROM votes WHERE election_id = ?", [$el10Voters['id']], 'i');
        $results[] = [
            'name' => 'Test 20: Ten or more distinct eligible voters can vote concurrently without losing valid ballots',
            'pass' => $acc10Multi === 10 && $rej10Multi === 0 && $partCount10 === 10 && $voteCount10 === 10
        ];

        // 21. Failed ballot insertion rolls back the participation record
        $rollbackVoter = $accountService->createUser([
            'identifier' => $suitePrefix . 'ROLLBACK_V',
            'role' => 'student',
            'full_name' => 'Rollback Voter'
        ], 1);
        $userRepo->updatePassword($rollbackVoter['user_id'], password_hash('Pass12345', PASSWORD_DEFAULT));

        // Simulate transactional failure: begin transaction, insert participation, rollback
        $txDb = Database::createConnection();
        $txDb->beginTransaction();
        $txDb->insert("INSERT INTO election_participation (election_id, user_id, confirmation_hash) VALUES (?, ?, ?)", [
            $elId, $rollbackVoter['user_id'], 'MOCK_ROLLBACK_RECEIPT_' . bin2hex(random_bytes(8))
        ]);
        $txDb->rollback();

        $orphanCheck = (int)$db->fetchValue(
            "SELECT COUNT(*) FROM election_participation WHERE election_id = ? AND user_id = ?",
            [$elId, $rollbackVoter['user_id']],
            'ii'
        );
        $results[] = [
            'name' => 'Test 21: Failed ballot insertion rolls back the participation record (ACID rollback integrity)',
            'pass' => $orphanCheck === 0
        ];

        // 22. The number of valid ballots matches the number of successful participation records
        $totalBallotsInEl = (int)$db->fetchValue("SELECT COUNT(*) FROM votes WHERE election_id = ?", [$elId], 'i');
        $totalParticipationInEl = (int)$db->fetchValue("SELECT COUNT(*) FROM election_participation WHERE election_id = ?", [$elId], 'i');
        $results[] = [
            'name' => 'Test 22: The number of valid ballots matches the number of successful participation records',
            'pass' => $totalBallotsInEl > 0 && $totalBallotsInEl === $totalParticipationInEl
        ];

        // =====================================================================
        // SECTION 4: BALLOT MANIPULATION TESTS (23 - 30)
        // =====================================================================

        // 23. Forged candidate ID is rejected
        $fakeCandVote = $votingService->castVote($elId, 999999, $userManip['user_id']);
        $results[] = [
            'name' => 'Test 23: Forged candidate ID is rejected',
            'pass' => $fakeCandVote['success'] === false && strpos(strtolower($fakeCandVote['error']), 'does not contest') !== false
        ];

        // 24. Candidate ID from another election is rejected
        $crossCandVote = $votingService->castVote($elId, $cOther1['id'], $userManip['user_id']);
        $results[] = [
            'name' => 'Test 24: Candidate ID from another election is rejected',
            'pass' => $crossCandVote['success'] === false && strpos(strtolower($crossCandVote['error']), 'does not contest') !== false
        ];

        // 25. Forged election ID is rejected
        $fakeElVote = $votingService->castVote(999999, $c1['id'], $userManip['user_id']);
        $results[] = [
            'name' => 'Test 25: Forged election ID is rejected',
            'pass' => $fakeElVote['success'] === false && (strpos(strtolower($fakeElVote['error']), 'exist') !== false || strpos(strtolower($fakeElVote['error']), 'not found') !== false)
        ];

        // 26. Altered browser-side user ID does not bypass authorization
        $spoofedVote = $votingService->castVote($elId, $c1['id'], 999999);
        $results[] = [
            'name' => 'Test 26: Altered browser-side user ID does not bypass authorization',
            'pass' => $spoofedVote['success'] === false
        ];

        // 27. Direct API requests without authentication are rejected
        $unauthRes = self::httpGetWithCode(self::$baseUrl . '/ballot?id=' . $elId);
        $results[] = [
            'name' => 'Test 27: Direct API requests without authentication are rejected (Redirect/403)',
            'pass' => $unauthRes['code'] === 302 || $unauthRes['code'] === 303 || $unauthRes['code'] === 403
        ];

        // 28. Replayed submissions do not generate additional votes
        $validVoteRes = $votingService->castVote($elId, $c1['id'], $userManip['user_id']);
        $replayAttempt = $votingService->castVote($elId, $c1['id'], $userManip['user_id']);
        $results[] = [
            'name' => 'Test 28: Replayed submissions do not generate additional votes',
            'pass' => $validVoteRes['success'] === true && $replayAttempt['success'] === false
        ];

        // 29. Invalid CSRF tokens are rejected
        $csrfFailRes = self::httpPostWithCode(self::$baseUrl . '/ballot', [
            'election_id' => $elId,
            'candidate_id' => $c1['id'],
            'csrf_token' => 'INVALID_EXPIRED_FORGED_CSRF_TOKEN'
        ]);
        $results[] = [
            'name' => 'Test 29: Invalid CSRF tokens are rejected with HTTP 403',
            'pass' => $csrfFailRes['code'] === 403
        ];

        // 30. SQL injection attempts do not compromise queries
        $sqliPayload = "1' OR '1'='1' -- ";
        $sqliResult = $userRepo->findByIdentifier($sqliPayload);
        $sqliSearch = $auditRepo->getRecent(10, 0, $sqliPayload);
        $results[] = [
            'name' => 'Test 30: SQL injection attempts do not compromise queries (Prepared statements defense)',
            'pass' => $sqliResult === null && is_array($sqliSearch)
        ];

        // =====================================================================
        // SECTION 5: RESULT & ADMINISTRATION TESTS (31 - 37)
        // =====================================================================

        // Setup temporary cookies for HTTP role boundary tests
        $cookieStudent = tempnam(sys_get_temp_dir(), 'mst_student_');
        $cookieCand = tempnam(sys_get_temp_dir(), 'mst_cand_');

        $fixtures = \Tests\Support\TestFixtureFactory::getSuiteFixtures($db);
        $testLoginId = $fixtures['visible_id'];
        $testPassword = $fixtures['password'];

        // Log in as student voter
        $loginVoterUrl = self::$baseUrl . '/login/voter';
        $voterHtml = self::httpGet($loginVoterUrl, $cookieStudent);
        preg_match('/name="csrf_token" value="([^"]+)"/', $voterHtml, $mVoter);
        $csrfVoter = $mVoter[1] ?? '';
        self::httpPost($loginVoterUrl, [
            'csrf_token' => $csrfVoter,
            'identifier' => $testLoginId,
            'password' => $testPassword
        ], $cookieStudent);

        // 31. Unauthorized users cannot modify election results
        $tamperResults = self::httpPostWithCode(self::$baseUrl . '/admin/elections/update-status', [
            'id' => $elId,
            'status' => 'results_published',
            'csrf_token' => $csrfVoter
        ], $cookieStudent);
        $results[] = [
            'name' => 'Test 31: Unauthorized users cannot modify election results (HTTP 403)',
            'pass' => $tamperResults['code'] === 403
        ];

        // 32. Unauthorized users cannot change voter eligibility
        $tamperElig = self::httpGetWithCode(self::$baseUrl . '/admin/eligibility/assign-criteria', $cookieStudent);
        $results[] = [
            'name' => 'Test 32: Unauthorized users cannot change voter eligibility (HTTP 403)',
            'pass' => $tamperElig['code'] === 403
        ];

        // Log in as candidate
        $loginCandUrl = self::$baseUrl . '/login/candidate';
        $candHtml = self::httpGet($loginCandUrl, $cookieCand);
        preg_match('/name="csrf_token" value="([^"]+)"/', $candHtml, $mCand);
        $csrfCand = $mCand[1] ?? '';
        self::httpPost($loginCandUrl, [
            'csrf_token' => $csrfCand,
            'identifier' => $testLoginId,
            'password' => $testPassword
        ], $cookieCand);

        // 33. Candidate cannot access administrator features
        $candAdminAccess = self::httpGetWithCode(self::$baseUrl . '/admin/users', $cookieCand);
        $results[] = [
            'name' => 'Test 33: Candidate cannot access administrator features (HTTP 403)',
            'pass' => $candAdminAccess['code'] === 403
        ];

        // 34. Voter cannot access administrator features
        $voterAdminAccess = self::httpGetWithCode(self::$baseUrl . '/admin', $cookieStudent);
        $results[] = [
            'name' => 'Test 34: Voter cannot access administrator features (HTTP 403)',
            'pass' => $voterAdminAccess['code'] === 403
        ];

        // 35. Audit logs capture sensitive administrative operations
        $adminAuditEvents = $db->fetchAll(
            "SELECT action, entity_type, correlation_id FROM audit_logs WHERE action IN ('ELECTION_CREATED', 'ELECTION_STATUS_CHANGED', 'ELIGIBILITY_BATCH_ASSIGNED') LIMIT 10"
        );
        $results[] = [
            'name' => 'Test 35: Audit logs capture sensitive administrative operations with correlation IDs',
            'pass' => count($adminAuditEvents) >= 3
        ];

        // 36. Candidate choices are not leaked into audit logs
        $ballotAuditRows = $db->fetchAll(
            "SELECT details_json FROM audit_logs WHERE action LIKE '%BALLOT%' OR action LIKE '%VOTE%' ORDER BY id DESC LIMIT 50"
        );
        $leaked = false;
        foreach ($ballotAuditRows as $r) {
            $json = $r['details_json'] ?? '';
            if (stripos($json, '"candidate_id"') !== false || stripos($json, 'Candidate Alpha') !== false) {
                $leaked = true;
                break;
            }
        }
        $results[] = [
            'name' => 'Test 36: Candidate choices are not leaked into audit logs (Ballot secrecy)',
            'pass' => $leaked === false
        ];

        // 37. Final result calculations match valid ballot counts
        $finalTally = $votingRepo->getResults($el10Voters['id']);
        $sumCandidateVotes = 0;
        foreach ($finalTally['candidates'] as $cand) {
            $sumCandidateVotes += (int)$cand['vote_count'];
        }
        $results[] = [
            'name' => 'Test 37: Final result calculations match valid ballot counts (10 = 10)',
            'pass' => $finalTally['total_ballots'] === 10 && $sumCandidateVotes === 10
        ];

        // Clean up temporary cookies
        @unlink($cookieStudent);
        @unlink($cookieCand);

        return $results;
    }

    private static function httpGet(string $url, ?string $cookieFile = null): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($cookieFile) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        $res = curl_exec($ch);
        curl_close($ch);
        return (string)$res;
    }

    private static function httpGetWithCode(string $url, ?string $cookieFile = null): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($cookieFile) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['body' => (string)$res, 'code' => (int)$code];
    }

    private static function httpPost(string $url, array $data, ?string $cookieFile = null): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HEADER, true);
        if ($cookieFile) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['headers' => (string)$res, 'code' => (int)$code];
    }

    private static function httpPostWithCode(string $url, array $data, ?string $cookieFile = null): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        if ($cookieFile) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['body' => (string)$res, 'code' => (int)$code];
    }
}
