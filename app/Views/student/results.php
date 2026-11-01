<?php
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Helpers\TimeHelper;
$pageTitle = 'Election Results — ' . $election['title'];
?>

<div class="container container-narrow section"
     <?= ($election['status'] === 'open' && $results['has_votes']) ? 'data-live-results="true" data-election-id="' . (int)$election['id'] . '" data-api-url="' . View::url('/api/live_results.php') . '"' : '' ?>>
  
  <div style="margin-bottom: 1.5rem;">
    <a href="<?= View::url('/explore') ?>" style="text-decoration: none; font-size: 0.9rem; font-weight: 600; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 0.35rem;">
      &larr; Return to All Elections
    </a>
  </div>

  <div class="card" style="margin-bottom: 2rem; padding: 2.25rem; border-top: 4px solid var(--brand-purple);">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
      <span class="status-pill <?= Sanitizer::e($election['status']) ?>">
        <span class="status-dot"></span>
        <span><?= ucfirst(str_replace('_', ' ', $election['status'])) ?></span>
      </span>
      <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">
        <?= Sanitizer::e($label ?? 'Election Standings') ?>
      </span>
    </div>

    <h1 style="font-family: var(--font-display); font-size: 2.35rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem;">
      <?= Sanitizer::e($election['title']) ?>
    </h1>
    <div style="font-size: 1.1rem; font-weight: 600; color: var(--brand-purple); margin-bottom: 0.75rem;">
      Contested Position: <?= Sanitizer::e($election['position']) ?>
    </div>
  </div>

  <?php if (!$visible): ?>
    <div class="card" style="text-align: center; padding: 3.5rem 1.5rem;">
      <div style="width: 52px; height: 52px; border-radius: 50%; background-color: var(--surface-lavender); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem auto;">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--brand-purple)" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      </div>
      <h3 style="font-family: var(--font-display); font-size: 1.65rem; color: var(--text-primary); margin-bottom: 0.5rem;">Standings Confidential</h3>
      <p style="color: var(--text-muted); max-width: 480px; margin: 0 auto; line-height: 1.6;">
        <?= Sanitizer::e($message ?? 'Results will be published following official election closure and certification by the returning officer.') ?>
      </p>
    </div>
  <?php else: ?>
    <!-- Statistics Grid -->
    <div class="stats-grid" style="margin-bottom: 2rem;">
      <div class="stat-card">
        <div class="stat-label">Total Ballots Cast</div>
        <div class="stat-value" id="stat-total-ballots" style="color: var(--brand-purple);"><?= (int)$results['total_ballots'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Eligible Whitelist</div>
        <div class="stat-value"><?= (int)$results['eligible_voters'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Voter Turnout</div>
        <div class="stat-value" id="stat-turnout" style="color: #16a34a;"><?= (float)$results['turnout_percentage'] ?>%</div>
      </div>
    </div>

    <!-- Tie or Winner Notification Banner -->
    <?php if ($is_final): ?>
      <?php if ($results['is_tie']): ?>
        <div class="alert alert-warning" style="margin-bottom: 2rem;">
          <div>
            <strong>Election Concluded in a Tie:</strong> Two or more leading candidates received an identical number of votes. Institutional tie-breaker protocol required.
          </div>
        </div>
      <?php elseif (!empty($results['winner'])): ?>
        <div class="alert alert-success" style="margin-bottom: 2rem; background-color: var(--surface-lime); border-color: var(--accent-lime); color: #2d4500;">
          <div>
            <strong>Certified Winner:</strong> <strong><?= Sanitizer::e($results['winner']['candidate_name']) ?></strong> has been elected to the position of <?= Sanitizer::e($election['position']) ?>.
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <!-- Candidate Standings Card -->
    <div class="card" style="padding: 2.25rem;">
      <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h3 style="font-family: var(--font-display); font-size: 1.6rem; margin: 0; color: var(--text-primary);">Candidate Standings</h3>
        <?php if ($election['status'] === 'open'): ?>
          <span class="status-pill open" style="font-size: 0.75rem;">
            <span class="status-dot"></span>
            <span>Live Polling Active</span>
          </span>
        <?php endif; ?>
      </div>

      <?php if (empty($results['candidates'])): ?>
        <p style="color: var(--text-muted);">No candidates recorded for this election.</p>
      <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
          <?php foreach ($results['candidates'] as $cand): ?>
            <?php $isWinner = ($is_final && !empty($results['winner']) && $results['winner']['candidate_id'] === $cand['candidate_id']); ?>
            <div class="result-row" data-candidate-id="<?= (int)$cand['candidate_id'] ?>" style="background-color: var(--surface-secondary); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid <?= $isWinner ? 'var(--accent-lime)' : 'var(--border-subtle)' ?>;">
              <div class="result-meta" style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.65rem;">
                <span class="result-name" style="font-family: var(--font-display); font-size: 1.3rem; font-weight: 700; color: var(--text-primary);">
                  <?= Sanitizer::e($cand['candidate_name']) ?>
                  <?php if ($isWinner): ?>
                    <span class="badge" style="background-color: var(--accent-lime); color: #1a2700; font-family: var(--font-body); font-size: 0.75rem; margin-left: 0.5rem; font-weight: 800;">ELECTED</span>
                  <?php endif; ?>
                </span>
                <span>
                  <strong class="result-count" style="font-family: var(--font-mono); font-size: 1.05rem; color: var(--text-primary);"><?= (int)$cand['vote_count'] ?> votes</strong>
                  <span class="result-percent" style="color: var(--text-muted); font-size: 0.88rem; margin-left: 0.35rem;">(<?= (float)$cand['percentage'] ?>%)</span>
                </span>
              </div>
              <div class="progress-track" style="height: 12px; background-color: var(--border-subtle); border-radius: 6px; overflow: hidden;">
                <div 
                  class="progress-fill <?= $isWinner ? 'winner' : '' ?>" 
                  style="width: <?= (float)$cand['percentage'] ?>%; height: 100%; background: <?= $isWinner ? 'linear-gradient(90deg, #D7FF36, #84cc16)' : 'linear-gradient(90deg, var(--brand-purple), var(--brand-purple-light))' ?>; border-radius: 6px; transition: width 0.6s ease;"
                ></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
