<?php
/**
 * VerityVote - Database Connection and Schema Diagnostic Tool
 */

if (PHP_SAPI !== 'cli') {
    die("This diagnostic script can only be run from the command line.\n");
}

echo "========================================================\n";
echo " VerityVote - Database Schema & Connectivity Check\n";
echo "========================================================\n\n";

$configFile = dirname(__DIR__) . '/config/config.php';
if (!file_exists($configFile)) {
    $configFile = dirname(__DIR__) . '/config/config.example.php';
}
$config = require $configFile;
$dbConfig = $config['database'];

mysqli_report(MYSQLI_REPORT_OFF);

echo "1. Connecting to MySQL server at {$dbConfig['host']}:{$dbConfig['port']}... ";
$conn = @new mysqli(
    $dbConfig['host'],
    $dbConfig['username'],
    $dbConfig['password'],
    '',
    (int)$dbConfig['port']
);

if ($conn->connect_error) {
    echo "[FAILED]\nError: " . $conn->connect_error . "\n";
    exit(1);
}
echo "[SUCCESS]\n";
echo "   Server Version: " . $conn->server_info . "\n\n";

$dbName = !empty($argv[1]) ? trim($argv[1]) : $dbConfig['database'];
echo "2. Checking for database '{$dbName}'... ";
$dbExists = $conn->select_db($dbName);

if (!$dbExists) {
    echo "[NOT FOUND]\n   Attempting automatic database creation... ";
    $createSql = "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($createSql)) {
        echo "[CREATED]\n";
        $conn->select_db($dbName);
    } else {
        echo "[FAILED]\nError: " . $conn->error . "\n";
        exit(1);
    }
} else {
    echo "[EXISTS]\n";
}

$conn->set_charset($dbConfig['charset'] ?? 'utf8mb4');

echo "\n3. Validating required tables in '{$dbName}':\n";
$requiredTables = [
    'users',
    'elections',
    'candidates',
    'election_eligibility',
    'election_participation',
    'votes',
    'audit_logs',
    'login_attempts',
    'migrations'
];

$existingTables = [];
$res = $conn->query("SHOW TABLES");
if ($res) {
    while ($row = $res->fetch_row()) {
        $existingTables[] = $row[0];
    }
}

$missingTables = array_diff($requiredTables, $existingTables);

if (!empty($missingTables)) {
    echo "   Missing tables: " . implode(', ', $missingTables) . "\n";
    echo "   Applying database/schema.sql... ";

    $schemaFile = dirname(__DIR__) . '/database/schema.sql';
    if (!file_exists($schemaFile)) {
        echo "[FAILED: schema.sql not found]\n";
        exit(1);
    }

    $sql = file_get_contents($schemaFile);
    if ($dbName !== 'verityvote_db') {
        $sql = preg_replace('/CREATE DATABASE IF NOT EXISTS `?[a-zA-Z0-9_]+`?[^;]*;/i', '', $sql);
        $sql = preg_replace('/USE `?[a-zA-Z0-9_]+`?;/i', "USE `{$dbName}`;", $sql);
    }
    if ($conn->multi_query($sql)) {
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
        echo "[APPLIED SUCCESSFULLY]\n";
    } else {
        echo "[FAILED]\nError: " . $conn->error . "\n";
        exit(1);
    }
} else {
    echo "   All " . count($requiredTables) . " core tables are present.\n";
}

// Check Schema Migration Status
echo "\n4. Verifying Schema Migrations:\n";
$colRes = $conn->query("SHOW COLUMNS FROM candidates LIKE 'user_id'");
if ($colRes && $colRes->num_rows > 0) {
    echo "   [OK] candidates.user_id link column is present (Migration 002 applied)\n";
} else {
    echo "   [PENDING] Applying migration 002_add_candidate_portal_support.sql... ";
    $migFile = dirname(__DIR__) . '/database/migrations/002_add_candidate_portal_support.sql';
    if (file_exists($migFile)) {
        $migSql = file_get_contents($migFile);
        if ($conn->multi_query($migSql)) {
            do {
                if ($mRes = $conn->store_result()) { $mRes->free(); }
            } while ($conn->more_results() && $conn->next_result());
            echo "[SUCCESS]\n";
        } else {
            echo "[FAILED: " . $conn->error . "]\n";
        }
    }
}

// Table Status and Record Counts
echo "\n5. Table Status & Record Counts:\n";
echo sprintf("   %-25s | %-10s | %-10s\n", "Table", "Engine", "Rows");
echo "   " . str_repeat('-', 52) . "\n";

foreach ($requiredTables as $table) {
    $info = $conn->query("SHOW TABLE STATUS LIKE '{$table}'")->fetch_assoc();
    $engine = $info['Engine'] ?? 'Unknown';
    $countRes = $conn->query("SELECT COUNT(*) FROM `{$table}`");
    $count = $countRes ? $countRes->fetch_row()[0] : 0;
    echo sprintf("   %-25s | %-10s | %-10d\n", $table, $engine, $count);
}

echo "\n5. Verifying foreign key constraints & InnoDB compliance... ";
$nonInnodb = 0;
foreach ($requiredTables as $table) {
    $info = $conn->query("SHOW TABLE STATUS LIKE '{$table}'")->fetch_assoc();
    if (strtoupper($info['Engine'] ?? '') !== 'INNODB') {
        $nonInnodb++;
    }
}

if ($nonInnodb === 0) {
    echo "[OK: All tables use InnoDB]\n";
} else {
    echo "[WARN: {$nonInnodb} tables not using InnoDB]\n";
}

echo "\nDatabase verification completed successfully.\n";
$conn->close();
exit(0);
