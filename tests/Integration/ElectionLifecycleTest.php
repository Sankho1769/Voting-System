<?php
namespace Tests\Integration;

use App\Core\Database;
use App\Services\ElectionService;
use App\Services\CandidateService;
use App\Repositories\ElectionRepository;
use App\Repositories\CandidateRepository;

class ElectionLifecycleTest
{
    public static function run(): array
    {
        $results = [];
        $db = Database::getInstance();
        $electionRepo = new ElectionRepository($db);
        $candidateRepo = new CandidateRepository($db);
        $electionService = new ElectionService($electionRepo, $candidateRepo);
        $candidateService = new CandidateService($candidateRepo, $electionRepo);

        // 1. Create Draft Election
        $now = time();
        $createRes = $electionService->createElection([
            'title' => 'Test Council Lifecycle Election',
            'position' => 'President',
            'description' => 'Lifecycle validation test',
            'opens_at' => date('Y-m-d H:i:s', $now - 60), // Set to allow opening
            'closes_at' => date('Y-m-d H:i:s', $now + 7200),
            'results_visibility' => 'live_provisional'
        ], 1);

        $results[] = [
            'name' => 'ElectionService initializes election in draft state',
            'pass' => $createRes['success'] && $createRes['id'] > 0
        ];

        $electionId = $createRes['id'];

        // 2. Cannot open election with 0 approved candidates
        $failOpen = $electionService->transitionState($electionId, 'open', 1);
        $results[] = [
            'name' => 'ElectionService rejects opening an election with fewer than 2 approved candidates',
            'pass' => !$failOpen['success'] && strpos($failOpen['error'], 'At least 2 approved candidates') !== false
        ];

        // 3. Add Candidate 1
        $c1 = $candidateService->createCandidate([
            'election_id' => $electionId,
            'name' => 'Candidate Alpha',
            'manifesto' => 'Platform Alpha',
            'approval_status' => 'approved'
        ], null, 1);

        // Still cannot open with only 1 candidate
        $failOpen1 = $electionService->transitionState($electionId, 'open', 1);
        $results[] = [
            'name' => 'ElectionService rejects opening with only 1 approved candidate',
            'pass' => !$failOpen1['success']
        ];

        // 4. Add Candidate 2
        $c2 = $candidateService->createCandidate([
            'election_id' => $electionId,
            'name' => 'Candidate Beta',
            'manifesto' => 'Platform Beta',
            'approval_status' => 'approved'
        ], null, 1);

        $results[] = [
            'name' => 'CandidateService successfully registers 2 approved candidates',
            'pass' => $c1['success'] && $c2['success']
        ];

        // 5. Open election with 2 approved candidates
        $openRes = $electionService->transitionState($electionId, 'open', 1);
        $results[] = [
            'name' => 'Election successfully transitions from Draft to Open',
            'pass' => $openRes['success'] && $openRes['new_status'] === 'open'
        ];

        // 6. Ballot configuration lock: Candidate addition rejected once Open
        $c3 = $candidateService->createCandidate([
            'election_id' => $electionId,
            'name' => 'Late Contender',
            'manifesto' => 'Late Platform',
            'approval_status' => 'approved'
        ], null, 1);

        $results[] = [
            'name' => 'CandidateService enforces ballot lock and rejects candidate addition once voting is open',
            'pass' => !$c3['success'] && strpos($c3['error'], 'locked') !== false
        ];

        // 7. Ballot configuration lock: Election title/date modification rejected once Open
        $editRes = $electionService->updateElection($electionId, [
            'title' => 'Tampered Election Title'
        ], 1);

        $results[] = [
            'name' => 'ElectionService enforces lock on election configuration once voting is open',
            'pass' => !$editRes['success'] && strpos($editRes['error'], 'locked') !== false
        ];

        // 8. Close election
        $closeRes = $electionService->transitionState($electionId, 'closed', 1);
        $results[] = [
            'name' => 'Election transitions from Open to Closed',
            'pass' => $closeRes['success'] && $closeRes['new_status'] === 'closed'
        ];

        // 9. Publish results
        $publishRes = $electionService->transitionState($electionId, 'results_published', 1);
        $results[] = [
            'name' => 'Election transitions from Closed to Results Published',
            'pass' => $publishRes['success'] && $publishRes['new_status'] === 'results_published'
        ];

        return $results;
    }
}
