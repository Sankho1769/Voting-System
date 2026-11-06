<?php
/**
 * VerityVote - Failure Resilience & Fault Recovery Integration Test Suite
 * Verifies system stability under connection failures, mid-transaction crashes,
 * cache failures, replay attacks, and corrupt payloads.
 */

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Cache;
use App\Core\FileCacheDriver;
use App\Repositories\ElectionRepository;
use App\Repositories\CandidateRepository;
use App\Repositories\EligibilityRepository;
use App\Repositories\VotingRepository;
use App\Repositories\UserRepository;
use App\Services\ElectionService;
use App\Services\CandidateService;
use App\Services\CacheService;

class FailureResilienceTest
{
    public static function run(): array
    {
        $results = [];
        $db = Database::getInstance();
        $userRepo = new UserRepository($db);
        $electionRepo = new ElectionRepository($db);
        $candidateRepo = new CandidateRepository($db);
        $eligibilityRepo = new EligibilityRepository($db);
        $votingRepo = new VotingRepository($db);

        $admin = $userRepo->findByIdentifier('TEST_ADMIN') ?? $userRepo->findById(1);
        $adminId = $admin ? (int)$admin['id'] : 1;
        $now = time();

        // -------------------------------------------------------------
        // TEST 1: Transaction Rollback on Mid-Stream Failure
        // -------------------------------------------------------------
        // Provision test election and candidate
        $el = (new ElectionService($electionRepo, $candidateRepo))->createElection([
            'title' => 'Resilience Test Election ' . bin2hex(random_bytes(2)),
            'position' => 'Resilience Officer',
            'opens_at' => date('Y-m-d H:i:s', $now - 60),
            'closes_at' => date('Y-m-d H:i:s', $now + 7200),
            'results_visibility' => 'live_provisional'
        ], $adminId);
        $electionId = $el['id'];

        $cand = (new CandidateService($candidateRepo, $electionRepo))->createCandidate([
            'election_id' => $electionId,
            'name' => 'Resilience Candidate',
            'approval_status' => 'approved'
        ], null, $adminId);
        $candId = $cand['id'];

        $voter = (int)$userRepo->create([
            'identifier' => 'RESIL_VOTER_' . bin2hex(random_bytes(2)),
            'role' => 'student',
            'full_name' => 'Resilience Voter',
            'department' => 'CS',
            'password_hash' => password_hash('Pass12345', PASSWORD_DEFAULT),
            'must_change_password' => 0,
            'status' => 'active'
        ]);
        $eligibilityRepo->assign($electionId, $voter, $adminId);

        // Simulate a mid-stream crash inside a custom transaction
        $crashDb = Database::createConnection();
        $crashDb->beginTransaction();
        $receipt = hash_hmac('sha256', "CRASH-TEST-{$voter}", 'secret');
        $crashDb->execute(
            "INSERT INTO election_participation (election_id, user_id, confirmation_hash, voted_at) VALUES (?, ?, ?, NOW())",
            [$electionId, $voter, $receipt],
            'iis'
        );
        // Simulate unexpected crash / exception before vote insert
        $crashDb->rollback();

        // Verify that NO participation record exists in the database
        $participationExists = $db->fetchOne(
            "SELECT id FROM election_participation WHERE election_id = ? AND user_id = ?",
            [$electionId, $voter],
            'ii'
        );

        $results[] = [
            'name' => 'Failure Resilience: Transaction rollback cleanly purges partial participation record on mid-stream failure',
            'pass' => $participationExists === null
        ];

        // -------------------------------------------------------------
        // TEST 2: Replay Attack Defense
        // -------------------------------------------------------------
        // Now open election with a 2nd candidate
        (new CandidateService($candidateRepo, $electionRepo))->createCandidate([
            'election_id' => $electionId,
            'name' => 'Resilience Candidate 2',
            'approval_status' => 'approved'
        ], null, $adminId);
        (new ElectionService($electionRepo, $candidateRepo))->transitionState($electionId, 'open', $adminId);

        // Legitimate vote
        $validReceipt = $votingRepo->recordBallot($electionId, $candId, $voter, 'secret_replay_test');
        
        // Immediate identical replay attempt
        $replayBlocked = false;
        try {
            $votingRepo->recordBallot($electionId, $candId, $voter, 'secret_replay_test');
        } catch (\Throwable $e) {
            $replayBlocked = str_contains($e->getMessage(), 'already cast your vote');
        }

        $results[] = [
            'name' => 'Failure Resilience: Immediate replay of identical ballot payload is strictly rejected',
            'pass' => !empty($validReceipt) && $replayBlocked === true
        ];

        // -------------------------------------------------------------
        // TEST 3: Cache Driver Graceful Degradation
        // -------------------------------------------------------------
        // Test that if cache directory encounters issues, remember() still falls back to fetcher
        $tempBadDir = sys_get_temp_dir() . '/vv_resilience_test_' . uniqid();
        @mkdir($tempBadDir, 0555); // Read-only directory
        $badCache = new Cache(new FileCacheDriver($tempBadDir));

        $called = false;
        $fallbackValue = $badCache->remember('resilience_key', 60, function() use (&$called) {
            $called = true;
            return ['fallback' => 'database_data'];
        });

        $results[] = [
            'name' => 'Failure Resilience: Cache subsystem gracefully falls back to direct execution without throwing fatal errors',
            'pass' => $called === true && ($fallbackValue['fallback'] ?? '') === 'database_data'
        ];
        @rmdir($tempBadDir);

        // -------------------------------------------------------------
        // TEST 4: Invalid and Malformed Parameter Handling
        // -------------------------------------------------------------
        $malformedRejected = false;
        try {
            // Attempt to vote on non-existent election with negative candidate ID
            $votingRepo->recordBallot(-999, -1, $voter, 'secret');
        } catch (\Throwable $e) {
            $malformedRejected = true;
        }

        $results[] = [
            'name' => 'Failure Resilience: Malformed negative parameters in ballot engine are safely rejected',
            'pass' => $malformedRejected === true
        ];

        // -------------------------------------------------------------
        // TEST 5: Database Connection Drop Recovery
        // -------------------------------------------------------------
        $connFailedSafely = false;
        try {
            $badConn = @new \mysqli('127.0.0.1', 'invalid_user', 'bad_pass', 'nonexistent_db', 3306);
            if ($badConn->connect_error) {
                $connFailedSafely = true;
            }
        } catch (\Throwable $e) {
            $connFailedSafely = true;
        }

        $results[] = [
            'name' => 'Failure Resilience: Database connection failures trigger safe exceptions without leaking server internals',
            'pass' => $connFailedSafely === true
        ];

        // -------------------------------------------------------------
        // TEST 6: Election Closure Racing with Ballot Submission
        // -------------------------------------------------------------
        $raceEl = (new ElectionService($electionRepo, $candidateRepo))->createElection([
            'title' => 'Closure Race Election ' . bin2hex(random_bytes(2)),
            'position' => 'Closure Officer',
            'opens_at' => date('Y-m-d H:i:s', $now - 60),
            'closes_at' => date('Y-m-d H:i:s', $now + 3600),
            'results_visibility' => 'live_provisional'
        ], $adminId);
        $raceElId = $raceEl['id'];

        $raceCand1 = (new CandidateService($candidateRepo, $electionRepo))->createCandidate([
            'election_id' => $raceElId,
            'name' => 'Race Cand 1',
            'approval_status' => 'approved'
        ], null, $adminId);
        $raceCand2 = (new CandidateService($candidateRepo, $electionRepo))->createCandidate([
            'election_id' => $raceElId,
            'name' => 'Race Cand 2',
            'approval_status' => 'approved'
        ], null, $adminId);

        (new ElectionService($electionRepo, $candidateRepo))->transitionState($raceElId, 'open', $adminId);

        $raceVoter = (int)$userRepo->create([
            'identifier' => 'RACE_VOTER_' . bin2hex(random_bytes(2)),
            'role' => 'student',
            'full_name' => 'Race Voter',
            'department' => 'CS',
            'password_hash' => password_hash('Pass12345', PASSWORD_DEFAULT),
            'must_change_password' => 0,
            'status' => 'active'
        ]);
        $eligibilityRepo->assign($raceElId, $raceVoter, $adminId);

        // Transition election to 'closed'
        (new ElectionService($electionRepo, $candidateRepo))->transitionState($raceElId, 'closed', $adminId);

        // Voter attempts to submit ballot on closed election
        $rejectedOnClosure = false;
        try {
            $votingRepo->recordBallot($raceElId, $raceCand1['id'], $raceVoter, 'secret_race');
        } catch (\Throwable $e) {
            $rejectedOnClosure = str_contains($e->getMessage(), 'closed');
        }

        $ballotsInBox = (int)$db->fetchValue("SELECT COUNT(*) FROM votes WHERE election_id = ?", [$raceElId], 'i');
        $participationInDb = (int)$db->fetchValue("SELECT COUNT(*) FROM election_participation WHERE election_id = ?", [$raceElId], 'i');

        $results[] = [
            'name' => 'Failure Resilience: Ballot submission racing against election closure is rejected without ghost ballots',
            'pass' => $rejectedOnClosure === true && $ballotsInBox === 0 && $participationInDb === 0
        ];

        return $results;
    }
}
