<?php
/**
 * VerityVote - Observability & Metrics Collector
 * Lightweight in-memory / file-backed telemetry engine
 * supporting counters, gauges, timing histograms, and Prometheus format.
 */

declare(strict_types=1);

namespace App\Core;

class Metrics
{
    private static ?Metrics $instance = null;
    private array $counters = [];
    private array $gauges = [];
    private array $timings = [];
    private string $storagePath;

    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? (dirname(__DIR__, 2) . '/storage/logs/metrics_snapshot.json');
        $this->initializeDefaults();
    }

    public static function getInstance(): Metrics
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializeDefaults(): void
    {
        $this->counters = [
            'votes_submitted_total' => 0,
            'duplicate_votes_blocked_total' => 0,
            'cache_hits_total' => 0,
            'cache_misses_total' => 0,
            'http_requests_total' => 0,
            'auth_attempts_total' => 0,
            'auth_failures_total' => 0,
            'auth_lockouts_total' => 0
        ];
    }

    public function increment(string $counter, int $by = 1): void
    {
        if (!isset($this->counters[$counter])) {
            $this->counters[$counter] = 0;
        }
        $this->counters[$counter] += $by;
    }

    public function gauge(string $metric, float|int $value): void
    {
        $this->gauges[$metric] = $value;
    }

    public function recordTiming(string $metric, float $durationMs): void
    {
        if (!isset($this->timings[$metric])) {
            $this->timings[$metric] = [
                'count' => 0,
                'total_ms' => 0.0,
                'min_ms' => PHP_FLOAT_MAX,
                'max_ms' => 0.0
            ];
        }
        $this->timings[$metric]['count']++;
        $this->timings[$metric]['total_ms'] += $durationMs;
        if ($durationMs < $this->timings[$metric]['min_ms']) {
            $this->timings[$metric]['min_ms'] = $durationMs;
        }
        if ($durationMs > $this->timings[$metric]['max_ms']) {
            $this->timings[$metric]['max_ms'] = $durationMs;
        }
    }

    public function recordVoteSubmitted(): void
    {
        $this->increment('votes_submitted_total');
    }

    public function recordDuplicateVoteBlocked(): void
    {
        $this->increment('duplicate_votes_blocked_total');
    }

    public function recordCacheHit(): void
    {
        $this->increment('cache_hits_total');
    }

    public function recordCacheMiss(): void
    {
        $this->increment('cache_misses_total');
    }

    public function recordHttpRequest(string $route, string $method, int $statusCode, float $durationMs): void
    {
        $this->increment('http_requests_total');
        $this->recordTiming('http_request_duration_ms', $durationMs);
    }

    public function getMetricsSnapshot(): array
    {
        $timingSummaries = [];
        foreach ($this->timings as $name => $t) {
            $avg = $t['count'] > 0 ? round($t['total_ms'] / $t['count'], 2) : 0.0;
            $timingSummaries[$name] = [
                'count' => $t['count'],
                'avg_ms' => $avg,
                'min_ms' => $t['count'] > 0 ? round($t['min_ms'], 2) : 0.0,
                'max_ms' => round($t['max_ms'], 2)
            ];
        }

        return [
            'timestamp' => date('c'),
            'counters' => $this->counters,
            'gauges' => $this->gauges,
            'timings' => $timingSummaries
        ];
    }

    public function exportPrometheusFormat(): string
    {
        $lines = [];
        $lines[] = '# HELP verityvote_metrics Institutional Voting Telemetry';
        $lines[] = '# TYPE verityvote_counter counter';

        foreach ($this->counters as $counter => $val) {
            $lines[] = "verityvote_{$counter} {$val}";
        }

        foreach ($this->gauges as $gauge => $val) {
            $lines[] = "verityvote_{$gauge} {$val}";
        }

        foreach ($this->timings as $metric => $t) {
            $avg = $t['count'] > 0 ? round($t['total_ms'] / $t['count'], 2) : 0.0;
            $lines[] = "verityvote_{$metric}_count {$t['count']}";
            $lines[] = "verityvote_{$metric}_avg_ms {$avg}";
            $lines[] = "verityvote_{$metric}_max_ms {$t['max_ms']}";
        }

        return implode("\n", $lines) . "\n";
    }

    public function persistSnapshot(): void
    {
        $dir = dirname($this->storagePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($this->storagePath, json_encode($this->getMetricsSnapshot(), JSON_PRETTY_PRINT));
    }
}
