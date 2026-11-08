<?php
namespace App\Services;

use App\Core\Logger;
use App\Helpers\Sanitizer;
use App\Helpers\Validator;
use App\Repositories\CandidateRepository;
use App\Repositories\ElectionRepository;

/**
 * Candidate Management and Safe Media Asset Service
 */
class CandidateService
{
    private CandidateRepository $candidateRepo;
    private ElectionRepository $electionRepo;
    private CacheService $cacheService;
    private array $config;

    public function __construct(
        ?CandidateRepository $candidateRepo = null,
        ?ElectionRepository $electionRepo = null,
        ?array $config = null,
        ?CacheService $cacheService = null
    ) {
        $this->candidateRepo = $candidateRepo ?? new CandidateRepository();
        $this->electionRepo = $electionRepo ?? new ElectionRepository();
        $this->cacheService = $cacheService ?? new CacheService();

        if ($config === null) {
            $configFile = dirname(__DIR__, 2) . '/config/config.php';
            if (!file_exists($configFile)) {
                $configFile = dirname(__DIR__, 2) . '/config/config.example.php';
            }
            $appConfig = require $configFile;
            $this->config = $appConfig['security'] ?? [];
        } else {
            $this->config = $config;
        }
    }

    public function createCandidate(array $data, ?array $file, int $userId): array
    {
        $electionId = (int)($data['election_id'] ?? 0);
        $election = $this->electionRepo->findById($electionId);

        if (!$election) {
            return ['success' => false, 'error' => 'Election not found.'];
        }

        if (in_array($election['status'], ['open', 'closed', 'results_published'], true)) {
            return [
                'success' => false,
                'error' => "Cannot add candidates: Ballot is locked while election is {$election['status']}."
            ];
        }

        $name = Sanitizer::cleanText($data['name'] ?? '');
        $userIdAccount = !empty($data['user_id']) ? (int)$data['user_id'] : null;
        if ($userIdAccount !== null) {
            $userRepo = new \App\Repositories\UserRepository();
            $targetUser = $userRepo->findById($userIdAccount);
            if (!$targetUser) {
                return ['success' => false, 'error' => 'Linked user account does not exist.'];
            }
            if ($targetUser['role'] === 'admin') {
                return ['success' => false, 'error' => 'Candidate profiles cannot be linked to administrator accounts.'];
            }
        }
        $manifesto = Sanitizer::cleanText($data['manifesto'] ?? '');
        $approvalStatus = in_array($data['approval_status'] ?? '', ['pending', 'approved', 'rejected'], true)
            ? $data['approval_status']
            : 'approved';
        $displayOrder = (int)($data['display_order'] ?? 0);

        if (empty($name)) {
            return ['success' => false, 'error' => 'Candidate name is required.'];
        }

        $photoPath = null;
        if ($file && !empty($file['tmp_name'])) {
            $uploadRes = $this->processPhotoUpload($file);
            if (!$uploadRes['success']) {
                return $uploadRes;
            }
            $photoPath = $uploadRes['path'];
        }

        $candidateId = $this->candidateRepo->create([
            'election_id' => $electionId,
            'user_id' => $userIdAccount,
            'name' => $name,
            'manifesto' => $manifesto,
            'photo_path' => $photoPath,
            'approval_status' => $approvalStatus,
            'display_order' => $displayOrder
        ]);

        Logger::audit('CANDIDATE_CREATED', 'candidate', (string)$candidateId, [
            'election_id' => $electionId,
            'user_id' => $userIdAccount,
            'name' => $name,
            'approval_status' => $approvalStatus
        ], $userId);

        $this->cacheService->invalidateCandidates($electionId);

        return ['success' => true, 'id' => $candidateId];
    }

