<?php
namespace Tests\Integration;

use App\Core\Database;
use App\Services\AccountService;
use App\Services\ElectionService;
use App\Services\CandidateService;
use App\Services\EligibilityService;
use App\Services\VotingService;
use App\Services\ResultsService;
use App\Repositories\ElectionRepository;
use App\Repositories\CandidateRepository;
use App\Repositories\EligibilityRepository;
use App\Repositories\VotingRepository;
use App\Repositories\UserRepository;

class VotingConcurrencyTest
{
    public static function run(): array
    {
        $results = [];
        $db = Database::getInstance();
        $userRepo = new UserRepository($db);
        $accountService = new AccountService($userRepo);
        $electionRepo = new ElectionRepository($db);
        $candidateRepo = new CandidateRepository($db);
        $electionService = new ElectionService($electionRepo, $candidateRepo);
        $candidateService = new CandidateService($candidateRepo, $electionRepo);
        $eligibilityRepo = new EligibilityRepository($db);
        $eligibilityService = new EligibilityService($eligibilityRepo, $electionRepo);
        $votingRepo = new VotingRepository($db);
        $votingService = new VotingService($votingRepo);
        $resultsService = new ResultsService($electionRepo, $votingRepo);

        // 1. Provision 3 test voters
        $voter1 = $accountService->createUser([
            'identifier' => 'VOTER_A_' . bin2hex(random_bytes(2)),
            'role' => 'student',
            'full_name' => 'Alice Voter',
            'department' => 'BCA'
        ], 1);
        $userRepo->updatePassword($voter1['user_id'], password_hash('Pass12345', PASSWORD_DEFAULT));

        $voter2 = $accountService->createUser([
            'identifier' => 'VOTER_B_' . bin2hex(random_bytes(2)),
            'role' => 'student',
            'full_name' => 'Bob Voter',
            'department' => 'BCA'
        ], 1);
        $userRepo->updatePassword($voter2['user_id'], password_hash('Pass12345', PASSWORD_DEFAULT));

        $voterIneligible = $accountService->createUser([
            'identifier' => 'VOTER_INELIG_' . bin2hex(random_bytes(2)),
            'role' => 'student',
            'full_name' => 'Charlie Ineligible',
            'department' => 'Commerce'
        ], 1);
        $userRepo->updatePassword($voterIneligible['user_id'], password_hash('Pass12345', PASSWORD_DEFAULT));

        // 2. Create open election with 2 candidates
        $now = time();
        $elRes = $electionService->createElection([
            'title' => 'Concurrency & Integrity Election',
            'position' => 'Secretary',
            'description' => 'Ballot engine validation',
            'opens_at' => date('Y-m-d H:i:s', $now - 60),
            'closes_at' => date('Y-m-d H:i:s', $now + 3600),
            'results_visibility' => 'live_provisional'
        ], 1);
        $electionId = $elRes['id'];

        $cand1 = $candidateService->createCandidate([
            'election_id' => $electionId,
            'name' => 'Candidate 1',
            'approval_status' => 'approved'
        ], null, 1);
        $cand2 = $candidateService->createCandidate([
            'election_id' => $electionId,
            'name' => 'Candidate 2',
            'approval_status' => 'approved'
        ], null, 1);

        // Assign voter 1 and 2 to eligibility whitelist
        $eligibilityService->assignVoters($electionId, [$voter1['user_id'], $voter2['user_id']], 1);

        // Transition to Open
        $electionService->transitionState($electionId, 'open', 1);

        // 3. Test eligible vote accepted
        $vote1 = $votingService->castVote($electionId, $cand1['id'], $voter1['user_id']);
        $results[] = [
            'name' => 'Eligible voter successfully casts ballot and receives cryptographic receipt',
            'pass' => $vote1['success'] && !empty($vote1['confirmation_receipt'])
        ];

        // 4. Test duplicate vote rejected (Double-Voting Defense)
        $dupVote = $votingService->castVote($electionId, $cand2['id'], $voter1['user_id']);
        $results[] = [
            'name' => 'Double-voting attempt by same user is rejected with error',
            'pass' => !$dupVote['success'] && strpos($dupVote['error'], 'already cast your vote') !== false
        ];

        // 5. Test ineligible voter rejected
        $ineligVote = $votingService->castVote($electionId, $cand1['id'], $voterIneligible['user_id']);
        $results[] = [
            'name' => 'Ineligible voter is rejected from casting ballot',
            'pass' => !$ineligVote['success'] && strpos($ineligVote['error'], 'not eligible') !== false
        ];

        // 6. Test wrong-election candidate rejected
        // Create dummy second election
        $el2 = $electionService->createElection([
            'title' => 'Other Election',
            'position' => 'Treasurer',
            'opens_at' => date('Y-m-d H:i:s', $now - 60),
            'closes_at' => date('Y-m-d H:i:s', $now + 3600)
        ], 1);
        $candOther = $candidateService->createCandidate([
            'election_id' => $el2['id'],
            'name' => 'Foreign Candidate',
            'approval_status' => 'approved'
        ], null, 1);

        $wrongCandVote = $votingService->castVote($electionId, $candOther['id'], $voter2['user_id']);
        $results[] = [
            'name' => 'Ballot for candidate contesting a different election is rejected',
            'pass' => !$wrongCandVote['success'] && strpos($wrongCandVote['error'], 'does not contest') !== false
        ];

        // 7. Cast valid vote for candidate 2 from voter 2 (creates a 1-1 tie)
        $vote2 = $votingService->castVote($electionId, $cand2['id'], $voter2['user_id']);
        $results[] = [
            'name' => 'Second eligible voter casts valid ballot',
            'pass' => $vote2['success']
        ];

        // 8. Verify Ballot Secrecy Schema: votes table MUST NOT have user_id column
        $cols = $db->fetchAll("SHOW COLUMNS FROM votes LIKE 'user_id'");
        $results[] = [
            'name' => 'Ballot Privacy: votes table has no user_id column (Participation separated from choice)',
            'pass' => empty($cols)
        ];

        // 9. Verify Ballot and Participation Consistency
        $votesCount = (int)$db->fetchValue("SELECT COUNT(*) FROM votes WHERE election_id = ?", [$electionId], 'i');
        $participationCount = (int)$db->fetchValue("SELECT COUNT(*) FROM election_participation WHERE election_id = ?", [$electionId], 'i');
        $results[] = [
            'name' => 'Consistency: Total valid ballots in box exactly matches participation receipts count',
            'pass' => $votesCount === 2 && $participationCount === 2
        ];

        // 10. Tie Handling in Results
        $tally = $votingRepo->getResults($electionId);
        $results[] = [
            'name' => 'Results Engine detects tie when vote counts are equal',
            'pass' => $tally['is_tie'] === true && $tally['total_ballots'] === 2
        ];

        // 11. Zero-Vote Handling: Create empty election and verify no division-by-zero
        $emptyEl = $electionService->createElection([
            'title' => 'Empty Zero-Vote Election',
            'position' => 'Auditor',
            'opens_at' => date('Y-m-d H:i:s', $now - 60),
            'closes_at' => date('Y-m-d H:i:s', $now + 3600)
        ], 1);
        $candidateService->createCandidate(['election_id' => $emptyEl['id'], 'name' => 'Zero A', 'approval_status' => 'approved'], null, 1);
        $candidateService->createCandidate(['election_id' => $emptyEl['id'], 'name' => 'Zero B', 'approval_status' => 'approved'], null, 1);
        $electionService->transitionState($emptyEl['id'], 'open', 1);

        $emptyTally = $votingRepo->getResults($emptyEl['id']);
        $results[] = [
            'name' => 'Zero-vote handling computes 0.0% without division-by-zero error',
            'pass' => $emptyTally['total_ballots'] === 0 && $emptyTally['candidates'][0]['percentage'] === 0.0
        ];

        // 12. 25 Concurrent Independent Sessions/Processes for the SAME Single Voter
        $voterConcurrent = $accountService->createUser([
            'identifier' => 'CONCUR_VOTER_' . bin2hex(random_bytes(3)),
            'role' => 'student',
            'full_name' => 'Concurrency Stress Target Voter',
            'department' => 'BCA'
        ], 1);
        $userRepo->updatePassword($voterConcurrent['user_id'], password_hash('Pass12345', PASSWORD_DEFAULT));

        $concEl = $electionService->createElection([
            'title' => '25-Worker Concurrency Stress Election',
            'position' => 'President',
            'opens_at' => date('Y-m-d H:i:s', $now - 60),
            'closes_at' => date('Y-m-d H:i:s', $now + 3600)
        ], 1);
        $cConc1 = $candidateService->createCandidate(['election_id' => $concEl['id'], 'name' => 'Alice Stress', 'approval_status' => 'approved'], null, 1);
        $cConc2 = $candidateService->createCandidate(['election_id' => $concEl['id'], 'name' => 'Bob Stress', 'approval_status' => 'approved'], null, 1);
        $eligibilityService->assignVoters($concEl['id'], [$voterConcurrent['user_id']], 1);
        $electionService->transitionState($concEl['id'], 'open', 1);

        $workerPath = dirname(__DIR__, 2) . '/bin/vote_worker.php';
        $phpBinary = PHP_BINARY;
        
        $concurrencyAttempts = 25;
        $processes = [];
        $pipes = [];

        for ($i = 0; $i < $concurrencyAttempts; $i++) {
            $targetCandidate = ($i % 2 === 0) ? $cConc1['id'] : $cConc2['id'];
            $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($workerPath) . ' ' . (int)$concEl['id'] . ' ' . (int)$targetCandidate . ' ' . (int)$voterConcurrent['user_id'];
            $proc = proc_open($cmd, [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ], $procPipes);

            if (is_resource($proc)) {
                $processes[] = $proc;
                $pipes[] = $procPipes;
            }
        }

        $acceptedVotes = 0;
        $rejectedVotes = 0;

        foreach ($processes as $idx => $proc) {
            $stdout = stream_get_contents($pipes[$idx][1]);
            $stderr = stream_get_contents($pipes[$idx][2]);
            fclose($pipes[$idx][1]);
            fclose($pipes[$idx][2]);
            $exitCode = proc_close($proc);

            $payload = json_decode(trim($stdout), true);
            if ($exitCode === 0 && ($payload['status'] ?? '') === 'success') {
                $acceptedVotes++;
            } else {
                $rejectedVotes++;
            }
        }

        $dbParticipationCount = (int)$db->fetchValue(
            "SELECT COUNT(*) FROM election_participation WHERE election_id = ? AND user_id = ?",
            [$concEl['id'], $voterConcurrent['user_id']],
            'ii'
        );
        $dbVotesCount = (int)$db->fetchValue(
            "SELECT COUNT(*) FROM votes WHERE election_id = ?",
            [$concEl['id']],
            'i'
        );

        $results[] = [
            'name' => "25 Concurrent Connections for Same Voter: Exactly 1 accepted ($acceptedVotes) and exactly 24 rejected ($rejectedVotes)",
            'pass' => ($acceptedVotes === 1 && $rejectedVotes === 24)
        ];

        $results[] = [
            'name' => "25 Concurrent Submissions: Database stores exactly 1 participation record and 1 ballot in votes table",
            'pass' => ($dbParticipationCount === 1 && $dbVotesCount === 1)
        ];

        // 13. High Concurrency: 10 Distinct Eligible Voters Casting Ballots Simultaneously
        $distinctVoterCount = 10;
        $distinctVoterIds = [];
        for ($v = 0; $v < $distinctVoterCount; $v++) {
            $u = $accountService->createUser([
                'identifier' => 'MULTI_VOTER_' . $v . '_' . bin2hex(random_bytes(2)),
                'role' => 'student',
                'full_name' => "Multi Voter $v",
                'department' => 'BCA'
            ], 1);
            $userRepo->updatePassword($u['user_id'], password_hash('Pass12345', PASSWORD_DEFAULT));
            $distinctVoterIds[] = $u['user_id'];
        }

        $multiEl = $electionService->createElection([
            'title' => 'Multi-Voter High Concurrency Election',
            'position' => 'Vice President',
            'opens_at' => date('Y-m-d H:i:s', $now - 60),
            'closes_at' => date('Y-m-d H:i:s', $now + 3600)
        ], 1);
        $cMulti1 = $candidateService->createCandidate(['election_id' => $multiEl['id'], 'name' => 'Multi Cand A', 'approval_status' => 'approved'], null, 1);
        $cMulti2 = $candidateService->createCandidate(['election_id' => $multiEl['id'], 'name' => 'Multi Cand B', 'approval_status' => 'approved'], null, 1);
        $cMulti3 = $candidateService->createCandidate(['election_id' => $multiEl['id'], 'name' => 'Multi Cand C', 'approval_status' => 'approved'], null, 1);
        $multiCandidates = [$cMulti1['id'], $cMulti2['id'], $cMulti3['id']];

        $eligibilityService->assignVoters($multiEl['id'], $distinctVoterIds, 1);
        $electionService->transitionState($multiEl['id'], 'open', 1);

        $multiProcesses = [];
        $multiPipes = [];
        foreach ($distinctVoterIds as $idx => $vId) {
            $targetCand = $multiCandidates[$idx % count($multiCandidates)];
            $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($workerPath) . ' ' . (int)$multiEl['id'] . ' ' . (int)$targetCand . ' ' . (int)$vId;
            $proc = proc_open($cmd, [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ], $procPipes);
            if (is_resource($proc)) {
                $multiProcesses[] = $proc;
                $multiPipes[] = $procPipes;
            }
        }

        $multiSuccesses = 0;
        $multiFailures = 0;
        $multiErrors = [];
        foreach ($multiProcesses as $idx => $proc) {
            $stdout = stream_get_contents($multiPipes[$idx][1]);
            $stderr = stream_get_contents($multiPipes[$idx][2]);
            fclose($multiPipes[$idx][1]);
            fclose($multiPipes[$idx][2]);
            $exitCode = proc_close($proc);
            $payload = json_decode(trim($stdout), true);
            if ($exitCode === 0 && ($payload['status'] ?? '') === 'success') {
                $multiSuccesses++;
            } else {
                $multiFailures++;
                $multiErrors[] = "Worker $idx (Exit $exitCode): " . ($payload['message'] ?? $stdout ?: $stderr);
            }
        }

        $multiParticipation = (int)$db->fetchValue("SELECT COUNT(*) FROM election_participation WHERE election_id = ?", [$multiEl['id']], 'i');
        $multiVotes = (int)$db->fetchValue("SELECT COUNT(*) FROM votes WHERE election_id = ?", [$multiEl['id']], 'i');

        $results[] = [
            'name' => "10 Distinct Eligible Voters Voting Simultaneously: All 10 succeed ($multiSuccesses/10, failures: $multiFailures" . (!empty($multiErrors) ? ' | ' . implode('; ', $multiErrors) : '') . ")",
            'pass' => ($multiSuccesses === 10 && $multiFailures === 0)
        ];

        $results[] = [
            'name' => "Multi-Voter Concurrency: Total votes and participation records both equal exactly 10 without race-condition data loss",
            'pass' => ($multiParticipation === 10 && $multiVotes === 10)
        ];

        return $results;
    }
}
