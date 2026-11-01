<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Helpers\Sanitizer;
use App\Helpers\Validator;
use App\Repositories\UserRepository;
use App\Repositories\LoginAttemptRepository;
use App\Repositories\CandidateRepository;

/**
 * Authentication and Password Life-Cycle Service
 */
class AuthService
{
    private UserRepository $userRepo;
    private LoginAttemptRepository $loginAttemptRepo;
    private CandidateRepository $candidateRepo;
    private array $config;

    public function __construct(
        ?UserRepository $userRepo = null,
        ?LoginAttemptRepository $loginAttemptRepo = null,
        ?CandidateRepository $candidateRepo = null,
        ?array $config = null
    ) {
        $this->userRepo = $userRepo ?? new UserRepository();
        $this->loginAttemptRepo = $loginAttemptRepo ?? new LoginAttemptRepository();
        $this->candidateRepo = $candidateRepo ?? new CandidateRepository();

        if ($config === null) {
            $configFile = dirname(__DIR__, 2) . '/config/config.php';
            if (!file_exists($configFile)) {
                $configFile = dirname(__DIR__, 2) . '/config/config.example.php';
            }
            $appConfig = require $configFile;
            $this->config = $appConfig['security'] ?? [];
        } else {
            $this->config = $config;
        }
    }

    /**
     * Authenticate user with institutional ID and password with portal-specific verification
     */
    public function login(string $rawIdentifier, string $password, string $portal = 'default'): array
    {
        $identifier = Sanitizer::cleanIdentifier($rawIdentifier);
        $ip = Logger::getClientIp();

        $maxAttempts = (int)($this->config['max_login_attempts'] ?? 5);
        $lockoutSeconds = (int)($this->config['lockout_duration_seconds'] ?? 900);

        // Check login throttling
        if ($this->loginAttemptRepo->isThrottled($identifier, $ip, $maxAttempts, $lockoutSeconds)) {
            Logger::audit('LOGIN_THROTTLED', 'user', $identifier, [
                'reason' => 'Rate limit exceeded',
                'portal' => $portal,
                'ip' => $ip
            ]);
            return [
                'success' => false,
                'error' => 'Too many failed sign-in attempts. For security, access is temporarily locked. Please try again in 15 minutes.'
            ];
        }

        // Generic authentication failure message (Does not reveal whether identifier exists)
        $genericFailure = match($portal) {
            'admin' => 'Invalid administrative credentials. Please check your Administrator ID and password.',
            'candidate' => 'Invalid candidate credentials. Please check your Candidate ID/roll number and password.',
            default => 'Invalid institutional credentials. Please check your roll number/employee ID and password.'
        };

        // 1. Resolve via portal-specific identifier mapping (supports portal-scoped login namespaces)
        $mappedUser = null;
        if (in_array($portal, ['admin', 'candidate', 'voter'], true)) {
            $mappedUser = $this->resolvePortalMappedUser($rawIdentifier, $portal);
            if (!$mappedUser && $identifier !== $rawIdentifier) {
                $mappedUser = $this->resolvePortalMappedUser($identifier, $portal);
            }
        }

        // 2. If in candidate portal and not mapped, check candidate code / ID lookup
        $candidateUser = null;
        if (!$mappedUser && $portal === 'candidate') {
            $candProfile = $this->candidateRepo->findByCandidateIdentifier($rawIdentifier);
            if (!$candProfile && $identifier !== $rawIdentifier) {
                $candProfile = $this->candidateRepo->findByCandidateIdentifier($identifier);
            }
            if ($candProfile && !empty($candProfile['user_id'])) {
                $candidateUser = $this->userRepo->findById((int)$candProfile['user_id']);
            }
        }

        $user = $mappedUser ?? $candidateUser ?? $this->userRepo->findByIdentifier($identifier);

        if (!$user) {
            $this->loginAttemptRepo->recordAttempt($identifier, $ip, false);
            return ['success' => false, 'error' => $genericFailure];
        }

        // Check password
        if (!password_verify($password, $user['password_hash'])) {
            $this->loginAttemptRepo->recordAttempt($identifier, $ip, false);
            Logger::audit('LOGIN_FAILED', 'user', (string)$user['id'], [
                'identifier' => $identifier,
                'portal' => $portal,
                'ip' => $ip
            ], (int)$user['id']);
            return ['success' => false, 'error' => $genericFailure];
        }

        // Check account status
        if ($user['status'] !== 'active') {
            $statusMsg = match ($user['status']) {
                'pending' => 'Your account registration is pending approval by the institutional administrator.',
                'suspended' => 'Your account has been suspended. Please contact the administrator.',
                'disabled' => 'Your account has been disabled. Please contact the administrator.',
                'deactivated' => 'Your account has been deactivated.',
                default => 'Your account is not active.'
            };
            return ['success' => false, 'error' => $statusMsg];
        }

        // Check temporary password expiry
        if ((bool)$user['must_change_password'] && !empty($user['temp_password_expires_at'])) {
            if (strtotime($user['temp_password_expires_at']) < time()) {
                Logger::audit('LOGIN_TEMP_PASSWORD_EXPIRED', 'user', (string)$user['id'], [], (int)$user['id']);
                return [
                    'success' => false,
                    'error' => 'Your temporary password has expired. Please contact the system administrator for a new credential.'
                ];
            }
        }

        // Portal-Specific Role Mismatch & Authorization Verification
        if ($portal === 'admin') {
            if (!in_array($user['role'], ['admin', 'election_officer'], true)) {
                $this->loginAttemptRepo->recordAttempt($identifier, $ip, false);
                Logger::audit('PORTAL_ROLE_MISMATCH', 'user', (string)$user['id'], [
                    'attempted_portal' => 'admin',
                    'user_role' => $user['role']
                ], (int)$user['id']);
                return [
                    'success' => false,
                    'error' => 'Access denied: Administrative credentials required. Please sign in through the Voter or Candidate portal.'
                ];
            }
        } elseif ($portal === 'candidate') {
            $hasCandidateProfile = $this->candidateRepo->findByUserId((int)$user['id']);
            if (empty($hasCandidateProfile) && $user['role'] !== 'candidate') {
                $this->loginAttemptRepo->recordAttempt($identifier, $ip, false);
                Logger::audit('PORTAL_ROLE_MISMATCH', 'user', (string)$user['id'], [
                    'attempted_portal' => 'candidate',
                    'user_role' => $user['role']
                ], (int)$user['id']);
                return [
                    'success' => false,
                    'error' => 'Access denied: No candidate profile found for this account. Please sign in through the Voter portal or contact an administrator.'
                ];
            }
        } elseif ($portal === 'voter') {
            // Admin accounts cannot sign in through voter portal unless they have student/staff role
            if ($user['role'] === 'admin') {
                $this->loginAttemptRepo->recordAttempt($identifier, $ip, false);
                Logger::audit('PORTAL_ROLE_MISMATCH', 'user', (string)$user['id'], [
                    'attempted_portal' => 'voter',
                    'user_role' => $user['role']
                ], (int)$user['id']);
                return [
                    'success' => false,
                    'error' => 'Access denied: Administrator accounts cannot sign in through the Voter portal. Please use the Admin portal.'
                ];
            }
        }

        // Record successful login
        $this->loginAttemptRepo->recordAttempt($identifier, $ip, true);
        $this->loginAttemptRepo->clearSuccessful($identifier, $ip);

        Auth::login($user, $portal);

        Logger::audit('LOGIN_SUCCESS', 'user', (string)$user['id'], [
            'role' => $user['role'],
            'portal' => $portal,
            'identifier' => $user['identifier']
        ], (int)$user['id']);

        return [
            'success' => true,
            'user' => $user,
            'portal' => $portal,
            'must_change_password' => (bool)$user['must_change_password']
        ];
    }

