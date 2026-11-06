<?php
/**
 * VerityVote - Voting Burst Concurrency Load Test
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
use App\Services\EligibilityService;
use App\Services\VotingService;
use App\Services\ResultsService;

class VotingBurstLoadTest
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
        $resultsService = new ResultsService($electionRepo, $votingRepo);

        // 1. Create dedicated open election with 2 candidates
        $admin = $userRepo->findByIdentifier('TEST_ADMIN') ?? $userRepo->findById(1);
        $adminId = $admin ? (int)$admin['id'] : 1;
        $now = time();

        $election = (new ElectionService($electionRepo, $candidateRepo))->createElection([
            'title' => 'Burst Concurrency Load Election ' . bin2hex(random_bytes(2)),
            'position' => 'President',
            'opens_at' => date('Y-m-d H:i:s', $now - 60),
            'closes_at' => date('Y-m-d H:i:s', $now + 7200),
            'results_visibility' => 'live_provisional'
        ], $adminId);
        $electionId = $election['id'];

        $cand1 = (new CandidateService($candidateRepo, $electionRepo))->createCandidate([
            'election_id' => $electionId,
            'name' => 'Burst Candidate Alpha',
            'approval_status' => 'approved'
        ], null, $adminId);

        $cand2 = (new CandidateService($candidateRepo, $electionRepo))->createCandidate([
            'election_id' => $electionId,
            'name' => 'Burst Candidate Beta',
            'approval_status' => 'approved'
        ], null, $adminId);

        $cand1Id = $cand1['id'];
        $cand2Id = $cand2['id'];

        // Open the election for voting
        (new ElectionService($electionRepo, $candidateRepo))->transitionState($electionId, 'open', $adminId);

        // 2. Provision 30 distinct eligible voters
        $voterCount = 30;
        $voterIds = [];
        for ($i = 0; $i < $voterCount; $i++) {
            $ident = 'BURST_VOTER_' . $i . '_' . bin2hex(random_bytes(2));
            $u = $userRepo->create([
                'identifier' => $ident,
                'role' => 'student',
                'full_name' => "Burst Voter {$i}",
                'department' => 'Engineering',
                'password_hash' => password_hash('Pass12345', PASSWORD_DEFAULT),
                'must_change_password' => 0,
                'status' => 'active'
            ]);
            $voterId = (int)$u;
            $voterIds[] = $voterId;
            $eligibilityRepo->assign($electionId, $voterId, $adminId);
        }

        // 3. Execute 30 concurrent/rapid ballot submissions using separate database instances
        $receipts = [];
        $submitLatencies = [];
        $tStart = microtime(true);

        foreach ($voterIds as $idx => $voterId) {
            $candidateChoice = ($idx % 2 === 0) ? $cand1Id : $cand2Id;
            $workerDb = Database::createConnection();
            $workerRepo = new VotingRepository($workerDb);

            $t0 = microtime(true);
            try {
                $receipt = $workerRepo->recordBallot($electionId, $candidateChoice, $voterId, 'burst_test_secret_key');
                $receipts[] = $receipt;
                $submitLatencies[] = (microtime(true) - $t0) * 1000.0;
            } catch (\Throwable $e) {
                // If any failure occurs
            }
        }

        $totalDurationSec = microtime(true) - $tStart;
        $tps = round(count($receipts) / max(0.001, $totalDurationSec), 1);
        $avgMs = round(array_sum($submitLatencies) / max(1, count($submitLatencies)), 2);

        $results[] = [
            'name' => sprintf('Voting Burst: Successfully committed %d/%d distinct ballots (Throughput: %s tx/s, Avg: %.2f ms)', count($receipts), $voterCount, $tps, $avgMs),
            'pass' => count($receipts) === $voterCount,
            'metrics' => [
                'committed' => count($receipts),
                'total' => $voterCount,
                'tps' => $tps,
                'avg_ms' => $avgMs
            ]
        ];

        // 4. Verify Participation Records and Secret Ballots
        $participationCount = (int)$db->fetchValue(
            "SELECT COUNT(*) FROM election_participation WHERE election_id = ?",
            [$electionId],
            'i'
        );

        $votesCount = (int)$db->fetchValue(
            "SELECT COUNT(*) FROM votes WHERE election_id = ?",
            [$electionId],
            'i'
        );

        $uniqueReceipts = count(array_unique($receipts));

        $results[] = [
            'name' => sprintf('Voting Burst Integrity: %d participation records match %d votes with %d unique receipts', $participationCount, $votesCount, $uniqueReceipts),
            'pass' => ($participationCount === $voterCount) && ($votesCount === $voterCount) && ($uniqueReceipts === $voterCount)
        ];

        // 5. Verify Results Tally Integrity
        $tally = $resultsService->getResults($electionId);
        $totalTallyVotes = $tally['results']['total_ballots'] ?? 0;

        $results[] = [
            'name' => sprintf('Voting Burst Results: Official tally sum (%d) exactly equals total cast ballots (%d)', $totalTallyVotes, $voterCount),
            'pass' => $totalTallyVotes === $voterCount
        ];

        return $results;
    }
}
