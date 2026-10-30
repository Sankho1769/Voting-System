<?php
namespace App\Services;

use App\Core\Logger;
use App\Helpers\Sanitizer;
use App\Helpers\Validator;
use App\Repositories\UserRepository;

/**
 * Account Provisioning and Life-Cycle Service
 */
class AccountService
{
    private UserRepository $userRepo;
    private int $tempPasswordExpiryHours;

    public function __construct(?UserRepository $userRepo = null)
    {
        $this->userRepo = $userRepo ?? new UserRepository();

        $configFile = dirname(__DIR__, 2) . '/config/config.php';
        if (!file_exists($configFile)) {
            $configFile = dirname(__DIR__, 2) . '/config/config.example.php';
        }
        $config = require $configFile;
        $this->tempPasswordExpiryHours = (int)($config['security']['temp_password_expiry_hours'] ?? 24);
    }

    /**
     * Generate a cryptographically secure, readable temporary password
     */
    public function generateTemporaryPassword(): string
    {
        // 8 characters random hex + upper + digit + special
        $hex = bin2hex(random_bytes(3));
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits = '23456789';
        $upper = $chars[random_int(0, strlen($chars) - 1)];
        $digit = $digits[random_int(0, strlen($digits) - 1)];

        return 'Vv-' . $upper . $hex . $digit;
    }

    /**
     * Create a new authorized collegiate account
     */
    public function createUser(array $data, int $adminId): array
    {
        $identifier = Sanitizer::cleanIdentifier($data['identifier'] ?? '');
        $role = $data['role'] ?? 'student';
        $fullName = Sanitizer::cleanText($data['full_name'] ?? '');
        $email = !empty($data['email']) ? filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL) : null;
        $department = Sanitizer::cleanText($data['department'] ?? '');
        $academicYear = Sanitizer::cleanText($data['academic_year'] ?? '');

        if (!Validator::validateIdentifier($identifier)) {
            return ['success' => false, 'error' => 'Invalid institutional ID. Must be 3-64 characters and contain only letters, numbers, and dashes.'];
        }

        if (empty($fullName)) {
            return ['success' => false, 'error' => 'Full name is required.'];
        }

        if (!in_array($role, ['student', 'staff', 'candidate', 'election_officer', 'admin'], true)) {
            return ['success' => false, 'error' => 'Invalid role specified.'];
        }

        if ($this->userRepo->findByIdentifier($identifier)) {
            return ['success' => false, 'error' => "An account with institutional ID '{$identifier}' already exists."];
        }

        // Generate temporary password
        $tempPassword = $this->generateTemporaryPassword();
        $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', time() + ($this->tempPasswordExpiryHours * 3600));

        $userId = $this->userRepo->create([
            'identifier' => $identifier,
            'role' => $role,
            'full_name' => $fullName,
            'email' => $email ?: null,
            'department' => $department ?: null,
            'academic_year' => $academicYear ?: null,
            'password_hash' => $passwordHash,
            'must_change_password' => 1,
            'status' => 'active',
            'temp_password_expires_at' => $expiresAt
        ]);

        Logger::audit('ACCOUNT_CREATED', 'user', (string)$userId, [
            'identifier' => $identifier,
            'role' => $role,
            'department' => $department,
            'expires_at' => $expiresAt
        ], $adminId);

