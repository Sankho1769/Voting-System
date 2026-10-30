<?php
namespace App\Services;

use App\Core\Logger;
use App\Repositories\EligibilityRepository;
use App\Repositories\ElectionRepository;

/**
 * Voter Eligibility Provisioning Service
 */
class EligibilityService
{
    private EligibilityRepository $eligibilityRepo;
    private ElectionRepository $electionRepo;

    public function __construct(
        ?EligibilityRepository $eligibilityRepo = null,
        ?ElectionRepository $electionRepo = null
    ) {
        $this->eligibilityRepo = $eligibilityRepo ?? new EligibilityRepository();
        $this->electionRepo = $electionRepo ?? new ElectionRepository();
    }

    public function assignVoters(int $electionId, array $userIds, int $adminId): array
    {
        $election = $this->electionRepo->findById($electionId);
        if (!$election) {
            return ['success' => false, 'error' => 'Election not found.'];
        }

        if (in_array($election['status'], ['open', 'closed', 'results_published'], true)) {
            return [
                'success' => false,
                'error' => "Voter eligibility is locked while the election is {$election['status']}."
            ];
        }

        $assigned = $this->eligibilityRepo->assignBatch($electionId, $userIds, $adminId);

        Logger::audit('ELIGIBILITY_BATCH_ASSIGNED', 'election', (string)$electionId, [
            'count' => $assigned
        ], $adminId);

        return ['success' => true, 'assigned_count' => $assigned];
    }

    public function assignByCriteria(
        int $electionId,
        ?string $role,
        ?string $department,
        ?string $academicYear,
        int $adminId
    ): array {
        $election = $this->electionRepo->findById($electionId);
        if (!$election) {
            return ['success' => false, 'error' => 'Election not found.'];
        }

        if (in_array($election['status'], ['open', 'closed', 'results_published'], true)) {
            return [
                'success' => false,
                'error' => "Voter eligibility is locked while the election is {$election['status']}."
            ];
        }

        $assigned = $this->eligibilityRepo->assignByCriteria($electionId, $role, $department, $academicYear, $adminId);

        Logger::audit('ELIGIBILITY_CRITERIA_ASSIGNED', 'election', (string)$electionId, [
            'role' => $role,
            'department' => $department,
            'academic_year' => $academicYear,
            'assigned_count' => $assigned
        ], $adminId);

        return ['success' => true, 'assigned_count' => $assigned];
    }

    public function removeVoter(int $electionId, int $userId, int $adminId): array
    {
        $election = $this->electionRepo->findById($electionId);
        if (!$election) {
            return ['success' => false, 'error' => 'Election not found.'];
        }

        if (in_array($election['status'], ['open', 'closed', 'results_published'], true)) {
            return [
                'success' => false,
                'error' => "Voter eligibility is locked while the election is {$election['status']}."
            ];
        }

        $removed = $this->eligibilityRepo->remove($electionId, $userId);

        Logger::audit('ELIGIBILITY_REMOVED', 'election', (string)$electionId, [
            'user_id' => $userId
        ], $adminId);

        return ['success' => true, 'removed' => $removed];
    }
}
