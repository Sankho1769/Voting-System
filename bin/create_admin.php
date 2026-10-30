<?php
/**
 * VerityVote - Initial System Administrator Provisioning Script
 * Strictly restricted to CLI execution.
 * 
 * Passwords are NEVER accepted as command-line arguments to prevent
 * process-list exposure (ps/top), shell history leakage, and shoulder surfing.
 */

if (PHP_SAPI !== 'cli') {
    die("This administrative script can only be run from the command line interface.\n");
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Logger;
use App\Helpers\Sanitizer;
use App\Helpers\Validator;
use App\Repositories\UserRepository;

echo "========================================================\n";
echo " VerityVote - Protected Administrator Provisioning\n";
echo "========================================================\n\n";

function prompt(string $message, string $default = ''): string {
    echo $message . ($default ? " [{$default}]" : "") . ": ";
    $line = trim(fgets(STDIN));
    return $line !== '' ? $line : $default;
}

// Accept optional metadata arguments only (Identifier, Name, Email)
// NEVER accept passwords from $argv!
$args = array_slice($argv, 1);

if (count($args) >= 3) {
    $identifier = $args[0];
    $fullName = $args[1];
    $email = $args[2];
} elseif (count($args) === 1) {
    $identifier = $args[0];
    $fullName = prompt("Enter Administrator Full Name", "System Administrator");
    $email = prompt("Enter Contact Email Address", "admin@verityvote.college");
} else {
    $identifier = prompt("Enter Administrator ID (e.g., ADMIN01)", "ADMIN01");
    $fullName = prompt("Enter Administrator Full Name", "System Administrator");
    $email = prompt("Enter Contact Email Address", "admin@verityvote.college");
}

$identifier = Sanitizer::cleanIdentifier($identifier);
$fullName = Sanitizer::cleanText($fullName);
$email = filter_var(trim($email), FILTER_VALIDATE_EMAIL) ?: null;

if (!Validator::validateIdentifier($identifier)) {
    echo "\n[ERROR] Invalid Administrator ID. Must be 3-64 characters alphanumeric.\n";
    exit(1);
}

if (empty($fullName)) {
    echo "\n[ERROR] Full name cannot be empty.\n";
    exit(1);
}

try {
    $userRepo = new UserRepository();
    $existing = $userRepo->findByIdentifier($identifier);

    if ($existing) {
        echo "\n[ERROR] An account with ID '{$identifier}' already exists (Role: {$existing['role']}).\n";
        echo "If you need to reset the password, use a password reset workflow.\n";
        exit(1);
    }

    // Generate cryptographically secure random temporary password (CSPRNG)
    $hex = bin2hex(random_bytes(4));
    $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $digits = '23456789';
    $specials = '!@#*';
    $randUpper = $letters[random_int(0, strlen($letters) - 1)];
    $randDigit = $digits[random_int(0, strlen($digits) - 1)];
    $randSpecial = $specials[random_int(0, strlen($specials) - 1)];
    
    $tempPassword = 'Vv-' . $randUpper . $hex . $randDigit . $randSpecial;
    $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24-hour expiry

    $adminId = $userRepo->create([
        'identifier' => $identifier,
        'role' => 'admin',
        'full_name' => $fullName,
        'email' => $email,
        'department' => 'Administration',
        'academic_year' => null,
        'password_hash' => $passwordHash,
        'must_change_password' => 1, // Mandatory first-login change
        'status' => 'active',
        'temp_password_expires_at' => $expiresAt
    ]);

    Logger::audit('INITIAL_ADMIN_CREATED', 'user', (string)$adminId, [
        'identifier' => $identifier,
        'email' => $email,
        'expires_at' => $expiresAt
    ], $adminId);

    echo "\n[SUCCESS] Administrator account provisioned successfully!\n";
    echo "========================================================\n";
    echo " Admin ID:           {$identifier}\n";
    echo " Full Name:          {$fullName}\n";
    echo " Role:               System Administrator (admin)\n";
    echo " Temporary Password: {$tempPassword}\n";
    echo " Valid Until:        {$expiresAt} (24 Hours)\n";
    echo " Mandatory Change:   YES (Required upon first login)\n";
    echo "========================================================\n";
    echo "SECURITY WARNING:\n";
    echo "1. This temporary credential is displayed ONCE and is NOT stored in plaintext.\n";
    echo "2. Copy this temporary password and sign in immediately at the portal.\n";
    echo "3. You will be required to establish a private personal password upon first sign-in.\n\n";

    exit(0);
} catch (\Throwable $e) {
    echo "\n[CRITICAL ERROR] Failed to provision administrator: " . $e->getMessage() . "\n";
    exit(1);
}
