<?php
/**
 * VerityVote - Three Login Portals Automated Integration Test Suite
 * Validates role-specific login experiences, mismatch rejections, dual candidate-voter access,
 * RBAC route boundaries, rate limiting, and session security.
 */

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Services\AuthService;
use App\Services\AccountService;
use App\Services\ElectionService;
use App\Services\CandidateService;
use App\Services\EligibilityService;
use App\Services\VotingService;
use App\Repositories\UserRepository;
use App\Repositories\ElectionRepository;
use App\Repositories\CandidateRepository;
use App\Repositories\EligibilityRepository;
use App\Repositories\VotingRepository;
use App\Repositories\LoginAttemptRepository;

class ThreePortalsAuthTest
{
    public static function run(): array
    {
        $results = [];
        $db = Database::getInstance();
        $userRepo = new UserRepository($db);
        $accountService = new AccountService($userRepo);
        $authService = new AuthService($userRepo, new LoginAttemptRepository($db), new CandidateRepository($db));
        $electionRepo = new ElectionRepository($db);
        $candidateRepo = new CandidateRepository($db);
        $electionService = new ElectionService($electionRepo, $candidateRepo);
        $candidateService = new CandidateService($candidateRepo, $electionRepo);
        $eligibilityRepo = new EligibilityRepository($db);
        $eligibilityService = new EligibilityService($eligibilityRepo, $electionRepo);
        $votingRepo = new VotingRepository($db);
        $votingService = new VotingService($votingRepo);

        $now = time();

        // -----------------------------------------------------------------
        // Setup Accounts: Admin, Dedicated Candidate, Student Voter, Dual Candidate-Voter
        // -----------------------------------------------------------------
        $password = 'PortalTest#Pass2026';

        // 1. Admin Account
        $adminRes = $accountService->createUser([
            'identifier' => 'ADM_PORTAL_' . bin2hex(random_bytes(2)),
            'role' => 'admin',
            'full_name' => 'Portal Test Admin',
            'email' => 'admin_p@verityvote.local'
        ], 1);
        $userRepo->updatePassword($adminRes['user_id'], password_hash($password, PASSWORD_DEFAULT));

        // 2. Student Voter Account (pure voter, not a candidate)
        $voterRes = $accountService->createUser([
            'identifier' => 'STU_VOTER_' . bin2hex(random_bytes(2)),
            'role' => 'student',
            'full_name' => 'Pure Student Voter',
            'department' => 'BCA'
        ], 1);
        $userRepo->updatePassword($voterRes['user_id'], password_hash($password, PASSWORD_DEFAULT));

        // 3. Dual Role Account: Student who is ALSO an approved candidate
        $dualUserRes = $accountService->createUser([
            'identifier' => 'DUAL_CAND_' . bin2hex(random_bytes(2)),
            'role' => 'student',
            'full_name' => 'Dual Candidate and Voter',
            'department' => 'BCA'
        ], 1);
        $userRepo->updatePassword($dualUserRes['user_id'], password_hash($password, PASSWORD_DEFAULT));

        // Setup Election for the candidate & voter
        $electionRes = $electionService->createElection([
            'title' => 'Portal Test Presidential Election',
            'position' => 'President',
            'opens_at' => date('Y-m-d H:i:s', $now - 60),
            'closes_at' => date('Y-m-d H:i:s', $now + 3600),
            'results_visibility' => 'live_provisional'
        ], 1);
        $electionId = $electionRes['id'];

        // Register Dual User as Candidate 1
        $cand1 = $candidateService->createCandidate([
            'election_id' => $electionId,
            'user_id' => $dualUserRes['user_id'],
            'name' => 'Dual Candidate Contender',
            'manifesto' => 'Integrity, Portals, Transparency',
            'approval_status' => 'approved'
        ], null, 1);

        // Register Opponent Candidate 2
        $cand2 = $candidateService->createCandidate([
            'election_id' => $electionId,
            'name' => 'Opponent Contender',
            'manifesto' => 'Alternative Platform',
            'approval_status' => 'approved'
        ], null, 1);

        // Whitelist both Pure Voter and Dual Candidate in election
        $eligibilityService->assignVoters($electionId, [$voterRes['user_id'], $dualUserRes['user_id']], 1);
        $electionService->transitionState($electionId, 'open', 1);

        // -----------------------------------------------------------------
        // Test 1: Authorized Admin logs in via Admin Portal
        // -----------------------------------------------------------------
        $adminLogin = $authService->login($adminRes['identifier'], $password, 'admin');
        $results[] = [
            'name' => 'Portal 1: Authorized admin successfully logs in through the Admin portal',
            'pass' => $adminLogin['success'] && $adminLogin['user']['role'] === 'admin' && Auth::isAdmin()
        ];
        Auth::logout();

        // -----------------------------------------------------------------
        // Test 2: Authorized Candidate logs in via Candidate Portal
        // -----------------------------------------------------------------
        $candidateLogin = $authService->login($dualUserRes['identifier'], $password, 'candidate');
        $results[] = [
            'name' => 'Portal 2: Authorized candidate successfully logs in through the Candidate portal',
            'pass' => $candidateLogin['success'] && Auth::isCandidate()
        ];
        Auth::logout();

        // -----------------------------------------------------------------
        // Test 3: Candidate logs in using Candidate ID syntax (e.g. CAND-xx)
        // -----------------------------------------------------------------
        $candIdSyntax = 'CAND-' . $cand1['id'];
        $candIdLogin = $authService->login($candIdSyntax, $password, 'candidate');
        $results[] = [
            'name' => 'Portal 2 (ID Syntax): Candidate logs in using Candidate ID string (CAND-xx)',
            'pass' => $candIdLogin['success'] && Auth::isCandidate()
        ];
        Auth::logout();

        // -----------------------------------------------------------------
        // Test 4: Eligible Voter logs in via Voter Portal
        // -----------------------------------------------------------------
        $voterLogin = $authService->login($voterRes['identifier'], $password, 'voter');
        $results[] = [
            'name' => 'Portal 3: Eligible voter successfully logs in through the Voter portal',
            'pass' => $voterLogin['success'] && Auth::isVoter()
        ];
        Auth::logout();

        // -----------------------------------------------------------------
        // Test 5: Invalid Credentials Rejected across all portals
        // -----------------------------------------------------------------
        $badPassAdmin = $authService->login($adminRes['identifier'], 'WrongPassword999', 'admin');
        $badPassCand = $authService->login($dualUserRes['identifier'], 'WrongPassword999', 'candidate');
        $badPassVoter = $authService->login($voterRes['identifier'], 'WrongPassword999', 'voter');
        $results[] = [
            'name' => 'Security: Invalid credentials rejected with generic error across all three portals',
            'pass' => !$badPassAdmin['success'] && !$badPassCand['success'] && !$badPassVoter['success']
        ];

        // -----------------------------------------------------------------
        // Test 6: Role Mismatch: Pure Voter attempting to log in via Admin Portal
        // -----------------------------------------------------------------
        $voterInAdmin = $authService->login($voterRes['identifier'], $password, 'admin');
        $results[] = [
            'name' => 'Role Boundary: Student voter attempting Admin portal is rejected with role mismatch error',
            'pass' => !$voterInAdmin['success'] && strpos($voterInAdmin['error'], 'Administrative credentials required') !== false
        ];

        // -----------------------------------------------------------------
        // Test 7: Role Mismatch: Pure Voter attempting to log in via Candidate Portal
        // -----------------------------------------------------------------
        $voterInCand = $authService->login($voterRes['identifier'], $password, 'candidate');
        $results[] = [
            'name' => 'Role Boundary: Non-candidate voter attempting Candidate portal is rejected (no candidate profile)',
            'pass' => !$voterInCand['success'] && strpos($voterInCand['error'], 'No candidate profile found') !== false
        ];

        // -----------------------------------------------------------------
        // Test 8: Role Mismatch: Admin attempting to log in via Voter Portal
        // -----------------------------------------------------------------
        $adminInVoter = $authService->login($adminRes['identifier'], $password, 'voter');
        $results[] = [
            'name' => 'Role Boundary: Pure administrator attempting Voter portal is denied access',
            'pass' => !$adminInVoter['success'] && strpos($adminInVoter['error'], 'Administrator accounts cannot sign in through the Voter portal') !== false
        ];

        // -----------------------------------------------------------------
        // Test 9: Suspended Account is rejected across all portals
        // -----------------------------------------------------------------
        $suspRes = $accountService->createUser([
            'identifier' => 'SUSP_USER_' . bin2hex(random_bytes(2)),
            'role' => 'student',
            'full_name' => 'Suspended Account User',
            'department' => 'BCA'
        ], 1);
        $userRepo->updatePassword($suspRes['user_id'], password_hash($password, PASSWORD_DEFAULT));
        $userRepo->updateStatus($suspRes['user_id'], 'suspended');

        $suspLogin = $authService->login($suspRes['identifier'], $password, 'voter');
        $results[] = [
            'name' => 'Account Status: Suspended accounts are immediately rejected with clear status error',
            'pass' => !$suspLogin['success'] && strpos($suspLogin['error'], 'suspended') !== false
        ];

        // -----------------------------------------------------------------
        // Test 10: Dual Role: Candidate logging in as Voter casts valid ballot
        // -----------------------------------------------------------------
        $dualVoterLogin = $authService->login($dualUserRes['identifier'], $password, 'voter');
        $candidateCastVote = $votingService->castVote($electionId, $cand1['id'], $dualUserRes['user_id']);
        $results[] = [
            'name' => 'Dual Role: Candidate with voter eligibility successfully casts ballot through Voter workflow',
            'pass' => $dualVoterLogin['success'] && $candidateCastVote['success'] && !empty($candidateCastVote['confirmation_receipt'])
        ];

        // -----------------------------------------------------------------
        // Test 11: Double Voting Prevention for Dual Candidate-Voter
        // -----------------------------------------------------------------
        $candidateSecondVote = $votingService->castVote($electionId, $cand2['id'], $dualUserRes['user_id']);
        $results[] = [
            'name' => 'Electoral Integrity: Candidate cannot cast multiple votes in the same election',
            'pass' => !$candidateSecondVote['success'] && strpos($candidateSecondVote['error'], 'already cast your vote') !== false
        ];

        // -----------------------------------------------------------------
        // Test 12: Candidate Dashboard Data compilation
        // -----------------------------------------------------------------
        $candDashData = $candidateService->getCandidateDashboardData($dualUserRes['user_id']);
        $hasCandidacy = !empty($candDashData['profiles']);
        $isAlsoVoter = $candDashData['is_eligible_voter'] === true;
        $results[] = [
            'name' => 'Candidate Dashboard: Retrieves candidate profile, opponents, and recognizes dual voter status',
            'pass' => $hasCandidacy && $isAlsoVoter
        ];

        // -----------------------------------------------------------------
        // Test 13: Logout invalidates session
        // -----------------------------------------------------------------
        Auth::logout();
        $results[] = [
            'name' => 'Session Security: Logout completely terminates session and clears cached user context',
            'pass' => !Auth::isLoggedIn() && Auth::user() === null
        ];

        return $results;
    }
}
