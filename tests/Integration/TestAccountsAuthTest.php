<?php
namespace Tests\Integration;

use App\Core\Auth;
use App\Core\Database;
use App\Repositories\UserRepository;
use App\Repositories\CandidateRepository;
use App\Repositories\EligibilityRepository;
use App\Repositories\VotingRepository;
use App\Services\AuthService;
use App\Services\CandidateService;
use App\Services\VotingService;

class TestAccountsAuthTest
{
    public static function run(): array
    {
        $results = [];
        $db = Database::getInstance();
        $authService = new AuthService();
        $userRepo = new UserRepository();
        $candidateRepo = new CandidateRepository();
        $eligibilityRepo = new EligibilityRepository();
        $votingRepo = new VotingRepository();
        $candidateService = new CandidateService();

        // 1. Verify Admin Login with visible ID Shubham
        $adminRes = $authService->login('Shubham', 'Shubham56ty2', 'admin');
        $results[] = [
            'name' => 'Admin Portal: Visible ID "Shubham" logs in as Administrator',
            'pass' => $adminRes['success'] === true && $adminRes['user']['role'] === 'admin'
        ];

        // 2. Verify Candidate Login with visible ID Shubham
        $candRes = $authService->login('Shubham', 'Shubham56ty2', 'candidate');
        $results[] = [
            'name' => 'Candidate Portal: Visible ID "Shubham" logs in as Candidate',
            'pass' => $candRes['success'] === true && (int)$candRes['user']['id'] !== (int)$adminRes['user']['id']
        ];

        // 3. Verify Candidate Login with Candidate ID syntax (e.g. CAND-xx)
        $candProfile = $candidateRepo->findByUserId((int)$candRes['user']['id']);
        $candIdSyntax = !empty($candProfile) ? 'CAND-' . $candProfile[0]['id'] : '';
        $candIdRes = !empty($candIdSyntax) ? $authService->login($candIdSyntax, 'Shubham56ty2', 'candidate') : ['success' => false];
        $results[] = [
            'name' => "Candidate Portal: Candidate ID syntax ({$candIdSyntax}) logs into candidate account",
            'pass' => $candIdRes['success'] === true && (int)$candIdRes['user']['id'] === (int)$candRes['user']['id']
        ];

        // 4. Verify Voter Login with visible ID Shubham
        $voterRes = $authService->login('Shubham', 'Shubham56ty2', 'voter');
        $results[] = [
            'name' => 'Voter Portal: Visible ID "Shubham" logs in as Eligible Voter',
            'pass' => $voterRes['success'] === true && $voterRes['user']['role'] === 'student' && (int)$voterRes['user']['id'] === (int)$candRes['user']['id']
        ];

        // 5. Verify Invalid Passwords rejected with generic message
        $badAdmin = $authService->login('Shubham', 'WrongPass999', 'admin');
        $badCand = $authService->login('Shubham', 'WrongPass999', 'candidate');
        $badVoter = $authService->login('Shubham', 'WrongPass999', 'voter');
        $results[] = [
            'name' => 'Security: Incorrect password with "Shubham" rejected across all 3 portals',
            'pass' => !$badAdmin['success'] && !$badCand['success'] && !$badVoter['success']
        ];

        // 6. Verify Passwords Stored as Bcrypt Hashes (not plaintext)
        $adminDb = $userRepo->findById((int)$adminRes['user']['id']);
        $voterDb = $userRepo->findById((int)$voterRes['user']['id']);
        $isHashed = str_starts_with($adminDb['password_hash'], '$2y$') &&
                    str_starts_with($voterDb['password_hash'], '$2y$') &&
                    $adminDb['password_hash'] !== 'Shubham56ty2';
        $results[] = [
            'name' => 'Security: Credentials stored securely as bcrypt hashes',
            'pass' => $isHashed
        ];

        // 7. Verify Candidate Dashboard Data for Shubham
        $dashboardData = $candidateService->getCandidateDashboardData((int)$voterRes['user']['id']);
        $hasApprovedProfile = !empty($dashboardData['profiles']) &&
                              $dashboardData['profiles'][0]['candidacy']['approval_status'] === 'approved';
        $results[] = [
            'name' => 'Candidate Control Center: Renders approved candidacy, election and manifesto',
            'pass' => $hasApprovedProfile && !empty($dashboardData['profiles'][0]['candidacy']['manifesto'])
        ];

        // 8. Verify Dual Authorization Banner Recognition
        $results[] = [
            'name' => 'Dual Authorization: Candidate dashboard recognizes user as an eligible voter',
            'pass' => !empty($dashboardData['is_eligible_voter']) && $dashboardData['eligible_elections_count'] >= 1
        ];

        // 9. Verify Voter Eligibility Isolation (Whitelisted for test election only)
        $testElectionId = !empty($dashboardData['profiles']) ? (int)$dashboardData['profiles'][0]['candidacy']['election_id'] : 0;
        $isEligibleTest = $eligibilityRepo->isEligible($testElectionId, (int)$voterRes['user']['id']);
        $isEligibleOther = $eligibilityRepo->isEligible(999999, (int)$voterRes['user']['id']);
        $results[] = [
            'name' => 'Voter Whitelist: Whitelisted only for assigned election, rejected for unassigned races',
            'pass' => $isEligibleTest && !$isEligibleOther
        ];

        // 10. Verify Real HTTP Journeys via Apache cURL
        $baseUrl = 'http://127.0.0.1/Baymax_Voting_System/public';
        $cookieAdmin = sys_get_temp_dir() . '/cookie_admin_shubham.txt';
        $cookieCand = sys_get_temp_dir() . '/cookie_cand_shubham.txt';
        $cookieVoter = sys_get_temp_dir() . '/cookie_voter_shubham.txt';
        @unlink($cookieAdmin);
        @unlink($cookieCand);
        @unlink($cookieVoter);

        // HTTP Admin Login Journey
        $loginAdminUrl = $baseUrl . '/login/admin';
        $adminHtml = self::httpGet($loginAdminUrl, $cookieAdmin);
        preg_match('/name="csrf_token" value="([^"]+)"/', $adminHtml, $mAdmin);
        $csrfAdmin = $mAdmin[1] ?? '';

        $postAdmin = self::httpPost($loginAdminUrl, [
            'csrf_token' => $csrfAdmin,
            'identifier' => 'Shubham',
            'password' => 'Shubham56ty2'
        ], $cookieAdmin);

        $adminDashHtml = self::httpGet($baseUrl . '/admin', $cookieAdmin);
        $results[] = [
            'name' => 'HTTP Journey: Admin portal sign-in with "Shubham" opens /admin dashboard (200 OK)',
            'pass' => strpos($postAdmin['headers'], '302') !== false &&
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
            'identifier' => 'Shubham',
            'password' => 'Shubham56ty2'
        ], $cookieCand);

        $candDashHtml = self::httpGet($baseUrl . '/candidate', $cookieCand);
        $results[] = [
            'name' => 'HTTP Journey: Candidate portal sign-in with "Shubham" opens /candidate Control Center (200 OK)',
            'pass' => strpos($postCand['headers'], '302') !== false &&
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
            'identifier' => 'Shubham',
            'password' => 'Shubham56ty2'
        ], $cookieVoter);

        $voterDashHtml = self::httpGet($baseUrl . '/voter', $cookieVoter);
        $results[] = [
            'name' => 'HTTP Journey: Voter portal sign-in with "Shubham" opens /voter dashboard (200 OK)',
            'pass' => strpos($postVoter['headers'], '302') !== false &&
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
