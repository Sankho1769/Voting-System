<?php
use App\Core\View;
$pageTitle = 'Institutional Access Portals';
?>

<div class="container" style="max-width: 1040px; margin-top: 2rem; margin-bottom: 3rem;">
  <div style="text-align: center; margin-bottom: 2.75rem;">
    <div class="brand-crest" style="width: 3.5rem; height: 3.5rem; font-size: 1.85rem; margin: 0 auto 1rem auto;">V</div>
    <h1 style="font-size: 2.4rem; margin-bottom: 0.5rem; letter-spacing: -0.02em;">VerityVote Portals</h1>
    <p style="color: var(--color-text-secondary); font-size: 1.05rem; max-width: 580px; margin: 0 auto;">
      Institutional College Voting System &bull; Select your designated portal to authenticate into the electoral network.
    </p>
  </div>

  <div class="grid grid-3" style="gap: 1.75rem;">
    <!-- 1. Administrator Portal Card -->
    <div class="card card-ivory" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 4px solid var(--color-emerald-green); padding: 2rem; position: relative;">
      <div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
          <span class="badge badge-active" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
            System Governance
          </span>
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-emerald-green)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          </svg>
        </div>
        <h2 style="font-size: 1.45rem; margin-bottom: 0.75rem;">Administrator Portal</h2>
        <p style="color: var(--color-text-secondary); font-size: 0.9rem; line-height: 1.55; margin-bottom: 1.5rem;">
          For authorized system administrators and election officers. Configure ballots, oversee student &amp; staff account provisioning, monitor eligibility whitelists, and export certified tallies.
        </p>
      </div>
      <div>
        <div style="font-size: 0.8rem; color: var(--color-text-muted); margin-bottom: 1rem; border-top: 1px solid var(--color-border-subtle); padding-top: 0.75rem;">
          Credentials: <strong>Admin ID &amp; Master Password</strong>
        </div>
        <a href="<?= View::url('/login/admin') ?>" class="btn btn-primary btn-block">
          Admin Sign-In &rarr;
        </a>
      </div>
    </div>

    <!-- 2. Candidate Portal Card -->
    <div class="card card-ivory" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 4px solid var(--color-vermilion); padding: 2rem; position: relative;">
      <div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
          <span class="badge" style="background-color: var(--color-vermilion-subtle); color: var(--color-vermilion-dark); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
            Contender Platform
          </span>
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-vermilion)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
          </svg>
        </div>
        <h2 style="font-size: 1.45rem; margin-bottom: 0.75rem;">Candidate Portal</h2>
        <p style="color: var(--color-text-secondary); font-size: 0.9rem; line-height: 1.55; margin-bottom: 1.5rem;">
          For approved student and faculty candidates. Inspect your campaign profile, verify election nomination status, manage your platform manifesto, review opponents, and view live standings.
        </p>
      </div>
      <div>
        <div style="font-size: 0.8rem; color: var(--color-text-muted); margin-bottom: 1rem; border-top: 1px solid var(--color-border-subtle); padding-top: 0.75rem;">
          Credentials: <strong>Candidate ID / Roll No &amp; Password</strong>
        </div>
        <a href="<?= View::url('/login/candidate') ?>" class="btn btn-secondary btn-block" style="border-color: var(--color-vermilion); color: var(--color-vermilion);">
          Candidate Sign-In &rarr;
        </a>
      </div>
    </div>

    <!-- 3. Voter Portal Card -->
    <div class="card card-ivory" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 4px solid var(--color-emerald-green); padding: 2rem; position: relative;">
      <div>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
          <span class="badge badge-open" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
            Democratic Participation
          </span>
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-emerald-green)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="9 11 12 14 22 4"/>
            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
          </svg>
        </div>
        <h2 style="font-size: 1.45rem; margin-bottom: 0.75rem;">Voter Portal</h2>
        <p style="color: var(--color-text-secondary); font-size: 0.9rem; line-height: 1.55; margin-bottom: 1.5rem;">
          For registered collegiate voters. Access your authorized single-position ballot races, cast your secret vote securely, obtain cryptographic participation receipts, and follow election returns.
        </p>
      </div>
      <div>
        <div style="font-size: 0.8rem; color: var(--color-text-muted); margin-bottom: 1rem; border-top: 1px solid var(--color-border-subtle); padding-top: 0.75rem;">
          Credentials: <strong>College Roll No / Emp ID &amp; Password</strong>
        </div>
        <a href="<?= View::url('/login/voter') ?>" class="btn btn-primary btn-block">
          Voter Sign-In &rarr;
        </a>
      </div>
    </div>
  </div>

  <!-- Institutional Security Notice -->
  <div style="margin-top: 2.5rem; text-align: center; color: var(--color-text-muted); font-size: 0.85rem; line-height: 1.5;">
    <p>
      Public registration is strictly disabled. All accounts are pre-authorized and provisioned by institutional administrators.<br>
      Single identity accounts with dual permissions (e.g. eligible student voter who is also a candidate) use the same credentials for both portals.
    </p>
  </div>
</div>
