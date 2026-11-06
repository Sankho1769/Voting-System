<?php
/**
 * VerityVote - Database Backup Utility
 * 
 * Safely exports database schema and table data into a self-contained SQL file
 * without exposing credentials on the command line or in process listings.
 * 
 * Usage:
 *   php bin/backup_db.php [target_database] [output_file]
 * 
 * Examples:
 *   php bin/backup_db.php
 *   php bin/backup_db.php verityvote_db database/backups/manual_backup.sql
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    die("This backup utility can only be executed via the command line.\n");
}

$configFile = dirname(__DIR__) . '/config/config.php';
if (!file_exists($configFile)) {
    $configFile = dirname(__DIR__) . '/config/config.example.php';
}
$appConfig = require $configFile;
$dbConfig = $appConfig['database'];

$targetDb = !empty($argv[1]) ? trim($argv[1]) : $dbConfig['database'];

$backupsDir = dirname(__DIR__) . '/database/backups';
if (!is_dir($backupsDir)) {
    @mkdir($backupsDir, 0750, true);
}

$defaultFilename = 'backup_' . date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $targetDb) . '.sql';
$outputFile = !empty($argv[2]) ? trim($argv[2]) : ($backupsDir . '/' . $defaultFilename);

echo "========================================================\n";
echo " VerityVote - Database Backup Utility\n";
echo "========================================================\n";
echo "Source Host:     {$dbConfig['host']}:{$dbConfig['port']}\n";
echo "Database:        {$targetDb}\n";
echo "Destination:     {$outputFile}\n";
echo "Timestamp:       " . date('Y-m-d H:i:s') . "\n";
echo "--------------------------------------------------------\n";

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli(
    $dbConfig['host'],
    $dbConfig['username'],
    $dbConfig['password'],
    $targetDb,
    (int)($dbConfig['port'] ?? 3306)
);

if ($conn->connect_error) {
    fwrite(STDERR, "[ERROR] Connection failed: " . $conn->connect_error . "\n");
    exit(1);
}

$conn->set_charset($dbConfig['charset'] ?? 'utf8mb4');

// Retrieve all tables in target database
$tables = [];
$res = $conn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
if (!$res) {
    fwrite(STDERR, "[ERROR] Failed to query tables: " . $conn->error . "\n");
    exit(1);
}

while ($row = $res->fetch_row()) {
    $tables[] = $row[0];
}
$res->free();

if (empty($tables)) {
    fwrite(STDERR, "[WARNING] No tables found in database '{$targetDb}'.\n");
    exit(1);
}

$handle = @fopen($outputFile, 'wb');
if (!$handle) {
    fwrite(STDERR, "[ERROR] Cannot open destination file for writing: {$outputFile}\n");
    exit(1);
}

// Write Header
fwrite($handle, "-- =====================================================================\n");
fwrite($handle, "-- VerityVote - Automated Database Backup Archive\n");
fwrite($handle, "-- Database:      {$targetDb}\n");
fwrite($handle, "-- Generated:     " . date('Y-m-d H:i:s') . "\n");
fwrite($handle, "-- Server:        " . $conn->server_info . "\n");
fwrite($handle, "-- Tables:        " . count($tables) . "\n");
fwrite($handle, "-- =====================================================================\n\n");
fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n");
fwrite($handle, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO,STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';\n");
fwrite($handle, "SET NAMES utf8mb4;\n\n");

$totalRows = 0;

foreach ($tables as $table) {
    echo "Dumping table `{$table}`... ";
    
    // Structure
    $createRes = $conn->query("SHOW CREATE TABLE `{$table}`");
    if (!$createRes) {
        fwrite(STDERR, "\n[ERROR] Failed to get table structure for `{$table}`: " . $conn->error . "\n");
        fclose($handle);
        exit(1);
    }
    $createRow = $createRes->fetch_assoc();
    $createRes->free();
    
    $createSql = $createRow['Create Table'] ?? '';
    
    fwrite($handle, "-- -----------------------------------------------------\n");
    fwrite($handle, "-- Table structure for `{$table}`\n");
    fwrite($handle, "-- -----------------------------------------------------\n");
    fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
    fwrite($handle, $createSql . ";\n\n");
    
    // Data
    $dataRes = $conn->query("SELECT * FROM `{$table}`");
    if (!$dataRes) {
        fwrite(STDERR, "\n[ERROR] Failed to select data from `{$table}`: " . $conn->error . "\n");
        fclose($handle);
        exit(1);
    }
    
    $rowCount = $dataRes->num_rows;
    $totalRows += $rowCount;
    
    if ($rowCount > 0) {
        fwrite($handle, "-- Dumping data for table `{$table}` (" . $rowCount . " rows)\n");
        fwrite($handle, "LOCK TABLES `{$table}` WRITE;\n");
        
        $fields = $dataRes->fetch_fields();
        $batch = [];
        $batchSize = 100;
        
        while ($row = $dataRes->fetch_assoc()) {
            $values = [];
            foreach ($fields as $field) {
                $val = $row[$field->name];
                if ($val === null) {
                    $values[] = 'NULL';
                } else {
                    $values[] = "'" . $conn->real_escape_string((string)$val) . "'";
                }
            }
            $batch[] = "(" . implode(', ', $values) . ")";
            
            if (count($batch) >= $batchSize) {
                fwrite($handle, "INSERT INTO `{$table}` VALUES \n" . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }
        
        if (!empty($batch)) {
            fwrite($handle, "INSERT INTO `{$table}` VALUES \n" . implode(",\n", $batch) . ";\n");
        }
        
        fwrite($handle, "UNLOCK TABLES;\n\n");
    }
    $dataRes->free();
    
    echo "[{$rowCount} rows]\n";
}

fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
fwrite($handle, "-- Backup completed at " . date('Y-m-d H:i:s') . "\n");
fclose($handle);

$fileSizeBytes = filesize($outputFile);
$fileSizeFormatted = number_format($fileSizeBytes / 1024, 2) . ' KB';

echo "--------------------------------------------------------\n";
echo "Backup Status:   SUCCESS\n";
echo "Tables Dumped:   " . count($tables) . "\n";
echo "Total Rows:      {$totalRows}\n";
echo "Archive Size:    {$fileSizeFormatted} ({$fileSizeBytes} bytes)\n";
echo "File Path:       {$outputFile}\n";
echo "========================================================\n";
exit(0);
