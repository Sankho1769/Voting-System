<?php
/**
 * VerityVote - Liveness Probe
 * Used by container orchestrators (Kubernetes / Docker Swarm / Load Balancers)
 * to verify that the web worker and PHP runtime are operational.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

http_response_code(200);
echo json_encode([
    'status' => 'alive',
    'timestamp' => date('c'),
    'php_version' => PHP_VERSION,
    'memory_usage_kb' => round(memory_get_usage(true) / 1024, 2)
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
