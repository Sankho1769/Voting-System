<?php
use App\Core\View;
$pageTitle = 'Access Portals';
?>

<div class="container" style="max-width: 1100px; margin-top: 3rem; margin-bottom: 4rem;">
  <div style="text-align: center; margin-bottom: 3.5rem;">
    <div class="brand-crest" style="width: 3.5rem; height: 3.5rem; margin: 0 auto 1.25rem auto;">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
        <rect x="2" y="2" width="20" height="20" rx="5" stroke="currentColor" stroke-width="2.2"/>
        <polyline points="6 12 10 16 18 7" stroke="#D7FF36" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
    <span class="badge badge-purple" style="margin-bottom: 0.5rem;">CAMPUS AUTHENTICATION GATEWAY</span>
    <h1 style="font-family: var(--font-display); font-size: 3rem; font-weight: 700; color: var(--text-primary); margin: 0 0 0.75rem 0;">
      VerityVote Portals
    </h1>
    <p style="color: var(--text-muted); font-size: 1.15rem; max-width: 600px; margin: 0 auto;">
      Select your designated portal to authenticate into the institutional voting platform.
    </p>
  </div>

  <div class="portals-strip">
    <!-- 1. Voter Portal Card (Pale Lavender Surface per Reference 1) -->
    <a href="<?= View::url('/login/voter') ?>" class="portal-card portal-card-voter" aria-label="Access Voter Login Portal">
      <div class="portal-card-header">
        <div class="portal-icon-circle portal-icon-voter">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
            <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
          </svg>
        </div>
      </div>
      <div class="portal-card-body">
        <h2 class="portal-title">Voter</h2>
        <p class="portal-desc">Log in with your college ID and cast your vote securely.</p>
        <div style="margin-top: 1rem; font-size: 0.8rem; color: var(--text-muted);">
          Credentials: <strong>Roll No &amp; Password</strong>
        </div>
      </div>
      <div class="portal-card-footer">
        <div class="circle-arrow-btn" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"></line>
            <polyline points="12 5 19 12 12 19"></polyline>
          </svg>
        </div>
      </div>
    </a>

    <!-- 2. Candidate Portal Card (Pale Lime Surface per Reference 1) -->
    <a href="<?= View::url('/login/candidate') ?>" class="portal-card portal-card-candidate" aria-label="Access Candidate Login Portal">
      <div class="portal-card-header">
        <div class="portal-icon-circle portal-icon-candidate">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
            <path d="M21 3H3C1.9 3 1 3.9 1 5v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-1 16H4c-.55 0-1-.45-1-1V6c0-.55.45-1 1-1h16c.55 0 1 .45 1 1v12c0 .55-.45 1-1 1zM9 8h2v8H9zm4 0h2v8h-2zm-8 4h2v4H5zm12-2h2v6h-2z"/>
          </svg>
        </div>
      </div>
      <div class="portal-card-body">
        <h2 class="portal-title">Candidate</h2>
        <p class="portal-desc">Present your vision and connect with students.</p>
        <div style="margin-top: 1rem; font-size: 0.8rem; color: var(--text-muted);">
          Credentials: <strong>Candidate ID / Roll No &amp; Password</strong>
        </div>
      </div>
      <div class="portal-card-footer">
        <div class="circle-arrow-btn" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"></line>
            <polyline points="12 5 19 12 12 19"></polyline>
          </svg>
        </div>
      </div>
    </a>

    <!-- 3. Admin Portal Card (Pale Pink Surface per Reference 1) -->
    <a href="<?= View::url('/login/admin') ?>" class="portal-card portal-card-admin" aria-label="Access Administrator Login Portal">
      <div class="portal-card-header">
        <div class="portal-icon-circle portal-icon-admin">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
            <path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>
          </svg>
        </div>
      </div>
      <div class="portal-card-body">
        <h2 class="portal-title">Admin</h2>
        <p class="portal-desc">Manage elections and ensure a fair process.</p>
        <div style="margin-top: 1rem; font-size: 0.8rem; color: var(--text-muted);">
          Credentials: <strong>Admin ID &amp; Password</strong>
        </div>
      </div>
      <div class="portal-card-footer">
        <div class="circle-arrow-btn" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"></line>
            <polyline points="12 5 19 12 12 19"></polyline>
          </svg>
        </div>
      </div>
    </a>
  </div>

  <!-- Institutional Security Notice -->
  <div style="margin-top: 3.5rem; text-align: center; color: var(--text-muted); font-size: 0.85rem; line-height: 1.6;">
    <p>
      Public registration is strictly disabled. All accounts are authorized and provisioned by institutional administrators.<br>
      Dual-role accounts (e.g. eligible student voter who is also a candidate) use the same credentials for both portals.
    </p>
  </div>
</div>
