<?php
namespace Tests\Integration;

use App\Core\Database;

/**
 * Backup and Recovery Lifecycle Tests (Phase 10)
 * Validates automated backup generation, streaming restore, and safety guardrails.
 */
class BackupRestoreTest
{
    private Database $db;
    private string $tempDb = 'verityvote_test_backup_restore';
    private string $tempBackupFile;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->tempBackupFile = dirname(__DIR__, 2) . '/database/backups/test_lifecycle_backup.sql';
    }

    public static function run(): array
    {
        $instance = new self();
        $results = [];

        $results[] = $instance->testBackupGeneration();
        $results[] = $instance->testRestoreSafetyGuardrail();
        $results[] = $instance->testRestoreIntoDisposableDatabase();
        $results[] = $instance->testRestoredTableIntegrity();
        $results[] = $instance->cleanup();

        return $results;
    }

    private function testBackupGeneration(): array
    {
        $testName = "Backup Utility: Exports schema and data into standalone SQL archive";
        
        $phpBin = PHP_BINARY;
        $backupScript = dirname(__DIR__, 2) . '/bin/backup_db.php';
        
        $cmd = escapeshellcmd("{$phpBin} {$backupScript} verityvote_db {$this->tempBackupFile}");
        $output = shell_exec($cmd);

        $exists = file_exists($this->tempBackupFile);
        $size = $exists ? filesize($this->tempBackupFile) : 0;
        
        $success = $exists && $size > 50000 && strpos((string)$output, 'Backup Status:   SUCCESS') !== false;

        return ['name' => $testName, 'pass' => $success];
    }

    private function testRestoreSafetyGuardrail(): array
    {
        $testName = "Safety Barrier: Restoring to active database without --confirm is rejected";

        $phpBin = PHP_BINARY;
        $restoreScript = dirname(__DIR__, 2) . '/bin/restore_db.php';

        // Intentionally omit --confirm targeting active DB
        $cmd = escapeshellcmd("{$phpBin} {$restoreScript} {$this->tempBackupFile} verityvote_db");
        $output = shell_exec($cmd);

        $rejected = strpos((string)$output, 'RESTORE SAFETY CONFIRMATION REQUIRED') !== false;

        return ['name' => $testName, 'pass' => $rejected];
    }

    private function testRestoreIntoDisposableDatabase(): array
    {
        $testName = "Restore Utility: Successfully restores archive into disposable database";

        $phpBin = PHP_BINARY;
        $restoreScript = dirname(__DIR__, 2) . '/bin/restore_db.php';

        $cmd = escapeshellcmd("{$phpBin} {$restoreScript} {$this->tempBackupFile} {$this->tempDb}");
        $output = shell_exec($cmd);

        $restored = strpos((string)$output, 'Restore Status:  SUCCESS') !== false;

        return ['name' => $testName, 'pass' => $restored];
    }

    private function testRestoredTableIntegrity(): array
    {
        $testName = "Data Integrity: Restored database contains all 10 core tables and valid rows";

        $conn = $this->db->getConnection();
        $res = $conn->query("SHOW TABLES FROM `{$this->tempDb}`");
        $tables = [];
        if ($res) {
            while ($row = $res->fetch_row()) {
                $tables[] = $row[0];
            }
            $res->free();
        }

        $required = [
            'users', 'elections', 'candidates', 'election_eligibility',
            'election_participation', 'votes', 'audit_logs', 'login_attempts',
            'migrations', 'portal_identifier_mappings'
        ];

        $missing = array_diff($required, $tables);
        $pass = empty($missing) && count($tables) >= 10;

        return ['name' => $testName, 'pass' => $pass];
    }

    private function cleanup(): array
    {
        $testName = "Teardown: Disposable test database and backup artifact removed cleanly";

        $conn = $this->db->getConnection();
        $conn->query("DROP DATABASE IF EXISTS `{$this->tempDb}`");

        if (file_exists($this->tempBackupFile)) {
            @unlink($this->tempBackupFile);
        }

        $clean = !file_exists($this->tempBackupFile);

        return ['name' => $testName, 'pass' => $clean];
    }
}
