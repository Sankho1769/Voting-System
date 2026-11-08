<?php
/**
 * VerityVote - Clean Distribution Archive Audit & Credential Scanner
 */

declare(strict_types=1);

$zipPath = dirname(__DIR__) . '/VerityVote_Clean_Distribution.zip';

if (!file_exists($zipPath)) {
    die("[FAIL] Distribution archive not found at: {$zipPath}\n");
}

$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    die("[FAIL] Could not open ZIP archive: {$zipPath}\n");
}

echo "========================================================\n";
echo " VerityVote - Distribution Archive Audit & Security Scan\n";
echo " Archive: " . basename($zipPath) . " (" . round(filesize($zipPath) / 1024, 2) . " KB)\n";
echo " Total Entries: {$zip->numFiles}\n";
echo "========================================================\n\n";

// 1. Forbidden Patterns Check
$forbiddenPatterns = [
    '#^config/config\.php$#i',
    '#^\.git/#i',
    '#\.log$#i',
    '#\.tmp$#i',
    '#\.bak$#i',
    '#\.py$#i',
    '#screenshots/#i',
    '#\.test_database_override#i',
    '#powershell\.exe#i',
    '#database/backups/.+\.sql$#i',
    '#VerityVote_Friend_Handoff#i',
];

$violations = [];
$totalFiles = 0;

for ($i = 0; $i < $zip->numFiles; $i++) {
    $stat = $zip->statIndex($i);
    $name = $stat['name'];
    $totalFiles++;

    // Allow database/schema.sql and database/migrations/*.sql
    if (preg_match('/\.sql$/i', $name)) {
        if ($name !== 'database/schema.sql' && !preg_match('#^database/migrations/\d+_.+\.sql$#i', $name)) {
            $violations[] = "Unexpected SQL dump: {$name}";
        }
    }

    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $name)) {
            $violations[] = "Forbidden pattern match [{$pattern}]: {$name}";
        }
    }
}

if (empty($violations)) {
    echo "[PASS] Exclusion Guard: Zero forbidden files, logs, dumps, or git metadata found.\n";
} else {
    echo "[FAIL] Exclusion Guard Violations Detected:\n";
    foreach ($violations as $v) {
        echo "  - {$v}\n";
    }
}

// 2. Required Core Files & Assets Check
$requiredFiles = [
    'config/config.example.php',
    'database/schema.sql',
    'database/migrations/001_initial_schema.sql',
    'database/migrations/002_add_candidate_portal_support.sql',
    'database/migrations/003_add_portal_identifier_mappings.sql',
    'database/migrations/004_security_hardening.sql',
    'database/migrations/005_add_election_categories.sql',
    'database/migrations/006_performance_optimization_indexes.sql',
    'public/index.php',
    'public/assets/css/style.css',
    'public/assets/js/app.js',
    'public/assets/js/vote.js',
    'bin/check_env.php',
    'bin/install_check.php',
    'bin/check_db.php',
    'bin/migrate.php',
    'bin/create_admin.php',
    'bin/backup_db.php',
    'bin/restore_db.php',
    'bin/rc_lifecycle_validation.php',
    'bin/qa_acceptance_audit.php',
    'docs/installation.md',
    'docs/INSTALLATION_GUIDE.md',
    'README.md',
    '.htaccess',
    'public/.htaccess',
    'storage/logs/.gitkeep',
    'storage/cache/.gitkeep',
    'storage/uploads/candidates/.gitkeep',
    'database/backups/.gitkeep',
];

$missing = [];
foreach ($requiredFiles as $req) {
    if ($zip->locateName($req) === false) {
        $missing[] = $req;
    }
}

if (empty($missing)) {
    echo "[PASS] Required Files Check: All " . count($requiredFiles) . " core files and .gitkeep directories present.\n";
} else {
    echo "[FAIL] Missing Required Files:\n";
    foreach ($missing as $m) {
        echo "  - {$m}\n";
    }
}

// 3. Sensitive Credential & Secret Scanner
echo "\nScanning all packaged files for leaked secrets, API keys, and real credentials...\n";

$credentialPatterns = [
    '#(["\']password["\']\s*=>\s*["\'])(?![\'"])[^"\'\s]{3,}(["\'])#i',
    '#BEGIN\s+(RSA|OPENSSH|DSA|EC)\s+PRIVATE\s+KEY#i',
    '#AIza[0-9A-Za-z-_]{35}#',
    '#ghp_[0-9a-zA-Z]{36}#',
    '#xoxb-[0-9]{11,13}-[0-9]{11,13}-[0-9a-zA-Z]{24}#',
];

$credentialLeaks = [];

for ($i = 0; $i < $zip->numFiles; $i++) {
    $stat = $zip->statIndex($i);
    $name = $stat['name'];

    // Skip directories and binary assets (images, fonts)
    if (substr($name, -1) === '/' || preg_match('/\.(png|jpg|jpeg|gif|ico|woff|woff2|ttf|eot)$/i', $name)) {
        continue;
    }

    $content = $zip->getFromIndex($i);
    if ($content === false) {
        continue;
    }

    // Check for real database password in config files
    if (basename($name) === 'config.example.php') {
        // config.example.php should have empty password
        if (preg_match("/'password'\s*=>\s*'([^']+)'/", $content, $matches) && $matches[1] !== '') {
            $credentialLeaks[] = "Non-empty password in {$name}: '{$matches[1]}'";
        }
        continue;
    }

    // Skip test directories and markdown documentation for dummy password examples
    if (strpos($name, 'docs/') === 0 || strpos($name, 'tests/') === 0) {
        continue;
    }

    foreach ($credentialPatterns as $pattern) {
        if (preg_match($pattern, $content, $matches)) {
            $matchedText = substr($matches[0], 0, 50);
            $credentialLeaks[] = "Suspicious pattern match in {$name}: {$matchedText}";
        }
    }
}

if (empty($credentialLeaks)) {
    echo "[PASS] Credential Security Scan: Zero real secrets, private keys, or API credentials detected!\n";
} else {
    echo "[WARN/FAIL] Potential Credential Leaks Detected:\n";
    foreach ($credentialLeaks as $leak) {
        echo "  - {$leak}\n";
    }
}

$zip->close();
echo "\nAudit complete.\n";
