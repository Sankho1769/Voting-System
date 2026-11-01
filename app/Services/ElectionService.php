<?php
namespace App\Services;

use App\Core\Logger;
use App\Helpers\Sanitizer;
use App\Helpers\Validator;
use App\Repositories\ElectionRepository;
use App\Repositories\CandidateRepository;
use RuntimeException;

/**
 * Election Lifecycle and State Machine Service
 */
class ElectionService
{
    private ElectionRepository $electionRepo;
    private CandidateRepository $candidateRepo;

    public function __construct(
        ?ElectionRepository $electionRepo = null,
        ?CandidateRepository $candidateRepo = null
    ) {
        $this->electionRepo = $electionRepo ?? new ElectionRepository();
        $this->candidateRepo = $candidateRepo ?? new CandidateRepository();
    }

    public function createElection(array $data, int $userId): array
    {
        $title = Sanitizer::cleanText($data['title'] ?? '');
        $position = Sanitizer::cleanText($data['position'] ?? '');
        $category = !empty($data['category']) ? Sanitizer::cleanText($data['category']) : 'student_council';
        $department = !empty($data['department']) ? Sanitizer::cleanText($data['department']) : null;
        $description = Sanitizer::cleanText($data['description'] ?? '');
        $opensAt = trim($data['opens_at'] ?? '');
        $closesAt = trim($data['closes_at'] ?? '');
        $visibility = in_array($data['results_visibility'] ?? '', ['live_provisional', 'after_closure_only'], true)
            ? $data['results_visibility']
            : 'live_provisional';

        if (empty($title) || empty($position)) {
            return ['success' => false, 'error' => 'Title and Position are required fields.'];
        }

        $dateError = null;
        if (!Validator::validateElectionDates($opensAt, $closesAt, $dateError)) {
            return ['success' => false, 'error' => $dateError];
        }

        $electionId = $this->electionRepo->create([
            'title' => $title,
            'position' => $position,
            'category' => $category,
            'department' => $department,
            'description' => $description,
            'status' => 'draft',
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'results_visibility' => $visibility,
            'created_by' => $userId
        ]);

        Logger::audit('ELECTION_CREATED', 'election', (string)$electionId, [
            'title' => $title,
            'position' => $position,
            'category' => $category,
            'opens_at' => $opensAt,
            'closes_at' => $closesAt
        ], $userId);

        return ['success' => true, 'id' => $electionId];
    }

    public function updateElection(int $id, array $data, int $userId): array
    {
        $election = $this->electionRepo->findById($id);
        if (!$election) {
            return ['success' => false, 'error' => 'Election not found.'];
        }

        // Lock configuration once voting is open or closed
        if (in_array($election['status'], ['open', 'closed', 'results_published'], true)) {
            return [
                'success' => false,
                'error' => "Ballot configuration is locked because this election is currently {$election['status']}."
            ];
        }

        $title = Sanitizer::cleanText($data['title'] ?? $election['title']);
        $position = Sanitizer::cleanText($data['position'] ?? $election['position']);
        $category = !empty($data['category']) ? Sanitizer::cleanText($data['category']) : ($election['category'] ?? 'student_council');
        $department = array_key_exists('department', $data) ? Sanitizer::cleanText($data['department']) : ($election['department'] ?? null);
        $description = Sanitizer::cleanText($data['description'] ?? $election['description']);
        $opensAt = trim($data['opens_at'] ?? $election['opens_at']);
        $closesAt = trim($data['closes_at'] ?? $election['closes_at']);
        $visibility = in_array($data['results_visibility'] ?? '', ['live_provisional', 'after_closure_only'], true)
            ? $data['results_visibility']
            : $election['results_visibility'];

        $dateError = null;
        if (!Validator::validateElectionDates($opensAt, $closesAt, $dateError)) {
            return ['success' => false, 'error' => $dateError];
        }

        $this->electionRepo->update($id, [
            'title' => $title,
            'position' => $position,
            'category' => $category,
            'department' => $department,
            'description' => $description,
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'results_visibility' => $visibility
        ]);

        Logger::audit('ELECTION_UPDATED', 'election', (string)$id, [
            'title' => $title,
            'position' => $position
        ], $userId);

        return ['success' => true];
    }

    public function transitionState(int $id, string $targetState, int $userId): array
    {
        $election = $this->electionRepo->findById($id);
        if (!$election) {
            return ['success' => false, 'error' => 'Election not found.'];
        }

        $currentState = $election['status'];

        $allowedTransitions = [
            'draft' => ['scheduled', 'open', 'cancelled'],
            'scheduled' => ['open', 'draft', 'cancelled'],
            'open' => ['closed', 'cancelled'],
            'closed' => ['results_published'],
            'results_published' => [],
            'cancelled' => []
        ];

        if (!in_array($targetState, $allowedTransitions[$currentState] ?? [], true)) {
            return [
                'success' => false,
                'error' => "Invalid transition from '{$currentState}' to '{$targetState}'."
            ];
        }

        // Validate rules for opening or scheduling an election
        if (in_array($targetState, ['scheduled', 'open'], true)) {
            $approvedCandidates = $this->candidateRepo->countApproved($id);
            if ($approvedCandidates < 2) {
                return [
                    'success' => false,
                    'error' => "Cannot transition to '{$targetState}': At least 2 approved candidates are required before opening (Current approved: {$approvedCandidates})."
                ];
            }

            if (strtotime($election['closes_at']) <= time()) {
                return [
                    'success' => false,
                    'error' => "Cannot open election: Closing time is in the past."
                ];
            }
        }

        $this->electionRepo->updateStatus($id, $targetState);

        Logger::audit('ELECTION_STATE_TRANSITION', 'election', (string)$id, [
            'old_state' => $currentState,
            'new_state' => $targetState
        ], $userId);

        return ['success' => true, 'new_status' => $targetState];
    }

    public static function getCategories(): array
    {
        return [
            'student_council' => [
                'name' => 'Student Council',
                'label' => 'Student Council Elections',
                'desc' => 'Apex collegiate student leadership and executive representation',
                'badge' => 'badge-council'
            ],
            'college_president' => [
                'name' => 'Presidential',
                'label' => 'College President Elections',
                'desc' => 'University & college student body president ballots',
                'badge' => 'badge-president'
            ],
            'department' => [
                'name' => 'Departmental',
                'label' => 'Department Elections',
                'desc' => 'BCA, Computer Science, Commerce and Faculty representation',
                'badge' => 'badge-dept'
            ],
            'class_rep' => [
                'name' => 'Class Rep',
                'label' => 'Class Representative Elections',
                'desc' => 'Batch, semester, and section representative elections',
                'badge' => 'badge-class'
            ],
            'club_society' => [
                'name' => 'Clubs & Societies',
                'label' => 'Club & Society Elections',
                'desc' => 'Sports, cultural, debate, technical, and arts society offices',
                'badge' => 'badge-club'
            ],
            'committee' => [
                'name' => 'Committees',
                'label' => 'Committee & Board Elections',
                'desc' => 'Library, mess, welfare, and disciplinary committees',
                'badge' => 'badge-committee'
            ],
            'university' => [
                'name' => 'University',
                'label' => 'University & Senate Elections',
                'desc' => 'Campus-wide senate and institutional delegate positions',
                'badge' => 'badge-univ'
            ],
            'other' => [
                'name' => 'Special Ballots',
                'label' => 'Other Authorized Voting Events',
                'desc' => 'Campus referendums, special initiatives and student polls',
                'badge' => 'badge-other'
            ]
        ];
    }
}
