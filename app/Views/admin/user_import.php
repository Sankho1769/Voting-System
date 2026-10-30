<?php
use App\Core\View;
use App\Helpers\Sanitizer;
$pageTitle = 'Bulk CSV Import';
?>

<div class="container container-wide">
  <div style="margin-bottom: 1.5rem;">
    <a href="<?= View::url('/admin/users') ?>" style="text-decoration: none; font-size: 0.9rem; font-weight: 500;">
      &larr; Back to Authorized Accounts
    </a>
  </div>

  <div class="card card-ivory" style="padding: 2.25rem; margin-bottom: 2rem;">
    <div style="margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--color-border-medium); display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
      <div>
        <h2>Bulk Import Students &amp; Staff via CSV</h2>
        <p style="color: var(--color-text-secondary); font-size: 0.95rem; margin-bottom: 0;">
          Upload official college enrollment registers in standard CSV format.
        </p>
      </div>
      <div>
        <a href="<?= View::url('/admin/users/template') ?>" class="btn btn-secondary btn-sm">
          &darr; Download Sample CSV Template
        </a>
      </div>
    </div>

    <form action="<?= View::url('/admin/users/import') ?>" method="POST" enctype="multipart/form-data">
      <?= $csrfField ?>

      <div class="form-group" style="max-width: 500px; margin-bottom: 1.5rem;">
        <label for="csv_file" class="form-label">
          Select CSV File <span class="required">*</span>
        </label>
        <input 
          type="file" 
          id="csv_file" 
          name="csv_file" 
          class="form-control" 
          accept=".csv,text/csv" 
          required
        >
        <div class="form-help">
          Expected CSV header columns: <code>identifier, role, full_name, email, department, academic_year</code>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-lg">
        Upload &amp; Provision Accounts
      </button>
    </form>
  </div>

  <?php if (!empty($importResult)): ?>
    <div class="card" style="padding: 2rem;">
      <h3>Import Summary</h3>
      <div style="display: flex; gap: 2rem; margin: 1rem 0 1.5rem 0;">
        <div style="background-color: var(--color-emerald-subtle); padding: 1rem 1.5rem; border-radius: var(--radius-sm);">
          <span style="font-size: 0.8rem; text-transform: uppercase; color: var(--color-emerald-dark); font-weight: 700;">Successfully Provisioned</span>
          <div style="font-size: 2rem; font-weight: 700; color: var(--color-emerald-dark);"><?= (int)$importResult['imported'] ?></div>
        </div>
        <div style="background-color: var(--color-vermilion-subtle); padding: 1rem 1.5rem; border-radius: var(--radius-sm);">
          <span style="font-size: 0.8rem; text-transform: uppercase; color: var(--color-vermilion-dark); font-weight: 700;">Skipped / Existing</span>
          <div style="font-size: 2rem; font-weight: 700; color: var(--color-vermilion-dark);"><?= (int)$importResult['skipped'] ?></div>
        </div>
      </div>

      <?php if (!empty($importResult['errors'])): ?>
        <div class="alert alert-warning">
          <div>
            <strong>Import Notice / Warnings:</strong>
            <ul style="margin-top: 0.5rem; padding-left: 1.25rem; font-size: 0.85rem;">
              <?php foreach ($importResult['errors'] as $err): ?>
                <li><?= Sanitizer::e($err) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($importResult['credentials'])): ?>
        <div style="margin-top: 1.5rem;">
          <h4>Issued Temporary Credentials</h4>
          <p style="font-size: 0.85rem; color: var(--color-vermilion); font-weight: 600;">
            Important: These temporary passwords are shown only once upon generation. Copy or securely distribute them to students/staff now.
          </p>
          <div class="table-container">
            <table class="table">
              <thead>
                <tr>
                  <th>Roll / Employee ID</th>
                  <th>Full Name</th>
                  <th>Role</th>
                  <th>Department</th>
                  <th>Temporary Password</th>
                  <th>Expires</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($importResult['credentials'] as $cred): ?>
                  <tr>
                    <td><strong><?= Sanitizer::e($cred['identifier']) ?></strong></td>
                    <td><?= Sanitizer::e($cred['full_name']) ?></td>
                    <td><?= Sanitizer::e($cred['role']) ?></td>
                    <td><?= Sanitizer::e($cred['department']) ?></td>
                    <td><code style="font-size: 1rem; font-weight: 700; color: var(--color-emerald-green);"><?= Sanitizer::e($cred['temp_password']) ?></code></td>
                    <td style="font-size: 0.8rem; color: var(--color-text-muted);"><?= Sanitizer::e($cred['expires_at']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
