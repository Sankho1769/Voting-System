<?php
/**
 * VerityVote - Authentication & Portal Security Load Test
 */

declare(strict_types=1);

namespace Tests\Load;

use App\Core\Database;
use App\Repositories\UserRepository;
use App\Repositories\LoginAttemptRepository;
use App\Repositories\CandidateRepository;
use App\Services\AuthService;
use App\Services\AccountService;

class AuthLoadTest
{
    public static function run(): array
    {
        $results = [];
        $db = Database::getInstance();
        $userRepo = new UserRepository($db);
        $accountService = new AccountService($userRepo);
        $loginAttemptRepo = new LoginAttemptRepository($db);
        $candidateRepo = new CandidateRepository($db);
        $authService = new AuthService($userRepo, $loginAttemptRepo, $candidateRepo);

        // 1. Create a dedicated load test student account
        $loadUser = 'LOAD_STU_' . bin2hex(random_bytes(3));
        $validPass = 'Student@2026';
        $created = $accountService->createUser([
            'identifier' => $loadUser,
            'role' => 'student',
            'full_name' => 'Load Test Student',
            'department' => 'Computer Science'
        ], 1);
        $userRepo->updatePassword($created['user_id'], password_hash($validPass, PASSWORD_DEFAULT));

        // 2. Measure Authentication Verification Latency
        $authLatencies = [];
        for ($i = 0; $i < 20; $i++) {
            $t0 = microtime(true);
            $loginRes = $authService->login($loadUser, $validPass, 'voter');
            $authLatencies[] = (microtime(true) - $t0) * 1000.0;
            // Clean up login attempt row to prevent early lockout during timing test
            $db->execute("DELETE FROM login_attempts WHERE identifier = ?", [$loadUser], 's');
        }

        $avgAuthMs = round(array_sum($authLatencies) / count($authLatencies), 2);
        sort($authLatencies);
        $p95AuthMs = round($authLatencies[(int)(count($authLatencies) * 0.95)], 2);

        $results[] = [
            'name' => sprintf('Auth Load: Argon2id/Bcrypt password verification completes in avg %.2f ms (p95: %.2f ms)', $avgAuthMs, $p95AuthMs),
            'pass' => $avgAuthMs < 250.0 && $loginRes['success'] === true,
            'metrics' => ['avg_ms' => $avgAuthMs, 'p95_ms' => $p95AuthMs]
        ];

        // 3. Brute Force Throttling Burst Test
        // Submit 6 rapid invalid passwords for a distinct target
        $targetUser = 'BRUTE_VICTIM_' . bin2hex(random_bytes(3));
        $accountService->createUser([
            'identifier' => $targetUser,
            'role' => 'student',
            'full_name' => 'Brute Force Target',
            'department' => 'Commerce'
        ], 1);

        $lockoutTriggered = false;
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $badLogin = $authService->login($targetUser, 'WrongPassword999!', 'voter');
            if ($attempt >= 5 && str_contains($badLogin['error'] ?? '', 'temporarily locked')) {
                $lockoutTriggered = true;
            }
        }

        $results[] = [
            'name' => 'Auth Load: Lockout throttle activates after 5 consecutive failed login bursts',
            'pass' => $lockoutTriggered === true
        ];

        return $results;
    }
}
