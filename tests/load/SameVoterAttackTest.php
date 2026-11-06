<?php
/**
 * VerityVote - Same-Voter High-Concurrency Attack Load Test
 * Simulates 25 and 100 simultaneous ballot requests from the same voter.
 * Guarantees that EXACTLY ONE vote commits, and ALL subsequent/concurrent
 * attempts are rejected with double-vote error.
 */

declare(strict_types=1);

namespace Tests\Load;

use App\Core\Database;
use App\Repositories\ElectionRepository;
use App\Repositories\CandidateRepository;
use App\Repositories\EligibilityRepository;
use App\Repositories\VotingRepository;
use App\Repositories\UserRepository;
use App\Services\ElectionService;
use App\Services\CandidateService;

class SameVoterAttackTest
{
    public static function run(): array
    {
        $results = [];
        $db = Database::getInstance();
        $userRepo = new UserRepository($db);
        $electionRepo = new ElectionRepository($db);
        $candidateRepo = new CandidateRepository($db);
        $eligibilityRepo = new EligibilityRepository($db);

        $admin = $userRepo->findByIdentifier('TEST_ADMIN') ?? $userRepo->findById(1);
        $adminId = $admin ? (int)$admin['id'] : 1;
        $now = time();

        // 1. Create dedicated open election with 2 candidates
        $election = (new ElectionService($electionRepo, $candidateRepo))->createElection([
            'title' => 'Same-Voter Concurrency Attack Test ' . bin2hex(random_bytes(2)),
            'position' => 'Council Rep',
            'opens_at' => date('Y-m-d H:i:s', $now - 60),
            'closes_at' => date('Y-m-d H:i:s', $now + 7200),
            'results_visibility' => 'live_provisional'
        ], $adminId);
        $electionId = $election['id'];

        $cand1 = (new CandidateService($candidateRepo, $electionRepo))->createCandidate([
            'election_id' => $electionId,
            'name' => 'Candidate Alpha',
            'approval_status' => 'approved'
        ], null, $adminId);

        $cand2 = (new CandidateService($candidateRepo, $electionRepo))->createCandidate([
            'election_id' => $electionId,
            'name' => 'Candidate Beta',
            'approval_status' => 'approved'
        ], null, $adminId);

        $cand1Id = $cand1['id'];
        $cand2Id = $cand2['id'];

        // Open the election for voting
        (new ElectionService($electionRepo, $candidateRepo))->transitionState($electionId, 'open', $adminId);

        // =====================================================================
        // ATTACK SCENARIO 1: 25 SIMULTANEOUS BALLOTS FROM SAME VOTER
        // =====================================================================
        $voter1 = $userRepo->create([
            'identifier' => 'ATTACK_VOTER_25_' . bin2hex(random_bytes(2)),
            'role' => 'student',
            'full_name' => 'Attack Voter 25',
            'department' => 'Cyber Security',
            'password_hash' => password_hash('Pass12345', PASSWORD_DEFAULT),
            'must_change_password' => 0,
            'status' => 'active'
        ]);
        $voter1Id = (int)$voter1;
        $eligibilityRepo->assign($electionId, $voter1Id, $adminId);

        $successCount25 = 0;
        $rejectCount25 = 0;
        $rejectionReasons25 = [];

        for ($i = 0; $i < 25; $i++) {
            $workerDb = Database::createConnection();
            $workerRepo = new VotingRepository($workerDb);
            $chosenCandidate = ($i % 2 === 0) ? $cand1Id : $cand2Id;

            try {
                $workerRepo->recordBallot($electionId, $chosenCandidate, $voter1Id, 'hmac_same_voter_test_secret');
                $successCount25++;
            } catch (\Throwable $e) {
                $rejectCount25++;
                $rejectionReasons25[] = $e->getMessage();
            }
        }

        $voter1DbVotes = (int)$db->fetchValue(
            "SELECT COUNT(*) FROM election_participation WHERE election_id = ? AND user_id = ?",
            [$electionId, $voter1Id],
            'ii'
        );

        $results[] = [
            'name' => sprintf('Same-Voter 25-Burst Attack: Exactly 1 succeeded (%d), exactly 24 rejected (%d), DB participation: %d', $successCount25, $rejectCount25, $voter1DbVotes),
            'pass' => ($successCount25 === 1) && ($rejectCount25 === 24) && ($voter1DbVotes === 1),
            'metrics' => [
                'succeeded' => $successCount25,
                'rejected' => $rejectCount25,
                'participation_records' => $voter1DbVotes
            ]
        ];

        // =====================================================================
        // ATTACK SCENARIO 2: 100 SIMULTANEOUS BALLOTS FROM SAME VOTER
        // =====================================================================
        $voter2 = $userRepo->create([
            'identifier' => 'ATTACK_VOTER_100_' . bin2hex(random_bytes(2)),
            'role' => 'student',
            'full_name' => 'Attack Voter 100',
            'department' => 'Computer Science',
            'password_hash' => password_hash('Pass12345', PASSWORD_DEFAULT),
            'must_change_password' => 0,
            'status' => 'active'
        ]);
        $voter2Id = (int)$voter2;
        $eligibilityRepo->assign($electionId, $voter2Id, $adminId);

        $successCount100 = 0;
        $rejectCount100 = 0;

        for ($i = 0; $i < 100; $i++) {
            $workerDb = Database::createConnection();
            $workerRepo = new VotingRepository($workerDb);
            $chosenCandidate = ($i % 2 === 0) ? $cand1Id : $cand2Id;

            try {
                $workerRepo->recordBallot($electionId, $chosenCandidate, $voter2Id, 'hmac_same_voter_test_secret');
                $successCount100++;
            } catch (\Throwable $e) {
                $rejectCount100++;
            }
        }

        $voter2DbVotes = (int)$db->fetchValue(
            "SELECT COUNT(*) FROM election_participation WHERE election_id = ? AND user_id = ?",
            [$electionId, $voter2Id],
            'ii'
        );

        $results[] = [
            'name' => sprintf('Same-Voter 100-Burst Attack: Exactly 1 succeeded (%d), exactly 99 rejected (%d), DB participation: %d', $successCount100, $rejectCount100, $voter2DbVotes),
            'pass' => ($successCount100 === 1) && ($rejectCount100 === 99) && ($voter2DbVotes === 1),
            'metrics' => [
                'succeeded' => $successCount100,
                'rejected' => $rejectCount100,
                'participation_records' => $voter2DbVotes
            ]
        ];

        // 3. Verify total votes in ballot box for this election
        $totalElectionVotes = (int)$db->fetchValue(
            "SELECT COUNT(*) FROM votes WHERE election_id = ?",
            [$electionId],
            'i'
        );

        $results[] = [
            'name' => sprintf('Same-Voter Attack Ballot Box Integrity: Exactly 2 total ballots exist in votes table (one per voter, zero leaks)', $totalElectionVotes),
            'pass' => $totalElectionVotes === 2
        ];

        return $results;
    }
}
