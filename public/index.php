<?php
/**
 * VerityVote - Front Controller & Routing Gateway
 */

declare(strict_types=1);

// Bootstrap Application Autoloader and Environment
require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Helpers\CSVExporter;
use App\Helpers\Sanitizer;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Middleware\CandidateMiddleware;
use App\Middleware\VoterMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Repositories\UserRepository;
use App\Repositories\ElectionRepository;
use App\Repositories\CandidateRepository;
use App\Repositories\EligibilityRepository;
use App\Repositories\VotingRepository;
use App\Repositories\AuditRepository;
use App\Services\AuthService;
use App\Services\AccountService;
use App\Services\ElectionService;
use App\Services\CandidateService;
use App\Services\EligibilityService;
use App\Services\VotingService;
use App\Services\ResultsService;

// Initialize Session
Session::start();

// Resolve Route
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
if ($scriptDir !== '/' && $scriptDir !== '.' && strpos($path, $scriptDir) === 0) {
    $path = substr($path, strlen($scriptDir));
}
$path = '/' . trim($path, '/');

// Query-param fallback for environments with mod_rewrite disabled
if (isset($_GET['route'])) {
    $path = '/' . trim($_GET['route'], '/');
}

// Enforce CSRF token verification on state mutations with route awareness
CsrfMiddleware::handle($path);

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// =====================================================================
// ROUTING DEFINITIONS
// =====================================================================

