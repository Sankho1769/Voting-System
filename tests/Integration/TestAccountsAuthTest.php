<?php
/**
 * VerityVote - Dedicated Portal Authentication Tests
 *
 * Validates cross-portal authentication, candidate ID syntax, dual candidate/voter authorization,
 * password hash verification, and HTTP role boundaries using isolated dynamic fixtures.
 */

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Auth;
use App\Core\Database;
use App\Repositories\UserRepository;
use App\Repositories\CandidateRepository;
use App\Repositories\EligibilityRepository;
use App\Repositories\VotingRepository;
use App\Services\AuthService;
use App\Services\CandidateService;
use Tests\Support\TestFixtureFactory;

class TestAccountsAuthTest
{
    public static function run(): array
    {
        $results = [];
        $db = Database::getInstance();
        $fixtures = TestFixtureFactory::getSuiteFixtures($db);

        $authService = new AuthService();
        $userRepo = new UserRepository($db);
        $candidateRepo = new CandidateRepository($db);
        $eligibilityRepo = new EligibilityRepository($db);
        $candidateService = new CandidateService($candidateRepo);

        $visibleId = $fixtures['visible_id'];
        $password = $fixtures['password'];
        $adminId = (int)$fixtures['admin_id'];
        $voterId = (int)$fixtures['voter_id'];
        $candIdSyntax = $fixtures['candidate_id_syntax'];
        $testElectionId = (int)$fixtures['election_id'];

        // 1. Verify Admin Login with visible ID
        $adminRes = $authService->login($visibleId, $password, 'admin');
        $adminUser = $adminRes['user'] ?? null;
        $results[] = [
            'name' => "Admin Portal: Visible ID \"{$visibleId}\" logs in as Administrator",
            'pass' => $adminRes['success'] === true && is_array($adminUser) && ($adminUser['role'] ?? '') === 'admin' && (int)$adminUser['id'] === $adminId
        ];

        // 2. Verify Candidate Login with visible ID
        $candRes = $authService->login($visibleId, $password, 'candidate');
        $candUser = $candRes['user'] ?? null;
        $results[] = [
            'name' => "Candidate Portal: Visible ID \"{$visibleId}\" logs in as Candidate",
            'pass' => $candRes['success'] === true && is_array($candUser) && (int)$candUser['id'] === $voterId && (int)$candUser['id'] !== $adminId
        ];

        // 3. Verify Candidate Login with Candidate ID syntax (e.g. CAND-xx)
        $candIdRes = $authService->login($candIdSyntax, $password, 'candidate');
        $candIdUser = $candIdRes['user'] ?? null;
        $results[] = [
            'name' => "Candidate Portal: Candidate ID syntax ({$candIdSyntax}) logs into candidate account",
            'pass' => $candIdRes['success'] === true && is_array($candIdUser) && (int)$candIdUser['id'] === $voterId
        ];

        // 4. Verify Voter Login with visible ID
        $voterRes = $authService->login($visibleId, $password, 'voter');
        $voterUser = $voterRes['user'] ?? null;
        $results[] = [
            'name' => "Voter Portal: Visible ID \"{$visibleId}\" logs in as Eligible Voter",
            'pass' => $voterRes['success'] === true && is_array($voterUser) && ($voterUser['role'] ?? '') === 'student' && (int)$voterUser['id'] === $voterId
        ];

        // 5. Verify Invalid Passwords rejected with generic message
        $badAdmin = $authService->login($visibleId, 'WrongPass999', 'admin');
        $badCand = $authService->login($visibleId, 'WrongPass999', 'candidate');
        $badVoter = $authService->login($visibleId, 'WrongPass999', 'voter');
        $results[] = [
            'name' => "Security: Incorrect password with \"{$visibleId}\" rejected across all 3 portals",
            'pass' => !$badAdmin['success'] && !$badCand['success'] && !$badVoter['success']
        ];

        // 6. Verify Passwords Stored as Bcrypt Hashes (not plaintext)
        $adminDb = $userRepo->findById($adminId);
        $voterDb = $userRepo->findById($voterId);
        $adminHash = (string)($adminDb['password_hash'] ?? '');
        $voterHash = (string)($voterDb['password_hash'] ?? '');
        $isHashed = str_starts_with($adminHash, '$2y$') &&
                    str_starts_with($voterHash, '$2y$') &&
                    $adminHash !== $password;
        $results[] = [
            'name' => 'Security: Credentials stored securely as bcrypt hashes',
            'pass' => $isHashed
        ];

        // 7. Verify Candidate Dashboard Data
        $dashboardData = $candidateService->getCandidateDashboardData($voterId);
        $hasApprovedProfile = !empty($dashboardData['profiles']) &&
                              ($dashboardData['profiles'][0]['candidacy']['approval_status'] ?? '') === 'approved';
        $results[] = [
            'name' => 'Candidate Control Center: Renders approved candidacy, election and manifesto',
            'pass' => $hasApprovedProfile && !empty($dashboardData['profiles'][0]['candidacy']['manifesto'])
        ];

        // 8. Verify Dual Authorization Recognition
        $results[] = [
            'name' => 'Dual Authorization: Candidate dashboard recognizes user as an eligible voter',
            'pass' => !empty($dashboardData['is_eligible_voter']) && ($dashboardData['eligible_elections_count'] ?? 0) >= 1
        ];

        // 9. Verify Voter Eligibility Isolation (Whitelisted for test election only)
        $isEligibleTest = $eligibilityRepo->isEligible($testElectionId, $voterId);
        $isEligibleOther = $eligibilityRepo->isEligible(999999, $voterId);
        $results[] = [
            'name' => 'Voter Whitelist: Whitelisted only for assigned election, rejected for unassigned races',
            'pass' => $isEligibleTest && !$isEligibleOther
        ];

        // 10. Verify Real HTTP Journeys via Apache cURL
        $dir = basename(dirname(__DIR__, 2));
        if ($dir === 'Voting-System' || !empty(getenv('CI'))) {
            $dir = 'Baymax_Voting_System';
        }
        $baseUrl = 'http://127.0.0.1/' . $dir . '/public';
        $cookieAdmin = tempnam(sys_get_temp_dir(), 'ck_adm_');
        $cookieCand = tempnam(sys_get_temp_dir(), 'ck_cnd_');
        $cookieVoter = tempnam(sys_get_temp_dir(), 'ck_vtr_');

        // HTTP Admin Login Journey
        $loginAdminUrl = $baseUrl . '/login/admin';
        $adminHtml = self::httpGet($loginAdminUrl, $cookieAdmin);
        preg_match('/name="csrf_token" value="([^"]+)"/', $adminHtml, $mAdmin);
        $csrfAdmin = $mAdmin[1] ?? '';

        $postAdmin = self::httpPost($loginAdminUrl, [
            'csrf_token' => $csrfAdmin,
            'identifier' => $visibleId,
            'password' => $password
        ], $cookieAdmin);

        $adminDashHtml = self::httpGet($baseUrl . '/admin', $cookieAdmin);
        $results[] = [
            'name' => "HTTP Journey: Admin portal sign-in with \"{$visibleId}\" opens /admin dashboard (200 OK)",
            'pass' => in_array($postAdmin['code'], [302, 303]) &&
                      strpos($adminDashHtml, 'Administrator') !== false
        ];

        // HTTP Admin attempting /candidate (Must return 403 Forbidden)
        $adminAtCand = self::httpGetWithCode($baseUrl . '/candidate', $cookieAdmin);
        $results[] = [
            'name' => 'HTTP Boundary: Administrator accessing /candidate is blocked with 403 Forbidden',
            'pass' => $adminAtCand['code'] === 403
        ];

        // HTTP Admin attempting /voter (Must return 403 Forbidden)
        $adminAtVoter = self::httpGetWithCode($baseUrl . '/voter', $cookieAdmin);
        $results[] = [
            'name' => 'HTTP Boundary: Administrator accessing /voter is blocked with 403 Forbidden',
            'pass' => $adminAtVoter['code'] === 403
        ];

        // HTTP Candidate Login Journey
        $loginCandUrl = $baseUrl . '/login/candidate';
        $candHtml = self::httpGet($loginCandUrl, $cookieCand);
        preg_match('/name="csrf_token" value="([^"]+)"/', $candHtml, $mCand);
        $csrfCand = $mCand[1] ?? '';

        $postCand = self::httpPost($loginCandUrl, [
            'csrf_token' => $csrfCand,
            'identifier' => $visibleId,
            'password' => $password
        ], $cookieCand);

        $candDashHtml = self::httpGet($baseUrl . '/candidate', $cookieCand);
        $results[] = [
            'name' => "HTTP Journey: Candidate portal sign-in with \"{$visibleId}\" opens /candidate Control Center (200 OK)",
            'pass' => in_array($postCand['code'], [302, 303]) &&
                      strpos($candDashHtml, 'Candidate Control Center') !== false
        ];

        // HTTP Candidate attempting /admin (Must return 403 Forbidden)
        $candAtAdmin = self::httpGetWithCode($baseUrl . '/admin', $cookieCand);
        $results[] = [
            'name' => 'HTTP Boundary: Candidate session accessing /admin is blocked with 403 Forbidden',
            'pass' => $candAtAdmin['code'] === 403
        ];

        // HTTP Voter Login Journey
        $loginVoterUrl = $baseUrl . '/login/voter';
        $voterHtml = self::httpGet($loginVoterUrl, $cookieVoter);
        preg_match('/name="csrf_token" value="([^"]+)"/', $voterHtml, $mVoter);
        $csrfVoter = $mVoter[1] ?? '';

        $postVoter = self::httpPost($loginVoterUrl, [
            'csrf_token' => $csrfVoter,
            'identifier' => $visibleId,
            'password' => $password
        ], $cookieVoter);

        $voterDashHtml = self::httpGet($baseUrl . '/voter', $cookieVoter);
        $results[] = [
            'name' => "HTTP Journey: Voter portal sign-in with \"{$visibleId}\" opens /voter dashboard (200 OK)",
            'pass' => in_array($postVoter['code'], [302, 303]) &&
                      (strpos($voterDashHtml, 'Institutional Elections') !== false || strpos($voterDashHtml, 'Member Elections Portal') !== false)
        ];

        // Clean up temporary session cookies
        @unlink($cookieAdmin);
        @unlink($cookieCand);
        @unlink($cookieVoter);

        return $results;
    }

    public static function httpGet(string $url, string $cookieFile): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        $res = curl_exec($ch);
        curl_close($ch);
        return (string)$res;
    }

    public static function httpGetWithCode(string $url, string $cookieFile): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['body' => (string)$res, 'code' => (int)$code];
    }

    public static function httpPost(string $url, array $data, string $cookieFile): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['headers' => (string)$res, 'code' => (int)$code];
    }
}