    public function updateCandidate(int $id, array $data, ?array $file, int $userId): array
    {
        $candidate = $this->candidateRepo->findById($id);
        if (!$candidate) {
            return ['success' => false, 'error' => 'Candidate not found.'];
        }

        $election = $this->electionRepo->findById((int)$candidate['election_id']);
        if (in_array($election['status'], ['open', 'closed', 'results_published'], true)) {
            return [
                'success' => false,
                'error' => "Cannot modify candidate: Ballot is locked while election is {$election['status']}."
            ];
        }

        $name = Sanitizer::cleanText($data['name'] ?? $candidate['name']);
        $manifesto = Sanitizer::cleanText($data['manifesto'] ?? $candidate['manifesto']);
        $approvalStatus = in_array($data['approval_status'] ?? '', ['pending', 'approved', 'rejected'], true)
            ? $data['approval_status']
            : $candidate['approval_status'];
        $displayOrder = isset($data['display_order']) ? (int)$data['display_order'] : (int)$candidate['display_order'];

        $photoPath = null;
        if ($file && !empty($file['tmp_name'])) {
            $uploadRes = $this->processPhotoUpload($file);
            if (!$uploadRes['success']) {
                return $uploadRes;
            }
            $photoPath = $uploadRes['path'];
        }

        $this->candidateRepo->update($id, [
            'name' => $name,
            'manifesto' => $manifesto,
            'photo_path' => $photoPath,
            'approval_status' => $approvalStatus,
            'display_order' => $displayOrder
        ]);

        Logger::audit('CANDIDATE_UPDATED', 'candidate', (string)$id, [
            'name' => $name,
            'approval_status' => $approvalStatus
        ], $userId);

        $this->cacheService->invalidateCandidates((int)$candidate['election_id']);

        return ['success' => true];
    }

    public function updateApprovalStatus(int $candidateId, string $status, int $userId): array
    {
        $status = strtolower(trim($status));
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            return ['success' => false, 'error' => 'Invalid approval status. Must be pending, approved, or rejected.'];
        }

        $candidate = $this->candidateRepo->findById($candidateId);
        if (!$candidate) {
            return ['success' => false, 'error' => 'Candidate not found.'];
        }

        $election = $this->electionRepo->findById((int)$candidate['election_id']);
        if (!$election) {
            return ['success' => false, 'error' => 'Associated election not found.'];
        }

        if (in_array($election['status'], ['open', 'closed', 'results_published'], true)) {
            return [
                'success' => false,
                'error' => "Cannot change candidate approval: Ballot configuration is locked while election is {$election['status']}."
            ];
        }

        $this->candidateRepo->updateStatus($candidateId, $status);

        Logger::audit('CANDIDATE_STATUS_CHANGED', 'candidate', (string)$candidateId, [
            'election_id' => $candidate['election_id'],
            'candidate_name' => $candidate['name'],
            'previous_status' => $candidate['approval_status'],
            'new_status' => $status
        ], $userId);

        $this->cacheService->invalidateCandidates((int)$candidate['election_id']);

