<?php
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Helpers\TimeHelper;
$pageTitle = 'Security Audit Logs';

$distinctActions = $distinctActions ?? [];
$selectedAction = $selectedAction ?? '';
$search = $search ?? '';
?>

<div class="container container-wide">
  <div class="page-header">
    <div class="page-header-title">
      <h1>Security Audit Trail</h1>
      <div class="page-header-subtitle">
        Immutable institutional ledger of operational, administrative, and authentication actions with request correlation
      </div>
    </div>
  </div>

  <!-- Filter & Search Controls -->
  <div class="card card-ivory" style="padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;">
    <form action="<?= View::url('/admin/audit') ?>" method="GET" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
      <div style="flex: 1; min-width: 220px;">
        <label for="search" class="form-label" style="font-size: 0.85rem; margin-bottom: 0.25rem;">Search Ledger</label>
        <input 
          type="text" 
          id="search" 
          name="search" 
          class="form-control" 
          placeholder="Filter by Actor ID, Entity #, Correlation ID, or IP..." 
          value="<?= Sanitizer::e($search) ?>"
        >
      </div>

      <div style="min-width: 200px;">
        <label for="action" class="form-label" style="font-size: 0.85rem; margin-bottom: 0.25rem;">Filter by Event Action</label>
        <select id="action" name="action" class="form-control">
          <option value="">All Event Actions</option>
          <?php foreach ($distinctActions as $act): ?>
            <option value="<?= Sanitizer::e($act) ?>" <?= $selectedAction === $act ? 'selected' : '' ?>>
              <?= Sanitizer::e($act) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display: flex; gap: 0.5rem;">
        <button type="submit" class="btn btn-secondary">
          Apply Filter
        </button>
        <?php if (!empty($selectedAction) || !empty($search)): ?>
          <a href="<?= View::url('/admin/audit') ?>" class="btn btn-outline">
            Reset
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card" style="padding: 0; overflow: hidden;">
    <div class="table-container" style="border: none; margin: 0;">
      <table class="table">
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>Event Action</th>
            <th>Target Entity</th>
            <th>Actor Identity</th>
            <th>Client IP</th>
            <th>Trace / Correlation ID</th>
            <th>Sanitized Audit Details</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr>
              <td colspan="7" style="text-align: center; color: var(--color-text-muted); padding: 2.5rem;">
                No matching audit events recorded.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($logs as $log): ?>
              <?php
                $actionName = $log['action'];
                $isSecurityAlert = strpos($actionName, 'REJECT') !== false 
                                || strpos($actionName, 'FAIL') !== false 
                                || strpos($actionName, 'THROTTLED') !== false 
                                || strpos($actionName, 'MISMATCH') !== false
                                || strpos($actionName, 'UNAUTHORIZED') !== false;
                $isSuccess = strpos($actionName, 'ACCEPTED') !== false 
                          || strpos($actionName, 'SUCCESS') !== false 
                          || strpos($actionName, 'COMMITTED') !== false;
                
                $badgeStyle = 'background-color: var(--color-surface-muted); color: var(--color-text-primary);';
                if ($isSecurityAlert) {
                    $badgeStyle = 'background-color: var(--color-vermilion-subtle); color: var(--color-vermilion-dark); font-weight: 700;';
                } elseif ($isSuccess) {
                    $badgeStyle = 'background-color: var(--color-emerald-subtle); color: var(--color-emerald-dark); font-weight: 700;';
                }
              ?>
              <tr>
                <td style="font-size: 0.825rem; white-space: nowrap; font-family: var(--font-mono);">
                  <?= TimeHelper::format($log['created_at'], 'd M Y, H:i:s') ?>
                </td>
                <td>
                  <span class="badge" style="<?= $badgeStyle ?> font-family: var(--font-mono); font-size: 0.725rem;">
                    <?= Sanitizer::e($actionName) ?>
                  </span>
                </td>
                <td>
                  <span style="font-size: 0.85rem; font-weight: 600;">
                    <?= Sanitizer::e($log['entity_type']) ?>
                  </span>
                  <?php if (!empty($log['entity_id'])): ?>
                    <span style="font-size: 0.8rem; color: var(--color-text-muted);">#<?= Sanitizer::e($log['entity_id']) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!empty($log['identifier'])): ?>
                    <strong><?= Sanitizer::e($log['identifier']) ?></strong>
                    <div style="font-size: 0.75rem; color: var(--color-text-muted);"><?= Sanitizer::e($log['user_name'] ?? '') ?> (<?= Sanitizer::e($log['user_role'] ?? '') ?>)</div>
                  <?php else: ?>
                    <span style="color: var(--color-text-muted); font-size: 0.85rem;">System / Anon</span>
                  <?php endif; ?>
                </td>
                <td style="font-family: var(--font-mono); font-size: 0.8rem;">
                  <?= Sanitizer::e($log['ip_address'] ?? '127.0.0.1') ?>
                </td>
                <td style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--color-text-muted);">
                  <?= Sanitizer::e($log['correlation_id'] ?: '—') ?>
                </td>
                <td style="font-size: 0.8rem; font-family: var(--font-mono); color: var(--color-text-secondary); max-width: 320px; word-break: break-all;">
                  <?= Sanitizer::e($log['details_json'] ?? '—') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
