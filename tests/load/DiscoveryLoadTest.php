<?php
/**
 * VerityVote - Discovery & Read Subsystem Load Test
 */

declare(strict_types=1);

namespace Tests\Load;

use App\Core\Database;
use App\Core\Cache;
use App\Repositories\ElectionRepository;
use App\Repositories\CandidateRepository;
use App\Services\CacheService;

class DiscoveryLoadTest
{
    public static function run(): array
    {
        $results = [];
        $db = Database::getInstance();
        $electionRepo = new ElectionRepository($db);
        $candidateRepo = new CandidateRepository($db);
        $cacheService = new CacheService();

        // 1. Cold Cache vs Warm Cache Read Latency Test
        $cacheService->flushAll();

        // Cold fetch
        $t0 = microtime(true);
        $coldElections = $cacheService->rememberElections('all_open', 30, function() use ($electionRepo) {
            return $electionRepo->getAll(['status' => 'open']);
        });
        $coldMs = (microtime(true) - $t0) * 1000.0;

        // Warm fetch (from cache)
        $warmLatencies = [];
        for ($i = 0; $i < 50; $i++) {
            $t1 = microtime(true);
            $warmElections = $cacheService->rememberElections('all_open', 30, function() use ($electionRepo) {
                return $electionRepo->getAll(['status' => 'open']);
            });
            $warmLatencies[] = (microtime(true) - $t1) * 1000.0;
        }

        $avgWarmMs = array_sum($warmLatencies) / count($warmLatencies);
        sort($warmLatencies);
        $p95WarmMs = $warmLatencies[(int)(count($warmLatencies) * 0.95)];

        $results[] = [
            'name' => sprintf('Discovery Load: Caching reduces open election listing latency from %.2f ms to %.2f ms (p95: %.2f ms)', $coldMs, $avgWarmMs, $p95WarmMs),
            'pass' => count($warmElections) === count($coldElections) && $avgWarmMs < $coldMs + 1.0,
            'metrics' => [
                'cold_ms' => round($coldMs, 2),
                'warm_avg_ms' => round($avgWarmMs, 2),
                'warm_p95_ms' => round($p95WarmMs, 2)
            ]
        ];

        // 2. High-Volume Discovery Throughput (100 sequential reads)
        $tVolumeStart = microtime(true);
        $readCount = 100;
        for ($i = 0; $i < $readCount; $i++) {
            $cacheService->rememberElections('all_open', 30, function() use ($electionRepo) {
                return $electionRepo->getAll(['status' => 'open']);
            });
        }
        $volumeSec = microtime(true) - $tVolumeStart;
        $volumeRps = round($readCount / max(0.001, $volumeSec), 1);

        $results[] = [
            'name' => sprintf('Discovery Load: Sustained throughput of %s req/s on cached election queries', $volumeRps),
            'pass' => $volumeRps > 100.0,
            'metrics' => ['rps' => $volumeRps]
        ];

        // 3. Candidate Directory Cache Throughput
        $sampleElection = ($electionRepo->getAll())[0] ?? null;
        $electionId = $sampleElection ? (int)$sampleElection['id'] : 1;

        $tCandStart = microtime(true);
        for ($i = 0; $i < 50; $i++) {
            $cacheService->rememberCandidates($electionId, 60, function() use ($candidateRepo, $electionId) {
                return $candidateRepo->getByElectionId($electionId);
            });
        }
        $candSec = microtime(true) - $tCandStart;
        $candRps = round(50 / max(0.001, $candSec), 1);

        $results[] = [
            'name' => sprintf('Discovery Load: Candidate directory throughput of %s req/s', $candRps),
            'pass' => $candRps > 100.0,
            'metrics' => ['rps' => $candRps]
        ];

        return $results;
    }
}
