<?php
namespace App\Core;

/**
 * Authentication and Authorization Context
 * Manages identity, session synchronization, and role-based permissions.
 */
class Auth
{
    private static ?array $cachedUser = null;

    public static function isLoggedIn(): bool
    {
        Session::start();
        return Session::has('user_id');
    }

    public static function id(): ?int
    {
        Session::start();
        $id = Session::get('user_id');
        return $id !== null ? (int)$id : null;
    }

    public static function role(): ?string
    {
        Session::start();
        return Session::get('user_role');
    }

    public static function identifier(): ?string
    {
        Session::start();
        return Session::get('user_identifier');
    }

    public static function mustChangePassword(): bool
    {
        Session::start();
        return (bool)Session::get('must_change_password', false);
    }

    public static function user(): ?array
    {
        if (!self::isLoggedIn()) {
            return null;
        }

        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }

        $userId = self::id();
        $db = Database::getInstance();
        $user = $db->fetchOne(
            "SELECT id, identifier, role, full_name, email, department, academic_year, must_change_password, status, auth_version 
             FROM users WHERE id = ?",
            [$userId],
            'i'
        );

        if (!$user) {
            self::logout();
            return null;
        }

        // Verify account status
        if ($user['status'] !== 'active') {
            self::logout();
            return null;
        }

        // Verify session auth_version matches DB auth_version
        $sessionAuthVersion = Session::get('auth_version');
        if ($sessionAuthVersion !== null && (int)$sessionAuthVersion !== (int)$user['auth_version']) {
            self::logout();
            return null;
        }

        self::$cachedUser = $user;
        return $user;
    }

    public static function login(array $user, string $portal = 'default'): void
    {
        Session::regenerate();
        Session::set('user_id', (int)$user['id']);
        Session::set('user_identifier', $user['identifier']);
        Session::set('user_role', $user['role']);
        Session::set('user_name', $user['full_name']);
        Session::set('auth_version', (int)$user['auth_version']);
        Session::set('must_change_password', (bool)$user['must_change_password']);
        Session::set('auth_portal', $portal);

        // Check and cache candidate status
        $db = Database::getInstance();
        $hasCandidateProfile = $db->fetchValue(
            "SELECT id FROM candidates WHERE user_id = ? LIMIT 1",
            [(int)$user['id']],
            'i'
        ) !== null;
        Session::set('is_candidate', $hasCandidateProfile || ($user['role'] === 'candidate'));

        // Rotate CSRF token on authentication to prevent session fixation / login CSRF
        CSRF::regenerate();

        self::$cachedUser = $user;
    }

    public static function portal(): ?string
    {
        Session::start();
        return Session::get('auth_portal');
    }

    public static function logout(): void
    {
        self::$cachedUser = null;
        Session::remove('user_id');
        Session::remove('user_identifier');
        Session::remove('user_role');
        Session::remove('user_name');
        Session::remove('auth_version');
        Session::remove('must_change_password');
        Session::remove('auth_portal');
        Session::remove('is_candidate');
        Session::destroy();
    }

    public static function hasRole(string ...$roles): bool
    {
        $currentRole = self::role();
        if (!$currentRole) {
            return false;
        }
        return in_array($currentRole, $roles, true);
    }

    public static function isAdmin(): bool
    {
        return self::hasRole('admin');
    }

    public static function isOfficer(): bool
    {
        return self::hasRole('election_officer', 'admin');
    }

    public static function isCandidate(): bool
    {
        if (!self::isLoggedIn()) {
            return false;
        }

        if (self::hasRole('candidate')) {
            return true;
        }

        Session::start();
        if (Session::has('is_candidate')) {
            return (bool)Session::get('is_candidate');
        }

        $userId = self::id();
        if (!$userId) {
            return false;
        }

        $db = Database::getInstance();
        $hasProfile = $db->fetchValue(
            "SELECT id FROM candidates WHERE user_id = ? LIMIT 1",
            [$userId],
            'i'
        ) !== null;

        Session::set('is_candidate', $hasProfile);
        return $hasProfile;
    }

    public static function isStaff(): bool
    {
        return self::hasRole('staff');
    }

    public static function isStudent(): bool
    {
        return self::hasRole('student');
    }

    public static function isVoter(): bool
    {
        return self::hasRole('student', 'staff', 'candidate');
    }
}
