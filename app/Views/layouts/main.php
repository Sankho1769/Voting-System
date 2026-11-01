<?php
use App\Core\Auth;
use App\Core\View;
use App\Helpers\Sanitizer;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="VerityVote - Closed Institutional College Voting Platform. Secure, auditable, and transparent campus elections.">
  <meta name="theme-color" content="#7026C9">
  <title><?= isset($pageTitle) ? Sanitizer::e($pageTitle) . ' — ' : '' ?>VerityVote</title>

  <!-- Google Fonts: Editorial Serif + Modern Clean Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@400;500;600&family=Playfair+Display:ital,wght@0,600;0,700;0,800;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?= View::asset('css/style.css') ?>">
  <link rel="stylesheet" href="<?= View::asset('css/responsive.css') ?>">
</head>
<body>

  <!-- Masthead Header (Reference 1 Visuals) -->
  <header class="site-header">
    <div class="container site-header-inner">
      <!-- VerityVote Brand Crest & Wordmark -->
      <a href="<?= View::url('/') ?>" class="brand" aria-label="VerityVote Home">
        <div class="brand-crest" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
            <rect x="2" y="2" width="20" height="20" rx="5" stroke="currentColor" stroke-width="2.2"/>
            <polyline points="6 12 10 16 18 7" stroke="#D7FF36" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <div class="brand-text">
          <span class="brand-title">Verity<span class="brand-title-accent">Vote</span></span>
        </div>
      </a>

      <!-- Desktop Links (Reference 1: How it works, Candidates, Help + CricHeroes: Explore) -->
      <nav class="site-nav nav-desktop-links" aria-label="Main Navigation">
        <a href="<?= View::url('/explore') ?>" class="nav-link <?= (isset($activeNav) && $activeNav === 'explore') ? 'active' : '' ?>">Explore Elections</a>
        <a href="<?= View::url('/how-it-works') ?>" class="nav-link <?= (isset($activeNav) && $activeNav === 'how-it-works') ? 'active' : '' ?>">How it works</a>
        <a href="<?= View::url('/candidates') ?>" class="nav-link <?= (isset($activeNav) && $activeNav === 'candidates') ? 'active' : '' ?>">Candidates</a>
        <a href="<?= View::url('/help') ?>" class="nav-link <?= (isset($activeNav) && $activeNav === 'help') ? 'active' : '' ?>">Help</a>
      </nav>

      <!-- Right Header Actions: Theme Switcher & Auth State -->
      <div class="header-actions">
        <!-- Theme Toggle Button -->
        <button type="button" class="theme-toggle-btn" data-action="toggle-theme" aria-label="Toggle theme">
          <span class="theme-toggle-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
            </svg>
          </span>
        </button>

        <?php if (Auth::isLoggedIn()): ?>
          <!-- Logged In User Capsule -->
          <div class="user-capsule">
            <span class="role-badge"><?= Sanitizer::e(ucfirst(Auth::role())) ?></span>
            <span class="user-name" title="Logged in as <?= Sanitizer::e(Auth::identifier()) ?>"><?= Sanitizer::e(Auth::identifier()) ?></span>
            
            <div class="dropdown" style="position: relative; display: inline-block;">
              <button type="button" class="btn btn-secondary btn-sm" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="padding: 0.35rem 0.6rem; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.25rem;">
                <span>Portal</span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
              </button>
              <div class="dropdown-menu" style="right: 0; min-width: 170px;">
                <?php if (Auth::isAdmin()): ?>
                  <a href="<?= View::url('/admin') ?>" class="dropdown-item">Admin Dashboard</a>
                  <a href="<?= View::url('/admin/elections') ?>" class="dropdown-item">Manage Elections</a>
                  <a href="<?= View::url('/admin/users') ?>" class="dropdown-item">Manage Accounts</a>
                  <a href="<?= View::url('/admin/reports') ?>" class="dropdown-item">Election Reports</a>
                  <a href="<?= View::url('/admin/audit') ?>" class="dropdown-item">Audit Trail</a>
                <?php elseif (Auth::isOfficer()): ?>
                  <a href="<?= View::url('/officer') ?>" class="dropdown-item">Officer Dashboard</a>
                  <a href="<?= View::url('/officer/elections') ?>" class="dropdown-item">Elections</a>
                <?php else: ?>
                  <a href="<?= View::url('/voter') ?>" class="dropdown-item">Ballot Box</a>
                  <?php if (Auth::isCandidate()): ?>
                    <a href="<?= View::url('/candidate') ?>" class="dropdown-item" style="color: var(--vermilion); font-weight: 700;">Candidate Desk</a>
                  <?php endif; ?>
                <?php endif; ?>
                <div style="border-top: 1px solid var(--border-subtle); margin: 0.25rem 0;"></div>
                <a href="<?= View::url('/change-password') ?>" class="dropdown-item">Security / Password</a>
                <a href="<?= View::url('/logout') ?>" class="dropdown-item text-danger">Sign Out</a>
              </div>
            </div>
          </div>
        <?php else: ?>
          <!-- Logged Out Sign In Action (Reference 1 Neon Lime Pill with Dropdown) -->
          <div class="dropdown" style="position: relative; display: inline-block;">
            <button type="button" class="btn btn-lime-pill" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <span>Sign in</span>
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
              </svg>
            </button>
            <div class="dropdown-menu" style="right: 0; min-width: 200px;">
              <a href="<?= View::url('/login/voter') ?>" class="dropdown-item" style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="width: 8px; height: 8px; border-radius: 50%; background-color: var(--brand-purple);"></span>
                <span>Voter Portal</span>
              </a>
              <a href="<?= View::url('/login/candidate') ?>" class="dropdown-item" style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="width: 8px; height: 8px; border-radius: 50%; background-color: var(--accent-lime); border: 1px solid #7026C9;"></span>
                <span>Candidate Portal</span>
              </a>
              <a href="<?= View::url('/login/admin') ?>" class="dropdown-item" style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="width: 8px; height: 8px; border-radius: 50%; background-color: var(--vermilion);"></span>
                <span>Administrator Portal</span>
              </a>
              <div style="border-top: 1px solid var(--border-subtle); margin: 0.35rem 0;"></div>
              <a href="<?= View::url('/login') ?>" class="dropdown-item" style="font-size: 0.8rem; color: var(--text-muted);">View All Portals</a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <!-- Flash Messages Banner -->
  <?php if (!empty($flashes)): ?>
    <div class="container" style="margin-top: 1.25rem;">
      <?php foreach ($flashes as $type => $messages): ?>
        <?php foreach ($messages as $msg): ?>
          <div class="alert alert-<?= $type === 'error' ? 'danger' : Sanitizer::e($type) ?> alert-dismissible" role="alert">
            <div><?= Sanitizer::e($msg) ?></div>
            <button type="button" class="alert-close" aria-label="Dismiss">&times;</button>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Page Main Content Body -->
  <main class="main-content">
    <?= $content ?? '' ?>
  </main>

  <!-- VerityVote Editorial Footer -->
  <footer class="site-footer">
    <div class="container site-footer-inner">
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <div class="brand-crest" style="width: 24px; height: 24px;" aria-hidden="true">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
            <polyline points="6 12 10 16 18 7" stroke="#D7FF36" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <div>
          <strong style="color: var(--text-primary); font-family: var(--font-display); font-size: 1.1rem;">VerityVote</strong>
          <span style="color: var(--text-muted); font-size: 0.85rem;"> &bull; Institutional College Voting System &bull; BCA Academic Project</span>
        </div>
      </div>
      <div style="font-size: 0.82rem; color: var(--text-muted);">
        <span>Built with PHP 8.2 &amp; MySQLi &bull; MariaDB Inspectable via phpMyAdmin</span>
      </div>
    </div>
  </footer>

  <!-- CricHeroes-Style Mobile Bottom Navigation Bar -->
  <nav class="mobile-bottom-bar" aria-label="Mobile Navigation">
    <a href="<?= View::url('/') ?>" class="mobile-nav-item <?= (!isset($activeNav) || $activeNav === 'home') ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span>Home</span>
    </a>
    <a href="<?= View::url('/explore') ?>" class="mobile-nav-item <?= (isset($activeNav) && $activeNav === 'explore') ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
      <span>Elections</span>
    </a>
    <a href="<?= View::url('/candidates') ?>" class="mobile-nav-item <?= (isset($activeNav) && $activeNav === 'candidates') ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span>Candidates</span>
    </a>
    <?php if (Auth::isLoggedIn()): ?>
      <a href="<?= Auth::isAdmin() ? View::url('/admin') : (Auth::isCandidate() ? View::url('/candidate') : View::url('/voter')) ?>" class="mobile-nav-item <?= (isset($activeNav) && in_array($activeNav, ['portal', 'admin', 'voter', 'candidate'])) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span>Portal</span>
      </a>
    <?php else: ?>
      <a href="<?= View::url('/login') ?>" class="mobile-nav-item <?= (isset($activeNav) && $activeNav === 'login') ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        <span>Sign In</span>
      </a>
    <?php endif; ?>
    <button type="button" class="mobile-nav-item" data-action="toggle-theme" style="background: none; border: none; cursor: pointer;">
      <svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      <span>Theme</span>
    </button>
  </nav>

  <script src="<?= View::asset('js/app.js') ?>"></script>
  <?= $extraScripts ?? '' ?>
</body>
</html>
