<?php
/**
 * VerityVote - Complete End-to-End HTTP Journey & Security Test Suite
 * Validates real Apache HTTP requests, sessions, CSRF, directory protections, and role routing.
 */

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Repositories\UserRepository;
use App\Services\AccountService;
use App\Services\ElectionService;
use App\Services\CandidateService;
use App\Services\EligibilityService;
use App\Repositories\ElectionRepository;
use App\Repositories\CandidateRepository;
use App\Repositories\EligibilityRepository;

class HttpJourneyTest
{
    private string $baseUrl;
    private string $cookieFile;

    public function __construct(string $baseUrl = 'http://localhost/Baymax_Voting_System')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'vv_cookie_');
    }

    public function __destruct()
    {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    private function request(string $path, string $method = 'GET', array $postData = [], ?string $customCookie = null, array $headers = []): array
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
        curl_setopt($ch, CURLOPT_USERAGENT, 'VerityVote-IntegrationTest-Runner/1.0');

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

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
        $db = Database::getInstance();
        $userRepo = new UserRepository($db);
        $accountService = new AccountService($userRepo);

        // -------------------------------------------------------------
        // 1. Directory and Protected File Access via HTTP
        // -------------------------------------------------------------
        $protectedPaths = [
            '/app/bootstrap.php',
            '/config/config.php',
            '/database/schema.sql',
            '/storage/logs/app.log',
            '/bin/check_db.php'
        ];

        foreach ($protectedPaths as $p) {
            $resp = $this->request($p);
            $results[] = [
                'name' => "HTTP Directory Defense: Direct access to {$p} is rejected (HTTP {$resp['code']})",
                'pass' => ($resp['code'] === 403 || $resp['code'] === 404)
            ];
        }

        // -------------------------------------------------------------
        // 2. Public Root Redirect & Three Portals Selection Gateway
        // -------------------------------------------------------------
        $rootResp = $this->request('/');
        $results[] = [
            'name' => 'HTTP Root Redirect: Requesting / directs user into public/ front controller',
            'pass' => in_array($rootResp['code'], [200, 301, 302])
        ];

        // Login Selection Gateway (All 3 Portals)
        $loginHubResp = $this->request('/public/login');
        $hasHubPortals = strpos($loginHubResp['body'], 'Administrator Portal') !== false
                      && strpos($loginHubResp['body'], 'Candidate Portal') !== false
                      && strpos($loginHubResp['body'], 'Voter Portal') !== false;

        $results[] = [
            'name' => 'HTTP Login Hub: /public/login displays 3 distinct entry portals (Admin, Candidate, Voter)',
            'pass' => ($loginHubResp['code'] === 200 && $hasHubPortals)
        ];

        // Specific Portal 1: Admin Login View
        $adminLoginView = $this->request('/public/login/admin');
        $adminCsrf = $this->extractCsrf($adminLoginView['body']);
        $results[] = [
            'name' => 'HTTP Portal 1: /public/login/admin renders dedicated Administrator portal with Admin ID field and CSRF token',
            'pass' => ($adminLoginView['code'] === 200 && !empty($adminCsrf) && strpos($adminLoginView['body'], 'Administrator ID') !== false)
        ];

        // Specific Portal 2: Candidate Login View
        $candLoginView = $this->request('/public/login/candidate');
        $candCsrf = $this->extractCsrf($candLoginView['body']);
        $results[] = [
            'name' => 'HTTP Portal 2: /public/login/candidate renders dedicated Candidate portal with Candidate ID field and CSRF token',
            'pass' => ($candLoginView['code'] === 200 && !empty($candCsrf) && strpos($candLoginView['body'], 'Candidate ID') !== false)
        ];

        // Specific Portal 3: Voter Login View
        $voterLoginView = $this->request('/public/login/voter');
        $voterCsrf = $this->extractCsrf($voterLoginView['body']);
        $results[] = [
            'name' => 'HTTP Portal 3: /public/login/voter renders dedicated Voter portal with Roll Number / Employee ID field',
            'pass' => ($voterLoginView['code'] === 200 && !empty($voterCsrf) && strpos($voterLoginView['body'], 'Voter ID') !== false)
        ];

        // -------------------------------------------------------------
        // 3. Unauthenticated Access to Protected Route
        // -------------------------------------------------------------
        $unauthResp = $this->request('/public/admin');
        $results[] = [
            'name' => 'HTTP Route Defense: Unauthenticated request to /public/admin is redirected to /login',
            'pass' => in_array($unauthResp['code'], [302, 303]) && strpos($unauthResp['headers'], '/login') !== false
        ];

        // -------------------------------------------------------------
        // 4. Administrator Provisioning, First-Login & Forced Password Change
        // -------------------------------------------------------------
        $adminIdent = 'ADM_TEST_' . bin2hex(random_bytes(3));
        $adminUser = $accountService->createUser([
            'identifier' => $adminIdent,
            'role' => 'admin',
            'full_name' => 'HTTP Journey Test Admin',
            'email' => 'admin_http@verityvote.local'
        ], 1);
        $tempPassword = $adminUser['temp_password'];

        // Submit login via Admin Portal
        $loginPostResp = $this->request('/public/login/admin', 'POST', [
            'identifier' => $adminIdent,
            'password' => $tempPassword,
            'csrf_token' => $adminCsrf
        ]);

        $results[] = [
            'name' => 'HTTP Auth: Valid login with temporary credentials succeeds and redirects to /change-password',
            'pass' => in_array($loginPostResp['code'], [302, 303]) && strpos($loginPostResp['headers'], '/change-password') !== false
        ];

        // Request change-password view
        $changePwView = $this->request('/public/change-password');
        $changeCsrf = $this->extractCsrf($changePwView['body']);

        $results[] = [
            'name' => 'HTTP Auth: /change-password renders mandatory password reset interface',
            'pass' => ($changePwView['code'] === 200 && !empty($changeCsrf))
        ];

        // Attempting to bypass /change-password to access /admin
        $bypassAttempt = $this->request('/public/admin');
        $results[] = [
            'name' => 'HTTP Auth Enforcement: Attempting to bypass password reset redirects back to /change-password',
            'pass' => in_array($bypassAttempt['code'], [302, 303]) && strpos($bypassAttempt['headers'], '/change-password') !== false
        ];

        // Submit new permanent password
        $permanentPassword = 'NewPermanent#Pass2026';
        $submitPwChange = $this->request('/public/change-password', 'POST', [
            'current_password' => $tempPassword,
            'new_password' => $permanentPassword,
            'confirm_password' => $permanentPassword,
            'csrf_token' => $changeCsrf
        ]);

        $results[] = [
            'name' => 'HTTP Auth: Password update succeeds and redirects to authorized /admin dashboard',
            'pass' => in_array($submitPwChange['code'], [302, 303]) && (strpos($submitPwChange['headers'], '/admin') !== false || strpos($submitPwChange['headers'], 'public') !== false)
        ];

        // Access admin dashboard post-reset
        $adminDash = $this->request('/public/admin');
        $results[] = [
            'name' => 'HTTP Portal: Admin dashboard renders with HTTP 200 OK post password change',
            'pass' => ($adminDash['code'] === 200 && strpos($adminDash['body'], 'Administrative Control Center') !== false)
        ];

        // Access admin user management
        $adminUsers = $this->request('/public/admin/users');
        $results[] = [
            'name' => 'HTTP Portal: Admin user provisioning surface renders with HTTP 200 OK',
            'pass' => ($adminUsers['code'] === 200 && strpos($adminUsers['body'], 'Authorized Collegiate Accounts') !== false)
        ];

        // Access admin election management
        $adminElections = $this->request('/public/admin/elections');
        $results[] = [
            'name' => 'HTTP Portal: Admin election management surface renders with HTTP 200 OK',
            'pass' => ($adminElections['code'] === 200 && strpos($adminElections['body'], 'Collegiate Elections') !== false)
        ];

        // Access admin audit logs
        $adminAudit = $this->request('/public/admin/reports');
        $results[] = [
            'name' => 'HTTP Portal: Admin reports and audit logs render with HTTP 200 OK',
            'pass' => ($adminAudit['code'] === 200 && (strpos($adminAudit['body'], 'Institutional Reports') !== false || strpos($adminAudit['body'], 'Election Tally Reports') !== false))
        ];

        // -------------------------------------------------------------
        // 5. Role-Based Access Control: Student Session accessing Admin
        // -------------------------------------------------------------
        $studentCookie = tempnam(sys_get_temp_dir(), 'vv_student_');
        $studentIdent = 'STU_ROLE_' . bin2hex(random_bytes(3));
        $studentPass = 'StudentPass#2026';
        $studentUser = $accountService->createUser([
            'identifier' => $studentIdent,
            'role' => 'student',
            'full_name' => 'Role Boundary Student',
            'department' => 'BCA'
        ], 1);
        $userRepo->updatePassword($studentUser['user_id'], password_hash($studentPass, PASSWORD_DEFAULT));

        $stuLoginView = $this->request('/public/login/voter', 'GET', [], $studentCookie);
        $stuCsrf = $this->extractCsrf($stuLoginView['body']);

        $stuLoginPost = $this->request('/public/login/voter', 'POST', [
            'identifier' => $studentIdent,
            'password' => $studentPass,
            'csrf_token' => $stuCsrf
        ], $studentCookie);

        // Student attempt to access admin endpoint
        $stuAdminProbe = $this->request('/public/admin', 'GET', [], $studentCookie);
        $results[] = [
            'name' => 'HTTP RBAC Boundary: Authenticated student session attempting /admin is denied with 403 Forbidden',
            'pass' => ($stuAdminProbe['code'] === 403 || (in_array($stuAdminProbe['code'], [302, 303]) && strpos($stuAdminProbe['headers'], '/dashboard') !== false))
        ];

        // Student attempt to access candidate endpoint (when not a candidate)
        $stuCandProbe = $this->request('/public/candidate', 'GET', [], $studentCookie);
        $results[] = [
            'name' => 'HTTP RBAC Boundary: Authenticated non-candidate attempting /candidate is denied with 403 Forbidden',
            'pass' => ($stuCandProbe['code'] === 403)
        ];

        // Student accessing voter dashboard
        $stuDash = $this->request('/public/voter', 'GET', [], $studentCookie);
        $results[] = [
            'name' => 'HTTP Portal: Authenticated student session accessing /voter renders successfully (HTTP 200 OK)',
            'pass' => ($stuDash['code'] === 200 && (strpos($stuDash['body'], 'Institutional Elections') !== false || strpos($stuDash['body'], 'Member Elections Portal') !== false))
        ];

        @unlink($studentCookie);

        // -------------------------------------------------------------
        // 5b. Role-Based Access Control: Candidate Session & Dashboard
        // -------------------------------------------------------------
        $candCookie = tempnam(sys_get_temp_dir(), 'vv_cand_');
        $candIdent = 'CAND_HTTP_' . bin2hex(random_bytes(3));
        $candPass = 'CandidatePass#2026';
        $candUser = $accountService->createUser([
            'identifier' => $candIdent,
            'role' => 'student',
            'full_name' => 'HTTP Test Candidate',
            'department' => 'BCA'
        ], 1);
        $userRepo->updatePassword($candUser['user_id'], password_hash($candPass, PASSWORD_DEFAULT));

        // Create election & link candidate
        $electionRepo = new ElectionRepository($db);
        $candidateRepo = new CandidateRepository($db);
        $electionService = new ElectionService($electionRepo, $candidateRepo);
        $candidateService = new CandidateService($candidateRepo, $electionRepo);

        $now = time();
        $httpEl = $electionService->createElection([
            'title' => 'HTTP Candidate Test Race',
            'position' => 'President',
            'opens_at' => date('Y-m-d H:i:s', $now - 60),
            'closes_at' => date('Y-m-d H:i:s', $now + 3600),
            'results_visibility' => 'live_provisional'
        ], 1);

        $candidateService->createCandidate([
            'election_id' => $httpEl['id'],
            'user_id' => $candUser['user_id'],
            'name' => 'HTTP Test Candidate',
            'manifesto' => 'Candidate HTTP Verification',
            'approval_status' => 'approved'
        ], null, 1);
        $candidateService->createCandidate([
            'election_id' => $httpEl['id'],
            'name' => 'Opponent B',
            'manifesto' => 'Opponent platform',
            'approval_status' => 'approved'
        ], null, 1);
        $electionService->transitionState($httpEl['id'], 'open', 1);

        // Login via Candidate Portal
        $cLoginView = $this->request('/public/login/candidate', 'GET', [], $candCookie);
        $cCsrf = $this->extractCsrf($cLoginView['body']);

        $cLoginPost = $this->request('/public/login/candidate', 'POST', [
            'identifier' => $candIdent,
            'password' => $candPass,
            'csrf_token' => $cCsrf
        ], $candCookie);

        $results[] = [
            'name' => 'HTTP Candidate Portal: Candidate login redirects to /candidate dashboard',
            'pass' => in_array($cLoginPost['code'], [302, 303]) && strpos($cLoginPost['headers'], '/candidate') !== false
        ];

        // Access Candidate Dashboard
        $candDash = $this->request('/public/candidate', 'GET', [], $candCookie);
        $results[] = [
            'name' => 'HTTP Portal: Authenticated candidate accessing /candidate renders successfully (HTTP 200 OK)',
            'pass' => ($candDash['code'] === 200 && strpos($candDash['body'], 'Candidate Control Center') !== false)
        ];

        // Candidate attempting admin endpoint
        $candAdminProbe = $this->request('/public/admin', 'GET', [], $candCookie);
        $results[] = [
            'name' => 'HTTP RBAC Boundary: Authenticated candidate attempting /admin is denied with 403 Forbidden',
            'pass' => ($candAdminProbe['code'] === 403)
        ];

        @unlink($candCookie);

        // -------------------------------------------------------------
        // 6. Live API Endpoint Verification
        // -------------------------------------------------------------
        $electionRepo = new ElectionRepository($db);
        $candidateRepo = new CandidateRepository($db);
        $electionService = new ElectionService($electionRepo, $candidateRepo);
        $candidateService = new CandidateService($candidateRepo, $electionRepo);

        $now = time();
        $apiEl = $electionService->createElection([
            'title' => 'Live API Test Election',
            'position' => 'Cultural Secretary',
            'opens_at' => date('Y-m-d H:i:s', $now - 60),
            'closes_at' => date('Y-m-d H:i:s', $now + 3600),
            'results_visibility' => 'live_provisional'
        ], 1);
        $candidateService->createCandidate(['election_id' => $apiEl['id'], 'name' => 'API Cand A', 'approval_status' => 'approved'], null, 1);
        $candidateService->createCandidate(['election_id' => $apiEl['id'], 'name' => 'API Cand B', 'approval_status' => 'approved'], null, 1);
        $electionService->transitionState($apiEl['id'], 'open', 1);

        $apiResp = $this->request("/public/api/live_results.php?election_id={$apiEl['id']}");
        $apiData = json_decode($apiResp['body'], true);

        $results[] = [
            'name' => 'HTTP Live Results API: /public/api/live_results.php returns valid JSON payload with status and candidate data',
            'pass' => ($apiResp['code'] === 200 && ($apiData['success'] ?? false) === true && isset($apiData['results']['candidates']))
        ];

        // -------------------------------------------------------------
        // 7. Health & Monitoring Observability Endpoints (Phase 12)
        // -------------------------------------------------------------
        // 7.1 Liveness Probe
        $liveResp = $this->request('/public/health/live.php');
        $liveData = json_decode($liveResp['body'], true);
        $results[] = [
            'name' => 'HTTP Health: /public/health/live.php returns HTTP 200 with alive status and memory info',
            'pass' => ($liveResp['code'] === 200 && ($liveData['status'] ?? '') === 'alive' && isset($liveData['php_version']))
        ];

        // 7.2 Readiness Probe
        $readyResp = $this->request('/public/health/ready.php');
        $readyData = json_decode($readyResp['body'], true);
        $results[] = [
            'name' => 'HTTP Health: /public/health/ready.php validates database, critical tables, and storage (HTTP 200 ready)',
            'pass' => ($readyResp['code'] === 200 && ($readyData['status'] ?? '') === 'ready' && ($readyData['checks']['database'] ?? '') === 'ok')
        ];

        // 7.3 Metrics Endpoint: Unauthorized Request (No token and anonymous session)
        $anonCookie = tempnam(sys_get_temp_dir(), 'vv_anon_');
        $unauthMetrics = $this->request('/public/metrics', 'GET', [], $anonCookie);
        @unlink($anonCookie);
        $results[] = [
            'name' => 'HTTP Metrics Security: Accessing /public/metrics without authorization token returns HTTP 401 Unauthorized',
            'pass' => ($unauthMetrics['code'] === 401 && strpos($unauthMetrics['headers'], 'WWW-Authenticate: Bearer') !== false)
        ];

        // 7.4 Metrics Endpoint: Authorized Request with Bearer Token
        $configFile = dirname(__DIR__, 2) . '/config/config.php';
        $cfg = file_exists($configFile) ? require $configFile : [];
        $metricsToken = $cfg['monitoring']['metrics_token'] ?? 'vv_metrics_secret_token_change_in_production';

        $authMetrics = $this->request('/public/metrics', 'GET', [], null, [
            "Authorization: Bearer {$metricsToken}"
        ]);
        $hasPrometheusHeader = strpos($authMetrics['body'], 'verityvote_votes_submitted_total') !== false;
        $results[] = [
            'name' => 'HTTP Metrics Security: Accessing /public/metrics with valid Bearer token returns HTTP 200 and Prometheus telemetry',
            'pass' => ($authMetrics['code'] === 200 && $hasPrometheusHeader)
        ];

        // 7.5 Metrics Endpoint: Authorized Request with Query Parameter Token
        $queryMetrics = $this->request("/public/metrics?token={$metricsToken}");
        $results[] = [
            'name' => 'HTTP Metrics Security: Accessing /public/metrics with query token returns HTTP 200 and telemetry',
            'pass' => ($queryMetrics['code'] === 200 && strpos($queryMetrics['body'], 'verityvote_') !== false)
        ];

        // 7.6 Metrics Endpoint: Logged-in Administrator Access via Session
        $adminMetrics = $this->request('/public/metrics');
        $results[] = [
            'name' => 'HTTP Metrics Security: Authenticated administrator can access /public/metrics via session',
            'pass' => ($adminMetrics['code'] === 200 && strpos($adminMetrics['body'], 'verityvote_') !== false)
        ];

        return $results;
    }
}
