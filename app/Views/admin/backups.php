<?php
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Helpers\TimeHelper;

$pageTitle = 'Database Backups & Maintenance';
?>

<div class="container container-wide section">
  <div class="page-header" style="margin-bottom: 2rem;">
    <div>
      <span class="badge badge-purple" style="margin-bottom: 0.5rem;">SYSTEM MAINTENANCE</span>
      <h1 style="font-family: var(--font-display); font-size: 2.75rem; font-weight: 700; color: var(--text-primary); margin: 0 0 0.35rem 0;">
        Database Archives &amp; Snapshots
      </h1>
      <div style="color: var(--text-muted); font-size: 1rem;">
        Self-Contained Transactional Backups &bull; Automated DDL/DML Export &bull; Secure Storage
      </div>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
      <a href="<?= View::url('/admin') ?>" class="btn btn-secondary btn-sm">&larr; Back to Admin Console</a>
      <form action="<?= View::url('/admin/backups') ?>" method="POST" onsubmit="return confirm('Generate an immediate SQL backup snapshot of the primary database?');">
        <?= $csrfField ?>
        <button type="submit" class="btn btn-primary btn-sm">+ Create Backup Snapshot</button>
      </form>
    </div>
  </div>

  <!-- Metric Statistics -->
  <div class="stats-grid" style="margin-bottom: 2rem;">
    <div class="stat-card">
      <div class="stat-label">Available Backups</div>
      <div class="stat-value" style="color: var(--brand-purple);"><?= count($backups) ?></div>
      <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.35rem;">Stored in protected storage</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total Storage Used</div>
      <div class="stat-value" style="color: var(--text-primary);">
        <?php
          $totalBytes = array_sum(array_column($backups, 'size_bytes'));
          if ($totalBytes >= 1048576) {
              echo number_format($totalBytes / 1048576, 2) . ' MB';
          } elseif ($totalBytes >= 1024) {
              echo number_format($totalBytes / 1024, 1) . ' KB';
          } else {
              echo $totalBytes . ' B';
          }
        ?>
      </div>
      <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.35rem;">Compressed SQL archives</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Latest Snapshot</div>
      <div class="stat-value" style="font-size: 1.35rem; color: #16a34a; font-family: var(--font-mono);">
        <?= !empty($backups[0]['age']) ? Sanitizer::e($backups[0]['age']) : 'None' ?>
      </div>
      <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.35rem;">
        <?= !empty($backups[0]['created_at']) ? Sanitizer::e($backups[0]['created_at']) : 'No backups created yet' ?>
      </div>
    </div>
  </div>

  <!-- Backup Archive Table -->
  <div class="card" style="padding: 2rem;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
      <h2 style="font-family: var(--font-display); font-size: 1.65rem; font-weight: 700; margin: 0; color: var(--text-primary);">
        Stored Backup Files
      </h2>
      <span style="font-size: 0.85rem; color: var(--text-muted); font-family: var(--font-mono);">database/backups/</span>
    </div>

    <?php if (empty($backups)): ?>
      <div style="text-align: center; padding: 3rem 1rem;">
        <p style="color: var(--text-muted); margin-bottom: 1.5rem;">No database backup files found on disk.</p>
        <form action="<?= View::url('/admin/backups') ?>" method="POST">
          <?= $csrfField ?>
          <button type="submit" class="btn btn-primary">Generate Initial Backup Snapshot Now</button>
        </form>
      </div>
    <?php else: ?>
      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>Archive Filename</th>
              <th>Archive Size</th>
              <th>Generated At</th>
              <th>Age</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($backups as $b): ?>
              <tr>
                <td>
                  <strong style="color: var(--text-primary); font-family: var(--font-mono); font-size: 0.85rem;">
                    <?= Sanitizer::e($b['filename']) ?>
                  </strong>
                </td>
                <td>
                  <span class="badge badge-purple" style="font-family: var(--font-mono); font-size: 0.75rem;">
                    <?= Sanitizer::e($b['formatted_size']) ?>
                  </span>
                </td>
                <td style="font-size: 0.85rem; color: var(--text-muted);">
                  <?= Sanitizer::e($b['created_at']) ?>
                </td>
                <td style="font-size: 0.85rem; color: var(--text-muted);">
                  <?= Sanitizer::e($b['age']) ?>
                </td>
                <td style="text-align: right;">
                  <a href="<?= View::url('/admin/backups/download?file=' . urlencode($b['filename'])) ?>" class="btn btn-secondary btn-sm" style="padding: 0.25rem 0.65rem; font-size: 0.8rem;">
                    &darr; Download SQL
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Safety & Security Guidelines -->
  <div class="card" style="padding: 1.5rem; margin-top: 2rem; background-color: var(--surface-card); border-left: 4px solid var(--brand-purple);">
    <h4 style="font-family: var(--font-display); font-size: 1.15rem; margin-bottom: 0.5rem; color: var(--text-primary);">
      Backup &amp; Restoration Security Policy
    </h4>
    <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0; line-height: 1.6;">
      Database backups contain sensitive administrative audit logs and voter participation tokens. All backup archives in <code>database/backups/</code> are strictly protected against direct web HTTP requests by Apache <code>.htaccess</code> directives. Restoration can only be executed via the dedicated CLI recovery utility <code>bin/restore_db.php</code> with explicit administrator confirmation to prevent accidental primary database overwrites.
    </p>
  </div>
</div>
