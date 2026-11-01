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
        $status = in_array($data['status'] ?? '', ['pending', 'active', 'suspended', 'disabled', 'deactivated'], true)
            ? $data['status']
            : 'active';

        if (!Validator::validateIdentifier($identifier)) {
            return ['success' => false, 'error' => 'Invalid institutional ID. Must be 3-64 characters and contain only letters, numbers, and dashes.'];
        }

        if (empty($fullName)) {
            return ['success' => false, 'error' => 'Full name is required.'];
        }

        if (!in_array($role, ['student', 'staff', 'candidate', 'election_officer', 'admin'], true)) {
            return ['success' => false, 'error' => 'Invalid role specified.'];
        }

        // Case-insensitive canonical duplicate identity check
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
            'status' => $status,
            'temp_password_expires_at' => $expiresAt
        ]);

        Logger::audit('ACCOUNT_CREATED', 'user', (string)$userId, [
            'identifier' => $identifier,
            'role' => $role,
            'status' => $status,
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
     * Parse and preview students/staff from a CSV file before committing
     * Detects in-file duplicates, DB duplicates, and malformed identities.
     */
    public function previewCsv(string $filePath): array
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

        $cleanHeader = array_map(function($h) {
            $h = strtolower(trim($h));
            if ($h === 'institutional_id' || $h === 'roll_number' || $h === 'employee_id' || $h === 'voter_id') return 'identifier';
            if ($h === 'account_category' || $h === 'category') return 'role';
            if ($h === 'designation') return 'academic_year';
            return $h;
        }, $header);

        $requiredCols = ['identifier', 'role', 'full_name'];
        foreach ($requiredCols as $col) {
            if (!in_array($col, $cleanHeader, true)) {
                fclose($handle);
                return [
                    'success' => false,
                    'error' => "Missing required CSV column: '{$col}'. Expected columns: identifier (or institutional_id), role (or account_category), full_name, email, department, academic_year (or designation)."
                ];
            }
        }

        $colMap = array_flip($cleanHeader);

        $accepted = [];
        $duplicatesFile = [];
        $duplicatesDb = [];
        $invalid = [];
        $allIssues = [];

        $seenInFile = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (empty(array_filter($row))) {
                continue;
            }

            $rawId = $row[$colMap['identifier']] ?? '';
            $identifier = Sanitizer::cleanIdentifier($rawId);
            $rawRole = strtolower(trim($row[$colMap['role']] ?? 'student'));
            $role = match ($rawRole) {
                'student', 'pupil' => 'student',
                'staff', 'faculty', 'employee', 'teacher' => 'staff',
                'candidate' => 'candidate',
                'election_officer', 'officer' => 'election_officer',
                'admin', 'administrator' => 'admin',
                default => $rawRole
            };
            $fullName = Sanitizer::cleanText($row[$colMap['full_name']] ?? '');
            $email = isset($colMap['email']) ? trim($row[$colMap['email']]) : '';
            $department = isset($colMap['department']) ? Sanitizer::cleanText($row[$colMap['department']]) : '';
            $academicYear = isset($colMap['academic_year']) ? Sanitizer::cleanText($row[$colMap['academic_year']]) : '';

            $rowData = [
                'row' => $rowNum,
                'identifier' => $identifier,
                'role' => $role,
                'full_name' => $fullName,
                'email' => $email,
                'department' => $department,
                'academic_year' => $academicYear
            ];

            // 1. Validation checks
            if (empty($identifier) || empty($fullName)) {
                $reason = "Missing required institutional ID or full name";
                $rowData['reason'] = $reason;
                $invalid[] = $rowData;
                $allIssues[] = ['row' => $rowNum, 'identifier' => $rawId, 'full_name' => $fullName, 'category' => 'Invalid Record', 'details' => $reason];
                continue;
            }

            if (!Validator::validateIdentifier($identifier)) {
                $reason = "Invalid institutional ID format (allowed: letters, numbers, dashes, 3-64 chars)";
                $rowData['reason'] = $reason;
                $invalid[] = $rowData;
                $allIssues[] = ['row' => $rowNum, 'identifier' => $identifier, 'full_name' => $fullName, 'category' => 'Invalid Format', 'details' => $reason];
                continue;
            }

            if (!in_array($role, ['student', 'staff', 'candidate', 'election_officer', 'admin'], true)) {
                $reason = "Invalid role '{$rawRole}'. Must be student, staff, or candidate.";
                $rowData['reason'] = $reason;
                $invalid[] = $rowData;
                $allIssues[] = ['row' => $rowNum, 'identifier' => $identifier, 'full_name' => $fullName, 'category' => 'Invalid Role', 'details' => $reason];
                continue;
            }

            // 2. In-file duplicate detection (case-insensitive canonical check)
            $idLower = strtolower($identifier);
            if (isset($seenInFile[$idLower])) {
                $prevRow = $seenInFile[$idLower];
                $reason = "Duplicate institutional ID within CSV (first encountered at Row {$prevRow})";
                $rowData['reason'] = $reason;
                $duplicatesFile[] = $rowData;
                $allIssues[] = ['row' => $rowNum, 'identifier' => $identifier, 'full_name' => $fullName, 'category' => 'In-File Duplicate', 'details' => $reason];
                continue;
            }
            $seenInFile[$idLower] = $rowNum;

            // 3. Database duplicate detection
            if ($this->userRepo->findByIdentifier($identifier)) {
                $reason = "Institutional ID '{$identifier}' already provisioned in database";
                $rowData['reason'] = $reason;
                $duplicatesDb[] = $rowData;
                $allIssues[] = ['row' => $rowNum, 'identifier' => $identifier, 'full_name' => $fullName, 'category' => 'Existing Database Account', 'details' => $reason];
                continue;
            }

            // Record is accepted and ready to commit
            $accepted[] = $rowData;
        }

        fclose($handle);

        $importToken = bin2hex(random_bytes(16));
        $previewData = [
            'import_token' => $importToken,
            'total_rows' => $rowNum - 1,
            'accepted_count' => count($accepted),
            'duplicate_file_count' => count($duplicatesFile),
            'duplicate_db_count' => count($duplicatesDb),
            'invalid_count' => count($invalid),
            'accepted' => $accepted,
            'duplicates_file' => $duplicatesFile,
            'duplicates_db' => $duplicatesDb,
            'invalid' => $invalid,
            'issues' => $allIssues
        ];

        \App\Core\Session::set('_csv_preview_' . $importToken, $previewData);

        return [
            'success' => true,
            'data' => $previewData
        ];
    }

    /**
     * Commit a confirmed CSV import
     */
    public function commitCsvImport(string $importToken, int $adminId): array
    {
        $sessionKey = '_csv_preview_' . $importToken;
        $preview = \App\Core\Session::get($sessionKey);

        if (!$preview || empty($preview['accepted'])) {
            return ['success' => false, 'error' => 'No valid preview found or no accepted records to commit.'];
        }

        $imported = 0;
        $issuedCredentials = [];
        $errors = [];

        foreach ($preview['accepted'] as $row) {
            $res = $this->createUser([
                'identifier' => $row['identifier'],
                'role' => $row['role'],
                'full_name' => $row['full_name'],
                'email' => $row['email'],
                'department' => $row['department'],
                'academic_year' => $row['academic_year']
            ], $adminId);

            if ($res['success']) {
                $imported++;
                $issuedCredentials[] = [
                    'identifier' => $row['identifier'],
                    'full_name' => $row['full_name'],
                    'role' => $row['role'],
                    'department' => $row['department'],
                    'temp_password' => $res['temp_password'],
                    'expires_at' => $res['expires_at']
                ];
            } else {
                $errors[] = "Row {$row['row']}: {$res['error']}";
            }
        }

        \App\Core\Session::remove($sessionKey);

        Logger::audit('CSV_IMPORT_COMMITTED', 'system', 'bulk_import', [
            'imported_count' => $imported,
            'skipped_count' => $preview['total_rows'] - $imported,
            'total_file_rows' => $preview['total_rows']
        ], $adminId);

        return [
            'success' => true,
            'imported' => $imported,
            'skipped' => $preview['total_rows'] - $imported,
            'errors' => $errors,
            'credentials' => $issuedCredentials
        ];
    }

    /**
     * Direct programmatic bulk import from a CSV file (maintains backward compatibility)
     */
    public function importFromCsv(string $filePath, int $adminId): array
    {
        $previewRes = $this->previewCsv($filePath);
        if (!$previewRes['success']) {
            return $previewRes;
        }

        $preview = $previewRes['data'];
        $commitRes = $this->commitCsvImport($preview['import_token'], $adminId);

        $allErrors = [];
        foreach ($preview['duplicates_file'] as $d) {
            $allErrors[] = "Row {$d['row']}: {$d['reason']}";
        }
        foreach ($preview['duplicates_db'] as $d) {
            $allErrors[] = "Row {$d['row']}: {$d['reason']}";
        }
        foreach ($preview['invalid'] as $inv) {
            $allErrors[] = "Row {$inv['row']}: {$inv['reason']}";
        }

        return [
            'success' => true,
            'imported' => $commitRes['imported'],
            'skipped' => $commitRes['skipped'],
            'errors' => array_merge($allErrors, $commitRes['errors']),
            'credentials' => $commitRes['credentials']
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
     * Change account status (pending, active, suspended, disabled, deactivated)
     */
    public function updateStatus(int $userId, string $status, int $adminId): array
    {
        if (!in_array($status, ['pending', 'active', 'suspended', 'disabled', 'deactivated'], true)) {
            return ['success' => false, 'error' => 'Invalid account status. Expected: pending, active, suspended, disabled, deactivated.'];
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