        return ['success' => true, 'new_status' => $status];
    }

    public function selfUpdateProfile(int $candidateId, int $userId, ?string $manifesto, ?array $photoFile): array
    {
        $candidate = $this->candidateRepo->findById($candidateId);
        if (!$candidate) {
            return ['success' => false, 'error' => 'Candidate profile not found.'];
        }

        if ((int)($candidate['user_id'] ?? 0) !== $userId) {
            return ['success' => false, 'error' => 'Unauthorized: You can only edit your own candidacy profile.'];
        }

        $election = $this->electionRepo->findById((int)$candidate['election_id']);
        if (!$election || in_array($election['status'], ['closed', 'results_published', 'cancelled'], true)) {
            return ['success' => false, 'error' => 'Cannot edit profile: This election has closed or completed.'];
        }

        $cleanManifesto = $manifesto !== null ? Sanitizer::cleanText($manifesto) : $candidate['manifesto'];

        $photoPath = $candidate['photo_path'];
        if ($photoFile && !empty($photoFile['tmp_name'])) {
            $uploadRes = $this->processPhotoUpload($photoFile);
            if (!$uploadRes['success']) {
                return $uploadRes;
            }
            $photoPath = $uploadRes['path'];
        }

        $this->candidateRepo->update($candidateId, [
            'name' => $candidate['name'],
            'manifesto' => $cleanManifesto,
            'photo_path' => $photoPath,
            'approval_status' => $candidate['approval_status'],
            'display_order' => (int)$candidate['display_order']
        ]);

        Logger::audit('CANDIDATE_SELF_PROFILE_UPDATED', 'candidate', (string)$candidateId, [
            'election_id' => $candidate['election_id'],
            'manifesto_updated' => ($manifesto !== null),
            'photo_updated' => ($photoFile && !empty($photoFile['tmp_name']))
        ], $userId);

        $this->cacheService->invalidateCandidates((int)$candidate['election_id']);

        return ['success' => true];
    }

    public function deleteCandidate(int $id, int $userId): array
    {
        $candidate = $this->candidateRepo->findById($id);
        if (!$candidate) {
            return ['success' => false, 'error' => 'Candidate not found.'];
        }

        $election = $this->electionRepo->findById((int)$candidate['election_id']);
        if (in_array($election['status'], ['open', 'closed', 'results_published'], true)) {
            return [
                'success' => false,
                'error' => "Cannot remove candidate: Election is currently {$election['status']}."
            ];
        }

        $this->candidateRepo->delete($id);

        Logger::audit('CANDIDATE_DELETED', 'candidate', (string)$id, [
            'election_id' => $candidate['election_id'],
            'name' => $candidate['name']
        ], $userId);

        $this->cacheService->invalidateCandidates((int)$candidate['election_id']);

        return ['success' => true];
    }

    /**
     * Compile complete dashboard view payload for an authenticated candidate
     */
    public function getCandidateDashboardData(int $userId): array
    {
        $profiles = $this->candidateRepo->findByUserId($userId);

        $detailedProfiles = [];
        $votingRepo = new \App\Repositories\VotingRepository();
        $eligibilityRepo = new \App\Repositories\EligibilityRepository();

        foreach ($profiles as $profile) {
            $electionId = (int)$profile['election_id'];
            $opponents = $this->candidateRepo->getByElectionId($electionId, true);

            // Filter out self from opponents list
            $filteredOpponents = array_filter($opponents, fn($o) => (int)$o['id'] !== (int)$profile['id']);

            // Get live or final results if permitted
            $tallyData = null;
            $resultsService = new \App\Services\ResultsService();
            $resultsCheck = $resultsService->getResults($electionId, ['role' => 'candidate', 'id' => $userId]);

            $detailedProfiles[] = [
                'candidacy' => $profile,
                'opponents' => array_values($filteredOpponents),
                'results' => $resultsCheck['visible'] ? ($resultsCheck['results'] ?? null) : null,
                'results_label' => $resultsCheck['label'] ?? null,
                'results_visible' => (bool)($resultsCheck['visible'] ?? false)
            ];
        }

        // Check if this candidate is ALSO an eligible voter in any elections
        $eligibleElections = $this->electionRepo->getEligibleForUser($userId);

        return [
            'profiles' => $detailedProfiles,
            'is_eligible_voter' => !empty($eligibleElections),
            'eligible_elections_count' => count($eligibleElections)
        ];
    }

    private function processPhotoUpload(array $file): array
    {
        $allowedMimes = $this->config['allowed_image_mime_types'] ?? ['image/jpeg', 'image/png', 'image/webp'];
        $allowedExts = $this->config['allowed_image_extensions'] ?? ['jpg', 'jpeg', 'png', 'webp'];
        $maxBytes = (int)($this->config['max_upload_bytes'] ?? 2097152);

        $uploadError = null;
        if (!Validator::validateUploadedImage($file, $allowedMimes, $allowedExts, $maxBytes, $uploadError)) {
            return ['success' => false, 'error' => $uploadError];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $safeFilename = 'cand_' . bin2hex(random_bytes(12)) . '.' . $ext;

        $targetDir = dirname(__DIR__, 2) . '/storage/uploads/candidates';
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $destination = $targetDir . '/' . $safeFilename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return ['success' => false, 'error' => 'Failed to save uploaded image.'];
        }

        return ['success' => true, 'path' => 'uploads/candidates/' . $safeFilename];
    }
}
