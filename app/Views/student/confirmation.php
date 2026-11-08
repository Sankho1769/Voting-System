<?php
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Helpers\TimeHelper;
$pageTitle = 'Ballot Submission Receipt';
?>

<div class="container container-narrow section">
  <div class="card" style="padding: 3rem 2.25rem; text-align: center; margin-top: 1.5rem; border-top: 4px solid var(--accent-lime);">
    <div style="width: 4.5rem; height: 4.5rem; background-color: var(--accent-lime); color: var(--brand-purple-dark); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem auto; box-shadow: var(--shadow-md);">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="20 6 9 17 4 12"/>
      </svg>
    </div>

    <span class="badge badge-purple" style="margin-bottom: 0.5rem;">OFFICIALLY VERIFIED</span>
    <h1 style="font-family: var(--font-display); font-size: 2.5rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">
      Ballot Sealed &amp; Recorded
    </h1>
    <p style="color: var(--text-secondary); font-size: 1.05rem; margin-bottom: 2rem; max-width: 520px; margin-left: auto; margin-right: auto; line-height: 1.5;">
      Your official vote for <strong><?= Sanitizer::e($election['title']) ?></strong> (Position: <?= Sanitizer::e($election['position']) ?>) has been cryptographically confirmed.
    </p>

    <!-- Receipt Display Card -->
    <div style="background-color: var(--surface-secondary); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.75rem; text-align: left; margin-bottom: 2rem;">
      <div style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); font-weight: 700; margin-bottom: 0.5rem;">
        Cryptographic Participation Receipt
      </div>
      <div style="font-family: var(--font-mono); font-size: 0.85rem; word-break: break-all; background-color: var(--surface-card); padding: 0.85rem 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-subtle); margin-bottom: 1rem; color: var(--brand-purple); font-weight: 600;">
        <?= Sanitizer::e($receipt) ?>
      </div>
      <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--text-muted); flex-wrap: wrap; gap: 0.5rem;">
        <span>Recorded At: <strong style="color: var(--text-primary);"><?= TimeHelper::format($votedAt) ?></strong></span>
        <span>Audit Status: <strong style="color: #16a34a;">&check; ACID Confirmed</strong></span>
      </div>
    </div>

    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
      <button type="button" class="btn btn-secondary" onclick="if(navigator.clipboard){navigator.clipboard.writeText('<?= Sanitizer::e($receipt) ?>').then(function(){alert('Receipt token copied to clipboard!');});}else{prompt('Copy your receipt token:', '<?= Sanitizer::e($receipt) ?>');}">
        Copy Receipt Token
      </button>
      <button type="button" class="btn btn-secondary" onclick="window.print();">
        Print Receipt
      </button>
      <a href="<?= View::url('/voter') ?>" class="btn btn-secondary">
        &larr; Return to Ballot Box
      </a>
      <a href="<?= View::url('/explore') ?>" class="btn btn-outline">
        Explore Other Elections
      </a>
      <?php if ($election['results_visibility'] === 'live_provisional' || $election['status'] === 'results_published'): ?>
        <a href="<?= View::url('/results?id=' . $election['id']) ?>" class="btn btn-primary">
          View Live Standings &rarr;
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>
