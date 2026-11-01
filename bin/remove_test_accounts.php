<?php
/**
 * VerityVote - Test Account Removal Utility
 * Purges local test accounts and test elections created for portal testing.
 *
 * Usage:
 *   php bin/remove_test_accounts.php [login_id]
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    die("This utility can only be executed from the CLI.\n");
}

$loginId = !empty($argv[1]) ? trim($argv[1]) : 'Shubham';

$seedScript = __DIR__ . '/seed_test_accounts.php';
if (!file_exists($seedScript)) {
    die("Error: seed_test_accounts.php not found.\n");
}

passthru(PHP_BINARY . ' ' . escapeshellarg($seedScript) . ' --remove ' . escapeshellarg($loginId));
