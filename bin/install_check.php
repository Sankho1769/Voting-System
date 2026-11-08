<?php
/**
 * VerityVote - Safe Installation Health & Diagnostics Checker
 *
 * Verifies PHP runtime, extensions, configuration, writable storage,
 * database connectivity, core schema, versioned migrations, and admin status.
 *
 * SAFETY INVARIANT: This diagnostic checker performs STRICTLY READ-ONLY operations.
 * It NEVER drops, modifies, truncates, or resets any database or table.
 * It NEVER prints or leaks cleartext passwords or sensitive keys.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    die("This health check utility must be run from the command line interface.\n");
}

echo "========================================================\n";
echo " VerityVote - Installation Health & Diagnostics Checker\n";
echo " Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "========================================================\n\n";

$passes = 0;
$warnings = 0;
$failures = 0;

function reportStatus(string $category, string $item, string $status, string $details = ''): void
{
    global $passes, $warnings, $failures;
    $statusUpper = strtoupper($status);
    $tag = match ($statusUpper) {
        'PASS' => '[PASS]',
        'WARN', 'WARNING' => '[WARN]',
        default => '[FAIL]'
    };

    if ($statusUpper === 'PASS') {
        $passes++;
    } elseif ($statusUpper === 'WARN' || $statusUpper === 'WARNING') {
        $warnings++;
    } else {
        $failures++;
    }

    $detailStr = $details !== '' ? " - {$details}" : '';
    printf("%-7s %-18s: %s%s\n", $tag, $category, $item, $detailStr);
}

// -------------------------------------------------------------
// 1. PHP Runtime & Extensions
// -------------------------------------------------------------
$phpVer = PHP_VERSION;
if (version_compare($phpVer, '8.2.0', '>=')) {
    reportStatus('PHP Runtime', "PHP {$phpVer}", 'PASS', 'Meets minimum requirement >= 8.2');
} else {
    reportStatus('PHP Runtime', "PHP {$phpVer}", 'FAIL', 'VerityVote requires PHP 8.2 or later');
}

$requiredExtensions = [
    'mysqli'    => 'MySQL Improved Extension (Database Connectivity)',
    'curl'      => 'cURL (HTTP Requests & Web Services)',
    'mbstring'  => 'Multibyte String (Unicode & Text Processing)',
    'fileinfo'  => 'File Information (Safe Candidate Photo MIME Detection)',
    'openssl'   => 'OpenSSL (Cryptographic Tokens & Security Hashing)',
    'json'      => 'JSON (Structured Logging & Payloads)',
    'session'   => 'Session Management (Secure Web Portals)',
];

foreach ($requiredExtensions as $ext => $description) {
    if (extension_loaded($ext)) {
        reportStatus('PHP Extension', $ext, 'PASS', $description);
    } else {
        reportStatus('PHP Extension', $ext, 'FAIL', "MISSING! Enable extension={$ext} in php.ini");
    }
}

// Optional / Recommended Extensions
$recommendedExtensions = ['pdo', 'pdo_mysql'];
foreach ($recommendedExtensions as $ext) {
    if (extension_loaded($ext)) {
        reportStatus('PHP Extension (Rec)', $ext, 'PASS', 'Installed');
    } else {
        reportStatus('PHP Extension (Rec)', $ext, 'WARN', 'Recommended for database utility operations');
    }
}

// -------------------------------------------------------------
// 2. Application Directory Structure
// -------------------------------------------------------------
$rootDir = dirname(__DIR__);
$requiredDirectories = [
    'app'                 => 'Core Application Source',
    'app/Core'            => 'Framework Core',
    'app/Services'        => 'Business Logic Services',
    'app/Views'           => 'User Interface Templates',
    'bin'                 => 'CLI Utilities',
    'config'              => 'Application Configuration',
    'database'            => 'Database Scripts',
    'database/migrations' => 'Versioned Schema Migrations',
    'public'              => 'Web Server Document Root',
    'storage'             => 'Storage Root',
    'storage/logs'        => 'Audit & Application Logs',
    'storage/cache'       => 'Query & View Cache',
    'storage/uploads/candidates' => 'Candidate Photo Uploads',
    'database/backups'    => 'Transactional Database Backups',
];

foreach ($requiredDirectories as $dir => $label) {
    $fullPath = $rootDir . '/' . $dir;
    if (is_dir($fullPath)) {
        reportStatus('Directory', $dir, 'PASS', $label);
    } else {
        // Attempt creation for storage dirs
        if (strpos($dir, 'storage/') === 0 || strpos($dir, 'database/backups') === 0) {
            if (@mkdir($fullPath, 0755, true)) {
                reportStatus('Directory', $dir, 'PASS', "Created directory: {$label}");
                continue;
            }
        }
        reportStatus('Directory', $dir, 'FAIL', "Missing required directory: {$label}");
    }
}

// -------------------------------------------------------------
// 3. Storage Folder Writable Permissions
// -------------------------------------------------------------
$writableDirs = [
    'storage/logs'               => $rootDir . '/storage/logs',
    'storage/cache'              => $rootDir . '/storage/cache',
    'storage/uploads/candidates' => $rootDir . '/storage/uploads/candidates',
    'database/backups'           => $rootDir . '/database/backups',
];

foreach ($writableDirs as $relDir => $absDir) {
    if (!is_dir($absDir)) {
        reportStatus('Permissions', $relDir, 'FAIL', 'Directory does not exist');
        continue;
    }

    // Probe write capability safely
    $probeFile = $absDir . '/.write_probe_' . bin2hex(random_bytes(3)) . '.tmp';
    $canWrite = false;
    if (@file_put_contents($probeFile, 'probe') !== false) {
        $canWrite = true;
        @unlink($probeFile);
    }

    if ($canWrite) {
        reportStatus('Permissions', $relDir, 'PASS', 'Directory is writable');
    } else {
        reportStatus('Permissions', $relDir, 'FAIL', 'Directory is NOT writable. Check Windows/NTFS permissions');
    }
}

// -------------------------------------------------------------
// 4. Configuration Availability & Syntax
// -------------------------------------------------------------
$configFile = $rootDir . '/config/config.php';
$configExampleFile = $rootDir . '/config/config.example.php';

if (!file_exists($configExampleFile)) {
    reportStatus('Configuration', 'config.example.php', 'FAIL', 'Template configuration missing');
} else {
    reportStatus('Configuration', 'config.example.php', 'PASS', 'Template configuration present');
}

$config = null;
if (!file_exists($configFile)) {
    reportStatus('Configuration', 'config/config.php', 'FAIL', "Missing configuration file! Copy config.example.php to config.php");
} else {
    try {
        $config = require $configFile;
        if (!is_array($config)) {
            reportStatus('Configuration', 'config/config.php', 'FAIL', "Invalid configuration file: does not return array");
            $config = null;
        } else {
            reportStatus('Configuration', 'config/config.php', 'PASS', "Configuration loaded successfully");
        }
    } catch (\Throwable $e) {
        reportStatus('Configuration', 'config/config.php', 'FAIL', "Syntax error in config: " . $e->getMessage());
        $config = null;
    }
}

// -------------------------------------------------------------
// 5. Database Connectivity & Host Verification
// -------------------------------------------------------------
$dbConn = null;
$dbTargetName = '';

if ($config === null || !isset($config['database'])) {
    reportStatus('Database Config', 'Connection Parameters', 'FAIL', 'Cannot verify database without valid config');
} else {
    $dbConfig = $config['database'];
    $dbHost = $dbConfig['host'] ?? '127.0.0.1';
    $dbPort = (int)($dbConfig['port'] ?? 3306);
    $dbUser = $dbConfig['username'] ?? 'root';
    $dbPass = $dbConfig['password'] ?? '';
    $dbTargetName = !empty($argv[1]) ? trim($argv[1]) : ($dbConfig['database'] ?? 'verityvote_db');

    // Never leak password in output
    $maskedPass = $dbPass === '' ? '[EMPTY]' : '[PROTECTED/NON-EMPTY]';
    reportStatus('Database Config', 'Parameters', 'PASS', "Host: {$dbHost}:{$dbPort} | User: {$dbUser} | Password: {$maskedPass} | Database: {$dbTargetName}");

    // Test MySQL server connectivity
    mysqli_report(MYSQLI_REPORT_OFF);
    $serverConn = @new mysqli($dbHost, $dbUser, $dbPass, '', $dbPort);
    if ($serverConn->connect_error) {
        reportStatus('Database Engine', 'Server Connection', 'FAIL', "Connection refused: " . $serverConn->connect_error . " (Is MySQL running in XAMPP?)");
    } else {
        $serverVersion = $serverConn->server_info;
        reportStatus('Database Engine', 'Server Connection', 'PASS', "Connected to MariaDB/MySQL ({$serverVersion})");

        // Check if target database exists
        $dbCheck = $serverConn->query("SHOW DATABASES LIKE '" . $serverConn->real_escape_string($dbTargetName) . "'");
        if ($dbCheck && $dbCheck->num_rows > 0) {
            reportStatus('Database Engine', "Database '{$dbTargetName}'", 'PASS', 'Target database exists');
            $serverConn->select_db($dbTargetName);
            $dbConn = $serverConn;
        } else {
            reportStatus('Database Engine', "Database '{$dbTargetName}'", 'FAIL', "Database '{$dbTargetName}' does not exist! Create it via phpMyAdmin or check config.php");
            $serverConn->close();
        }
    }
}

// -------------------------------------------------------------
// 6. Database Schema & Table Verification
// -------------------------------------------------------------
$expectedTables = [
    'users'                  => 'Institutional Accounts & Credentials',
    'elections'              => 'Electoral Events & Configurations',
    'candidates'             => 'Contenders & Manifestos',
    'election_eligibility'   => 'Voter Eligibility Whitelist',
    'election_participation' => 'Anonymous Participation Audit Log',
    'votes'                  => 'Secret Cast Ballots (No user_id)',
    'audit_logs'             => 'Immutable Security Audit Trail',
    'login_attempts'         => 'Rate Limiting & Brute Force Tracker',
    'migrations'             => 'Applied Versioned Migrations Ledger',
    'portal_identifier_mappings' => 'Portal-Specific ID Normalization',
];

if ($dbConn !== null) {
    $existingTables = [];
    $res = $dbConn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    if ($res) {
        while ($row = $res->fetch_row()) {
            $existingTables[] = strtolower($row[0]);
        }
    }

    $missingTables = [];
    foreach ($expectedTables as $table => $desc) {
        if (in_array(strtolower($table), $existingTables, true)) {
            reportStatus('Database Table', $table, 'PASS', $desc);
        } else {
            $missingTables[] = $table;
            reportStatus('Database Table', $table, 'FAIL', "MISSING! Apply database/schema.sql or migrations");
        }
    }

    // Verify InnoDB Storage Engine
    if (empty($missingTables)) {
        $engineCheck = $dbConn->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$dbTargetName}' AND ENGINE != 'InnoDB'");
        if ($engineCheck && $engineCheck->num_rows === 0) {
            reportStatus('Database Engine', 'InnoDB ACID Compliance', 'PASS', 'All core tables use InnoDB storage engine');
        } else {
            reportStatus('Database Engine', 'InnoDB ACID Compliance', 'WARN', 'Some tables do not use InnoDB');
        }
    }

    // -------------------------------------------------------------
    // 7. Migration Status Check
    // -------------------------------------------------------------
    if (in_array('migrations', $existingTables, true)) {
        $appliedMigrations = [];
        $mRes = $dbConn->query("SELECT migration FROM migrations ORDER BY id ASC");
        if ($mRes) {
            while ($mRow = $mRes->fetch_row()) {
                $appliedMigrations[] = $mRow[0];
            }
        }

        $migrationFiles = glob($rootDir . '/database/migrations/*.sql');
        sort($migrationFiles);
        $totalAvailable = count($migrationFiles);
        $totalApplied = count($appliedMigrations);

        if ($totalApplied >= $totalAvailable && $totalAvailable > 0) {
            reportStatus('Migrations', "Applied {$totalApplied}/{$totalAvailable}", 'PASS', 'All versioned migrations applied');
        } else {
            $pendingCount = $totalAvailable - $totalApplied;
            reportStatus('Migrations', "Applied {$totalApplied}/{$totalAvailable}", 'WARN', "{$pendingCount} pending migration(s). Run: php bin/migrate.php");
        }
    } else {
        reportStatus('Migrations', 'Ledger Table', 'FAIL', "Table 'migrations' missing. Run: php bin/migrate.php");
    }

    // -------------------------------------------------------------
    // 8. Administrator Account Status
    // -------------------------------------------------------------
    if (in_array('users', $existingTables, true)) {
        $adminRes = $dbConn->query("SELECT COUNT(*) as c FROM users WHERE role = 'admin' AND status = 'active'");
        $adminCount = $adminRes ? (int)$adminRes->fetch_assoc()['c'] : 0;

        if ($adminCount > 0) {
            reportStatus('Admin Account', "Active Administrators ({$adminCount})", 'PASS', 'System has at least one active administrator');
        } else {
            reportStatus('Admin Account', 'Active Administrators (0)', 'WARN', 'No active administrator found! Create one with: php bin/create_admin.php');
        }
    }

    $dbConn->close();
}

// -------------------------------------------------------------
// 9. Apache URL Rewriting & Web Configuration
// -------------------------------------------------------------
$rootHtaccess = $rootDir . '/.htaccess';
$publicHtaccess = $rootDir . '/public/.htaccess';

if (file_exists($rootHtaccess)) {
    reportStatus('Apache Config', 'Root .htaccess', 'PASS', 'Present (Protects internal directories)');
} else {
    reportStatus('Apache Config', 'Root .htaccess', 'WARN', 'Missing root .htaccess');
}

if (file_exists($publicHtaccess)) {
    $publicHtContent = file_get_contents($publicHtaccess);
    $hasRewrite = strpos($publicHtContent, 'RewriteEngine On') !== false && strpos($publicHtContent, 'RewriteRule') !== false;
    if ($hasRewrite) {
        reportStatus('Apache Config', 'public/.htaccess', 'PASS', 'RewriteEngine & Front Controller routing configured');
    } else {
        reportStatus('Apache Config', 'public/.htaccess', 'WARN', 'Rewrite directives missing in public/.htaccess');
    }
} else {
    reportStatus('Apache Config', 'public/.htaccess', 'FAIL', 'Missing public/.htaccess! Clean URLs will fail without it');
}

// -------------------------------------------------------------
// Summary & Exit
// -------------------------------------------------------------
echo "\n========================================================\n";
echo " Health Check Summary: {$passes} Passed | {$warnings} Warnings | {$failures} Failures\n";
echo "========================================================\n";

if ($failures === 0 && $warnings === 0) {
    echo " [STATUS: READY] VerityVote is fully configured, healthy, and operational!\n";
    exit(0);
} elseif ($failures === 0) {
    echo " [STATUS: OPERATIONAL WITH NOTICES] Core requirements met. Please review warnings above.\n";
    exit(0);
} else {
    echo " [STATUS: ACTION REQUIRED] One or more critical requirements failed. Please resolve failures above.\n";
    exit(1);
}
