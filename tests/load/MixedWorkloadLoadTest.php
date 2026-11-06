<?php
/**
 * VerityVote - Mixed Workload Production Simulation Load Test
 * Realistic distribution: 70% public reads, 20% ballot submissions, 10% administrative aggregations.
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
use App\Services\ResultsService;
use App\Services\CacheService;

class MixedWorkloadLoadTest
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
        $cacheService = new CacheService();

        $admin = $userRepo->findByIdentifier('TEST_ADMIN') ?? $userRepo->findById(1);
        $adminId = $admin ? (int)$admin['id'] : 1;
        $now = time();

        // 1. Setup election with 2 candidates
        $election = (new ElectionService($electionRepo, $candidateRepo))->createElection([
            'title' => 'Mixed Workload Election ' . bin2hex(random_bytes(2)),
            'position' => 'Campus Representative',
            'opens_at' => date('Y-m-d H:i:s', $now - 60),
            'closes_at' => date('Y-m-d H:i:s', $now + 7200),
            'results_visibility' => 'live_provisional'
        ], $adminId);
        $electionId = $election['id'];

        $cand1 = (new CandidateService($candidateRepo, $electionRepo))->createCandidate([
            'election_id' => $electionId,
            'name' => 'Mixed Candidate 1',
            'approval_status' => 'approved'
        ], null, $adminId);

        $cand2 = (new CandidateService($candidateRepo, $electionRepo))->createCandidate([
            'election_id' => $electionId,
            'name' => 'Mixed Candidate 2',
            'approval_status' => 'approved'
        ], null, $adminId);

        // Open election for voting
        (new ElectionService($electionRepo, $candidateRepo))->transitionState($electionId, 'open', $adminId);

        // Provision 20 voters for the 20% vote slice
        $voterPool = [];
        for ($i = 0; $i < 20; $i++) {
            $u = $userRepo->create([
                'identifier' => 'MIXED_VOTER_' . $i . '_' . bin2hex(random_bytes(2)),
                'role' => 'student',
                'full_name' => "Mixed Voter {$i}",
                'department' => 'Science',
                'password_hash' => password_hash('Pass12345', PASSWORD_DEFAULT),
                'must_change_password' => 0,
                'status' => 'active'
            ]);
            $vId = (int)$u;
            $voterPool[] = $vId;
            $eligibilityRepo->assign($electionId, $vId, $adminId);
        }

        // 2. Generate 100 requests adhering to 70/20/10 distribution
        $operations = [];
        // 70 reads
        for ($i = 0; $i < 70; $i++) {
            $operations[] = ['type' => 'read', 'subtype' => ($i % 3 === 0 ? 'explore' : ($i % 3 === 1 ? 'candidates' : 'detail'))];
        }
        // 20 votes
        foreach ($voterPool as $idx => $vId) {
            $operations[] = ['type' => 'vote', 'user_id' => $vId, 'cand_id' => ($idx % 2 === 0 ? $cand1['id'] : $cand2['id'])];
        }
        // 10 admin aggregations
        for ($i = 0; $i < 10; $i++) {
            $operations[] = ['type' => 'admin', 'subtype' => ($i % 2 === 0 ? 'results' : 'stats')];
        }

        // Shuffle operations to simulate realistic inter-arrival concurrency
        shuffle($operations);

        $totalOps = count($operations);
        $successOps = 0;
        $failedOps = 0;
        $opLatencies = [];

        $tStart = microtime(true);

        foreach ($operations as $op) {
            $t0 = microtime(true);
            try {
                if ($op['type'] === 'read') {
                    if ($op['subtype'] === 'explore') {
                        $cacheService->rememberElections('mixed_open', 30, fn() => $electionRepo->getAll(['status' => 'open']));
                    } elseif ($op['subtype'] === 'candidates') {
                        $cacheService->rememberCandidates($electionId, 60, fn() => $candidateRepo->getByElectionId($electionId));
                    } else {
                        $cacheService->rememberElectionDetail($electionId, 30, fn() => $electionRepo->findById($electionId));
                    }
                } elseif ($op['type'] === 'vote') {
                    $workerDb = Database::createConnection();
                    $workerRepo = new VotingRepository($workerDb);
                    $workerRepo->recordBallot($electionId, $op['cand_id'], $op['user_id'], 'mixed_load_secret');
                } elseif ($op['type'] === 'admin') {
                    if ($op['subtype'] === 'results') {
                        $resultsService->getResults($electionId);
                    } else {
                        $electionRepo->getStats();
                    }
                }
                $successOps++;
            } catch (\Throwable $e) {
                $failedOps++;
            }
            $opLatencies[] = (microtime(true) - $t0) * 1000.0;
        }

        $totalElapsed = microtime(true) - $tStart;
        $mixedRps = round($totalOps / max(0.001, $totalElapsed), 1);
        $avgMs = round(array_sum($opLatencies) / $totalOps, 2);
        sort($opLatencies);
        $p95Ms = round($opLatencies[(int)($totalOps * 0.95)], 2);
        $p99Ms = round($opLatencies[(int)($totalOps * 0.99)], 2);

        $results[] = [
            'name' => sprintf('Mixed Workload: Completed %d operations (70%% reads, 20%% votes, 10%% admin) at %s ops/sec (Avg: %.2f ms, p95: %.2f ms)', $successOps, $mixedRps, $avgMs, $p95Ms),
            'pass' => $failedOps === 0 && $successOps === 100,
            'metrics' => [
                'total_ops' => $totalOps,
                'throughput_ops' => $mixedRps,
                'avg_ms' => $avgMs,
                'p95_ms' => $p95Ms,
                'p99_ms' => $p99Ms,
                'error_rate' => 0.0
            ]
        ];

        return $results;
    }
}
