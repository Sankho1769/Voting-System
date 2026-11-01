<?php
use App\Core\Auth;
use App\Core\View;
use App\Helpers\Sanitizer;
?>

<div class="homepage-container">

  <!-- =========================================================================
       HERO SECTION — EXACT VISUAL IDENTITY FROM REFERENCE 1
       ========================================================================= -->
  <section class="hero-section">
    <div class="container hero-grid">

      <!-- Left Column: Editorial Typography & CTAs -->
      <div class="hero-content">
        <!-- Eyebrow Tag -->
        <div class="eyebrow-tag" style="margin-bottom: 0.5rem;">
          COLLEGE VOTING PLATFORM
        </div>

        <!-- Master Headline -->
        <h1 class="hero-headline">
          <span class="hero-word word-your-1">Your</span>
          <span class="hero-word word-voice">voice.</span>
          <span class="hero-word word-your-2">Your</span>
          <span class="hero-word word-vote">vote.</span>
        </h1>

        <!-- Subtitle -->
        <p class="hero-subtitle">
          A simpler, more transparent way to shape campus life.
        </p>

        <!-- CTA Action Buttons -->
        <div class="hero-actions">
          <a href="<?= View::url('/explore') ?>" class="btn btn-dark">
            <span>Explore elections</span>
            <span class="btn-icon-lime" aria-hidden="true">&rarr;</span>
          </a>

          <?php if (Auth::isLoggedIn()): ?>
            <a href="<?= Auth::isAdmin() ? View::url('/admin') : (Auth::isCandidate() ? View::url('/candidate') : View::url('/voter')) ?>" class="btn btn-outline" style="border-radius: 9999px; padding: 0.85rem 1.5rem; font-weight: 600;">
              Go to My Portal &rarr;
            </a>
          <?php endif; ?>
        </div>

        <!-- Vertical Tag List (Bottom Left from Reference 1) -->
        <div class="stacked-tag-list" style="border-left: 2px solid var(--color-ink-subtle); padding-left: 0.85rem; margin-top: 2rem;">
          <span>IDEAS</span>
          <span>PEOPLE</span>
          <span>CAMPUS</span>
          <span>TOMORROW</span>
        </div>
      </div>

      <!-- Right Column: Signature 3D Ribbon & Floating Ballot Card Sculpture -->
      <div class="hero-artwork-wrapper" aria-hidden="true">
        <svg class="hero-sculpture-svg" viewBox="0 0 540 680" fill="none" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <!-- Purple Ribbon Gradients -->
            <linearGradient id="purpleLoopGrad" x1="120" y1="60" x2="420" y2="580" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="#CBA8F5" />
              <stop offset="35%" stop-color="#A749F5" />
              <stop offset="70%" stop-color="#7026C9" />
              <stop offset="100%" stop-color="#4A148C" />
            </linearGradient>

            <linearGradient id="purpleHighlight" x1="180" y1="100" x2="260" y2="280" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="#FFFFFF" stop-opacity="0.85" />
              <stop offset="40%" stop-color="#E4CCFF" stop-opacity="0.5" />
              <stop offset="100%" stop-color="#A749F5" stop-opacity="0" />
            </linearGradient>

            <!-- Vermilion Loop Gradients -->
            <linearGradient id="vermilionLoopGrad" x1="100" y1="140" x2="460" y2="520" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="#FF7A66" />
              <stop offset="30%" stop-color="#F04432" />
              <stop offset="70%" stop-color="#C92515" />
              <stop offset="100%" stop-color="#7A1006" />
            </linearGradient>

            <linearGradient id="vermilionHighlight" x1="140" y1="160" x2="240" y2="340" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="#FFE6E2" stop-opacity="0.9" />
              <stop offset="50%" stop-color="#FF7A66" stop-opacity="0.4" />
              <stop offset="100%" stop-color="#F04432" stop-opacity="0" />
            </linearGradient>

            <!-- Neon Lime Sphere Gradients -->
            <radialGradient id="limeSphereGrad1" cx="35%" cy="30%" r="65%">
              <stop offset="0%" stop-color="#F2FF9E" />
              <stop offset="30%" stop-color="#D7FF36" />
              <stop offset="75%" stop-color="#9BC700" />
              <stop offset="100%" stop-color="#557500" />
            </radialGradient>

            <radialGradient id="limeSphereGrad2" cx="35%" cy="30%" r="65%">
              <stop offset="0%" stop-color="#FFFFFF" />
              <stop offset="25%" stop-color="#D7FF36" />
              <stop offset="70%" stop-color="#8DBF00" />
              <stop offset="100%" stop-color="#3B5900" />
            </radialGradient>

            <!-- Card Dropshadow Filter -->
            <filter id="cardShadow" x="190" y="160" width="310" height="390" filterUnits="userSpaceOnUse">
              <feDropShadow dx="-15" dy="25" stdDeviation="22" flood-color="#18072B" flood-opacity="0.32" />
              <feDropShadow dx="0" dy="8" stdDeviation="8" flood-color="#7026C9" flood-opacity="0.18" />
            </filter>

            <filter id="sphereGlow" x="-20%" y="-20%" width="140%" height="140%">
              <feGaussianBlur stdDeviation="8" result="blur" />
              <feComposite in="SourceGraphic" in2="blur" operator="over" />
            </filter>
          </defs>

          <!-- Back Neon Lime Orbit Rings -->
          <ellipse class="sculpture-orbit-ring" cx="360" cy="300" rx="190" ry="210" stroke="#D7FF36" stroke-width="2.5" fill="none" opacity="0.85" stroke-dasharray="14 10" transform="rotate(-22 360 300)" />
          <ellipse cx="280" cy="340" rx="230" ry="170" stroke="#E4CCFF" stroke-width="1.8" fill="none" opacity="0.4" transform="rotate(35 280 340)" />

          <!-- Back Ribbon Segments (Vermilion loop background section) -->
          <path d="M 180 180 C 130 220 100 320 140 420 C 180 520 280 580 380 540 C 470 500 500 400 460 310 C 440 260 400 230 360 220" 
                stroke="url(#vermilionLoopGrad)" stroke-width="72" stroke-linecap="round" fill="none" />
          <path d="M 180 180 C 130 220 100 320 140 420 C 180 520 280 580 380 540" 
                stroke="url(#vermilionHighlight)" stroke-width="20" stroke-linecap="round" fill="none" />

          <!-- Top-Right Floating Neon Lime Sphere with Orbit Dot -->
          <circle class="sculpture-sphere-top" cx="450" cy="110" r="38" fill="url(#limeSphereGrad1)" filter="url(#sphereGlow)" />
          <line x1="390" y1="125" x2="450" y2="110" stroke="#D7FF36" stroke-width="2" opacity="0.9" />

          <!-- Main Glossy Purple Ribbon Loop (Front & Flowing) -->
          <path d="M 230 10 C 210 110 240 210 320 290 C 400 370 480 430 460 520 C 440 600 340 660 230 630 C 130 600 80 500 110 400 C 140 300 220 230 290 180" 
                stroke="url(#purpleLoopGrad)" stroke-width="78" stroke-linecap="round" fill="none" />
          <path d="M 230 10 C 210 110 240 210 320 290 C 370 340 430 390 450 460" 
                stroke="url(#purpleHighlight)" stroke-width="24" stroke-linecap="round" fill="none" />

          <!-- Intertwining Foreground Vermilion Arch (Looping through purple ribbon) -->
          <path d="M 200 440 C 150 490 140 560 220 620 C 300 680 420 650 470 570 C 500 510 490 450 450 410" 
                stroke="url(#vermilionLoopGrad)" stroke-width="70" stroke-linecap="round" fill="none" />

          <!-- Floating White 3D Ballot Card (Perspective-Tilted per Reference 1) -->
          <g class="sculpture-ballot-card" transform="translate(260, 210) rotate(11)" filter="url(#cardShadow)">
            <!-- Ballot Card Body -->
            <rect x="0" y="0" width="220" height="295" rx="22" fill="#FFFFFF" />
            <rect x="0" y="0" width="220" height="295" rx="22" stroke="#E6DBF5" stroke-width="2" fill="none" />

            <!-- Card Header: VOTE -->
            <text x="32" y="44" fill="#9D91AD" font-family="'Plus Jakarta Sans', sans-serif" font-size="12" font-weight="700" letter-spacing="3.5">VOTE</text>

            <!-- Candidate A Row (Checked) -->
            <g transform="translate(24, 66)">
              <rect x="0" y="0" width="172" height="66" rx="14" fill="#F8F5FC" />
              <!-- Avatar circle -->
              <circle cx="26" cy="33" r="15" fill="#CBA8F5" />
              <path d="M 26 25 A 6 6 0 0 1 26 37 A 6 6 0 0 1 26 25 Z" fill="#7026C9" opacity="0.8" />
              <!-- Candidate A Label & Bar -->
              <text x="50" y="29" fill="#1A1028" font-family="'Plus Jakarta Sans', sans-serif" font-size="12" font-weight="700">Candidate A</text>
              <rect x="50" y="36" width="48" height="6" rx="3" fill="#D6C7EB" />
              <!-- Lime Checked Square -->
              <rect x="130" y="19" width="28" height="28" rx="8" fill="#D7FF36" />
              <polyline points="137 33 142 38 152 26" stroke="#100A18" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none" />
            </g>

            <!-- Candidate B Row (Unchecked) -->
            <g transform="translate(24, 144)">
              <rect x="0" y="0" width="172" height="66" rx="14" fill="#FFFFFF" stroke="#EEE7F7" stroke-width="1.5" />
              <!-- Avatar circle -->
              <circle cx="26" cy="33" r="15" fill="#E8E2F0" />
              <circle cx="26" cy="31" r="6" fill="#A89BB8" />
              <!-- Candidate B Label & Bar -->
              <text x="50" y="29" fill="#3D3050" font-family="'Plus Jakarta Sans', sans-serif" font-size="12" font-weight="600">Candidate B</text>
              <rect x="50" y="36" width="38" height="6" rx="3" fill="#E8E2F0" />
              <!-- Empty Checkbox -->
              <rect x="130" y="19" width="28" height="28" rx="8" fill="none" stroke="#D1C5E3" stroke-width="2" />
            </g>

            <!-- Subtle Security Seal / Watermark at bottom of ballot card -->
            <circle cx="36" cy="254" r="10" fill="#F4ECFF" />
            <text x="54" y="258" fill="#B4A6C9" font-family="'JetBrains Mono', monospace" font-size="9" font-weight="600">ENCRYPTED &bull; VERITYVOTE</text>
          </g>

          <!-- Foreground Floating Neon Lime Sphere (Bottom Left) -->
          <circle class="sculpture-sphere-bottom" cx="30" cy="590" r="58" fill="url(#limeSphereGrad2)" filter="url(#sphereGlow)" />
        </svg>
      </div>

    </div>

    <!-- Right Side Vertical Tag (Bottom Right from Reference 1) -->
    <div class="stacked-tag-list hero-side-tag" style="position: absolute; right: 2rem; bottom: 1.5rem; text-align: right; border-right: 2px solid var(--color-ink-subtle); padding-right: 0.85rem; line-height: 1.6;">
      <span>A FAIRER</span>
      <span>BRIGHTER</span>
      <span>CAMPUS</span>
      <span>TOGETHER</span>
    </div>
  </section>

  <!-- =========================================================================
       THREE REFERENCE PORTAL CARDS (VOTER / CANDIDATE / ADMIN)
       Exact colors, typography, icon badges, and circular arrow buttons
       ========================================================================= -->
  <section class="portals-section">
    <div class="container">
      <div class="portals-strip">

        <!-- Portal 1: Voter Card (Pale Lavender Surface) -->
        <a href="<?= View::url('/login/voter') ?>" class="portal-card portal-card-voter" aria-label="Access Voter Login Portal">
          <div class="portal-card-header">
            <div class="portal-icon-circle portal-icon-voter">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
              </svg>
            </div>
          </div>
          <div class="portal-card-body">
            <h3 class="portal-title">Voter</h3>
            <p class="portal-desc">Log in with your college ID and cast your vote securely.</p>
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

        <!-- Portal 2: Candidate Card (Pale Lime Surface) -->
        <a href="<?= View::url('/login/candidate') ?>" class="portal-card portal-card-candidate" aria-label="Access Candidate Portal">
          <div class="portal-card-header">
            <div class="portal-icon-circle portal-icon-candidate">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M21 3H3C1.9 3 1 3.9 1 5v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-1 16H4c-.55 0-1-.45-1-1V6c0-.55.45-1 1-1h16c.55 0 1 .45 1 1v12c0 .55-.45 1-1 1zM9 8h2v8H9zm4 0h2v8h-2zm-8 4h2v4H5zm12-2h2v6h-2z"/>
              </svg>
            </div>
          </div>
          <div class="portal-card-body">
            <h3 class="portal-title">Candidate</h3>
            <p class="portal-desc">Present your vision and connect with students.</p>
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

        <!-- Portal 3: Admin Card (Pale Pink Surface) -->
        <a href="<?= View::url('/login/admin') ?>" class="portal-card portal-card-admin" aria-label="Access Admin Portal">
          <div class="portal-card-header">
            <div class="portal-icon-circle portal-icon-admin">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>
              </svg>
            </div>
          </div>
          <div class="portal-card-body">
            <h3 class="portal-title">Admin</h3>
            <p class="portal-desc">Manage elections and ensure a fair process.</p>
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
    </div>
  </section>

  <!-- =========================================================================
       CRICHEROES-INSPIRED FEATURED & LIVE ELECTIONS DISCOVERY STRIP
       ========================================================================= -->
  <section class="section" style="padding-top: 4rem; padding-bottom: 3rem;">
    <div class="container">
      <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem;">
        <div>
          <span class="badge badge-purple" style="margin-bottom: 0.5rem;">CAMPUS ELECTION HUB</span>
          <h2 style="font-family: var(--font-display); font-size: 2.25rem; font-weight: 700; margin: 0; color: var(--text-primary);">
            Active &amp; Featured Ballots
          </h2>
          <p style="color: var(--text-muted); margin: 0.25rem 0 0 0; font-size: 0.95rem;">
            Discover elections across student council, departments, and campus societies.
          </p>
        </div>
        <div>
          <a href="<?= View::url('/explore') ?>" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem;">
            <span>View All Elections</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </a>
        </div>
      </div>

      <!-- Election Cards Grid (CricHeroes Style) -->
      <?php if (!empty($featuredElections)): ?>
        <div class="elections-grid">
          <?php foreach ($featuredElections as $election): ?>
            <?php
              $isOpen = ($election['status'] === 'open');
              $isResults = ($election['status'] === 'results_published');
              $categoryLabel = ucwords(str_replace('_', ' ', $election['category'] ?? 'General'));
            ?>
            <article class="election-card">
              <div class="election-card-header">
                <span class="badge badge-purple"><?= Sanitizer::e($categoryLabel) ?></span>
                <span class="status-pill <?= Sanitizer::e($election['status']) ?>">
                  <span class="status-dot"></span>
                  <span><?= $isOpen ? 'VOTING LIVE' : ($isResults ? 'RESULTS PUBLISHED' : ucfirst($election['status'])) ?></span>
                </span>
              </div>

              <div class="election-card-body">
                <h3 class="election-card-title">
                  <a href="<?= View::url('/election?id=' . (int)$election['id']) ?>">
                    <?= Sanitizer::e($election['title']) ?>
                  </a>
                </h3>
                <div class="election-card-position">
                  Position: <strong><?= Sanitizer::e($election['position'] ?? 'Candidate') ?></strong>
                  <?php if (!empty($election['department'])): ?>
                    &bull; <span><?= Sanitizer::e($election['department']) ?></span>
                  <?php endif; ?>
                </div>

                <div class="election-card-meta">
                  <div class="meta-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?php if ($isOpen && !empty($election['end_time'])): ?>
                      <span class="countdown-ticker" data-countdown-target="<?= Sanitizer::e($election['end_time']) ?>" data-expired-label="Polls Closed">
                        Closing soon
                      </span>
                    <?php elseif (!empty($election['start_time'])): ?>
                      <span>Opens <?= Sanitizer::e(date('M j, Y', strtotime($election['start_time']))) ?></span>
                    <?php else: ?>
                      <span>Institutional Ballot</span>
                    <?php endif; ?>
                  </div>

                  <div class="meta-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    <span><?= (int)($election['candidate_count'] ?? 0) ?> Candidates</span>
                  </div>
                </div>
              </div>

              <div class="election-card-footer">
                <div>
                  <span class="badge badge-outline" style="font-size: 0.75rem;">
                    <?= !empty($election['department']) ? Sanitizer::e($election['department']) : 'Campus-Wide' ?>
                  </span>
                </div>
                <div>
                  <?php if ($isOpen): ?>
                    <a href="<?= View::url('/ballot?election_id=' . (int)$election['id']) ?>" class="btn btn-primary btn-sm">
                      Vote Now &rarr;
                    </a>
                  <?php elseif ($isResults): ?>
                    <a href="<?= View::url('/results?election_id=' . (int)$election['id']) ?>" class="btn btn-secondary btn-sm">
                      View Results
                    </a>
                  <?php else: ?>
                    <a href="<?= View::url('/election?id=' . (int)$election['id']) ?>" class="btn btn-outline btn-sm">
                      Details &amp; Rules
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state" style="background-color: var(--surface-card); border-radius: var(--radius-lg); padding: 3rem; text-align: center; border: 1px solid var(--border-subtle);">
          <h4 style="font-family: var(--font-display); font-size: 1.5rem; color: var(--text-primary); margin-bottom: 0.5rem;">No Active Elections At This Moment</h4>
          <p style="color: var(--text-muted); max-width: 460px; margin: 0 auto 1.5rem auto;">
            Campus elections are scheduled periodically by the election administration. Check back during scheduled polling windows or explore candidates.
          </p>
          <a href="<?= View::url('/candidates') ?>" class="btn btn-secondary btn-sm">Meet Approved Candidates</a>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- =========================================================================
       HOW IT WORKS SECTION (4 STEPS TO CAMPUS DEMOCRACY)
       ========================================================================= -->
  <section class="section" style="background-color: var(--surface-secondary); border-top: 1px solid var(--border-subtle); border-bottom: 1px solid var(--border-subtle); padding: 4.5rem 0;">
    <div class="container">
      <div style="text-align: center; max-width: 600px; margin: 0 auto 3.5rem auto;">
        <span class="badge badge-purple" style="margin-bottom: 0.5rem;">TRANSPARENT &bull; SECURE &bull; CLOSED</span>
        <h2 style="font-family: var(--font-display); font-size: 2.5rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.75rem;">
          How VerityVote Works
        </h2>
        <p style="color: var(--text-muted); font-size: 1.05rem;">
          Built from the ground up for college campuses to ensure only genuine, eligible voters decide elections.
        </p>
      </div>

      <div class="grid grid-4">
        <!-- Step 1 -->
        <div class="card" style="padding: 1.75rem; text-align: left;">
          <div style="width: 44px; height: 44px; border-radius: 12px; background-color: var(--surface-lavender); display: flex; align-items: center; justify-content: center; font-weight: 800; font-family: var(--font-display); font-size: 1.3rem; color: var(--brand-purple); margin-bottom: 1.25rem;">
            1
          </div>
          <h3 style="font-family: var(--font-display); font-size: 1.35rem; margin-bottom: 0.5rem; color: var(--text-primary);">Institutional Verification</h3>
          <p style="color: var(--text-muted); font-size: 0.88rem; line-height: 1.6; margin: 0;">
            Log in using your official college roll number or staff ID. Strict whitelist rules protect every ballot.
          </p>
        </div>

        <!-- Step 2 -->
        <div class="card" style="padding: 1.75rem; text-align: left;">
          <div style="width: 44px; height: 44px; border-radius: 12px; background-color: var(--surface-lime); display: flex; align-items: center; justify-content: center; font-weight: 800; font-family: var(--font-display); font-size: 1.3rem; color: #4B6300; margin-bottom: 1.25rem;">
            2
          </div>
          <h3 style="font-family: var(--font-display); font-size: 1.35rem; margin-bottom: 0.5rem; color: var(--text-primary);">Discover &amp; Compare</h3>
          <p style="color: var(--text-muted); font-size: 0.88rem; line-height: 1.6; margin: 0;">
            Browse candidate profiles, read official manifestos, and see what changes each contender proposes for campus.
          </p>
        </div>

        <!-- Step 3 -->
        <div class="card" style="padding: 1.75rem; text-align: left;">
          <div style="width: 44px; height: 44px; border-radius: 12px; background-color: var(--surface-pink); display: flex; align-items: center; justify-content: center; font-weight: 800; font-family: var(--font-display); font-size: 1.3rem; color: var(--vermilion); margin-bottom: 1.25rem;">
            3
          </div>
          <h3 style="font-family: var(--font-display); font-size: 1.35rem; margin-bottom: 0.5rem; color: var(--text-primary);">Cast Secret Ballot</h3>
          <p style="color: var(--text-muted); font-size: 0.88rem; line-height: 1.6; margin: 0;">
            Cast your ballot in seconds. Your choice is separated from your identity to ensure 100% ballot secrecy.
          </p>
        </div>

        <!-- Step 4 -->
        <div class="card" style="padding: 1.75rem; text-align: left;">
          <div style="width: 44px; height: 44px; border-radius: 12px; background-color: var(--surface-lavender); display: flex; align-items: center; justify-content: center; font-weight: 800; font-family: var(--font-display); font-size: 1.3rem; color: var(--brand-purple); margin-bottom: 1.25rem;">
            4
          </div>
          <h3 style="font-family: var(--font-display); font-size: 1.35rem; margin-bottom: 0.5rem; color: var(--text-primary);">Auditable Standings</h3>
          <p style="color: var(--text-muted); font-size: 0.88rem; line-height: 1.6; margin: 0;">
            Certified live results, mathematical verification, and cryptographic receipts guarantee tamper-free counting.
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- =========================================================================
       STATISTICS & INTEGRITY BANNER
       ========================================================================= -->
  <section class="section" style="padding: 3.5rem 0;">
    <div class="container">
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-label">Ballot Privacy</div>
          <div class="stat-value" style="color: var(--brand-purple);">100%</div>
          <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem;">Decoupled voter identity</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Duplicate Prevention</div>
          <div class="stat-value" style="color: var(--vermilion);">0%</div>
          <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem;">ACID row-level lock enforced</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Active Categories</div>
          <div class="stat-value" style="color: var(--text-primary);"><?= (int)($stats['total_categories'] ?? 8) ?></div>
          <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem;">Council, Depts &amp; Societies</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Audit Logging</div>
          <div class="stat-value" style="color: var(--brand-purple);">Live</div>
          <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem;">Immutable activity ledger</div>
        </div>
      </div>
    </div>
  </section>

</div>
