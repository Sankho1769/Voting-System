<?php
/**
 * VerityVote - Documentation Relative Link Checker
 */

declare(strict_types=1);

$rootDir = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootDir, RecursiveDirectoryIterator::SKIP_DOTS));

$brokenLinks = [];
$totalLinks = 0;

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'md' || strpos($file->getPathname(), '.git') !== false) {
        continue;
    }

    $content = file_get_contents($file->getPathname());
    $dir = $file->getPath();

    if (preg_match_all('/\[([^\]]+)\]\(([^http#\:\)][^\)]*)\)/', $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $totalLinks++;
            $target = trim($match[2]);
            if ($target === '' || strpos($target, 'mailto:') === 0 || strpos($target, 'http://') === 0 || strpos($target, 'https://') === 0) {
                continue;
            }
            if (($hashPos = strpos($target, '#')) !== false) {
                $target = substr($target, 0, $hashPos);
            }
            if ($target === '') {
                continue;
            }

            // Handle file:/// URI scheme
            if (strpos($target, 'file:///') === 0) {
                $filePath = substr($target, 8); // remove file:///
                $filePath = str_replace('/', DIRECTORY_SEPARATOR, $filePath);
                if (!file_exists($filePath)) {
                    $relFile = substr($file->getPathname(), strlen($rootDir) + 1);
                    $brokenLinks[] = "In {$relFile}: File URI points to non-existent path: {$filePath}";
                }
                continue;
            }

            // Replace forward/backward slashes
            $targetNormalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $target);
            $fullPath = $dir . DIRECTORY_SEPARATOR . $targetNormalized;

            if (!file_exists($fullPath)) {
                $relFile = substr($file->getPathname(), strlen($rootDir) + 1);
                $brokenLinks[] = "In {$relFile}: Link '{$match[0]}' points to non-existent path: {$target}";
            }
        }
    }
}

echo "Checked {$totalLinks} relative documentation links.\n";
if (empty($brokenLinks)) {
    echo "[PASS] All relative documentation links are valid!\n";
} else {
    echo "[FAIL] Broken links detected:\n";
    foreach ($brokenLinks as $b) {
        echo "  - {$b}\n";
    }
}
