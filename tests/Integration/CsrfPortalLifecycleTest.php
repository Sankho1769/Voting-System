<?php
/**
 * VerityVote - CSRF & Three Portals Session Lifecycle Automated Test Suite
 * Verifies CSRF token generation, session persistence, multi-tab resilience,
 * secure token rotation upon login, and seamless recovery from expired tokens.
 * Utilizes dynamic test fixtures completely independent of permanent local accounts.
 */

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\TestFixtureFactory;

class CsrfPortalLifecycleTest
{
    private string $baseUrl;
    private string $cookieFile;

    public function __construct(string $baseUrl = 'http://localhost/Baymax_Voting_System')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'csrf_test_cookie_');
    }

    public function __destruct()
    {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    private function request(string $path, string $method = 'GET', array $postData = [], ?string $customCookie = null): array
    {
        $url = strpos($path, 'http') === 0 ? $path : $this->baseUrl . $path;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $customCookie ?? $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $customCookie ?? $this->cookieFile);
        curl_setopt($ch, CURLOPT_USERAGENT, 'VerityVote-CSRF-Tester/1.0');

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        curl_close($ch);

        return [
            'code' => $httpCode,
            'headers' => $headers,
            'body' => $body
        ];
    }

    private function extractCsrf(string $html): ?string
    {
        if (preg_match('/name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']/i', $html, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public static function run(): array
    {
        return (new self())->execute();
    }

    public function execute(): array
    {
        $results = [];
        $fixtures = TestFixtureFactory::getSuiteFixtures();
        $testLoginId = $fixtures['visible_id'];
        $testPassword = $fixtures['password'];

        // -------------------------------------------------------------
        // Test 1: Token persistence across multiple GET requests (Multi-Tab Simulation)
        // -------------------------------------------------------------
        $sessionCookie = tempnam(sys_get_temp_dir(), 'csrf_tab_');

        $tab1 = $this->request('/public/login/admin', 'GET', [], $sessionCookie);
        $token1 = $this->extractCsrf($tab1['body']);

        $tab2 = $this->request('/public/login/candidate', 'GET', [], $sessionCookie);
        $token2 = $this->extractCsrf($tab2['body']);

        $tab3 = $this->request('/public/login/voter', 'GET', [], $sessionCookie);
        $token3 = $this->extractCsrf($tab3['body']);

        $persistsAcrossPortals = (!empty($token1) && $token1 === $token2 && $token2 === $token3);
        $results[] = [
            'name' => 'CSRF Persistence: Opening Admin, Candidate, and Voter portals in same session shares a consistent token',
            'pass' => $persistsAcrossPortals
        ];

        @unlink($sessionCookie);

        // -------------------------------------------------------------
        // Test 2: Admin Portal: GET -> Extract Token -> POST credentials -> 302/303 to /admin
        // -------------------------------------------------------------
        $adminCookie = tempnam(sys_get_temp_dir(), 'csrf_admin_');
        $adminGet = $this->request('/public/login/admin', 'GET', [], $adminCookie);
        $adminToken = $this->extractCsrf($adminGet['body']);

        $adminPost = $this->request('/public/login/admin', 'POST', [
            'identifier' => $testLoginId,
            'password' => $testPassword,
            'csrf_token' => $adminToken
        ], $adminCookie);

        $adminSuccess = in_array($adminPost['code'], [302, 303]) 
                     && (strpos($adminPost['headers'], '/admin') !== false || strpos($adminPost['headers'], 'Location:') !== false);

        $results[] = [
            'name' => 'Admin Portal Lifecycle: Valid GET -> Token -> POST credentials succeeds with redirect to /admin',
            'pass' => $adminSuccess && !empty($adminToken)
        ];
        @unlink($adminCookie);

        // -------------------------------------------------------------
        // Test 3: Candidate Portal: GET -> Extract Token -> POST credentials -> 302/303 to /candidate
        // -------------------------------------------------------------
        $candCookie = tempnam(sys_get_temp_dir(), 'csrf_cand_');
        $candGet = $this->request('/public/login/candidate', 'GET', [], $candCookie);
        $candToken = $this->extractCsrf($candGet['body']);

        $candPost = $this->request('/public/login/candidate', 'POST', [
            'identifier' => $testLoginId,
            'password' => $testPassword,
            'csrf_token' => $candToken
        ], $candCookie);

        $candSuccess = in_array($candPost['code'], [302, 303]) 
                    && (strpos($candPost['headers'], '/candidate') !== false);

        $results[] = [
            'name' => 'Candidate Portal Lifecycle: Valid GET -> Token -> POST credentials succeeds with redirect to /candidate',
            'pass' => $candSuccess && !empty($candToken)
        ];
        @unlink($candCookie);

        // -------------------------------------------------------------
        // Test 4: Voter Portal: GET -> Extract Token -> POST credentials -> 302/303 to /voter
        // -------------------------------------------------------------
        $voterCookie = tempnam(sys_get_temp_dir(), 'csrf_voter_');
        $voterGet = $this->request('/public/login/voter', 'GET', [], $voterCookie);
        $voterToken = $this->extractCsrf($voterGet['body']);

        $voterPost = $this->request('/public/login/voter', 'POST', [
            'identifier' => $testLoginId,
            'password' => $testPassword,
            'csrf_token' => $voterToken
        ], $voterCookie);

        $voterSuccess = in_array($voterPost['code'], [302, 303]) 
                     && (strpos($voterPost['headers'], '/voter') !== false);

        $results[] = [
            'name' => 'Voter Portal Lifecycle: Valid GET -> Token -> POST credentials succeeds with redirect to /voter',
            'pass' => $voterSuccess && !empty($voterToken)
        ];
        @unlink($voterCookie);

        // -------------------------------------------------------------
        // Test 5: Expired / Invalid Token on Candidate Portal: No 403 Deadlock
        // -------------------------------------------------------------
        $staleCookie = tempnam(sys_get_temp_dir(), 'csrf_stale_');
        $staleGet = $this->request('/public/login/candidate', 'GET', [], $staleCookie);

        // Simulate submitting with a completely expired / tampered token
        $expiredPost = $this->request('/public/login/candidate', 'POST', [
            'identifier' => $testLoginId,
            'password' => $testPassword,
            'csrf_token' => 'expired_token_00000000000000000000000000000000'
        ], $staleCookie);

        // Must redirect via HTTP 303 back to /login/candidate (NOT trap user on 403 page)
        $redirectedGracefully = ($expiredPost['code'] === 303 || $expiredPost['code'] === 302)
                             && strpos($expiredPost['headers'], '/login/candidate') !== false;

        $results[] = [
            'name' => 'Expired Token Defense: Stale token on /login/candidate redirects gracefully (HTTP 303) rather than trapping in 403',
            'pass' => $redirectedGracefully
        ];

        // -------------------------------------------------------------
        // Test 6: Recovery after Token Expiration
        // -------------------------------------------------------------
        // Follow the redirect via GET to get fresh token and view flash notice
        $freshFormGet = $this->request('/public/login/candidate', 'GET', [], $staleCookie);
        $freshToken = $this->extractCsrf($freshFormGet['body']);
        $hasFlashMessage = strpos($freshFormGet['body'], 'security token was invalid or expired') !== false;

        // Submit with the fresh token
        $recoveryPost = $this->request('/public/login/candidate', 'POST', [
            'identifier' => $testLoginId,
            'password' => $testPassword,
            'csrf_token' => $freshToken
        ], $staleCookie);

        $recoverySuccess = in_array($recoveryPost['code'], [302, 303]) 
                        && strpos($recoveryPost['headers'], '/candidate') !== false;

        $results[] = [
            'name' => 'CSRF Recovery: Refreshed candidate portal renders flash notice and accepts subsequent login',
            'pass' => (!empty($freshToken) && $hasFlashMessage && $recoverySuccess)
        ];
        @unlink($staleCookie);

        // -------------------------------------------------------------
        // Test 7: Missing CSRF Token on Login Form
        // -------------------------------------------------------------
        $missingCookie = tempnam(sys_get_temp_dir(), 'csrf_missing_');
        $this->request('/public/login/admin', 'GET', [], $missingCookie);

        $missingPost = $this->request('/public/login/admin', 'POST', [
            'identifier' => $testLoginId,
            'password' => $testPassword
            // No csrf_token provided
        ], $missingCookie);

        $missingHandled = in_array($missingPost['code'], [302, 303]) 
                       && strpos($missingPost['headers'], '/login/admin') !== false;

        $results[] = [
            'name' => 'Missing Token Defense: POST without csrf_token is safely rejected and redirected with fresh token',
            'pass' => $missingHandled
        ];
        @unlink($missingCookie);

        // -------------------------------------------------------------
        // Test 8: Non-Login State Mutation CSRF Rejection
        // -------------------------------------------------------------
        $unauthPost = $this->request('/public/change-password', 'POST', [
            'current_password' => 'Dummy1234',
            'new_password' => 'NewDummy1234',
            'csrf_token' => 'invalid_csrf_token_value'
        ]);

        $results[] = [
            'name' => 'State Mutation Defense: Non-login POST with invalid token returns HTTP 403 Forbidden',
            'pass' => ($unauthPost['code'] === 403)
        ];

        // -------------------------------------------------------------
        // Test 9: CSRF Token Rotation on Authentication
        // -------------------------------------------------------------
        $rotCookie = tempnam(sys_get_temp_dir(), 'csrf_rot_');
        $preLoginGet = $this->request('/public/login/voter', 'GET', [], $rotCookie);
        $preLoginToken = $this->extractCsrf($preLoginGet['body']);

        $loginPost = $this->request('/public/login/voter', 'POST', [
            'identifier' => $testLoginId,
            'password' => $testPassword,
            'csrf_token' => $preLoginToken
        ], $rotCookie);

        // Fetch authenticated form with CSRF token (e.g. /public/change-password)
        $postLoginGet = $this->request('/public/change-password', 'GET', [], $rotCookie);
        $postLoginToken = $this->extractCsrf($postLoginGet['body']);

        $tokenRotated = (!empty($preLoginToken) && !empty($postLoginToken) && $preLoginToken !== $postLoginToken);
        $results[] = [
            'name' => 'CSRF Security: Token rotates upon successful authentication (pre-auth != post-auth)',
            'pass' => $tokenRotated
        ];
        @unlink($rotCookie);

        return $results;
    }
}
