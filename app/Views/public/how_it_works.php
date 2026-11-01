<?php
use App\Core\Auth;
use App\Core\View;
use App\Helpers\Sanitizer;
?>

<div class="container section">

  <!-- Header -->
  <div style="max-width: 720px; margin: 0 auto 3.5rem auto; text-align: center;">
    <span class="badge badge-purple" style="margin-bottom: 0.5rem;">STUDENT GUIDE</span>
    <h1 style="font-family: var(--font-display); font-size: 3rem; font-weight: 700; color: var(--text-primary); margin: 0 0 0.75rem 0;">
      How VerityVote Works
    </h1>
    <p style="color: var(--text-muted); font-size: 1.15rem; line-height: 1.6;">
      VerityVote is an institutional college voting platform designed to safeguard electoral integrity, ballot privacy, and equal student representation.
    </p>
  </div>

  <!-- Detailed 4-Step Breakdown -->
  <div style="max-width: 840px; margin: 0 auto; display: flex; flex-direction: column; gap: 2.5rem;">

    <!-- Step 1 -->
    <div class="card" style="padding: 2.25rem; display: flex; gap: 1.75rem; align-items: flex-start;">
      <div style="width: 52px; height: 52px; border-radius: 14px; background: var(--surface-lavender); display: flex; align-items: center; justify-content: center; font-family: var(--font-display); font-size: 1.6rem; font-weight: 800; color: var(--brand-purple); flex-shrink: 0;">
        1
      </div>
      <div>
        <h3 style="font-family: var(--font-display); font-size: 1.65rem; margin: 0 0 0.5rem 0; color: var(--text-primary);">
          Institutional Whitelist Verification
        </h3>
        <p style="color: var(--text-secondary); line-height: 1.6; margin: 0 0 1rem 0;">
          Only enrolled college students, faculty members, and authorized election officers can log in. Accounts are provisioned with official college roll numbers or institutional email credentials.
        </p>
        <div style="background-color: var(--surface-secondary); padding: 0.85rem 1.25rem; border-radius: var(--radius-md); font-size: 0.85rem; border-left: 3px solid var(--brand-purple);">
          <strong>Security Guarantee:</strong> Outside public registration is strictly disabled. No duplicate identities can be created.
        </div>
      </div>
    </div>

    <!-- Step 2 -->
    <div class="card" style="padding: 2.25rem; display: flex; gap: 1.75rem; align-items: flex-start;">
      <div style="width: 52px; height: 52px; border-radius: 14px; background: var(--surface-lime); display: flex; align-items: center; justify-content: center; font-family: var(--font-display); font-size: 1.6rem; font-weight: 800; color: #4B6300; flex-shrink: 0;">
        2
      </div>
      <div>
        <h3 style="font-family: var(--font-display); font-size: 1.65rem; margin: 0 0 0.5rem 0; color: var(--text-primary);">
          Multi-Election Discovery &amp; Manifestos
        </h3>
        <p style="color: var(--text-secondary); line-height: 1.6; margin: 0 0 1rem 0;">
          Explore all campus contests—from Student Council and Department Class Representatives to College Societies and Committees. Read vetted candidate manifestos, review campaign goals, and follow live polling schedules.
        </p>
        <div style="background-color: var(--surface-secondary); padding: 0.85rem 1.25rem; border-radius: var(--radius-md); font-size: 0.85rem; border-left: 3px solid #84cc16;">
          <strong>Transparency:</strong> Every approved candidate is presented with equal prominence and verified credentials.
        </div>
      </div>
    </div>

    <!-- Step 3 -->
    <div class="card" style="padding: 2.25rem; display: flex; gap: 1.75rem; align-items: flex-start;">
      <div style="width: 52px; height: 52px; border-radius: 14px; background: var(--surface-pink); display: flex; align-items: center; justify-content: center; font-family: var(--font-display); font-size: 1.6rem; font-weight: 800; color: var(--vermilion); flex-shrink: 0;">
        3
      </div>
      <div>
        <h3 style="font-family: var(--font-display); font-size: 1.65rem; margin: 0 0 0.5rem 0; color: var(--text-primary);">
          Secret Ballot &amp; Atomic Concurrency
        </h3>
        <p style="color: var(--text-secondary); line-height: 1.6; margin: 0 0 1rem 0;">
          When you submit your ballot, the system records your participation timestamp to prevent double-voting, while depositing your candidate selection into an anonymous ballot table completely decoupled from your student roll number.
        </p>
        <div style="background-color: var(--surface-secondary); padding: 0.85rem 1.25rem; border-radius: var(--radius-md); font-size: 0.85rem; border-left: 3px solid var(--vermilion);">
          <strong>Mathematical Privacy:</strong> The database table storing votes has no user foreign key. It is physically impossible for administrators to link your vote back to you.
        </div>
      </div>
    </div>

    <!-- Step 4 -->
    <div class="card" style="padding: 2.25rem; display: flex; gap: 1.75rem; align-items: flex-start;">
      <div style="width: 52px; height: 52px; border-radius: 14px; background: var(--surface-lavender); display: flex; align-items: center; justify-content: center; font-family: var(--font-display); font-size: 1.6rem; font-weight: 800; color: var(--brand-purple); flex-shrink: 0;">
        4
      </div>
      <div>
        <h3 style="font-family: var(--font-display); font-size: 1.65rem; margin: 0 0 0.5rem 0; color: var(--text-primary);">
          Instant Receipt &amp; Verifiable Tally
        </h3>
        <p style="color: var(--text-secondary); line-height: 1.6; margin: 0 0 1rem 0;">
          Upon casting your ballot, you receive an immutable cryptographic receipt token (HMAC-SHA256). Once polls close, election officials publish the tallies. All results are verifiable and inspectable via MySQL/phpMyAdmin.
        </p>
        <div style="background-color: var(--surface-secondary); padding: 0.85rem 1.25rem; border-radius: var(--radius-md); font-size: 0.85rem; border-left: 3px solid var(--brand-purple);">
          <strong>Full Auditability:</strong> Immutable audit logs track every administrative operation without ever compromising ballot privacy.
        </div>
      </div>
    </div>

  </div>

  <!-- Bottom CTA -->
  <div style="text-align: center; margin-top: 3.5rem;">
    <a href="<?= View::url('/explore') ?>" class="btn-hero-explore" style="display: inline-flex;">
      <span class="btn-hero-label">Explore Active Elections</span>
      <span class="btn-hero-circle">&rarr;</span>
    </a>
  </div>

</div>
