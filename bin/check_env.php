<?php
/**
 * VerityVote - CLI Environment Health Checker
 */

if (PHP_SAPI !== 'cli') {
    die("This diagnostic script can only be run from the command line.\n");
}

echo "========================================================\n";
echo " VerityVote - Environment Health & Diagnostic Check\n";
echo "========================================================\n\n";

$passes = 0;
$warnings = 0;
$failures = 0;

function report(string $name, bool $ok, string $detail = '', bool $isWarning = false) {
    global $passes, $warnings, $failures;
    if ($ok) {
        $passes++;
        echo " [OK]      {$name}" . ($detail ? " ({$detail})" : "") . "\n";
    } elseif ($isWarning) {
        $warnings++;
        echo " [WARN]    {$name}: {$detail}\n";
    } else {
        $failures++;
        echo " [FAIL]    {$name}: {$detail}\n";
    }
}

// 1. PHP Version
$phpVersion = PHP_VERSION;
$phpOk = version_compare($phpVersion, '8.2.0', '>=');
report("PHP Version", $phpOk, $phpVersion . " (Required: >= 8.2)");

// 2. Required PHP Extensions
$extensions = ['mysqli', 'openssl', 'mbstring', 'fileinfo', 'json', 'session'];
foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    report("Extension '{$ext}'", $loaded, $loaded ? "Installed" : "Missing required extension!");
}

// 3. MySQLi Strict Report
$mysqliAvailable = extension_loaded('mysqli');
if ($mysqliAvailable) {
    report("MySQLi Client API", true, mysqli_get_client_info());
}

// 4. Directory Permissions
$directories = [
    'storage/logs' => dirname(__DIR__) . '/storage/logs',
    'storage/uploads' => dirname(__DIR__) . '/storage/uploads',
    'storage/uploads/candidates' => dirname(__DIR__) . '/storage/uploads/candidates',
];

foreach ($directories as $label => $path) {
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
    $writable = is_writable($path);
    report("Directory Writable: {$label}", $writable, $writable ? "Writable" : "Permission denied");
}

// 5. Config File
$configPath = dirname(__DIR__) . '/config/config.php';
$configExamplePath = dirname(__DIR__) . '/config/config.example.php';
$hasConfig = file_exists($configPath);
$hasExample = file_exists($configExamplePath);

report("Configuration File (config/config.php)", $hasConfig, $hasConfig ? "Present" : "Missing (Copy from config.example.php)");
report("Template Configuration (config.example.php)", $hasExample, $hasExample ? "Present" : "Missing template");

echo "\n--------------------------------------------------------\n";
echo "Summary: {$passes} Passed | {$warnings} Warnings | {$failures} Failures\n";
echo "--------------------------------------------------------\n";

exit($failures > 0 ? 1 : 0);
