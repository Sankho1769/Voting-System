<?php
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Helpers\TimeHelper;
$pageTitle = 'Manage Elections';
?>

<div class="container container-wide section">
  <div class="page-header" style="margin-bottom: 2rem;">
    <div>
      <span class="badge badge-purple" style="margin-bottom: 0.5rem;">CENTRAL ELECTION GOVERNANCE</span>
      <h1 style="font-family: var(--font-display); font-size: 2.75rem; font-weight: 700; color: var(--text-primary); margin: 0 0 0.35rem 0;">
        Collegiate Elections
      </h1>
      <div style="color: var(--text-muted); font-size: 1rem;">
        Single-position ballot configurations &bull; Lifecycle governance &bull; Voter eligibility
      </div>
    </div>
    <div>
      <a href="<?= View::url('/admin/elections/create') ?>" class="btn btn-primary btn-sm">+ Create Election</a>
    </div>
  </div>

  <div class="card" style="padding: 0; overflow: hidden;">
    <div class="table-container" style="border: none; margin: 0;">
      <table class="table">
        <thead>
          <tr>
            <th>Election Title &amp; Position</th>
            <th>Category</th>
            <th>Lifecycle Status</th>
            <th>Contenders</th>
            <th>Whitelist</th>
            <th>Ballots</th>
            <th>Schedule Window</th>
            <th>Visibility</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($elections)): ?>
            <tr>
              <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 3rem;">
                No elections created yet. Click "Create Election" to initialize a ballot.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($elections as $el): ?>
              <tr>
                <td>
                  <strong style="color: var(--text-primary);"><?= Sanitizer::e($el['title']) ?></strong>
                  <div style="font-size: 0.8rem; color: var(--text-muted);">Position: <?= Sanitizer::e($el['position']) ?></div>
                </td>
                <td>
                  <span class="badge badge-purple" style="font-size: 0.72rem;">
                    <?= Sanitizer::e(ucwords(str_replace('_', ' ', $el['category'] ?? 'General'))) ?>
                  </span>
                </td>
                <td>
                  <span class="status-pill <?= Sanitizer::e($el['status']) ?>">
                    <span class="status-dot"></span>
                    <span><?= ucfirst(str_replace('_', ' ', $el['status'])) ?></span>
                  </span>
                </td>
                <td><?= (int)$el['approved_candidates_count'] ?></td>
                <td><?= (int)$el['eligible_voters_count'] ?></td>
                <td><strong style="font-family: var(--font-mono);"><?= (int)$el['total_votes_cast'] ?></strong></td>
                <td style="font-size: 0.825rem; color: var(--text-muted);">
                  <div>Opens: <?= TimeHelper::format($el['opens_at'], 'd M, H:i') ?></div>
                  <div>Closes: <?= TimeHelper::format($el['closes_at'], 'd M, H:i') ?></div>
                </td>
                <td style="font-size: 0.8rem; color: var(--text-secondary);">
                  <?= $el['results_visibility'] === 'live_provisional' ? 'Live Provisional' : 'After Closure' ?>
                </td>
                <td style="text-align: right;">
                  <a href="<?= View::url('/admin/elections/detail?id=' . $el['id']) ?>" class="btn btn-secondary btn-sm" style="padding: 0.25rem 0.65rem; font-size: 0.8rem;">
                    Manage &rarr;
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
