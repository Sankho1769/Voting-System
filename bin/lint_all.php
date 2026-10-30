<?php
/**
 * VerityVote - Full Repository PHP Syntax Lint
 */

declare(strict_types=1);

$rootDir = dirname(__DIR__);
$dir = new RecursiveDirectoryIterator($rootDir);
$ite = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($ite, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

$count = 0;
$errors = 0;
$phpBinary = PHP_BINARY;

echo "Linting all PHP files in repository...\n";

foreach ($files as $file) {
    $path = $file[0];
    if (strpos($path, 'vendor') !== false || strpos($path, '.git') !== false) {
        continue;
    }
    
    $output = [];
    $returnCode = 0;
    exec(escapeshellarg($phpBinary) . ' -l ' . escapeshellarg($path), $output, $returnCode);
    $count++;
    
    if ($returnCode !== 0) {
        echo "[ERROR] Syntax issue in: " . $path . "\n";
        echo implode("\n", $output) . "\n";
        $errors++;
    }
}

echo "--------------------------------------------------------\n";
echo "Lint Complete: {$count} files checked. Syntax errors: {$errors}\n";
echo "--------------------------------------------------------\n";

exit($errors === 0 ? 0 : 1);
