<?php
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Helpers\TimeHelper;
$pageTitle = 'Administrator Console';
?>

<div class="container container-wide section">
  <div class="page-header" style="margin-bottom: 2rem;">
    <div>
      <span class="badge badge-purple" style="margin-bottom: 0.5rem;">CENTRAL GOVERNANCE</span>
      <h1 style="font-family: var(--font-display); font-size: 2.75rem; font-weight: 700; color: var(--text-primary); margin: 0 0 0.35rem 0;">
        Administrative Control Center
      </h1>
      <div style="color: var(--text-muted); font-size: 1rem;">
        System Overview &bull; Institutional Election Governance &bull; Session Authenticated
      </div>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
      <a href="<?= View::url('/admin/users/create') ?>" class="btn btn-secondary btn-sm">+ Provision Account</a>
      <a href="<?= View::url('/admin/users/import') ?>" class="btn btn-secondary btn-sm">&uarr; Bulk Import CSV</a>
      <a href="<?= View::url('/admin/elections/create') ?>" class="btn btn-primary btn-sm">+ Create Election</a>
    </div>
  </div>

  <!-- Metric Statistics Cards -->
  <div class="stats-grid" style="margin-bottom: 2.5rem;">
    <div class="stat-card">
      <div class="stat-label">Total Collegiate Accounts</div>
      <div class="stat-value" style="color: var(--text-primary);"><?= (int)$stats['total_users'] ?></div>
      <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.35rem;">
        <?= (int)$stats['student_count'] ?> Students &bull; <?= (int)$stats['staff_count'] ?> Staff
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Active Elections</div>
      <div class="stat-value" style="color: #16a34a;"><?= (int)$stats['open_elections'] ?></div>
      <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.35rem;">
        <?= (int)$stats['total_elections'] ?> Total Ballots Configured
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total Ballots Cast</div>
      <div class="stat-value" style="color: var(--brand-purple);"><?= (int)$stats['total_votes_cast'] ?></div>
      <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.35rem;">
        Across all active &amp; closed ballots
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Audited Security Events</div>
      <div class="stat-value" style="color: var(--vermilion);"><?= (int)$stats['audit_events_count'] ?></div>
      <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.35rem;">
        <a href="<?= View::url('/admin/audit') ?>" style="color: var(--brand-purple); font-weight: 600;">View Audit Trail &rarr;</a>
      </div>
    </div>
  </div>

  <!-- Recent Collegiate Elections Table -->
  <div class="card" style="padding: 2rem;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
      <h2 style="font-family: var(--font-display); font-size: 1.65rem; font-weight: 700; margin: 0; color: var(--text-primary);">Campus Elections Management</h2>
      <a href="<?= View::url('/admin/elections') ?>" class="btn btn-secondary btn-sm">Manage All Elections &rarr;</a>
    </div>

    <?php if (empty($recentElections)): ?>
      <p style="color: var(--text-muted); padding: 1.5rem 0; text-align: center;">No elections created yet. Click "Create Election" to initialize a position ballot.</p>
    <?php else: ?>
      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>Title &amp; Position</th>
              <th>Category</th>
              <th>Status</th>
              <th>Candidates</th>
              <th>Whitelist</th>
              <th>Ballots</th>
              <th>Schedule Window</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentElections as $el): ?>
              <tr>
                <td>
                  <strong style="color: var(--text-primary);"><?= Sanitizer::e($el['title']) ?></strong>
                  <div style="font-size: 0.8rem; color: var(--text-muted);"><?= Sanitizer::e($el['position']) ?></div>
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
                  <?= TimeHelper::format($el['opens_at'], 'd M, H:i') ?> &rarr; <?= TimeHelper::format($el['closes_at'], 'd M, H:i') ?>
                </td>
                <td>
                  <a href="<?= View::url('/admin/elections/detail?id=' . $el['id']) ?>" class="btn btn-secondary btn-sm" style="padding: 0.25rem 0.65rem; font-size: 0.8rem;">
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
