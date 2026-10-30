<?php
use App\Core\View;
use App\Helpers\Sanitizer;
$pageTitle = 'Sign In';
?>

<div class="container container-narrow">
  <div class="card card-ivory" style="padding: 2.25rem; margin-top: 2rem;">
    <div style="text-align: center; margin-bottom: 2rem;">
      <div class="brand-crest" style="width: 3.25rem; height: 3.25rem; font-size: 1.75rem; margin: 0 auto 1rem auto;">V</div>
      <h1 style="font-size: 1.95rem; margin-bottom: 0.35rem;">VerityVote Portal</h1>
      <p style="color: var(--color-text-secondary); font-size: 0.95rem;">
        Official Institutional Authentication
      </p>
    </div>

    <form action="<?= View::url('/login') ?>" method="POST" autocomplete="off">
      <?= $csrfField ?>

      <div class="form-group">
        <label for="identifier" class="form-label">
          College Roll Number / Employee ID <span class="required">*</span>
        </label>
        <input 
          type="text" 
          id="identifier" 
          name="identifier" 
          class="form-control" 
          placeholder="e.g. BCA2024-001 or EMP-104"
          required 
          autofocus
          value="<?= isset($identifier) ? Sanitizer::e($identifier) : '' ?>"
        >
        <div class="form-help">Students use college roll number; staff and administrators use institutional ID.</div>
      </div>

      <div class="form-group" style="margin-bottom: 1.75rem;">
        <label for="password" class="form-label">
          Password <span class="required">*</span>
        </label>
        <input 
          type="password" 
          id="password" 
          name="password" 
          class="form-control" 
          placeholder="Enter your confidential password"
          required
        >
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-bottom: 1.5rem;">
        Authenticate &amp; Continue
      </button>

      <div style="background-color: #FFFFFF; border: 1px solid var(--color-border-subtle); border-radius: var(--radius-sm); padding: 1rem; font-size: 0.825rem; color: var(--color-text-muted); line-height: 1.45;">
        <strong>Access Policy Notice:</strong> Public self-registration is disabled. All voting accounts are created and authenticated exclusively by authorized college administrators. If you do not possess temporary credentials, contact your department office.
      </div>
    </form>
  </div>
</div>
