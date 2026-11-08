<?php
/**
 * VerityVote - Database Restore Utility
 *
 * Safely restores database schema and table data from a verified SQL backup file.
 * Streams queries individually to avoid packet overflow (max_allowed_packet limits).
 * Requires explicit --confirm flag when restoring to the active production database.
 *
 * Usage:
 *   php bin/restore_db.php <backup_file> [target_database] [--confirm]
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    die("This restore utility can only be executed via the command line.\n");
}

if ($argc < 2) {
    echo "Usage: php bin/restore_db.php <backup_file> [target_database] [--confirm]\n";
    exit(1);
}

$backupFile = trim($argv[1]);
$targetDb = null;
$confirmed = false;

for ($i = 2; $i < $argc; $i++) {
    $arg = trim($argv[$i]);
    if ($arg === '--confirm') {
        $confirmed = true;
    } elseif ($targetDb === null && strpos($arg, '--') !== 0) {
        $targetDb = $arg;
    }
}

if (!file_exists($backupFile)) {
    fwrite(STDERR, "[ERROR] Backup file not found: {$backupFile}\n");
    exit(1);
}

$configFile = dirname(__DIR__) . '/config/config.php';
if (!file_exists($configFile)) {
    $configFile = dirname(__DIR__) . '/config/config.example.php';
}
$appConfig = require $configFile;
$dbConfig = $appConfig['database'];

// Check test override if present
$overrideFile = dirname(__DIR__) . '/storage/.test_database_override';
$activeConfiguredDb = $dbConfig['database'];
if (file_exists($overrideFile)) {
    $overrideDb = trim((string)file_get_contents($overrideFile));
    if ($overrideDb !== '') {
        $activeConfiguredDb = $overrideDb;
    }
}

if ($targetDb === null) {
    $targetDb = $activeConfiguredDb;
}

// Strict Safety Guardrail: Prevent accidental overwriting of primary production database
if (strtolower((string)$targetDb) === 'verityvote_db') {
    $allowProd = getenv('ALLOW_PRODUCTION_RESTORE');
    if ($allowProd !== 'true' && $allowProd !== '1') {
        echo "========================================================\n";
        echo " [SAFETY LOCK] RESTORING DIRECTLY TO 'verityvote_db' BLOCKED\n";
        echo "========================================================\n";
        echo "Direct restore into the primary operational database is prohibited\n";
        echo "to prevent accidental data loss.\n";
        echo "Restores must be performed into disposable or staging databases.\n";
        echo "To force in a disaster recovery emergency:\n";
        echo "  set ALLOW_PRODUCTION_RESTORE=1\n";
        echo "  php bin/restore_db.php \"{$backupFile}\" verityvote_db --confirm\n";
        echo "========================================================\n";
        exit(1);
    }
}

// Safety check: if restoring into active configured database, require explicit confirmation
if ($targetDb === $activeConfiguredDb && !$confirmed) {
    echo "========================================================\n";
    echo " [ATTENTION] RESTORE SAFETY CONFIRMATION REQUIRED\n";
    echo "========================================================\n";
    echo "You are about to restore into the active configured database:\n";
    echo "  Database: '{$targetDb}'\n";
    echo "  Backup:   '{$backupFile}'\n\n";
    echo "This operation will overwrite existing tables in '{$targetDb}'.\n";
    echo "To proceed, run the command with the --confirm flag:\n";
    echo "  php bin/restore_db.php \"{$backupFile}\" {$targetDb} --confirm\n";
    echo "========================================================\n";
    exit(1);
}

echo "========================================================\n";
echo " VerityVote - Database Restore Utility\n";
echo "========================================================\n";
echo "Target Host:     {$dbConfig['host']}:{$dbConfig['port']}\n";
echo "Target Database: {$targetDb}\n";
echo "Backup Archive:  {$backupFile}\n";
echo "Timestamp:       " . date('Y-m-d H:i:s') . "\n";
echo "--------------------------------------------------------\n";

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli(
    $dbConfig['host'],
    $dbConfig['username'],
    $dbConfig['password'],
    '',
    (int)($dbConfig['port'] ?? 3306)
);

if ($conn->connect_error) {
    fwrite(STDERR, "[ERROR] Connection failed: " . $conn->connect_error . "\n");
    exit(1);
}

$conn->set_charset($dbConfig['charset'] ?? 'utf8mb4');

// Ensure target database exists
echo "1. Checking target database '{$targetDb}'... ";
if (!$conn->select_db($targetDb)) {
    echo "[CREATING]... ";
    $createSql = "CREATE DATABASE IF NOT EXISTS `{$targetDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($createSql)) {
        echo "[CREATED]\n";
        $conn->select_db($targetDb);
    } else {
        echo "[FAILED]\nError: " . $conn->error . "\n";
        exit(1);
    }
} else {
    echo "[EXISTS]\n";
}

$fileSizeKb = number_format(filesize($backupFile) / 1024, 2);
echo "2. Streaming and executing SQL statements from archive ({$fileSizeKb} KB)... ";

$handle = fopen($backupFile, 'rb');
if (!$handle) {
    fwrite(STDERR, "[FAILED]\nError: Cannot open backup file for reading.\n");
    exit(1);
}

// Temporary disable FK checks for restore session
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$conn->query("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO,STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");

$queryBuffer = '';
$executedStatements = 0;
$inMultilineComment = false;

while (($line = fgets($handle)) !== false) {
    $trimmedLine = trim($line);

    // Skip empty lines
    if ($trimmedLine === '') {
        continue;
    }

    // Skip single-line comments
    if (strpos($trimmedLine, '--') === 0 || strpos($trimmedLine, '#') === 0) {
        continue;
    }

    // Handle multi-line comment start
    if (strpos($trimmedLine, '/*') === 0) {
        if (strpos($trimmedLine, '*/') !== false) {
            continue;
        }
        $inMultilineComment = true;
        continue;
    }

    if ($inMultilineComment) {
        if (strpos($trimmedLine, '*/') !== false) {
            $inMultilineComment = false;
        }
        continue;
    }

    $queryBuffer .= $line;

    // Check if line terminates the SQL statement
    if (substr($trimmedLine, -1) === ';') {
        $stmt = trim($queryBuffer);
        $queryBuffer = '';

        if (!empty($stmt)) {
            if (!$conn->query($stmt)) {
                echo "\n[QUERY ERROR]\nStatement: " . substr($stmt, 0, 120) . "...\n";
                echo "Error: " . $conn->error . "\n";
                fclose($handle);
                $conn->query("SET FOREIGN_KEY_CHECKS = 1");
                exit(1);
            }
            $executedStatements++;
        }
    }
}

fclose($handle);
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "[APPLIED {$executedStatements} STATEMENTS]\n";

echo "3. Verifying restored tables and record counts in '{$targetDb}':\n";
$tablesRes = $conn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
$restoredTables = [];
if ($tablesRes) {
    while ($r = $tablesRes->fetch_row()) {
        $restoredTables[] = $r[0];
    }
    $tablesRes->free();
}

$totalRows = 0;
foreach ($restoredTables as $tbl) {
    $cntRes = $conn->query("SELECT COUNT(*) FROM `{$tbl}`");
    $cnt = $cntRes ? (int)$cntRes->fetch_row()[0] : 0;
    if ($cntRes) $cntRes->free();
    $totalRows += $cnt;
    printf("   - %-26s : %6d rows\n", $tbl, $cnt);
}

echo "--------------------------------------------------------\n";
echo "Restore Status:  SUCCESS\n";
echo "Tables Restored: " . count($restoredTables) . "\n";
echo "Total Rows:      {$totalRows}\n";
echo "========================================================\n";
exit(0);
