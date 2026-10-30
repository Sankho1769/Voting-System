<?php
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Helpers\TimeHelper;
$pageTitle = 'Security Audit Logs';
?>

<div class="container container-wide">
  <div class="page-header">
    <div class="page-header-title">
      <h1>Security Audit Trail</h1>
      <div class="page-header-subtitle">
        Immutable institutional ledger of operational, administrative, and authentication actions
      </div>
    </div>
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
            <th>Sanitized Audit Details</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr>
              <td colspan="6" style="text-align: center; color: var(--color-text-muted); padding: 2.5rem;">
                No audit events recorded.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($logs as $log): ?>
              <tr>
                <td style="font-size: 0.825rem; white-space: nowrap; font-family: var(--font-mono);">
                  <?= TimeHelper::format($log['created_at'], 'd M Y, H:i:s') ?>
                </td>
                <td>
                  <span class="badge" style="background-color: var(--color-surface-muted); color: var(--color-text-primary); font-family: var(--font-mono); font-size: 0.725rem;">
                    <?= Sanitizer::e($log['action']) ?>
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
                <td style="font-size: 0.8rem; font-family: var(--font-mono); color: var(--color-text-secondary); max-width: 350px; word-break: break-all;">
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
