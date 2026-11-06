<?php
/**
 * VerityVote - Readiness Probe
 * Used by load balancers and orchestrators to determine whether the node
 * is ready to accept production voter traffic.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Database;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$checks = [];
$isHealthy = true;

// 1. Database Connectivity Check
try {
    $db = Database::getInstance();
    $ping = $db->fetchValue("SELECT 1");
    if ((int)$ping === 1) {
        $checks['database'] = 'ok';
    } else {
        $checks['database'] = 'failed (invalid ping response)';
        $isHealthy = false;
    }
} catch (\Throwable $e) {
    $checks['database'] = 'error: ' . $e->getMessage();
    $isHealthy = false;
}

// 2. Critical Tables Presence Check
if ($isHealthy) {
    try {
        $requiredTables = ['users', 'elections', 'candidates', 'election_eligibility', 'election_participation', 'votes', 'audit_logs'];
        $placeholders = implode(',', array_fill(0, count($requiredTables), '?'));
        $types = str_repeat('s', count($requiredTables));
        $rows = $db->fetchAll(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ({$placeholders})",
            $requiredTables,
            $types
        );
        $found = array_column($rows, 'table_name');
        $missing = array_diff($requiredTables, $found);

        if (empty($missing)) {
            $checks['schema_tables'] = 'ok';
        } else {
            $checks['schema_tables'] = 'missing: ' . implode(', ', $missing);
            $isHealthy = false;
        }
    } catch (\Throwable $e) {
        $checks['schema_tables'] = 'error: ' . $e->getMessage();
        $isHealthy = false;
    }
}

// 3. Storage Directory Write Permissions
$storageDirs = [
    'logs' => dirname(__DIR__, 2) . '/storage/logs',
    'cache' => dirname(__DIR__, 2) . '/storage/cache',
    'uploads' => dirname(__DIR__, 2) . '/storage/uploads'
];

$storageOk = true;
$storageDetails = [];
foreach ($storageDirs as $name => $path) {
    if (!is_dir($path)) {
        @mkdir($path, 0750, true);
    }
    $testFile = $path . '/.health_test_' . uniqid();
    $written = @file_put_contents($testFile, 'test');
    if ($written !== false) {
        @unlink($testFile);
        $storageDetails[$name] = 'writable';
    } else {
        $storageDetails[$name] = 'non-writable';
        $storageOk = false;
    }
}
$checks['storage'] = $storageOk ? 'ok' : 'degraded';
if (!$storageOk) {
    $isHealthy = false;
}

// 4. Memory Footprint Check
$memBytes = memory_get_usage(true);
$checks['memory'] = [
    'used_kb' => round($memBytes / 1024, 2),
    'status' => 'ok'
];

http_response_code($isHealthy ? 200 : 503);
echo json_encode([
    'status' => $isHealthy ? 'ready' : 'unhealthy',
    'checks' => $checks,
    'timestamp' => date('c'),
    'environment' => defined('APP_ENV') ? APP_ENV : 'production'
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
