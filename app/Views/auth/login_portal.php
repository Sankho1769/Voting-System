<?php
use App\Core\View;
use App\Helpers\Sanitizer;

$portal = $portal ?? 'voter';

$meta = match ($portal) {
    'admin' => [
        'pageTitle' => 'Administrator Sign-In',
        'badge' => 'System Governance',
        'badgeClass' => 'badge-active',
        'headline' => 'Administrator Portal',
        'subtitle' => 'Institutional Governance & Election Commission Gateway',
        'idLabel' => 'Administrator ID',
        'idPlaceholder' => 'e.g. ADMIN01 or OFF-01',
        'idHelp' => 'Use your authorized system administrator ID or election officer credential.',
        'btnText' => 'Sign In to Administrator Console &rarr;',
        'btnClass' => 'btn-primary'
    ],
    'candidate' => [
        'pageTitle' => 'Candidate Sign-In',
        'badge' => 'Contender Platform',
        'badgeClass' => 'badge-scheduled',
        'headline' => 'Candidate Portal',
        'subtitle' => 'Campaign Management, Candidacy Review & Election Standings',
        'idLabel' => 'Candidate ID / College Identifier',
        'idPlaceholder' => 'e.g. CAND-01 or BCA2024010',
        'idHelp' => 'Enter your assigned Candidate ID (e.g. CAND-01) or your institutional roll number.',
        'btnText' => 'Sign In to Candidate Dashboard &rarr;',
        'btnClass' => 'btn-primary'
    ],
    default => [
        'pageTitle' => 'Voter Sign-In',
        'badge' => 'Democratic Participation',
        'badgeClass' => 'badge-open',
        'headline' => 'Collegiate Voter Portal',
        'subtitle' => 'Institutional Ballot Box & Electoral Participation',
        'idLabel' => 'Voter ID / Roll Number / Employee ID',
        'idPlaceholder' => 'e.g. BCA2024001 or EMP-101',
        'idHelp' => 'Students enter your college roll number; faculty and staff enter your Employee ID.',
        'btnText' => 'Sign In to Voter Portal &rarr;',
        'btnClass' => 'btn-primary'
    ]
};

$pageTitle = $meta['pageTitle'];
?>

<div class="container container-narrow" style="margin-top: 2rem; margin-bottom: 3rem;">
  <!-- Return Link to All Portals -->
  <div style="margin-bottom: 1.25rem;">
    <a href="<?= View::url('/login') ?>" style="text-decoration: none; font-size: 0.9rem; font-weight: 500; color: var(--color-text-secondary); display: inline-flex; align-items: center; gap: 0.35rem;">
      &larr; Return to All Access Portals
    </a>
  </div>

  <div class="card card-ivory" style="padding: 2.25rem;">
    <!-- Portal Header -->
    <div style="text-align: center; margin-bottom: 2rem;">
      <div style="margin-bottom: 0.75rem;">
        <span class="badge <?= $meta['badgeClass'] ?>" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; padding: 0.3rem 0.75rem;">
          <?= $meta['badge'] ?>
        </span>
      </div>
      <h1 style="font-size: 2rem; margin-bottom: 0.35rem;"><?= $meta['headline'] ?></h1>
      <p style="color: var(--color-text-secondary); font-size: 0.95rem; max-width: 420px; margin: 0 auto;">
        <?= $meta['subtitle'] ?>
      </p>
    </div>

    <!-- Login Form -->
    <form action="<?= View::url('/login/' . $portal) ?>" method="POST" autocomplete="off" id="portalLoginForm">
      <?= $csrfField ?>
      <input type="hidden" name="portal" value="<?= Sanitizer::e($portal) ?>">

      <div class="form-group">
        <label for="identifier" class="form-label">
          <?= $meta['idLabel'] ?> <span class="required">*</span>
        </label>
        <input 
          type="text" 
          id="identifier" 
          name="identifier" 
          class="form-control" 
          placeholder="<?= $meta['idPlaceholder'] ?>"
          required 
          autofocus
          value="<?= isset($identifier) ? Sanitizer::e($identifier) : '' ?>"
        >
        <div class="form-help"><?= $meta['idHelp'] ?></div>
      </div>

      <div class="form-group" style="margin-bottom: 1.75rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
          <label for="password" class="form-label" style="margin-bottom: 0;">
            Password <span class="required">*</span>
          </label>
          <button type="button" id="togglePasswordBtn" style="background: none; border: none; color: var(--color-emerald-green); font-size: 0.825rem; font-weight: 600; cursor: pointer; padding: 0;" aria-label="Toggle password visibility">
            Show Password
          </button>
        </div>
        <div style="position: relative;">
          <input 
            type="password" 
            id="password" 
            name="password" 
            class="form-control" 
            placeholder="Enter your confidential account password"
            required
          >
        </div>
      </div>

      <button type="submit" class="btn <?= $meta['btnClass'] ?> btn-block btn-lg" id="submitBtn" style="margin-bottom: 1.5rem;">
        <?= $meta['btnText'] ?>
      </button>

      <!-- Role Mismatch & Security Notice -->
      <div style="background-color: #FFFFFF; border: 1px solid var(--color-border-subtle); border-radius: var(--radius-sm); padding: 1rem; font-size: 0.825rem; color: var(--color-text-muted); line-height: 1.45; margin-bottom: 1.5rem;">
        <strong>Security &amp; Authorization Notice:</strong> This portal authenticates only authorized <strong><?= $meta['headline'] ?></strong> credentials. Role mismatches are rejected by server-side gatekeepers and recorded in audit logs.
      </div>
    </form>

    <!-- Portal Switcher Links -->
    <div style="border-top: 1px solid var(--color-border-medium); padding-top: 1.25rem; text-align: center; font-size: 0.85rem; color: var(--color-text-secondary);">
      <span style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Switch Portal:</span>
      <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
        <?php if ($portal !== 'voter'): ?>
          <a href="<?= View::url('/login/voter') ?>" style="color: var(--color-emerald-green); font-weight: 600; text-decoration: none;">&bull; Voter Portal</a>
        <?php endif; ?>
        <?php if ($portal !== 'candidate'): ?>
          <a href="<?= View::url('/login/candidate') ?>" style="color: var(--color-vermilion); font-weight: 600; text-decoration: none;">&bull; Candidate Portal</a>
        <?php endif; ?>
        <?php if ($portal !== 'admin'): ?>
          <a href="<?= View::url('/login/admin') ?>" style="color: var(--color-emerald-dark); font-weight: 600; text-decoration: none;">&bull; Administrator Portal</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const toggleBtn = document.getElementById('togglePasswordBtn');
  const passwordInput = document.getElementById('password');
  const form = document.getElementById('portalLoginForm');
  const submitBtn = document.getElementById('submitBtn');

  if (toggleBtn && passwordInput) {
    toggleBtn.addEventListener('click', function () {
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleBtn.textContent = 'Hide Password';
      } else {
        passwordInput.type = 'password';
        toggleBtn.textContent = 'Show Password';
      }
    });
  }

  if (form && submitBtn) {
    form.addEventListener('submit', function () {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Authenticating Securely...';
    });
  }
});
</script>
