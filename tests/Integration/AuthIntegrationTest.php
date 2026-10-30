<?php
namespace Tests\Integration;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Services\AccountService;
use App\Services\AuthService;
use App\Repositories\UserRepository;
use App\Repositories\LoginAttemptRepository;

class AuthIntegrationTest
{
    public static function run(): array
    {
        $results = [];
        $db = Database::getInstance();
        $userRepo = new UserRepository($db);
        $loginAttemptRepo = new LoginAttemptRepository($db);
        $accountService = new AccountService($userRepo);
        $authService = new AuthService($userRepo, $loginAttemptRepo);

        // 1. Provision a test student account
        $testStudentId = 'TEST_STU_' . bin2hex(random_bytes(3));
        $created = $accountService->createUser([
            'identifier' => $testStudentId,
            'role' => 'student',
            'full_name' => 'Automated Test Student',
            'email' => 'test@verityvote.test',
            'department' => 'Computer Science',
            'academic_year' => '2024-2027'
        ], 1);

        $results[] = [
            'name' => 'AccountService creates student with secure temporary password',
            'pass' => $created['success'] && !empty($created['temp_password'])
        ];

        $tempPassword = $created['temp_password'];
        $userId = $created['user_id'];

        // 2. Reject incorrect password
        $failLogin = $authService->login($testStudentId, 'WrongPassword123');
        $results[] = [
            'name' => 'AuthService rejects incorrect password with generic error',
            'pass' => !$failLogin['success'] && strpos($failLogin['error'], 'Invalid institutional credentials') !== false
        ];

        // 3. Test Login Throttling after 5 failed attempts
        for ($i = 0; $i < 4; $i++) {
            $authService->login($testStudentId, 'WrongPassword123');
        }
        $throttledLogin = $authService->login($testStudentId, 'WrongPassword123');
        $results[] = [
            'name' => 'AuthService throttles after 5 failed login attempts',
            'pass' => !$throttledLogin['success'] && strpos($throttledLogin['error'], 'temporarily locked') !== false
        ];

        // Clear attempts for testing subsequent valid login
        $loginAttemptRepo->clearSuccessful($testStudentId, '127.0.0.1');

        // 4. Successful login with temporary password
        $successLogin = $authService->login($testStudentId, $tempPassword);
        $results[] = [
            'name' => 'AuthService authenticates with valid temporary password and flags mandatory change',
            'pass' => $successLogin['success'] && $successLogin['must_change_password'] === true
        ];

        // 5. Mandatory Password Change
        $newPermanentPassword = 'PermanentPass123';
        $changeRes = $authService->changePassword($userId, $tempPassword, $newPermanentPassword, $newPermanentPassword);
        $results[] = [
            'name' => 'AuthService successfully changes password and clears mandatory change flag',
            'pass' => $changeRes['success']
        ];

        $refreshedUser = $userRepo->findById($userId);
        $results[] = [
            'name' => 'User must_change_password becomes 0 in DB',
            'pass' => (int)$refreshedUser['must_change_password'] === 0
        ];

        // 6. Test login with newly established permanent password
        Auth::logout();
        $permLogin = $authService->login($testStudentId, $newPermanentPassword);
        $results[] = [
            'name' => 'User successfully signs in with new permanent password',
            'pass' => $permLogin['success'] && $permLogin['must_change_password'] === false
        ];

        // 7. Status suspension immediately invalidates session
        $accountService->updateStatus($userId, 'suspended', 1);
        $suspendedLogin = $authService->login($testStudentId, $newPermanentPassword);
        $results[] = [
            'name' => 'Suspended account is immediately rejected on authentication',
            'pass' => !$suspendedLogin['success'] && strpos($suspendedLogin['error'], 'suspended') !== false
        ];

        return $results;
    }
}
