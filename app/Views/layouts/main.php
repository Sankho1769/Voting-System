<?php
use App\Core\Auth;
use App\Core\View;
use App\Helpers\Sanitizer;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="VerityVote - Closed Institutional College Voting System">
  <title><?= isset($pageTitle) ? Sanitizer::e($pageTitle) . ' — ' : '' ?>VerityVote</title>
  
  <link rel="stylesheet" href="<?= View::asset('css/style.css') ?>">
  <link rel="stylesheet" href="<?= View::asset('css/responsive.css') ?>">
</head>
<body>

  <!-- Masthead Header -->
  <header class="site-header">
    <div class="container site-header-inner">
      <a href="<?= View::url('/') ?>" class="brand" aria-label="VerityVote Home">
        <div class="brand-crest" aria-hidden="true">V</div>
        <div>
          <span class="brand-title">VerityVote</span>
          <span class="brand-tagline">Institutional College Portal</span>
        </div>
      </a>

      <?php if (Auth::isLoggedIn()): ?>
        <nav class="site-nav" aria-label="Main Navigation">
          <?php if (Auth::isAdmin()): ?>
            <a href="<?= View::url('/admin') ?>">Dashboard</a>
            <a href="<?= View::url('/admin/users') ?>">Accounts</a>
            <a href="<?= View::url('/admin/elections') ?>">Elections</a>
            <a href="<?= View::url('/admin/reports') ?>">Reports</a>
            <a href="<?= View::url('/admin/audit') ?>">Audit Logs</a>
          <?php elseif (Auth::isOfficer()): ?>
            <a href="<?= View::url('/officer') ?>">Dashboard</a>
            <a href="<?= View::url('/officer/elections') ?>">Elections</a>
          <?php else: ?>
            <a href="<?= View::url('/voter') ?>">Ballot Box</a>
            <?php if (Auth::isCandidate()): ?>
              <a href="<?= View::url('/candidate') ?>" style="color: var(--color-vermilion); font-weight: 600;">Candidate Portal</a>
            <?php endif; ?>
          <?php endif; ?>
        </nav>

        <div class="user-capsule">
          <span class="role-badge"><?= Sanitizer::e(Auth::role()) ?></span>
          <span class="user-name"><strong><?= Sanitizer::e(Auth::identifier()) ?></strong></span>
          <a href="<?= View::url('/change-password') ?>" title="Change Password" style="font-size: 0.8rem;">Security</a>
          <a href="<?= View::url('/logout') ?>" class="btn btn-secondary btn-sm" style="padding: 0.2rem 0.5rem; font-size: 0.8rem;">Sign Out</a>
        </div>
      <?php else: ?>
        <div class="site-nav" style="display: flex; gap: 0.75rem; align-items: center;">
          <a href="<?= View::url('/login/admin') ?>" style="font-size: 0.85rem; font-weight: 500;">Admin</a>
          <a href="<?= View::url('/login/candidate') ?>" style="font-size: 0.85rem; font-weight: 500;">Candidate</a>
          <a href="<?= View::url('/login/voter') ?>" class="btn btn-primary btn-sm">Voter Sign-In</a>
        </div>
      <?php endif; ?>
    </div>
  </header>

  <!-- Flash Messages -->
  <div class="container" style="margin-top: 1.5rem;">
    <?php if (!empty($flashes)): ?>
      <?php foreach ($flashes as $type => $messages): ?>
        <?php foreach ($messages as $msg): ?>
          <div class="alert alert-<?= $type === 'error' ? 'danger' : Sanitizer::e($type) ?>" role="alert">
            <div><?= Sanitizer::e($msg) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Page Main Content -->
  <main class="main-content">
    <?= $content ?? '' ?>
  </main>

  <!-- Footer -->
  <footer class="site-footer">
    <div class="container site-footer-inner">
      <div>
        <strong>VerityVote</strong> &bull; Institutional College Voting System &bull; BCA Academic Project
      </div>
      <div>
        <span>Built with PHP 8.2 &amp; MySQLi &bull; Inspectable via phpMyAdmin</span>
      </div>
    </div>
  </footer>

  <script src="<?= View::asset('js/app.js') ?>"></script>
  <?= $extraScripts ?? '' ?>
</body>
</html>
