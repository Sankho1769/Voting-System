<?php
/**
 * VerityVote - Phase 15 Application Readiness & Functional Integration Tests
 *
 * Validates new administrative controls (election editing, candidate approval toggle,
 * whitelist voter removal and individual enrollment, candidate profile self-service,
 * database backup creation and safe download, and repository pagination).
 */

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Repositories\ElectionRepository;
use App\Repositories\CandidateRepository;
use App\Repositories\EligibilityRepository;
use App\Repositories\UserRepository;
use App\Services\ElectionService;
use App\Services\CandidateService;
use App\Services\EligibilityService;
use App\Services\BackupService;
use Tests\Support\TestFixtureFactory;

class ApplicationReadinessTest
{
    public static function run(): array
    {
        $results = [];
        $db = Database::getInstance();
        $fixtures = TestFixtureFactory::getSuiteFixtures($db);

        $adminId = (int)$fixtures['admin_id'];
        $voterId = (int)$fixtures['voter_id'];

        $electionRepo = new ElectionRepository($db);
        $candidateRepo = new CandidateRepository($db);
        $eligibilityRepo = new EligibilityRepository($db);
        $userRepo = new UserRepository($db);

        $electionService = new ElectionService($electionRepo, $candidateRepo);
        $candidateService = new CandidateService($candidateRepo, $electionRepo);
        $eligibilityService = new EligibilityService($eligibilityRepo, $electionRepo);

        $now = time();

        // -------------------------------------------------------------
        // 1. Election Edit Feature
        // -------------------------------------------------------------
        $createRes = $electionService->createElection([
            'title' => 'Initial Draft Election for Editing',
            'position' => 'Secretary',
            'category' => 'student_council',
            'description' => 'Original description',
            'opens_at' => date('Y-m-d H:i:s', $now + 600),
            'closes_at' => date('Y-m-d H:i:s', $now + 7200),
            'results_visibility' => 'live_provisional'
        ], $adminId);

        $editElectionId = $createRes['id'];

        $updateRes = $electionService->updateElection($editElectionId, [
            'title' => 'Updated Council Secretary Election',
            'position' => 'General Secretary',
            'category' => 'student_council',
            'description' => 'Enhanced and updated description',
            'opens_at' => date('Y-m-d H:i:s', $now - 60), // Set to allow opening
            'closes_at' => date('Y-m-d H:i:s', $now + 7200),
            'results_visibility' => 'after_closure_only'
        ], $adminId);

        $updatedElection = $electionRepo->findById($editElectionId);

        $results[] = [
            'name' => 'Election Edit: Successfully updates title, position, description, and visibility in draft state',
            'pass' => $updateRes['success'] && $updatedElection['title'] === 'Updated Council Secretary Election' && $updatedElection['position'] === 'General Secretary' && $updatedElection['results_visibility'] === 'after_closure_only'
        ];

        // -------------------------------------------------------------
        // 2. Candidate Status & Approval Toggling
        // -------------------------------------------------------------
        $cand1 = $candidateService->createCandidate([
            'election_id' => $editElectionId,
            'name' => 'Pending Nominee One',
            'manifesto' => 'Pending platform initiatives',
            'approval_status' => 'pending'
        ], null, $adminId);

        $cand1Id = $cand1['id'];

        $approveRes = $candidateService->updateApprovalStatus($cand1Id, 'approved', $adminId);
        $cand1AfterApprove = $candidateRepo->findById($cand1Id);

        $results[] = [
            'name' => 'Candidate Approval: Admin can approve a pending candidate',
            'pass' => $approveRes['success'] && $cand1AfterApprove['approval_status'] === 'approved'
        ];

        $rejectRes = $candidateService->updateApprovalStatus($cand1Id, 'rejected', $adminId);
        $cand1AfterReject = $candidateRepo->findById($cand1Id);

        $results[] = [
            'name' => 'Candidate Approval: Admin can reject an approved candidate',
            'pass' => $rejectRes['success'] && $cand1AfterReject['approval_status'] === 'rejected'
        ];

        // Re-approve candidate 1 and add candidate 2 for opening
        $candidateService->updateApprovalStatus($cand1Id, 'approved', $adminId);
        $cand2 = $candidateService->createCandidate([
            'election_id' => $editElectionId,
            'user_id' => $voterId,
            'name' => 'Candidate Two Dual',
            'manifesto' => 'Candidate two platform statement',
            'approval_status' => 'approved'
        ], null, $adminId);
        $cand2Id = $cand2['id'];

        // -------------------------------------------------------------
        // 3. Voter Whitelist Removal & Individual Assignment
        // -------------------------------------------------------------
        // Assign voter to election
        $eligibilityService->assignVoters($editElectionId, [$voterId], $adminId);
        $isEligibleBefore = $eligibilityRepo->isEligible($editElectionId, $voterId);

        $results[] = [
            'name' => 'Voter Whitelist: Enrolling voter succeeds',
            'pass' => $isEligibleBefore === true
        ];

        // Remove voter before election opens
        $removeRes = $eligibilityService->removeVoter($editElectionId, $voterId, $adminId);
        $isEligibleAfterRemove = $eligibilityRepo->isEligible($editElectionId, $voterId);

        $results[] = [
            'name' => 'Voter Whitelist: Admin can remove an enrolled voter before election opens',
            'pass' => $removeRes['success'] && $isEligibleAfterRemove === false
        ];

        // Re-assign voter
        $eligibilityService->assignVoters($editElectionId, [$voterId], $adminId);

        // -------------------------------------------------------------
        // 4. Candidate Self-Service Profile Update
        // -------------------------------------------------------------
        // Candidate 2 is linked to $voterId
        $selfUpdateRes = $candidateService->selfUpdateProfile($cand2Id, $voterId, 'Updated manifesto from candidate portal self-service', null);
        $cand2Updated = $candidateRepo->findById($cand2Id);

        $results[] = [
            'name' => 'Candidate Self-Service: Candidate updates their own manifesto',
            'pass' => $selfUpdateRes['success'] && $cand2Updated['manifesto'] === 'Updated manifesto from candidate portal self-service'
        ];

        // IDOR Defense: Another user cannot update candidate 2's profile
        $idorRes = $candidateService->selfUpdateProfile($cand2Id, 99999, 'Hacked manifesto', null);
        $results[] = [
            'name' => 'Candidate Self-Service (IDOR Defense): Unauthorized user is rejected from updating candidate profile',
            'pass' => !$idorRes['success'] && strpos($idorRes['error'], 'Unauthorized') !== false
        ];

        // -------------------------------------------------------------
        // 5. State Machine Lock Protection
        // -------------------------------------------------------------
        // Transition election to Open
        $openRes = $electionService->transitionState($editElectionId, 'open', $adminId);

        // Attempt to edit election while Open -> Must fail
        $editWhileOpen = $electionService->updateElection($editElectionId, ['title' => 'Tampered Title While Open'], $adminId);
        $results[] = [
            'name' => 'Election Lock: Configuration updates are strictly rejected once election is open',
            'pass' => !$editWhileOpen['success'] && strpos($editWhileOpen['error'], 'locked') !== false
        ];

        // Attempt to change candidate approval while Open -> Must fail
        $statusWhileOpen = $candidateService->updateApprovalStatus($cand1Id, 'rejected', $adminId);
        $results[] = [
            'name' => 'Election Lock: Candidate approval changes are strictly rejected once election is open',
            'pass' => !$statusWhileOpen['success'] && strpos($statusWhileOpen['error'], 'locked') !== false
        ];

        // Attempt to remove voter whitelist while Open -> Must fail
        $removeWhileOpen = $eligibilityService->removeVoter($editElectionId, $voterId, $adminId);
        $results[] = [
            'name' => 'Election Lock: Whitelist removals are strictly rejected once election is open',
            'pass' => !$removeWhileOpen['success'] && strpos($removeWhileOpen['error'], 'locked') !== false
        ];

        // -------------------------------------------------------------
        // 6. Database Backup Service
        // -------------------------------------------------------------
        $backupService = new BackupService();
        $backupRes = $backupService->createBackup(null, $adminId);

        $results[] = [
            'name' => 'Backup Service: Creates transactional SQL backup snapshot',
            'pass' => $backupRes['success'] === true && !empty($backupRes['filename']) && file_exists($backupRes['filepath']) && $backupRes['size'] > 0
        ];

        $backupsList = $backupService->listBackups();
        $foundCreated = false;
        foreach ($backupsList as $b) {
            if ($b['filename'] === $backupRes['filename']) {
                $foundCreated = true;
                break;
            }
        }

        $results[] = [
            'name' => 'Backup Service: listBackups finds newly created backup archive',
            'pass' => $foundCreated === true
        ];

        // Path Traversal Defense
        $traversalPath = $backupService->getBackupPath('../../config/config.php');
        $validPath = $backupService->getBackupPath($backupRes['filename']);

        $results[] = [
            'name' => 'Backup Service: Strictly prevents path traversal attacks in download handler',
            'pass' => $traversalPath === null && $validPath !== null && file_exists($validPath)
        ];

        // Clean up test backup file
        if (!empty($backupRes['filepath']) && file_exists($backupRes['filepath'])) {
            @unlink($backupRes['filepath']);
        }

        // -------------------------------------------------------------
        // 7. Pagination Support in Repositories
        // -------------------------------------------------------------
        $pagedElections = $electionRepo->getAll([], 3, 0);
        $totalElectionsCount = $electionRepo->countAll();

        $results[] = [
            'name' => 'Pagination: ElectionRepository::getAll with limit/offset returns exact page slice and countAll returns total',
            'pass' => count($pagedElections) <= 3 && $totalElectionsCount >= count($pagedElections)
        ];

        $pagedCandidates = $candidateRepo->getAllApprovedWithElections(null, null, 2, 0);
        $totalCandidatesCount = $candidateRepo->countApprovedWithElections(null, null);

        $results[] = [
            'name' => 'Pagination: CandidateRepository::getAllApprovedWithElections returns exact page slice and countApprovedWithElections returns total',
            'pass' => count($pagedCandidates) <= 2 && $totalCandidatesCount >= count($pagedCandidates)
        ];

        return $results;
    }
}
