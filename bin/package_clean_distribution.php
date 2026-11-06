<?php
/**
 * VerityVote - Clean Distribution Packaging Tool
 * Builds a pristine ZIP distribution archive excluding all local secrets, credentials, logs, and binaries.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    die("This tool can only be run from the command line.\n");
}

$rootDir = dirname(__DIR__);
$zipFile = $rootDir . '/VerityVote_Clean_Distribution.zip';

if (file_exists($zipFile)) {
    @unlink($zipFile);
}

$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Failed to create ZIP archive: {$zipFile}\n");
}

$excludedExact = [
    str_replace('\\', '/', $rootDir . '/config/config.php'),
    str_replace('\\', '/', $rootDir . '/powershell.exe'),
    str_replace('\\', '/', $zipFile),
];

$excludedPrefixes = [
    str_replace('\\', '/', $rootDir . '/.git'),
    str_replace('\\', '/', $rootDir . '/screenshots'),
];

$dirIterator = new RecursiveDirectoryIterator($rootDir, RecursiveDirectoryIterator::SKIP_DOTS);
$iterator = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::SELF_FIRST);

$fileCount = 0;

foreach ($iterator as $item) {
    $itemPath = str_replace('\\', '/', $item->getRealPath());
    $relativePath = substr($itemPath, strlen(str_replace('\\', '/', $rootDir)) + 1);

    // Check exact exclusions
    if (in_array($itemPath, $excludedExact, true)) {
        continue;
    }

    // Check prefix exclusions (.git, screenshots)
    $skip = false;
    foreach ($excludedPrefixes as $prefix) {
        if (strpos($itemPath, $prefix) === 0) {
            $skip = true;
            break;
        }
    }
    if ($skip) {
        continue;
    }

    // Skip log files and cookie jars in storage
    if (preg_match('#storage/logs/.+\.(log|txt|json)$#i', $relativePath)) {
        continue;
    }

    // Skip cached files in storage (preserve .gitkeep)
    if (preg_match('#storage/cache/.+#i', $relativePath) && basename($relativePath) !== '.gitkeep') {
        continue;
    }

    // Skip temporary override tokens
    if (basename($relativePath) === '.test_database_override') {
        continue;
    }

    // Skip uploaded candidate photos in storage (preserve .gitkeep)
    if (preg_match('#storage/uploads/candidates/.+#i', $relativePath) && basename($relativePath) !== '.gitkeep') {
        continue;
    }

    // Skip database backup files (preserve .gitkeep)
    if (preg_match('#database/backups/.+#i', $relativePath) && basename($relativePath) !== '.gitkeep') {
        continue;
    }

    // Skip temporary archive files or OS metadata
    if (preg_match('/\.(zip|tar|gz|tmp|bak|swp|DS_Store)$/i', $relativePath) || basename($relativePath) === 'Thumbs.db') {
        continue;
    }

    if ($item->isDir()) {
        $zip->addEmptyDir($relativePath);
    } elseif ($item->isFile()) {
        $zip->addFile($itemPath, $relativePath);
        $fileCount++;
    }
}

$zip->close();

$zipSize = filesize($zipFile);
$sizeKb = round($zipSize / 1024, 2);

echo "========================================================\n";
echo " VerityVote - Clean Distribution Archive Created\n";
echo "========================================================\n";
echo " Archive: {$zipFile}\n";
echo " Total Files Packaged: {$fileCount}\n";
echo " Archive Size: {$sizeKb} KB\n\n";

// Verify Archive Integrity
$verifyZip = new ZipArchive();
if ($verifyZip->open($zipFile) === true) {
    echo "Archive Integrity Verification:\n";
    $hasConfig = ($verifyZip->locateName('config/config.php') !== false);
    $hasExampleConfig = ($verifyZip->locateName('config/config.example.php') !== false);
    $hasPowershell = ($verifyZip->locateName('powershell.exe') !== false);
    $hasSchema = ($verifyZip->locateName('database/schema.sql') !== false);
    $hasPublicIndex = ($verifyZip->locateName('public/index.php') !== false);
    $hasTests = ($verifyZip->locateName('tests/run_tests.php') !== false);
    $hasScreenshots = ($verifyZip->locateName('screenshots/desktop/home_1440.png') !== false);
    
    echo "  - Contains config.example.php: " . ($hasExampleConfig ? "[OK]" : "[MISSING]") . "\n";
    echo "  - Excluded config.php (local secrets): " . (!$hasConfig ? "[OK - Excluded]" : "[LEAK DETECTED]") . "\n";
    echo "  - Excluded powershell.exe: " . (!$hasPowershell ? "[OK - Excluded]" : "[LEAK DETECTED]") . "\n";
    echo "  - Excluded local screenshots: " . (!$hasScreenshots ? "[OK - Excluded]" : "[LEAK DETECTED]") . "\n";
    echo "  - Contains database/schema.sql: " . ($hasSchema ? "[OK]" : "[MISSING]") . "\n";
    echo "  - Contains public/index.php: " . ($hasPublicIndex ? "[OK]" : "[MISSING]") . "\n";
    echo "  - Contains full test suite: " . ($hasTests ? "[OK]" : "[MISSING]") . "\n";
    
    $hasBackup = ($verifyZip->locateName('bin/backup_db.php') !== false);
    $hasRestore = ($verifyZip->locateName('bin/restore_db.php') !== false);
    $hasProdDoc = ($verifyZip->locateName('docs/production-deployment.md') !== false);
    $hasRootHtaccess = ($verifyZip->locateName('.htaccess') !== false);

    echo "  - Contains bin/backup_db.php: " . ($hasBackup ? "[OK]" : "[MISSING]") . "\n";
    echo "  - Contains bin/restore_db.php: " . ($hasRestore ? "[OK]" : "[MISSING]") . "\n";
    echo "  - Contains docs/production-deployment.md: " . ($hasProdDoc ? "[OK]" : "[MISSING]") . "\n";
    echo "  - Contains root .htaccess: " . ($hasRootHtaccess ? "[OK]" : "[MISSING]") . "\n";
    $verifyZip->close();
}
