<?php
use App\Core\View;
use App\Helpers\Sanitizer;
$pageTitle = 'Bulk CSV Import & Verification';
?>

<div class="container container-wide">
  <div style="margin-bottom: 1.5rem;">
    <a href="<?= View::url('/admin/users') ?>" style="text-decoration: none; font-size: 0.9rem; font-weight: 500;">
      &larr; Back to Authorized Accounts
    </a>
  </div>

  <?php if (empty($preview) && empty($importResult)): ?>
    <div class="card card-ivory" style="padding: 2.25rem; margin-bottom: 2rem;">
      <div style="margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--color-border-medium); display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
        <div>
          <h2>Official College Voter Registration (CSV Import)</h2>
          <p style="color: var(--color-text-secondary); font-size: 0.95rem; margin-bottom: 0;">
            Upload college-approved student and staff registers. VerityVote performs multi-step verification, detecting duplicate identities and schema violations prior to commit.
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
        <input type="hidden" name="action" value="preview">

        <div class="form-group" style="max-width: 520px; margin-bottom: 1.5rem;">
          <label for="csv_file" class="form-label">
            Select Official CSV Register <span class="required">*</span>
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
            Required CSV columns: <code>identifier (or institutional_id), role (or account_category), full_name, email, department, academic_year</code>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg">
          Upload &amp; Preview Verification &rarr;
        </button>
      </form>
    </div>
  <?php endif; ?>

  <?php if (!empty($preview)): ?>
    <!-- Multi-Step Preview & Verification Surface -->
    <div class="card card-ivory" style="padding: 2.25rem; margin-bottom: 2rem; border-top: 4px solid var(--color-emerald-green);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--color-border-subtle); padding-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
        <div>
          <span class="badge badge-active" style="text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em;">Step 2: Pre-Commit Verification</span>
          <h2 style="margin-top: 0.35rem; margin-bottom: 0.25rem;">Voter Register Verification Preview</h2>
          <p style="color: var(--color-text-secondary); font-size: 0.9rem; margin: 0;">
            Review accepted records, duplicate collision alerts, and invalid rows before committing changes to the database.
          </p>
        </div>
        <div style="display: flex; gap: 0.75rem; align-items: center;">
          <?php if (!empty($preview['issues'])): ?>
            <a href="<?= View::url('/admin/users/import-errors?token=' . urlencode($preview['import_token'])) ?>" class="btn btn-secondary btn-sm" style="border-color: var(--color-vermilion); color: var(--color-vermilion);">
              &darr; Download Error Report (CSV)
            </a>
          <?php endif; ?>
          <a href="<?= View::url('/admin/users/import') ?>" class="btn btn-outline btn-sm">
            Cancel &amp; Upload Different File
          </a>
        </div>
      </div>

      <!-- Preview Metrics -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
        <div style="background-color: var(--color-emerald-subtle); border-left: 4px solid var(--color-emerald-green); padding: 1rem 1.25rem; border-radius: var(--radius-sm);">
          <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--color-emerald-dark);">Ready to Provision</div>
          <div style="font-size: 2rem; font-weight: 700; color: var(--color-emerald-dark); line-height: 1.2;"><?= (int)$preview['accepted_count'] ?></div>
          <div style="font-size: 0.8rem; color: var(--color-text-secondary);">Valid, unique identities</div>
        </div>

        <div style="background-color: #FEF3C7; border-left: 4px solid #D97706; padding: 1rem 1.25rem; border-radius: var(--radius-sm);">
          <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #92400E;">In-File Duplicates</div>
          <div style="font-size: 2rem; font-weight: 700; color: #92400E; line-height: 1.2;"><?= (int)$preview['duplicate_file_count'] ?></div>
          <div style="font-size: 0.8rem; color: var(--color-text-secondary);">Repeated in CSV</div>
        </div>

        <div style="background-color: #FEE2E2; border-left: 4px solid var(--color-vermilion); padding: 1rem 1.25rem; border-radius: var(--radius-sm);">
          <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--color-vermilion-dark);">Database Duplicates</div>
          <div style="font-size: 2rem; font-weight: 700; color: var(--color-vermilion-dark); line-height: 1.2;"><?= (int)$preview['duplicate_db_count'] ?></div>
          <div style="font-size: 0.8rem; color: var(--color-text-secondary);">Already in system</div>
        </div>

        <div style="background-color: var(--color-surface-muted); border-left: 4px solid var(--color-text-muted); padding: 1rem 1.25rem; border-radius: var(--radius-sm);">
          <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--color-text-primary);">Malformed / Invalid</div>
          <div style="font-size: 2rem; font-weight: 700; color: var(--color-text-primary); line-height: 1.2;"><?= (int)$preview['invalid_count'] ?></div>
          <div style="font-size: 0.8rem; color: var(--color-text-secondary);">Format/field errors</div>
        </div>
      </div>

      <!-- Preview Tables -->
      <?php if (!empty($preview['accepted'])): ?>
        <h3 style="font-size: 1.15rem; margin-bottom: 0.75rem; color: var(--color-emerald-dark); display: flex; align-items: center; gap: 0.5rem;">
          <span>&check;</span> Accepted Records Awaiting Commitment (<?= count($preview['accepted']) ?>)
        </h3>
        <div class="table-container" style="margin-bottom: 2rem; max-height: 280px; overflow-y: auto;">
          <table class="table">
            <thead>
              <tr>
                <th>CSV Row</th>
                <th>Canonical ID</th>
                <th>Full Name</th>
                <th>Role</th>
                <th>Department</th>
                <th>Academic Year</th>
                <th>Validation Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($preview['accepted'] as $row): ?>
                <tr>
                  <td>#<?= (int)$row['row'] ?></td>
                  <td><strong><?= Sanitizer::e($row['identifier']) ?></strong></td>
                  <td><?= Sanitizer::e($row['full_name']) ?></td>
                  <td><span class="badge" style="background-color: var(--color-surface-muted); font-size: 0.75rem;"><?= Sanitizer::e($row['role']) ?></span></td>
                  <td><?= Sanitizer::e($row['department'] ?: '—') ?></td>
                  <td><?= Sanitizer::e($row['academic_year'] ?: '—') ?></td>
                  <td><span class="badge badge-active" style="font-size: 0.725rem;">Ready to Commit</span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if (!empty($preview['issues'])): ?>
        <h3 style="font-size: 1.15rem; margin-bottom: 0.75rem; color: var(--color-vermilion); display: flex; align-items: center; gap: 0.5rem;">
          <span>&times;</span> Rejected / Skipped Rows (<?= count($preview['issues']) ?>)
        </h3>
        <div class="table-container" style="margin-bottom: 2rem; max-height: 240px; overflow-y: auto;">
          <table class="table">
            <thead>
              <tr>
                <th>CSV Row</th>
                <th>Identifier in File</th>
                <th>Full Name</th>
                <th>Issue Category</th>
                <th>Specific Rejection Reason</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($preview['issues'] as $issue): ?>
                <tr>
                  <td>#<?= (int)$issue['row'] ?></td>
                  <td><code><?= Sanitizer::e($issue['identifier'] ?: '—') ?></code></td>
                  <td><?= Sanitizer::e($issue['full_name'] ?: '—') ?></td>
                  <td>
                    <span class="badge" style="background-color: var(--color-vermilion-subtle); color: var(--color-vermilion-dark); font-size: 0.725rem;">
                      <?= Sanitizer::e($issue['category']) ?>
                    </span>
                  </td>
                  <td style="color: var(--color-vermilion); font-size: 0.825rem; font-weight: 500;">
                    <?= Sanitizer::e($issue['details']) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <!-- Confirmation Bar -->
      <div style="background-color: #FFFFFF; border: 1px solid var(--color-border-medium); border-radius: var(--radius-sm); padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
          <strong>Administrator Confirmation Required:</strong>
          <div style="font-size: 0.85rem; color: var(--color-text-secondary); margin-top: 0.25rem;">
            Only the <?= (int)$preview['accepted_count'] ?> validated records will be provisioned. All duplicates and invalid rows are safely discarded.
          </div>
        </div>

        <form action="<?= View::url('/admin/users/import') ?>" method="POST" style="margin: 0;">
          <?= $csrfField ?>
          <input type="hidden" name="action" value="commit">
          <input type="hidden" name="import_token" value="<?= Sanitizer::e($preview['import_token']) ?>">

          <div style="display: flex; gap: 0.75rem;">
            <a href="<?= View::url('/admin/users/import') ?>" class="btn btn-secondary">
              Discard Preview
            </a>
            <?php if ($preview['accepted_count'] > 0): ?>
              <button type="submit" class="btn btn-primary btn-lg">
                &check; Confirm &amp; Provision <?= (int)$preview['accepted_count'] ?> Accounts
              </button>
            <?php else: ?>
              <button type="button" class="btn btn-primary btn-lg" disabled title="No valid records to provision">
                No Valid Records
              </button>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($importResult)): ?>
    <!-- Post-Commit Credential Manifest -->
    <div class="card card-ivory" style="padding: 2.25rem; border-top: 4px solid var(--color-emerald-green);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--color-border-subtle); padding-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
        <div>
          <span class="badge badge-active" style="text-transform: uppercase; font-size: 0.75rem;">Step 3: Provisioning Complete</span>
          <h2 style="margin-top: 0.35rem; margin-bottom: 0.25rem;">Provisioned Voter Credentials</h2>
          <p style="color: var(--color-text-secondary); font-size: 0.9rem; margin: 0;">
            Successfully created <?= (int)$importResult['imported'] ?> institutional voter accounts. Temporary credentials require a mandatory password update upon first sign-in.
          </p>
        </div>
        <a href="<?= View::url('/admin/users/import') ?>" class="btn btn-secondary btn-sm">
          &plus; Import Another File
        </a>
      </div>

      <div style="display: flex; gap: 1.5rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
        <div style="background-color: var(--color-emerald-subtle); padding: 0.85rem 1.25rem; border-radius: var(--radius-sm);">
          <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--color-emerald-dark); font-weight: 700;">Provisioned</span>
          <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-emerald-dark);"><?= (int)$importResult['imported'] ?></div>
        </div>
        <div style="background-color: var(--color-surface-muted); padding: 0.85rem 1.25rem; border-radius: var(--radius-sm);">
          <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--color-text-secondary); font-weight: 700;">Skipped / Excluded</span>
          <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-text-secondary);"><?= (int)$importResult['skipped'] ?></div>
        </div>
      </div>

      <?php if (!empty($importResult['credentials'])): ?>
        <div style="margin-top: 1.5rem;">
          <p style="font-size: 0.85rem; color: var(--color-vermilion); font-weight: 600;">
            Important Security Notice: Temporary passwords are shown only once upon generation. Distribute these credentials securely to students and faculty.
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
                  <th>Expiry Window</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($importResult['credentials'] as $cred): ?>
                  <tr>
                    <td><strong><?= Sanitizer::e($cred['identifier']) ?></strong></td>
                    <td><?= Sanitizer::e($cred['full_name']) ?></td>
                    <td><span class="badge" style="background-color: var(--color-surface-muted); font-size: 0.75rem;"><?= Sanitizer::e($cred['role']) ?></span></td>
                    <td><?= Sanitizer::e($cred['department'] ?: '—') ?></td>
                    <td><code style="font-size: 0.95rem; font-weight: 700; color: var(--color-emerald-green); background: #E8F5E9; padding: 0.2rem 0.5rem; border-radius: var(--radius-sm);"><?= Sanitizer::e($cred['temp_password']) ?></code></td>
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
