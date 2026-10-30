<?php
namespace App\Services;

use App\Repositories\ElectionRepository;
use App\Repositories\VotingRepository;
use App\Helpers\CSVExporter;
use App\Helpers\TimeHelper;

/**
 * Election Results and Tally Aggregation Service
 */
class ResultsService
{
    private ElectionRepository $electionRepo;
    private VotingRepository $votingRepo;

    public function __construct(
        ?ElectionRepository $electionRepo = null,
        ?VotingRepository $votingRepo = null
    ) {
        $this->electionRepo = $electionRepo ?? new ElectionRepository();
        $this->votingRepo = $votingRepo ?? new VotingRepository();
    }

    public function getResults(int $electionId, ?array $user = null): array
    {
        $election = $this->electionRepo->findById($electionId);
        if (!$election) {
            return ['success' => false, 'error' => 'Election not found.'];
        }

        $isAdminOrOfficer = $user && in_array($user['role'] ?? '', ['admin', 'election_officer'], true);
        $status = $election['status'];
        $visibilityPolicy = $election['results_visibility'];

        $canView = false;
        $label = 'Provisional Results';

        if ($isAdminOrOfficer) {
            $canView = true;
            $label = ($status === 'results_published') ? 'Official Final Results' : 'Administrative Live Standings';
        } elseif ($status === 'results_published') {
            $canView = true;
            $label = 'Official Final Results';
        } elseif ($status === 'closed') {
            $canView = true;
            $label = 'Closed Election Tally (Pending Official Certification)';
        } elseif ($status === 'open') {
            if ($visibilityPolicy === 'live_provisional') {
                $canView = true;
                $label = 'Live Provisional Standings (Voting Open)';
            } else {
                $canView = false;
            }
        }

        if (!$canView) {
            return [
                'success' => true,
                'visible' => false,
                'election' => $election,
                'message' => 'Standings will be announced after election closure.'
            ];
        }

        $tally = $this->votingRepo->getResults($electionId);

        return [
            'success' => true,
            'visible' => true,
            'label' => $label,
            'is_final' => ($status === 'results_published'),
            'election' => $election,
            'results' => $tally
        ];
    }

    public function generateResultsCsv(int $electionId): array
    {
        $election = $this->electionRepo->findById($electionId);
        if (!$election) {
            throw new \RuntimeException("Election not found.");
        }

        $tally = $this->votingRepo->getResults($electionId);

        $headers = [
            'Election Title',
            'Position',
            'Candidate Name',
            'Votes Received',
            'Vote Share (%)',
            'Standing',
            'Total Ballots Cast',
            'Total Eligible Voters',
            'Turnout (%)'
        ];

        $rows = [];
        foreach ($tally['candidates'] as $cand) {
            $standing = 'Contender';
            if ($tally['is_tie']) {
                $standing = ($cand['vote_count'] === ($tally['candidates'][0]['vote_count'] ?? 0)) ? 'Tied' : 'Runner-up';
            } elseif (!empty($tally['winner']) && $tally['winner']['candidate_id'] === $cand['candidate_id']) {
                $standing = 'Elected / Winner';
            }

            $rows[] = [
                $election['title'],
                $election['position'],
                $cand['candidate_name'],
                $cand['vote_count'],
                $cand['percentage'] . '%',
                $standing,
                $tally['total_ballots'],
                $tally['eligible_voters'],
                $tally['turnout_percentage'] . '%'
            ];
        }

        $filename = 'verityvote_results_election_' . $electionId . '_' . date('Ymd_His') . '.csv';

        return [
            'filename' => $filename,
            'headers' => $headers,
            'rows' => $rows
        ];
    }
}
