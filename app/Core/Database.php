<?php
namespace App\Core;

use mysqli;
use mysqli_stmt;
use RuntimeException;
use Exception;

/**
 * Database Connection and Query Wrapper using MySQLi
 * Strictly adheres to MySQLi prepared statements across the application.
 */
class Database
{
    private static ?Database $instance = null;
    private ?mysqli $connection = null;
    private array $config;

    private function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }

    public static function getInstance(?array $config = null): Database
    {
        if (self::$instance === null) {
            if ($config === null) {
                $configFile = dirname(__DIR__, 2) . '/config/config.php';
                if (!file_exists($configFile)) {
                    $configFile = dirname(__DIR__, 2) . '/config/config.example.php';
                }
                $appConfig = require $configFile;
                $config = $appConfig['database'];
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * Create an independent, non-singleton Database connection
     * Useful for concurrency tests and multi-session validation.
     */
    public static function createConnection(?array $config = null): Database
    {
        if ($config === null) {
            $configFile = dirname(__DIR__, 2) . '/config/config.php';
            if (!file_exists($configFile)) {
                $configFile = dirname(__DIR__, 2) . '/config/config.example.php';
            }
            $appConfig = require $configFile;
            $config = $appConfig['database'];
        }
        return new self($config);
    }

    /**
     * Establish MySQLi connection with charset and error reporting
     */
    private function connect(): void
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $this->connection = new mysqli(
                $this->config['host'],
                $this->config['username'],
                $this->config['password'],
                $this->config['database'],
                (int)($this->config['port'] ?? 3306)
            );

            $charset = $this->config['charset'] ?? 'utf8mb4';
            $this->connection->set_charset($charset);

            // Set SQL mode for strict integrity and sync timezone with PHP
            $this->connection->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION,ERROR_FOR_DIVISION_BY_ZERO'");
            $tzOffset = date('P');
            $this->connection->query("SET SESSION time_zone = '{$tzOffset}'");
        } catch (Exception $e) {
            throw new RuntimeException("Database connection failure: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    public function getConnection(): mysqli
    {
        if ($this->connection === null || !$this->connection->ping()) {
            $this->connect();
        }
        return $this->connection;
    }

    /**
     * Begin an ACID transaction
     */
    public function beginTransaction(): bool
    {
        return $this->getConnection()->begin_transaction();
    }

    /**
     * Commit the current transaction
     */
    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }

    /**
     * Rollback the current transaction
     */
    public function rollback(): bool
    {
        return $this->getConnection()->rollback();
    }

    /**
     * Prepare a statement
     */
    public function prepare(string $sql): mysqli_stmt
    {
        $stmt = $this->getConnection()->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException("Failed to prepare statement: " . $this->getConnection()->error);
        }
        return $stmt;
    }

    /**
     * Execute a prepared query with parameters
     *
     * @param string $sql
     * @param array $params Array of values to bind
     * @param string $types Type string (e.g. 'ssi'), auto-inferred if empty
     * @return mysqli_stmt
     */
    public function execute(string $sql, array $params = [], string $types = ''): mysqli_stmt
    {
        $stmt = $this->prepare($sql);

        if (!empty($params)) {
            if (empty($types)) {
                $types = '';
                foreach ($params as $param) {
                    if (is_int($param)) {
                        $types .= 'i';
                    } elseif (is_float($param)) {
                        $types .= 'd';
                    } else {
                        $types .= 's';
                    }
                }
            }

            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        return $stmt;
    }

    /**
     * Fetch all rows as associative arrays
     */
    public function fetchAll(string $sql, array $params = [], string $types = ''): array
    {
        $stmt = $this->execute($sql, $params, $types);
        $result = $stmt->get_result();
        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $result->free();
        }
        $stmt->close();
        return $rows;
    }

    /**
     * Fetch a single row as associative array or null
     */
    public function fetchOne(string $sql, array $params = [], string $types = ''): ?array
    {
        $stmt = $this->execute($sql, $params, $types);
        $result = $stmt->get_result();
        $row = null;
        if ($result) {
            $row = $result->fetch_assoc();
            $result->free();
        }
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Fetch a single scalar value (e.g. COUNT(*))
     */
    public function fetchValue(string $sql, array $params = [], string $types = ''): mixed
    {
        $row = $this->fetchOne($sql, $params, $types);
        if ($row && !empty($row)) {
            return reset($row);
        }
        return null;
    }

    /**
     * Execute an INSERT and return the inserted ID
     */
    public function insert(string $sql, array $params = [], string $types = ''): int
    {
        $stmt = $this->execute($sql, $params, $types);
        $insertId = (int)$this->getConnection()->insert_id;
        $stmt->close();
        return $insertId;
    }

    /**
     * Execute an UPDATE/DELETE and return affected rows
     */
    public function mutate(string $sql, array $params = [], string $types = ''): int
    {
        $stmt = $this->execute($sql, $params, $types);
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    public function lastInsertId(): int
    {
        return (int)$this->getConnection()->insert_id;
    }

    /**
     * Get the live connected database name directly from the MySQLi session.
     */
    public function getConnectedDatabase(): string
    {
        $res = $this->getConnection()->query("SELECT DATABASE() as db");
        if ($res && ($row = $res->fetch_assoc())) {
            $dbName = (string)($row['db'] ?? '');
            $res->free();
            return $dbName;
        }
        return '';
    }

    /**
     * Enforce strict database identity verification.
     * Throws a RuntimeException if the live connection is to the primary production database
     * or is not in the explicitly permitted disposable databases list.
     *
     * @param array $allowedDisposables
     * @throws RuntimeException
     */
    public function assertDisposableDatabase(array $allowedDisposables = []): void
    {
        $liveDb = $this->getConnectedDatabase();
        if (strtolower($liveDb) === 'verityvote_db') {
            throw new RuntimeException("CRITICAL SAFETY REJECTION: Live database connection is to primary database '{$liveDb}'! Test operations strictly prohibited.");
        }
        if (!empty($allowedDisposables)) {
            $allowedLower = array_map('strtolower', $allowedDisposables);
            if (!in_array(strtolower($liveDb), $allowedLower, true)) {
                throw new RuntimeException("CRITICAL SAFETY REJECTION: Live database '{$liveDb}' is not in the approved disposable list! Operation rejected.");
            }
        }
    }

    /**
     * Reset connection for testing or fresh requests
     */
    public static function resetInstance(): void
    {
        if (self::$instance !== null && self::$instance->connection !== null) {
            @self::$instance->connection->close();
        }
        self::$instance = null;
    }
}
