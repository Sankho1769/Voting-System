<?php
use App\Core\Auth;
use App\Core\View;
use App\Helpers\Sanitizer;
$pageTitle = 'Update Password';
?>

<div class="container container-narrow">
  <div class="card" style="padding: 2.25rem; margin-top: 2rem;">
    <div style="margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--color-border-subtle);">
      <h2>Set Personal Password</h2>
      <p style="color: var(--color-text-secondary); font-size: 0.95rem;">
        <?php if (Auth::mustChangePassword()): ?>
          <span style="color: var(--color-vermilion); font-weight: 600;">Action Required:</span> You are currently signed in with a temporary institutional credential. You must establish a secure personal password before continuing.
        <?php else: ?>
          Update your institutional account password below.
        <?php endif; ?>
      </p>
    </div>

    <form action="<?= View::url('/change-password') ?>" method="POST" autocomplete="off">
      <?= $csrfField ?>

      <div class="form-group">
        <label for="current_password" class="form-label">
          Current Password / Temporary Credential <span class="required">*</span>
        </label>
        <input 
          type="password" 
          id="current_password" 
          name="current_password" 
          class="form-control" 
          required 
          autofocus
        >
      </div>

      <div class="form-group">
        <label for="new_password" class="form-label">
          New Personal Password <span class="required">*</span>
        </label>
        <input 
          type="password" 
          id="new_password" 
          name="new_password" 
          class="form-control" 
          required
        >
        <div class="form-help">Must be at least 8 characters and include both letters and numbers.</div>
      </div>

      <div class="form-group" style="margin-bottom: 1.75rem;">
        <label for="confirm_password" class="form-label">
          Confirm New Password <span class="required">*</span>
        </label>
        <input 
          type="password" 
          id="confirm_password" 
          name="confirm_password" 
          class="form-control" 
          required
        >
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg">
        Save Password &amp; Proceed
      </button>
    </form>
  </div>
</div>
