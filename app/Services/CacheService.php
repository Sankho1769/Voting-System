<?php
/**
 * VerityVote - Cache Service Orchestrator
 * High-level caching operations for public discovery, candidate directories,
 * and certified election results with strict cache invalidation rules.
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;

class CacheService
{
    private Cache $cache;

    public function __construct(?Cache $cache = null)
    {
        $this->cache = $cache ?? Cache::getInstance();
    }

    /**
     * Cache or retrieve public election directory listings
     */
    public function rememberElections(string $cacheKey, int $ttl, callable $fetcher): array
    {
        return $this->cache->remember('elections:query:' . $cacheKey, $ttl, $fetcher);
    }

    /**
     * Cache or retrieve election detail view
     */
    public function rememberElectionDetail(int $electionId, int $ttl, callable $fetcher): ?array
    {
        return $this->cache->remember('elections:detail:' . $electionId, $ttl, $fetcher);
    }

    /**
     * Cache or retrieve candidate profiles for an election
     */
    public function rememberCandidates(int $electionId, int $ttl, callable $fetcher): array
    {
        return $this->cache->remember('candidates:election:' . $electionId, $ttl, $fetcher);
    }

    /**
     * Cache or retrieve election results
     * Live provisional results: short TTL (e.g. 5-10s)
     * Published/Closed results: long TTL (e.g. 3600s)
     */
    public function rememberResults(int $electionId, int $ttl, callable $fetcher): array
    {
        return $this->cache->remember('results:election:' . $electionId, $ttl, $fetcher);
    }

    /**
     * Invalidate all election-related cached entries when an election changes status or metadata
     */
    public function invalidateElection(int $electionId): void
    {
        $this->cache->delete('elections:detail:' . $electionId);
        $this->cache->delete('results:election:' . $electionId);
        $this->cache->deletePrefix('elections:query:');
    }

    /**
     * Invalidate candidate cache when candidate is approved, added, or modified
     */
    public function invalidateCandidates(int $electionId): void
    {
        $this->cache->delete('candidates:election:' . $electionId);
        $this->cache->delete('elections:detail:' . $electionId);
        $this->cache->deletePrefix('elections:query:');
    }

    /**
     * Invalidate results cache when election closes or results are published
     */
    public function invalidateResults(int $electionId): void
    {
        $this->cache->delete('results:election:' . $electionId);
    }

    /**
     * Invalidate all application caches
     */
    public function flushAll(): void
    {
        $this->cache->clear();
    }
}
