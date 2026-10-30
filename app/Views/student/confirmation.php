<?php
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Helpers\TimeHelper;
$pageTitle = 'Ballot Submission Receipt';
?>

<div class="container container-narrow">
  <div class="card card-ivory" style="padding: 2.5rem; text-align: center; margin-top: 2rem;">
    <div style="width: 4rem; height: 4rem; background-color: var(--color-emerald-green); color: #FFFFFF; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 1.5rem auto;">
      &check;
    </div>

    <h1 style="font-size: 2.1rem; margin-bottom: 0.5rem;">Ballot Recorded Successfully</h1>
    <p style="color: var(--color-text-secondary); font-size: 1.05rem; margin-bottom: 2rem;">
      Your vote for <strong><?= Sanitizer::e($election['title']) ?></strong> (Position: <?= Sanitizer::e($election['position']) ?>) has been securely accepted into the collegiate voting engine.
    </p>

    <div style="background-color: #FFFFFF; border: 1px solid var(--color-border-medium); border-radius: var(--radius-md); padding: 1.75rem; text-align: left; margin-bottom: 2rem; box-shadow: var(--shadow-sm);">
      <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted); font-weight: 700; margin-bottom: 0.5rem;">
        Cryptographic Participation Receipt
      </div>
      <div style="font-family: var(--font-mono); font-size: 0.85rem; word-break: break-all; background-color: var(--color-surface-muted); padding: 0.85rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border-subtle); margin-bottom: 1rem; color: var(--color-text-primary);">
        <?= Sanitizer::e($receipt) ?>
      </div>
      <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--color-text-secondary);">
        <span>Recorded At: <strong><?= TimeHelper::format($votedAt) ?></strong></span>
        <span>Status: <strong style="color: var(--color-emerald-green);">Audited &amp; Sealed</strong></span>
      </div>
    </div>

    <div style="display: flex; gap: 1rem; justify-content: center;">
      <a href="<?= View::url('/dashboard') ?>" class="btn btn-secondary">
        &larr; Return to Dashboard
      </a>
      <?php if ($election['results_visibility'] === 'live_provisional' || $election['status'] === 'results_published'): ?>
        <a href="<?= View::url('/results?id=' . $election['id']) ?>" class="btn btn-primary">
          View Live Standings &rarr;
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>
