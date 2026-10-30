<?php
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Helpers\TimeHelper;
$pageTitle = 'Election Results — ' . $election['title'];
?>

<div class="container container-narrow"
     <?= ($election['status'] === 'open' && $results['has_votes']) ? 'data-live-results="true" data-election-id="' . (int)$election['id'] . '" data-api-url="' . View::url('/api/live_results.php') . '"' : '' ?>>
  
  <div style="margin-bottom: 1.5rem;">
    <a href="<?= View::url('/dashboard') ?>" style="text-decoration: none; font-size: 0.9rem; font-weight: 500;">
      &larr; Back to Elections Portal
    </a>
  </div>

  <div class="card card-ivory" style="margin-bottom: 2rem;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
      <span class="badge badge-<?= Sanitizer::e($election['status']) ?>">
        <?= ucfirst(str_replace('_', ' ', $election['status'])) ?>
      </span>
      <span style="font-size: 0.85rem; color: var(--color-text-secondary); font-weight: 600;">
        <?= Sanitizer::e($label ?? 'Election Standings') ?>
      </span>
    </div>

    <h1 style="font-size: 2rem; margin-bottom: 0.25rem;">
      <?= Sanitizer::e($election['title']) ?>
    </h1>
    <div style="font-size: 1.1rem; font-weight: 600; color: var(--color-emerald-green); margin-bottom: 0.75rem;">
      Position: <?= Sanitizer::e($election['position']) ?>
    </div>
  </div>

  <?php if (!$visible): ?>
    <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
      <h3 style="margin-bottom: 0.5rem;">Standings Confidential</h3>
      <p style="color: var(--color-text-secondary); max-width: 500px; margin: 0 auto;">
        <?= Sanitizer::e($message ?? 'Results will be published following official election closure and certification.') ?>
      </p>
    </div>
  <?php else: ?>
    <!-- Statistics Grid -->
    <div class="stats-grid" style="margin-bottom: 2rem;">
      <div class="stat-card">
        <div class="stat-label">Total Ballots Cast</div>
        <div class="stat-value" id="stat-total-ballots"><?= (int)$results['total_ballots'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Eligible Voters</div>
        <div class="stat-value"><?= (int)$results['eligible_voters'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Voter Turnout</div>
        <div class="stat-value" id="stat-turnout"><?= (float)$results['turnout_percentage'] ?>%</div>
      </div>
    </div>

    <!-- Tie or Winner Notification Banner -->
    <?php if ($is_final): ?>
      <?php if ($results['is_tie']): ?>
        <div class="alert alert-warning" style="margin-bottom: 2rem;">
          <div>
            <strong>Election Concluded in a Tie:</strong> Two or more leading candidates received an identical number of votes. No single winner is declared without collegiate tie-breaker procedures.
          </div>
        </div>
      <?php elseif (!empty($results['winner'])): ?>
        <div class="alert alert-success" style="margin-bottom: 2rem;">
          <div>
            <strong>Official Winner Declared:</strong> <strong><?= Sanitizer::e($results['winner']['candidate_name']) ?></strong> has been elected to the position of <?= Sanitizer::e($election['position']) ?>.
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <!-- Candidate Standings Card -->
    <div class="card" style="padding: 2rem;">
      <div class="card-header">
        <h3>Candidate Standings</h3>
        <?php if ($election['status'] === 'open'): ?>
          <span style="font-size: 0.8rem; color: var(--color-emerald-green); font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem;">
            <span style="width: 8px; height: 8px; background-color: var(--color-emerald-green); border-radius: 50%; display: inline-block;"></span>
            Live Polling Active
          </span>
        <?php endif; ?>
      </div>

      <?php if (empty($results['candidates'])): ?>
        <p style="color: var(--color-text-muted);">No candidates recorded for this election.</p>
      <?php else: ?>
        <?php foreach ($results['candidates'] as $cand): ?>
          <div class="result-row" data-candidate-id="<?= (int)$cand['candidate_id'] ?>">
            <div class="result-meta">
              <span class="result-name">
                <?= Sanitizer::e($cand['candidate_name']) ?>
                <?php if ($is_final && !empty($results['winner']) && $results['winner']['candidate_id'] === $cand['candidate_id']): ?>
                  <span class="badge badge-published" style="margin-left: 0.5rem;">Winner</span>
                <?php endif; ?>
              </span>
              <span>
                <strong class="result-count"><?= (int)$cand['vote_count'] ?> votes</strong>
                <span class="result-percent" style="color: var(--color-text-muted); font-size: 0.85rem; margin-left: 0.25rem;">(<?= (float)$cand['percentage'] ?>%)</span>
              </span>
            </div>
            <div class="progress-track">
              <div 
                class="progress-fill <?= ($is_final && !empty($results['winner']) && $results['winner']['candidate_id'] === $cand['candidate_id']) ? 'winner' : '' ?>" 
                style="width: <?= (float)$cand['percentage'] ?>%;"
              ></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
