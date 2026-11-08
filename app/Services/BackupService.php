<?php
/**
 * VerityVote - Database Backup and Archive Service
 *
 * Generates self-contained, transactional SQL backups of the database schema and records.
 * Strictly enforces file access safety and path traversal protection.
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use mysqli;
use RuntimeException;

class BackupService
{
    private array $dbConfig;
    private string $backupsDir;

    public function __construct(?array $dbConfig = null)
    {
        if ($dbConfig === null) {
            $configFile = dirname(__DIR__, 2) . '/config/config.php';
            if (!file_exists($configFile)) {
                $configFile = dirname(__DIR__, 2) . '/config/config.example.php';
            }
            $config = require $configFile;
            $this->dbConfig = $config['database'];
        } else {
            $this->dbConfig = $dbConfig;
        }

        $this->backupsDir = dirname(__DIR__, 2) . '/database/backups';
        if (!is_dir($this->backupsDir)) {
            @mkdir($this->backupsDir, 0750, true);
        }

        // Ensure directory is protected against direct web server access
        $htaccess = $this->backupsDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
        }
    }

    /**
     * Create a complete SQL backup of the specified database
     */
    public function createBackup(?string $targetDb = null, ?int $adminId = null): array
    {
        $dbName = $targetDb ?: ($this->dbConfig['database'] ?? 'verityvote_db');

        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = @new mysqli(
            $this->dbConfig['host'],
            $this->dbConfig['username'],
            $this->dbConfig['password'],
            $dbName,
            (int)($this->dbConfig['port'] ?? 3306)
        );

        if ($conn->connect_error) {
            return ['success' => false, 'error' => 'Connection failed: ' . $conn->connect_error];
        }

        $conn->set_charset($this->dbConfig['charset'] ?? 'utf8mb4');

        $tablesRes = $conn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        if (!$tablesRes) {
            $err = $conn->error;
            $conn->close();
            return ['success' => false, 'error' => 'Failed to query tables: ' . $err];
        }

        $tables = [];
        while ($row = $tablesRes->fetch_row()) {
            $tables[] = $row[0];
        }
        $tablesRes->free();

        $timestamp = date('Ymd_His');
        $cleanDbName = preg_replace('/[^a-zA-Z0-9_-]/', '', $dbName);
        $filename = "backup_{$timestamp}_{$cleanDbName}.sql";
        $filepath = $this->backupsDir . '/' . $filename;

        $fp = @fopen($filepath, 'w');
        if (!$fp) {
            $conn->close();
            return ['success' => false, 'error' => 'Failed to create backup file in destination directory.'];
        }

        // Write Header
        fwrite($fp, "-- ========================================================\n");
        fwrite($fp, "-- VerityVote Database Backup Archive\n");
        fwrite($fp, "-- Target Database: {$dbName}\n");
        fwrite($fp, "-- Host:            {$this->dbConfig['host']}:{$this->dbConfig['port']}\n");
        fwrite($fp, "-- Generated:       " . date('Y-m-d H:i:s') . "\n");
        fwrite($fp, "-- Total Tables:    " . count($tables) . "\n");
        fwrite($fp, "-- ========================================================\n\n");
        fwrite($fp, "SET FOREIGN_KEY_CHECKS = 0;\n");
        fwrite($fp, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
        fwrite($fp, "SET time_zone = '+00:00';\n\n");

        $totalRows = 0;

        foreach ($tables as $table) {
            $escapedTable = '`' . str_replace('`', '``', $table) . '`';

            fwrite($fp, "-- --------------------------------------------------------\n");
            fwrite($fp, "-- Structure and Data for table: {$escapedTable}\n");
            fwrite($fp, "-- --------------------------------------------------------\n");
            fwrite($fp, "DROP TABLE IF EXISTS {$escapedTable};\n");

            // Create Table DDL
            $createRes = $conn->query("SHOW CREATE TABLE {$escapedTable}");
            if ($createRes && ($createRow = $createRes->fetch_row())) {
                fwrite($fp, $createRow[1] . ";\n\n");
                $createRes->free();
            }

            // Table Data DML
            $dataRes = $conn->query("SELECT * FROM {$escapedTable}");
            if ($dataRes) {
                $numFields = $dataRes->field_count;
                $tableRows = 0;

                while ($row = $dataRes->fetch_row()) {
                    $tableRows++;
                    $totalRows++;

                    $values = [];
                    for ($i = 0; $i < $numFields; $i++) {
                        if ($row[$i] === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . $conn->real_escape_string($row[$i]) . "'";
                        }
                    }

                    fwrite($fp, "INSERT INTO {$escapedTable} VALUES (" . implode(', ', $values) . ");\n");
                }
                $dataRes->free();
                fwrite($fp, "\n");
            }
        }

        fwrite($fp, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fwrite($fp, "-- End of VerityVote Backup Archive\n");
        fclose($fp);
        $conn->close();

        $size = filesize($filepath);

        if ($adminId !== null) {
            Logger::audit('DATABASE_BACKUP_CREATED', 'system', 'database', [
                'filename' => $filename,
                'target_database' => $dbName,
                'tables_count' => count($tables),
                'total_rows' => $totalRows,
                'size_bytes' => $size
            ], $adminId);
        }

        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => $size,
            'tables_count' => count($tables),
            'total_rows' => $totalRows
        ];
    }

    /**
     * List all available backup files in the backups directory
     */
    public function listBackups(): array
    {
        $backups = [];
        $files = @scandir($this->backupsDir);
        if (!$files) {
            return [];
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === '.htaccess' || $file === '.gitkeep') {
                continue;
            }

            if (preg_match('/^backup_.*\.sql$/i', $file)) {
                $path = $this->backupsDir . '/' . $file;
                $size = @filesize($path) ?: 0;
                $mtime = @filemtime($path) ?: time();

                $backups[] = [
                    'filename' => $file,
                    'size_bytes' => $size,
                    'formatted_size' => $this->formatBytes($size),
                    'created_at' => date('Y-m-d H:i:s', $mtime),
                    'age' => $this->formatAge($mtime)
                ];
            }
        }

        // Sort newest first
        usort($backups, fn($a, $b) => strcmp($b['filename'], $a['filename']));

        return $backups;
    }

    /**
     * Get validated absolute path to backup file for secure download
     */
    public function getBackupPath(string $filename): ?string
    {
        $clean = basename($filename);
        if (!preg_match('/^backup_[a-zA-Z0-9_\-\.]+\.sql$/i', $clean)) {
            return null;
        }

        $fullPath = realpath($this->backupsDir . '/' . $clean);
        $baseDir = realpath($this->backupsDir);

        if (!$fullPath || !$baseDir || strpos($fullPath, $baseDir) !== 0 || !file_exists($fullPath)) {
            return null;
        }

        return $fullPath;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    private function formatAge(int $timestamp): string
    {
        $diff = time() - $timestamp;
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . ' mins ago';
        if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
        return floor($diff / 86400) . ' days ago';
    }
}
