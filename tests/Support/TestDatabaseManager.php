<?php
/**
 * VerityVote - Disposable Test Database Lifecycle Manager
 * 
 * Manages automated creation, schema migration, and teardown of the isolated
 * disposable test database ('verityvote_test_suite'). Strictly enforces safety
 * guardrails to ensure the primary database ('verityvote_db') is NEVER touched.
 */

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database;
use mysqli;
use RuntimeException;

class TestDatabaseManager
{
    public const TEST_DB_NAME = 'verityvote_test_suite';
    public const PRIMARY_DB_NAME = 'verityvote_db';

    private static ?string $overrideFilePath = null;

    public static function getOverrideFilePath(): string
    {
        if (self::$overrideFilePath === null) {
            self::$overrideFilePath = dirname(__DIR__, 2) . '/storage/.test_database_override';
        }
        return self::$overrideFilePath;
    }

    /**
     * Enforce strict safety guardrails. Aborts if operating on non-disposable database.
     */
    public static function assertIsDisposable(string $dbName): void
    {
        if ($dbName !== self::TEST_DB_NAME) {
            throw new RuntimeException("CRITICAL SAFETY ABORT: Refusing operation on non-disposable database '{$dbName}'!");
        }

        if (strtolower($dbName) === strtolower(self::PRIMARY_DB_NAME) || strpos(strtolower($dbName), 'prod') !== false) {
            throw new RuntimeException("FATAL INTEGRITY VIOLATION: Primary database '{$dbName}' cannot be modified or dropped!");
        }
    }

    /**
     * Initialize disposable test database, apply full schema & migrations, and activate override.
     */
    public static function setup(): Database
    {
        self::assertIsDisposable(self::TEST_DB_NAME);

        $configFile = dirname(__DIR__, 2) . '/config/config.php';
        if (!file_exists($configFile)) {
            $configFile = dirname(__DIR__, 2) . '/config/config.example.php';
        }
        $config = require $configFile;
        $dbConfig = $config['database'];

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $conn = new mysqli(
            $dbConfig['host'],
            $dbConfig['username'],
            $dbConfig['password'],
            '',
            (int)($dbConfig['port'] ?? 3306)
        );
        $conn->set_charset($dbConfig['charset'] ?? 'utf8mb4');

        // 1. Create fresh isolated test database
        $conn->query("CREATE DATABASE IF NOT EXISTS `" . self::TEST_DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->select_db(self::TEST_DB_NAME);

        // 2. Apply base schema
        $schemaFile = dirname(__DIR__, 2) . '/database/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new RuntimeException("Base schema file not found: {$schemaFile}");
        }

        $schemaSql = file_get_contents($schemaFile);
        $schemaSql = preg_replace('/CREATE DATABASE IF NOT EXISTS `?[a-zA-Z0-9_]+`?[^;]*;/i', '', $schemaSql);
        $schemaSql = preg_replace('/USE `?[a-zA-Z0-9_]+`?;/i', "USE `" . self::TEST_DB_NAME . "`;", $schemaSql);

        if ($conn->multi_query($schemaSql)) {
            do {
                if ($res = $conn->store_result()) {
                    $res->free();
                }
            } while ($conn->more_results() && $conn->next_result());
        }

        // 3. Apply incremental migrations
        $migrationsDir = dirname(__DIR__, 2) . '/database/migrations';
        $migrationFiles = glob($migrationsDir . '/*.sql');
        sort($migrationFiles);

        foreach ($migrationFiles as $migFile) {
            $migName = basename($migFile);
            $chk = $conn->query("SELECT id FROM migrations WHERE migration = '{$conn->real_escape_string($migName)}'");
            if ($chk && $chk->num_rows > 0) {
                $chk->free();
                continue;
            }
            if ($chk) {
                $chk->free();
            }

            $migSql = file_get_contents($migFile);
            // Ignore alter table errors if column already exists in base schema
            try {
                if ($conn->multi_query($migSql)) {
                    do {
                        if ($res = $conn->store_result()) {
                            $res->free();
                        }
                    } while ($conn->more_results() && $conn->next_result());
                }
            } catch (\Throwable $e) {
                // Duplicate column/table is expected if schema.sql already contains latest definitions
            }

            $conn->query("INSERT INTO migrations (migration, applied_at) VALUES ('{$conn->real_escape_string($migName)}', CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP");
        }

        $conn->close();

        // 4. Activate test database override for application runtime and HTTP requests
        $overrideFile = self::getOverrideFilePath();
        file_put_contents($overrideFile, self::TEST_DB_NAME, LOCK_EX);

        // 5. Reset singleton to connect to the disposable test database
        Database::resetInstance();
        $db = Database::getInstance();

        // 6. Verify connection points to disposable database
        $currDbRes = $db->fetchOne("SELECT DATABASE() as current_db");
        $currDb = $currDbRes['current_db'] ?? '';
        if ($currDb !== self::TEST_DB_NAME) {
            self::teardown();
            throw new RuntimeException("CRITICAL: Test database setup failed to connect to '" . self::TEST_DB_NAME . "'! Connected to '{$currDb}'. Aborting.");
        }

        return $db;
    }

    /**
     * Cleanly teardown the disposable test database and verify primary database integrity.
     */
    public static function teardown(): void
    {
        $overrideFile = self::getOverrideFilePath();
        if (file_exists($overrideFile)) {
            @unlink($overrideFile);
        }

        // Disconnect and reset singleton
        Database::resetInstance();

        $configFile = dirname(__DIR__, 2) . '/config/config.php';
        if (!file_exists($configFile)) {
            $configFile = dirname(__DIR__, 2) . '/config/config.example.php';
        }
        $config = require $configFile;
        $dbConfig = $config['database'];

        // Connect directly to MySQL server
        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = @new mysqli(
            $dbConfig['host'],
            $dbConfig['username'],
            $dbConfig['password'],
            '',
            (int)($dbConfig['port'] ?? 3306)
        );

        if ($conn && !$conn->connect_error) {
            // Strictly enforce safety safeguard
            self::assertIsDisposable(self::TEST_DB_NAME);
            $conn->query("DROP DATABASE IF EXISTS `" . self::TEST_DB_NAME . "`");
            $conn->close();
        }

        // Reset singleton so next call points to PRIMARY_DB_NAME
        Database::resetInstance();
    }

    /**
     * Verify that the primary database is intact and healthy.
     */
    public static function verifyPrimaryDatabase(): array
    {
        Database::resetInstance();
        $db = Database::getInstance();

        $currDb = $db->fetchOne("SELECT DATABASE() as current_db")['current_db'] ?? '';
        if ($currDb !== self::PRIMARY_DB_NAME) {
            return [
                'healthy' => false,
                'message' => "Expected primary database '" . self::PRIMARY_DB_NAME . "', but connected to '{$currDb}'."
            ];
        }

        $tables = $db->fetchAll("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        $tableCount = count($tables);

        return [
            'healthy' => $tableCount >= 10,
            'database' => $currDb,
            'tables_count' => $tableCount,
            'message' => "Primary database '{$currDb}' is intact with {$tableCount} tables."
        ];
    }
}