        return [
            'success' => true,
            'user_id' => $userId,
            'identifier' => $identifier,
            'full_name' => $fullName,
            'role' => $role,
            'temp_password' => $tempPassword,
            'expires_at' => $expiresAt
        ];
    }

    /**
     * Bulk import students or staff from a CSV file
     */
    public function importFromCsv(string $filePath, int $adminId): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return ['success' => false, 'error' => 'Uploaded CSV file could not be read.'];
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['success' => false, 'error' => 'Unable to open CSV file.'];
        }

        // Skip UTF-8 BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return ['success' => false, 'error' => 'CSV file is empty.'];
        }

        $header = array_map(function($h) {
            return strtolower(trim($h));
        }, $header);

        $requiredCols = ['identifier', 'role', 'full_name'];
        foreach ($requiredCols as $col) {
            if (!in_array($col, $header, true)) {
                fclose($handle);
                return [
                    'success' => false,
                    'error' => "Missing required CSV column: '{$col}'. Expected columns: identifier, role, full_name, email, department, academic_year."
                ];
            }
        }

        $colMap = array_flip($header);
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $issuedCredentials = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (empty(array_filter($row))) {
                continue; // Skip empty line
            }

            $rawId = $row[$colMap['identifier']] ?? '';
            $identifier = Sanitizer::cleanIdentifier($rawId);
            $role = strtolower(trim($row[$colMap['role']] ?? 'student'));
            $fullName = Sanitizer::cleanText($row[$colMap['full_name']] ?? '');
            $email = isset($colMap['email']) ? trim($row[$colMap['email']]) : '';
            $department = isset($colMap['department']) ? Sanitizer::cleanText($row[$colMap['department']]) : '';
            $academicYear = isset($colMap['academic_year']) ? Sanitizer::cleanText($row[$colMap['academic_year']]) : '';

            if (empty($identifier) || empty($fullName)) {
                $errors[] = "Row {$rowNum}: Missing institutional ID or full name.";
                $skipped++;
                continue;
            }

            if (!Validator::validateIdentifier($identifier)) {
                $errors[] = "Row {$rowNum}: Invalid ID format '{$identifier}'.";
                $skipped++;
                continue;
            }

            if (!in_array($role, ['student', 'staff', 'candidate', 'election_officer', 'admin'], true)) {
                $errors[] = "Row {$rowNum}: Invalid role '{$role}'.";
                $skipped++;
                continue;
            }

            if ($this->userRepo->findByIdentifier($identifier)) {
                $errors[] = "Row {$rowNum}: Duplicate ID '{$identifier}' already exists in system.";
                $skipped++;
                continue;
            }

            $res = $this->createUser([
                'identifier' => $identifier,
                'role' => $role,
                'full_name' => $fullName,
                'email' => $email,
                'department' => $department,
                'academic_year' => $academicYear
            ], $adminId);

            if ($res['success']) {
                $imported++;
                $issuedCredentials[] = [
                    'identifier' => $identifier,
                    'full_name' => $fullName,
                    'role' => $role,
                    'department' => $department,
                    'temp_password' => $res['temp_password'],
                    'expires_at' => $res['expires_at']
                ];
            } else {
                $errors[] = "Row {$rowNum}: " . ($res['error'] ?? 'Creation failed');
                $skipped++;
            }
        }

        fclose($handle);

        Logger::audit('CSV_IMPORT_COMPLETED', 'system', 'bulk_import', [
            'imported_count' => $imported,
            'skipped_count' => $skipped
        ], $adminId);

        return [
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'credentials' => $issuedCredentials
        ];
    }

    /**
     * Reset a user's password with a new temporary credential
     */
    public function resetTemporaryPassword(int $userId, int $adminId): array
    {
        $user = $this->userRepo->findById($userId);
        if (!$user) {
            return ['success' => false, 'error' => 'User not found.'];
        }

        $tempPassword = $this->generateTemporaryPassword();
        $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', time() + ($this->tempPasswordExpiryHours * 3600));

        $this->userRepo->resetTemporaryPassword($userId, $passwordHash, $expiresAt);

        Logger::audit('ACCOUNT_PASSWORD_RESET_ADMIN', 'user', (string)$userId, [
            'identifier' => $user['identifier'],
            'expires_at' => $expiresAt
        ], $adminId);

        return [
            'success' => true,
            'user_id' => $userId,
            'identifier' => $user['identifier'],
            'full_name' => $user['full_name'],
            'temp_password' => $tempPassword,
            'expires_at' => $expiresAt
        ];
    }

    /**
     * Change account status (active, suspended, deactivated)
     */
    public function updateStatus(int $userId, string $status, int $adminId): array
    {
        if (!in_array($status, ['active', 'suspended', 'deactivated'], true)) {
            return ['success' => false, 'error' => 'Invalid account status.'];
        }

        $user = $this->userRepo->findById($userId);
        if (!$user) {
            return ['success' => false, 'error' => 'User not found.'];
        }

        // Prevent admin from deactivating themselves
        if ($userId === $adminId && $status !== 'active') {
            return ['success' => false, 'error' => 'You cannot suspend or deactivate your own administrative account.'];
        }

        $this->userRepo->updateStatus($userId, $status);

        Logger::audit('ACCOUNT_STATUS_CHANGED', 'user', (string)$userId, [
            'old_status' => $user['status'],
            'new_status' => $status
        ], $adminId);

        return ['success' => true];
    }
}
