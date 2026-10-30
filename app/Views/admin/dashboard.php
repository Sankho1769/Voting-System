<?php
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Helpers\TimeHelper;
$pageTitle = 'Administrator Console';
?>

<div class="container container-wide">
  <div class="page-header">
    <div class="page-header-title">
      <h1>Administrative Control Center</h1>
      <div class="page-header-subtitle">
        System Overview &bull; Institutional Election Governance &bull; Session Authenticated
      </div>
    </div>
    <div style="display: flex; gap: 0.75rem;">
      <a href="<?= View::url('/admin/users/create') ?>" class="btn btn-secondary btn-sm">+ Provision Account</a>
      <a href="<?= View::url('/admin/users/import') ?>" class="btn btn-secondary btn-sm">&uarr; Bulk Import CSV</a>
      <a href="<?= View::url('/admin/elections/create') ?>" class="btn btn-primary btn-sm">+ Create Election</a>
    </div>
  </div>

  <!-- Metric Statistics Cards -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">Total Collegiate Accounts</div>
      <div class="stat-value"><?= (int)$stats['total_users'] ?></div>
      <div style="font-size: 0.8rem; color: var(--color-text-muted); margin-top: 0.25rem;">
        <?= (int)$stats['student_count'] ?> Students &bull; <?= (int)$stats['staff_count'] ?> Staff
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Active Elections</div>
      <div class="stat-value" style="color: var(--color-emerald-green);"><?= (int)$stats['open_elections'] ?></div>
      <div style="font-size: 0.8rem; color: var(--color-text-muted); margin-top: 0.25rem;">
        <?= (int)$stats['total_elections'] ?> Total Ballots Configured
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total Ballots Cast</div>
      <div class="stat-value"><?= (int)$stats['total_votes_cast'] ?></div>
      <div style="font-size: 0.8rem; color: var(--color-text-muted); margin-top: 0.25rem;">
        Across all active and closed elections
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Audited Security Events</div>
      <div class="stat-value"><?= (int)$stats['audit_events_count'] ?></div>
      <div style="font-size: 0.8rem; color: var(--color-text-muted); margin-top: 0.25rem;">
        <a href="<?= View::url('/admin/audit') ?>">View Audit Ledger &rarr;</a>
      </div>
    </div>
  </div>

  <!-- Recent Collegiate Elections -->
  <div class="card">
    <div class="card-header">
      <h2>Collegiate Elections</h2>
      <a href="<?= View::url('/admin/elections') ?>" class="btn btn-secondary btn-sm">Manage All Elections</a>
    </div>

    <?php if (empty($recentElections)): ?>
      <p style="color: var(--color-text-muted); padding: 1rem 0;">No elections created yet. Click "Create Election" to initialize a position ballot.</p>
    <?php else: ?>
      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>Title &amp; Position</th>
              <th>Status</th>
              <th>Approved Candidates</th>
              <th>Eligible Voters</th>
              <th>Votes Cast</th>
              <th>Schedule Window</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentElections as $el): ?>
              <tr>
                <td>
                  <strong><?= Sanitizer::e($el['title']) ?></strong>
                  <div style="font-size: 0.8rem; color: var(--color-text-muted);"><?= Sanitizer::e($el['position']) ?></div>
                </td>
                <td>
                  <span class="badge badge-<?= Sanitizer::e($el['status']) ?>">
                    <?= ucfirst(str_replace('_', ' ', $el['status'])) ?>
                  </span>
                </td>
                <td><?= (int)$el['approved_candidates_count'] ?></td>
                <td><?= (int)$el['eligible_voters_count'] ?></td>
                <td><strong><?= (int)$el['total_votes_cast'] ?></strong></td>
                <td style="font-size: 0.825rem;">
                  <?= TimeHelper::format($el['opens_at'], 'd M, H:i') ?> &rarr; <?= TimeHelper::format($el['closes_at'], 'd M, H:i') ?>
                </td>
                <td>
                  <a href="<?= View::url('/admin/elections/detail?id=' . $el['id']) ?>" class="btn btn-secondary btn-sm">
                    Manage &rarr;
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