switch ($path) {
    // -----------------------------------------------------------------
    // Home Dispatcher
    // -----------------------------------------------------------------
    // -----------------------------------------------------------------
    // Home Editorial Landing Page (Reference 1 Visuals + Ref 2 Hub)
    // -----------------------------------------------------------------
    case '/':
        $electionRepo = new ElectionRepository();
        $featuredElections = $electionRepo->getAll();
        $stats = $electionRepo->getStats();

        View::render('public/home', [
            'pageTitle' => 'College Voting Platform',
            'activeNav' => 'home',
            'featuredElections' => array_slice($featuredElections, 0, 6),
            'stats' => $stats
        ]);
        exit;

    // -----------------------------------------------------------------
    // Multi-Election Discovery Hub (CricHeroes Architecture)
    // -----------------------------------------------------------------
    case '/explore':
    case '/elections':
        $electionRepo = new ElectionRepository();
        $filters = [];
        if (!empty($_GET['category'])) {
            $filters['category'] = (string)$_GET['category'];
        }
        if (!empty($_GET['department'])) {
            $filters['department'] = (string)$_GET['department'];
        }
        if (!empty($_GET['scope'])) {
            $filters['scope'] = (string)$_GET['scope'];
        }
        if (!empty($_GET['q'])) {
            $filters['search'] = (string)$_GET['q'];
        }
        if (Auth::isLoggedIn()) {
            $filters['user_id'] = Auth::id();
        }

        $elections = $electionRepo->getAll($filters);
        $categories = ElectionService::getCategories();
        $departments = $electionRepo->getDepartments();

        View::render('public/explore', [
            'pageTitle' => 'Campus Elections',
            'activeNav' => 'explore',
            'elections' => $elections,
            'categories' => $categories,
            'departments' => $departments,
            'activeTab' => $_GET['scope'] ?? 'all'
        ]);
        exit;

    // -----------------------------------------------------------------
    // Dedicated Election Details & Match Center
    // -----------------------------------------------------------------
    case '/election':
    case '/elections/detail':
        $electionId = (int)($_GET['id'] ?? ($_GET['election_id'] ?? 0));
        $electionRepo = new ElectionRepository();
        $election = $electionRepo->findById($electionId);

        if (!$election) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        $candidateRepo = new CandidateRepository();
        $candidates = $candidateRepo->getByElectionId($electionId, true);
        
        $isEligible = false;
        $hasParticipated = false;
        if (Auth::isLoggedIn()) {
            $eligibilityRepo = new EligibilityRepository();
            $votingRepo = new VotingRepository();
            $isEligible = $eligibilityRepo->isEligible($electionId, Auth::id());
            $hasParticipated = $votingRepo->hasParticipated($electionId, Auth::id());
        }

        View::render('public/election_detail', [
            'pageTitle' => $election['title'],
            'activeNav' => 'explore',
            'election' => $election,
            'candidates' => $candidates,
            'isEligible' => $isEligible,
            'hasParticipated' => $hasParticipated
        ]);
        exit;

    // -----------------------------------------------------------------
    // Public Candidates Directory
    // -----------------------------------------------------------------
    case '/candidates':
        $candidateRepo = new CandidateRepository();
        $candidates = $candidateRepo->getAllApprovedWithElections($_GET['q'] ?? null, $_GET['category'] ?? null);

        View::render('public/candidates', [
            'pageTitle' => 'Campus Contenders & Candidates',
            'activeNav' => 'candidates',
            'candidates' => $candidates
        ]);
        exit;

    // -----------------------------------------------------------------
    // Information & Support Pages
    // -----------------------------------------------------------------
    case '/how-it-works':
        View::render('public/how_it_works', [
            'pageTitle' => 'How It Works',
            'activeNav' => 'how-it-works'
        ]);
        exit;

    case '/help':
        View::render('public/help', [
            'pageTitle' => 'Help & FAQs',
            'activeNav' => 'help'
        ]);
        exit;

    // -----------------------------------------------------------------
    // Media Gateway - Safe Photo Streaming (ASVS V12)
    // -----------------------------------------------------------------
    case '/media/candidate-photo':
    case '/uploads/candidates':
        $file = basename($_GET['file'] ?? '');
        if (empty($file) || !preg_match('/^cand_[a-f0-9]{24}\.(jpg|jpeg|png|webp)$/i', $file)) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        $baseCandidateDir = realpath(dirname(__DIR__) . '/storage/uploads/candidates');
        $filePath = realpath(dirname(__DIR__) . '/storage/uploads/candidates/' . $file);

        if (!$filePath || !$baseCandidateDir || strpos($filePath, $baseCandidateDir) !== 0 || !file_exists($filePath)) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $contentTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp'
        ];
        $contentType = $contentTypes[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . (string)filesize($filePath));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=86400');
        readfile($filePath);
        exit;

    // -----------------------------------------------------------------
    // Three Distinct Login Portals & Selection Gateway
    // -----------------------------------------------------------------
    case '/login':
        if (Auth::isLoggedIn()) {
            header('Location: ' . View::url('/'));
            exit;
        }

        if ($method === 'POST') {
            $identifier = $_POST['identifier'] ?? '';
            $password = $_POST['password'] ?? '';
            $portal = $_POST['portal'] ?? 'default';

            $authService = new AuthService();
            $result = $authService->login($identifier, $password, $portal);

            if ($result['success']) {
                if ($result['must_change_password']) {
                    Session::setFlash('warning', 'You must set a personal password before accessing the system.');
                    header('Location: ' . View::url('/change-password'));
                    exit;
                }
                Session::setFlash('success', 'Authenticated successfully.');
                header('Location: ' . View::url('/'));
                exit;
            }

            Session::setFlash('error', $result['error']);
            View::render('auth/login_hub', ['identifier' => $identifier]);
            exit;
        }

        View::render('auth/login_hub');
        exit;

    case '/login/admin':
        if (Auth::isLoggedIn()) {
            header('Location: ' . View::url('/'));
            exit;
        }

        if ($method === 'POST') {
            $identifier = $_POST['identifier'] ?? '';
            $password = $_POST['password'] ?? '';

            $authService = new AuthService();
            $result = $authService->login($identifier, $password, 'admin');

            if ($result['success']) {
                if ($result['must_change_password']) {
                    Session::setFlash('warning', 'You must set a personal password before accessing the administrator console.');
                    header('Location: ' . View::url('/change-password'));
                    exit;
                }
                Session::setFlash('success', 'Authenticated into Administrator Console.');
                header('Location: ' . View::url('/admin'));
                exit;
            }

            Session::setFlash('error', $result['error']);
            View::render('auth/login_portal', [
                'portal' => 'admin',
                'identifier' => $identifier
            ]);
            exit;
        }

        View::render('auth/login_portal', ['portal' => 'admin']);
        exit;

    case '/login/candidate':
        if (Auth::isLoggedIn()) {
            header('Location: ' . View::url('/'));
            exit;
        }

        if ($method === 'POST') {
            $identifier = $_POST['identifier'] ?? '';
            $password = $_POST['password'] ?? '';

            $authService = new AuthService();
            $result = $authService->login($identifier, $password, 'candidate');

            if ($result['success']) {
                if ($result['must_change_password']) {
                    Session::setFlash('warning', 'You must set a personal password before accessing your candidate dashboard.');
                    header('Location: ' . View::url('/change-password'));
                    exit;
                }
                Session::setFlash('success', 'Authenticated into Candidate Portal.');
                header('Location: ' . View::url('/candidate'));
                exit;
            }

            Session::setFlash('error', $result['error']);
            View::render('auth/login_portal', [
                'portal' => 'candidate',
                'identifier' => $identifier
            ]);
            exit;
        }

        View::render('auth/login_portal', ['portal' => 'candidate']);
        exit;

    case '/login/voter':
        if (Auth::isLoggedIn()) {
            header('Location: ' . View::url('/'));
            exit;
        }

        if ($method === 'POST') {
            $identifier = $_POST['identifier'] ?? '';
            $password = $_POST['password'] ?? '';

            $authService = new AuthService();
            $result = $authService->login($identifier, $password, 'voter');

            if ($result['success']) {
                if ($result['must_change_password']) {
                    Session::setFlash('warning', 'You must set a personal password before casting your ballot.');
                    header('Location: ' . View::url('/change-password'));
                    exit;
                }
                Session::setFlash('success', 'Authenticated into Collegiate Voter Portal.');
                header('Location: ' . View::url('/voter'));
                exit;
            }

            Session::setFlash('error', $result['error']);
            View::render('auth/login_portal', [
                'portal' => 'voter',
                'identifier' => $identifier
            ]);
            exit;
        }

        View::render('auth/login_portal', ['portal' => 'voter']);
        exit;

    case '/logout':
        Auth::logout();
        Session::setFlash('success', 'You have been safely signed out.');
        CSRF::regenerate();
        header('Location: ' . View::url('/login'));
        exit;

    case '/change-password':
        AuthMiddleware::handle();

        if ($method === 'POST') {
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            $authService = new AuthService();
            $result = $authService->changePassword(Auth::id(), $current, $new, $confirm);

            if ($result['success']) {
                Session::setFlash('success', 'Your password was updated successfully.');
                header('Location: ' . View::url('/'));
                exit;
            }

            Session::setFlash('error', $result['error']);
        }

        View::render('auth/change_password');
        exit;

    // -----------------------------------------------------------------
    // Candidate Portal Routes
    // -----------------------------------------------------------------
    case '/candidate':
        CandidateMiddleware::handle();

        $candidateService = new CandidateService();
        $candidateData = $candidateService->getCandidateDashboardData(Auth::id());

        View::render('candidate/dashboard', $candidateData);
        exit;

    // -----------------------------------------------------------------
    // Collegiate Voter Portal Routes
    // -----------------------------------------------------------------
    case '/voter':
    case '/dashboard':
        VoterMiddleware::handle();

        $electionRepo = new ElectionRepository();
        $elections = $electionRepo->getEligibleForUser(Auth::id());

        View::render('student/dashboard', [
            'elections' => $elections
        ]);
        exit;

    case '/ballot':
        VoterMiddleware::handle();
        $electionId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_GET['election_id'] ?? ($_POST['election_id'] ?? 0));

        $electionRepo = new ElectionRepository();
        $election = $electionRepo->findById($electionId);

        if (!$election) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        $candidateRepo = new CandidateRepository();
        $candidates = $candidateRepo->getByElectionId($electionId, true);
        $eligibilityRepo = new EligibilityRepository();
        $votingRepo = new VotingRepository();

        // Check eligibility
        if (!$eligibilityRepo->isEligible($electionId, Auth::id())) {
            Session::setFlash('error', 'You are not on the authorized voter whitelist for this election.');
            header('Location: ' . View::url('/dashboard'));
            exit;
        }

        // Check already voted
        if ($votingRepo->hasParticipated($electionId, Auth::id())) {
            Session::setFlash('info', 'You have already submitted your ballot for this election.');
            header('Location: ' . View::url('/results?id=' . $electionId));
            exit;
        }

        if ($method === 'POST') {
            $candidateId = (int)($_POST['candidate_id'] ?? 0);

            // Replay and rapid double-click defense via server-side ballot token
            $submittedBallotToken = $_POST['ballot_token'] ?? '';
            $expectedBallotToken = Session::get('_ballot_token_' . $electionId);

            if (!empty($expectedBallotToken) && !hash_equals($expectedBallotToken, $submittedBallotToken)) {
                Logger::audit('BALLOT_REPLAY_ATTEMPT', 'election', (string)$electionId, [
                    'reason' => 'Invalid or replayed ballot request token'
                ], Auth::id());
                Session::setFlash('error', 'Duplicate ballot submission detected. Each ballot may be submitted only once.');
                header('Location: ' . View::url('/results?id=' . $electionId));
                exit;
            }

            // Immediately invalidate single-use token before entering transaction
            Session::remove('_ballot_token_' . $electionId);

            $votingService = new VotingService();
            $res = $votingService->castVote($electionId, $candidateId, Auth::id());

            if ($res['success']) {
                header('Location: ' . View::url('/confirmation?id=' . $electionId . '&receipt=' . urlencode($res['confirmation_receipt'])));
                exit;
            }

            Session::setFlash('error', $res['error']);
        }

        // Generate fresh cryptographic ballot token for this session & election
        $ballotToken = bin2hex(random_bytes(16));
        Session::set('_ballot_token_' . $electionId, $ballotToken);

        View::render('student/ballot', [
            'election' => $election,
            'candidates' => $candidates,
            'ballotToken' => $ballotToken
        ]);
        exit;

    case '/confirmation':
        AuthMiddleware::handle();
        $electionId = (int)($_GET['id'] ?? ($_GET['election_id'] ?? 0));
        $receipt = $_GET['receipt'] ?? '';

        $electionRepo = new ElectionRepository();
        $election = $electionRepo->findById($electionId);
        $votingRepo = new VotingRepository();
        $participation = $votingRepo->getParticipation($electionId, Auth::id());

        if (!$election || !$participation) {
            header('Location: ' . View::url('/dashboard'));
            exit;
        }

        View::render('student/confirmation', [
            'election' => $election,
            'receipt' => $receipt ?: $participation['confirmation_hash'],
            'votedAt' => $participation['voted_at']
        ]);
        exit;

    case '/results':
        AuthMiddleware::handle();
        $electionId = (int)($_GET['id'] ?? ($_GET['election_id'] ?? 0));

        $resultsService = new ResultsService();
        $data = $resultsService->getResults($electionId, Auth::user());

        if (!$data['success']) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        View::render('student/results', $data);
        exit;

    // -----------------------------------------------------------------
    // Election Officer Routes
    // -----------------------------------------------------------------
    case '/officer':
    case '/officer/elections':
        RoleMiddleware::handle('election_officer', 'admin');

        $electionRepo = new ElectionRepository();
        $elections = $electionRepo->getAll();

        View::render('officer/dashboard', [
            'elections' => $elections
        ]);
        exit;

    // -----------------------------------------------------------------
    // System Administrator Routes
    // -----------------------------------------------------------------
    case '/admin':
        RoleMiddleware::handle('admin');

        $userRepo = new UserRepository();
        $electionRepo = new ElectionRepository();
        $votingRepo = new VotingRepository();
        $auditRepo = new AuditRepository();

        $stats = [
            'total_users' => $userRepo->countAll(),
            'student_count' => $userRepo->countAll(['role' => 'student']),
            'staff_count' => $userRepo->countAll(['role' => 'staff']),
            'total_elections' => count($electionRepo->getAll()),
            'open_elections' => count($electionRepo->getAll(['status' => 'open'])),
            'total_votes_cast' => (int)Database::getInstance()->fetchValue("SELECT COUNT(*) FROM votes"),
            'audit_events_count' => $auditRepo->countAll()
        ];

        $recentElections = array_slice($electionRepo->getAll(), 0, 5);

        View::render('admin/dashboard', [
            'stats' => $stats,
            'recentElections' => $recentElections
        ]);
        exit;

    case '/admin/users':
        RoleMiddleware::handle('admin');

        $userRepo = new UserRepository();
        $filters = [
            'role' => $_GET['role'] ?? '',
            'status' => $_GET['status'] ?? '',
            'search' => $_GET['search'] ?? ''
        ];

        $users = $userRepo->getAll($filters);

        View::render('admin/users', [
            'users' => $users,
            'filters' => $filters
        ]);
        exit;

    case '/admin/users/create':
        RoleMiddleware::handle('admin');

        if ($method === 'POST') {
            $accountService = new AccountService();
            $res = $accountService->createUser($_POST, Auth::id());

            if ($res['success']) {
                Session::setFlash('success', "Account for {$res['identifier']} provisioned! Temporary password: {$res['temp_password']}");
                header('Location: ' . View::url('/admin/users'));
                exit;
            }

            Session::setFlash('error', $res['error']);
        }

        View::render('admin/user_create');
        exit;

    case '/admin/users/import':
        RoleMiddleware::handle('admin');

        $preview = null;
        $importResult = null;
        $accountService = new AccountService();

        if ($method === 'POST') {
            $action = $_POST['action'] ?? 'preview';

            if ($action === 'commit') {
                $importToken = $_POST['import_token'] ?? '';
                $importResult = $accountService->commitCsvImport($importToken, Auth::id());

                if ($importResult['success']) {
                    Session::setFlash('success', "Import committed successfully: {$importResult['imported']} provisioned, {$importResult['skipped']} skipped.");
                } else {
                    Session::setFlash('error', $importResult['error']);
                }
            } else {
                // Initial upload & preview analysis
                if (empty($_FILES['csv_file']['tmp_name'])) {
                    Session::setFlash('error', 'Please choose a valid CSV file to upload.');
                } else {
                    $previewRes = $accountService->previewCsv($_FILES['csv_file']['tmp_name']);
                    if ($previewRes['success']) {
                        $preview = $previewRes['data'];
                        if ($preview['accepted_count'] === 0) {
                            Session::setFlash('warning', "Preview completed: 0 new accounts ready to provision. {$preview['duplicate_db_count']} already exist in database, {$preview['duplicate_file_count']} duplicate within file, {$preview['invalid_count']} invalid.");
                        } else {
                            Session::setFlash('info', "Preview ready: {$preview['accepted_count']} accounts valid and ready to provision. Please review the summary and confirm below.");
                        }
                    } else {
                        Session::setFlash('error', $previewRes['error']);
                    }
                }
            }
        }

        View::render('admin/user_import', [
            'preview' => $preview,
            'importResult' => $importResult
        ]);
        exit;

    case '/admin/users/import-errors':
        RoleMiddleware::handle('admin');
        $token = $_GET['token'] ?? '';
        $sessionKey = '_csv_preview_' . $token;
        $previewData = Session::get($sessionKey);

        if (!$previewData || empty($previewData['issues'])) {
            Session::setFlash('error', 'No error report available for this import token.');
            header('Location: ' . View::url('/admin/users/import'));
            exit;
        }

        $headers = ['Row', 'Institutional ID', 'Full Name', 'Category', 'Details'];
        $rows = [];
        foreach ($previewData['issues'] as $issue) {
            $rows[] = [
                $issue['row'],
                $issue['identifier'],
                $issue['full_name'],
                $issue['category'],
                $issue['details']
            ];
        }

        CSVExporter::download('verityvote_import_errors_' . date('Ymd_His') . '.csv', $headers, $rows);
        exit;

    case '/admin/users/template':
        RoleMiddleware::handle('admin');
        CSVExporter::download(
            'verityvote_users_template.csv',
            ['identifier', 'role', 'full_name', 'email', 'department', 'academic_year'],
            [
                ['BCA2024-001', 'student', 'Aditya Sen', 'aditya@college.edu', 'Computer Science', '2024-2027'],
                ['EMP-104', 'staff', 'Dr. Ramesh Sharma', 'sharma@college.edu', 'Physics', '']
            ]
        );
        exit;

    case '/admin/users/reset-password':
        RoleMiddleware::handle('admin');

        if ($method === 'POST') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $accountService = new AccountService();
            $res = $accountService->resetTemporaryPassword($userId, Auth::id());

            if ($res['success']) {
                Session::setFlash('success', "Temporary password generated for {$res['identifier']}: {$res['temp_password']}");
            } else {
                Session::setFlash('error', $res['error']);
            }
        }

        header('Location: ' . View::url('/admin/users'));
        exit;

    case '/admin/users/status':
        RoleMiddleware::handle('admin');

        if ($method === 'POST') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $newStatus = $_POST['status'] ?? '';
            $accountService = new AccountService();
            $res = $accountService->updateStatus($userId, $newStatus, Auth::id());

            if ($res['success']) {
                Session::setFlash('success', "Account status updated to {$newStatus}.");
            } else {
                Session::setFlash('error', $res['error']);
            }
        }

        header('Location: ' . View::url('/admin/users'));
        exit;

    case '/admin/elections':
        RoleMiddleware::handle('admin', 'election_officer');

        $electionRepo = new ElectionRepository();
        $elections = $electionRepo->getAll();

        View::render('admin/elections', [
            'elections' => $elections
        ]);
        exit;

    case '/admin/elections/create':
        RoleMiddleware::handle('admin', 'election_officer');

        if ($method === 'POST') {
            $electionService = new ElectionService();
            $res = $electionService->createElection($_POST, Auth::id());

            if ($res['success']) {
                Session::setFlash('success', 'Election initialized in Draft state. Please add candidates and configure eligibility.');
                header('Location: ' . View::url('/admin/elections/detail?id=' . $res['id']));
                exit;
            }

            Session::setFlash('error', $res['error']);
        }

        View::render('admin/election_create');
        exit;

    case '/admin/elections/detail':
        RoleMiddleware::handle('admin', 'election_officer');
        $id = (int)($_GET['id'] ?? 0);

        $electionRepo = new ElectionRepository();
        $election = $electionRepo->findById($id);

        if (!$election) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        $candidateRepo = new CandidateRepository();
        $candidates = $candidateRepo->getByElectionId($id, false);

        $eligibilityRepo = new EligibilityRepository();
        $eligibleVoters = $eligibilityRepo->getVotersForElection($id);

        $userRepo = new UserRepository();
        $departments = $userRepo->getDepartments();
        $availableUsers = $userRepo->getAll(['status' => 'active'], 200);

        View::render('admin/election_detail', [
            'election' => $election,
            'candidates' => $candidates,
            'eligibleVoters' => $eligibleVoters,
            'departments' => $departments,
            'availableUsers' => $availableUsers
        ]);
        exit;

    case '/admin/elections/status':
        RoleMiddleware::handle('admin', 'election_officer');

        if ($method === 'POST') {
            $id = (int)($_POST['election_id'] ?? 0);
            $status = $_POST['status'] ?? '';

            $electionService = new ElectionService();
            $res = $electionService->transitionState($id, $status, Auth::id());

            if ($res['success']) {
                Session::setFlash('success', "Election status updated to {$res['new_status']}.");
            } else {
                Session::setFlash('error', $res['error']);
            }

            header('Location: ' . View::url('/admin/elections/detail?id=' . $id));
            exit;
        }
        break;

    case '/admin/candidates/create':
        RoleMiddleware::handle('admin', 'election_officer');

        if ($method === 'POST') {
            $electionId = (int)($_POST['election_id'] ?? 0);
            $candidateService = new CandidateService();
            $photo = !empty($_FILES['photo']['name']) ? $_FILES['photo'] : null;

            $res = $candidateService->createCandidate($_POST, $photo, Auth::id());

            if ($res['success']) {
                Session::setFlash('success', 'Candidate added successfully.');
            } else {
                Session::setFlash('error', $res['error']);
            }

            header('Location: ' . View::url('/admin/elections/detail?id=' . $electionId));
            exit;
        }
        break;

    case '/admin/candidates/delete':
        RoleMiddleware::handle('admin', 'election_officer');

        if ($method === 'POST') {
            $candidateId = (int)($_POST['candidate_id'] ?? 0);
            $electionId = (int)($_POST['election_id'] ?? 0);

            $candidateService = new CandidateService();
            $res = $candidateService->deleteCandidate($candidateId, Auth::id());

            if ($res['success']) {
                Session::setFlash('success', 'Candidate removed.');
            } else {
                Session::setFlash('error', $res['error']);
            }

            header('Location: ' . View::url('/admin/elections/detail?id=' . $electionId));
            exit;
        }
        break;

    case '/admin/eligibility/assign-criteria':
        RoleMiddleware::handle('admin', 'election_officer');

        if ($method === 'POST') {
            $electionId = (int)($_POST['election_id'] ?? 0);
            $role = !empty($_POST['role']) ? $_POST['role'] : null;
            $dept = !empty($_POST['department']) ? $_POST['department'] : null;

            $eligibilityService = new EligibilityService();
            $res = $eligibilityService->assignByCriteria($electionId, $role, $dept, null, Auth::id());

            if ($res['success']) {
                Session::setFlash('success', "{$res['assigned_count']} voters authorized on the eligibility whitelist.");
            } else {
                Session::setFlash('error', $res['error']);
            }

            header('Location: ' . View::url('/admin/elections/detail?id=' . $electionId));
            exit;
        }
        break;

    case '/admin/audit':
        RoleMiddleware::handle('admin');

        $actionFilter = !empty($_GET['action']) ? trim($_GET['action']) : null;
        $searchFilter = !empty($_GET['search']) ? trim($_GET['search']) : null;

        $auditRepo = new AuditRepository();
        $logs = $auditRepo->getRecent(100, 0, $actionFilter, $searchFilter);
        $distinctActions = $auditRepo->getDistinctActions();

        View::render('admin/audit_logs', [
            'logs' => $logs,
            'distinctActions' => $distinctActions,
            'selectedAction' => $actionFilter,
            'search' => $searchFilter
        ]);
        exit;

    case '/admin/reports':
        RoleMiddleware::handle('admin', 'election_officer');

        $electionRepo = new ElectionRepository();
        $elections = $electionRepo->getAll();

        View::render('admin/reports', [
            'elections' => $elections
        ]);
        exit;

    case '/admin/reports/csv':
        RoleMiddleware::handle('admin', 'election_officer');
        $electionId = (int)($_GET['id'] ?? 0);

        $resultsService = new ResultsService();
        $csvData = $resultsService->generateResultsCsv($electionId);

        CSVExporter::download($csvData['filename'], $csvData['headers'], $csvData['rows']);
        exit;

    default:
        http_response_code(404);
        View::render('errors/404');
        exit;
}
