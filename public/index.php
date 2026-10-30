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

// Enforce CSRF token verification on state mutations
CsrfMiddleware::handle();

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

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// =====================================================================
// ROUTING DEFINITIONS
// =====================================================================

switch ($path) {
    // -----------------------------------------------------------------
    // Home Dispatcher
    // -----------------------------------------------------------------
    case '/':
        if (!Auth::isLoggedIn()) {
            header('Location: ' . View::url('/login'));
            exit;
        }
        if (Auth::isAdmin()) {
            header('Location: ' . View::url('/admin'));
            exit;
        }
        if (Auth::isOfficer()) {
            header('Location: ' . View::url('/officer'));
            exit;
        }
        if (Auth::portal() === 'candidate' || (Auth::isCandidate() && !Auth::isVoter())) {
            header('Location: ' . View::url('/candidate'));
            exit;
        }
        header('Location: ' . View::url('/voter'));
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
        $electionId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['election_id'] ?? 0);

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
            $votingService = new VotingService();
            $res = $votingService->castVote($electionId, $candidateId, Auth::id());

            if ($res['success']) {
                header('Location: ' . View::url('/confirmation?id=' . $electionId . '&receipt=' . urlencode($res['confirmation_receipt'])));
                exit;
            }

            Session::setFlash('error', $res['error']);
        }

        View::render('student/ballot', [
            'election' => $election,
            'candidates' => $candidates
        ]);
        exit;

    case '/confirmation':
        AuthMiddleware::handle();
        $electionId = (int)($_GET['id'] ?? 0);
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
        $electionId = (int)($_GET['id'] ?? 0);

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

        $importResult = null;
        if ($method === 'POST') {
            if (empty($_FILES['csv_file']['tmp_name'])) {
                Session::setFlash('error', 'Please choose a valid CSV file.');
            } else {
                $accountService = new AccountService();
                $importResult = $accountService->importFromCsv($_FILES['csv_file']['tmp_name'], Auth::id());

                if ($importResult['success']) {
                    Session::setFlash('success', "Import processed: {$importResult['imported']} provisioned, {$importResult['skipped']} skipped.");
                } else {
                    Session::setFlash('error', $importResult['error']);
                }
            }
        }

        View::render('admin/user_import', [
            'importResult' => $importResult
        ]);
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

        $auditRepo = new AuditRepository();
        $logs = $auditRepo->getRecent(100);

        View::render('admin/audit_logs', [
            'logs' => $logs
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
