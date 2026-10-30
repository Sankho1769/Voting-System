<?php
/**
 * VerityVote - Database Migration Runner
 * Executes pending SQL migrations safely and idempotently.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    die("This tool can only be run from the command line.\n");
}

echo "========================================================\n";
echo " VerityVote - Database Migration Runner\n";
echo "========================================================\n\n";

$configFile = dirname(__DIR__) . '/config/config.php';
if (!file_exists($configFile)) {
    $configFile = dirname(__DIR__) . '/config/config.example.php';
}
$config = require $configFile;
$dbConfig = $config['database'];

$dbName = !empty($argv[1]) ? trim($argv[1]) : $dbConfig['database'];

mysqli_report(MYSQLI_REPORT_OFF);

$conn = @new mysqli(
    $dbConfig['host'],
    $dbConfig['username'],
    $dbConfig['password'],
    $dbName,
    (int)$dbConfig['port']
);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}

$conn->set_charset($dbConfig['charset'] ?? 'utf8mb4');

// Ensure migrations table exists
$conn->query("CREATE TABLE IF NOT EXISTS `migrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` VARCHAR(255) NOT NULL UNIQUE,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

// Check applied migrations
$applied = [];
$res = $conn->query("SELECT migration FROM migrations");
if ($res) {
    while ($row = $res->fetch_row()) {
        $applied[] = $row[0];
    }
}

$migrationsDir = dirname(__DIR__) . '/database/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

$executed = 0;

foreach ($files as $file) {
    $migrationName = basename($file);
    if (in_array($migrationName, $applied, true)) {
        continue;
    }

    echo "Applying: {$migrationName}... ";
    
    // Check if migration 002 already applied via column check
    if ($migrationName === '002_add_candidate_portal_support.sql') {
        $colCheck = $conn->query("SHOW COLUMNS FROM candidates LIKE 'user_id'");
        if ($colCheck && $colCheck->num_rows > 0) {
            $stmt = $conn->prepare("INSERT IGNORE INTO migrations (migration) VALUES (?)");
            $stmt->bind_param('s', $migrationName);
            $stmt->execute();
            echo "[ALREADY APPLIED IN SCHEMA]\n";
            continue;
        }
    }

    $sql = file_get_contents($file);
    if ($conn->multi_query($sql)) {
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());

        $stmt = $conn->prepare("INSERT INTO migrations (migration) VALUES (?)");
        $stmt->bind_param('s', $migrationName);
        $stmt->execute();
        echo "[SUCCESS]\n";
        $executed++;
    } else {
        echo "[FAILED]\nError: " . $conn->error . "\n";
        exit(1);
    }
}

if ($executed === 0) {
    echo "All migrations are already up to date.\n";
} else {
    echo "\n{$executed} migration(s) applied successfully.\n";
}

$conn->close();
exit(0);