    /**
     * Change password (both for first-login temp password and normal user password change)
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword, string $confirmPassword): array
    {
        $user = $this->userRepo->findById($userId);
        if (!$user) {
            return ['success' => false, 'error' => 'User not found.'];
        }

        if (!password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Current password does not match our records.'];
        }

        if ($newPassword !== $confirmPassword) {
            return ['success' => false, 'error' => 'New password and confirmation do not match.'];
        }

        if (!Validator::validatePassword($newPassword)) {
            return [
                'success' => false,
                'error' => 'New password must be at least 8 characters long and contain both letters and numbers.'
            ];
        }

        if (password_verify($newPassword, $user['password_hash'])) {
            return [
                'success' => false,
                'error' => 'New password must be different from your current password.'
            ];
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->userRepo->updatePassword($userId, $newHash);

        // Re-read updated user and refresh session
        $updatedUser = $this->userRepo->findById($userId);
        Auth::login($updatedUser);

        Logger::audit('PASSWORD_CHANGED', 'user', (string)$userId, [], $userId);

        return ['success' => true];
    }

    /**
     * Resolve user via portal-specific identifier namespace mapping
     */
    public function resolvePortalMappedUser(string $identifier, string $portal): ?array
    {
        $db = Database::getInstance();
        $tableExists = $db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'portal_identifier_mappings'"
        );
        if (!$tableExists) {
            return null;
        }

        $clean = Sanitizer::cleanIdentifier($identifier);
        $userId = $db->fetchValue(
            "SELECT user_id FROM portal_identifier_mappings 
             WHERE portal = ? AND (portal_identifier = ? OR portal_identifier = ?) 
             LIMIT 1",
            [$portal, $identifier, $clean],
            'sss'
        );

        if ($userId) {
            return $this->userRepo->findById((int)$userId);
        }

        return null;
    }
}
