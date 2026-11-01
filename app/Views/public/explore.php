<?php
use App\Core\Auth;
use App\Core\View;
use App\Helpers\Sanitizer;
?>

<div class="container section">

  <!-- =========================================================================
       DISCOVERY HEADER (CRICHEROES-INSPIRED MULTI-ELECTION PORTAL)
       ========================================================================= -->
  <div style="margin-bottom: 2rem;">
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 1rem;">
      <div>
        <span class="badge badge-purple" style="margin-bottom: 0.5rem;">CENTRAL ELECTION HUB</span>
        <h1 style="font-family: var(--font-display); font-size: 2.75rem; font-weight: 700; color: var(--text-primary); margin: 0;">
          Campus Elections
        </h1>
        <p style="color: var(--text-muted); margin: 0.35rem 0 0 0; font-size: 1.05rem;">
          Discover, follow, and participate in active ballots across campus divisions.
        </p>
      </div>

      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <span class="badge badge-outline" data-discovery-count style="font-family: var(--font-mono); font-size: 0.85rem; padding: 0.4rem 0.85rem;">
          <?= count($elections) ?> elections
        </span>
      </div>
    </div>
  </div>

  <!-- =========================================================================
       CRICHEROES TABS: FOR YOU / ALL / LIVE / UPCOMING / COMPLETED / RESULTS
       ========================================================================= -->
  <div class="discovery-tabs-scroll">
    <div class="discovery-tabs">
      <button type="button" class="discovery-tab <?= (!isset($activeTab) || $activeTab === 'all') ? 'active' : '' ?>" data-discovery-tab="all">
        All Elections
      </button>
      <button type="button" class="discovery-tab <?= (isset($activeTab) && $activeTab === 'live') ? 'active' : '' ?>" data-discovery-tab="live">
        <span class="status-dot" style="display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #22c55e; margin-right: 4px;"></span>
        Live &amp; Active
      </button>
      <?php if (Auth::isLoggedIn() && Auth::isVoter()): ?>
        <button type="button" class="discovery-tab <?= (isset($activeTab) && $activeTab === 'for_you') ? 'active' : '' ?>" data-discovery-tab="for_you">
          For You (Eligible)
        </button>
      <?php endif; ?>
      <button type="button" class="discovery-tab <?= (isset($activeTab) && $activeTab === 'upcoming') ? 'active' : '' ?>" data-discovery-tab="upcoming">
        Upcoming
      </button>
      <button type="button" class="discovery-tab <?= (isset($activeTab) && $activeTab === 'completed') ? 'active' : '' ?>" data-discovery-tab="completed">
        Completed
      </button>
      <button type="button" class="discovery-tab <?= (isset($activeTab) && $activeTab === 'results') ? 'active' : '' ?>" data-discovery-tab="results">
        Results
      </button>
    </div>
  </div>

  <!-- =========================================================================
       CATEGORY PILLS CAROUSEL
       ========================================================================= -->
  <div class="category-pills-bar" style="margin-top: 1.25rem;">
    <button type="button" class="category-pill active" data-category-pill="all">
      All Categories
    </button>
    <?php foreach ($categories as $catKey => $catInfo): ?>
      <button type="button" class="category-pill" data-category-pill="<?= Sanitizer::e($catKey) ?>">
        <?= Sanitizer::e($catInfo['name']) ?>
      </button>
    <?php endforeach; ?>
  </div>

  <!-- =========================================================================
       SEARCH & FILTER TOOLBAR
       ========================================================================= -->
  <div class="search-filter-bar" style="margin-top: 1.5rem; margin-bottom: 2rem;">
    <div class="filter-bar-inner">
      <!-- Search Input -->
      <div class="filter-input-group" style="flex: 1; position: relative;">
        <span style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </span>
        <input type="text" class="form-input" data-search-input placeholder="Search elections by title, position, department, or contenders..." style="padding-left: 2.75rem; border-radius: var(--radius-md);">
      </div>

      <!-- Department Filter Dropdown -->
      <?php if (!empty($departments)): ?>
        <div style="min-width: 200px;">
          <select class="form-input" data-dept-filter style="border-radius: var(--radius-md);">
            <option value="all">All Departments</option>
            <?php foreach ($departments as $dept): ?>
              <?php if (!empty($dept)): ?>
                <option value="<?= Sanitizer::e($dept) ?>"><?= Sanitizer::e($dept) ?></option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- =========================================================================
       ELECTIONS MATCH-CARDS GRID (CRICHEROES INSPIRED)
       ========================================================================= -->
  <div class="elections-grid" data-discovery-grid>
    <?php if (!empty($elections)): ?>
      <?php foreach ($elections as $election): ?>
        <?php
          $isOpen = ($election['status'] === 'open');
          $isResults = ($election['status'] === 'results_published');
          $isClosed = ($election['status'] === 'closed');
          $isDraft = ($election['status'] === 'draft');
          
          $catKey = $election['category'] ?? 'other';
          $catName = $categories[$catKey]['name'] ?? ucwords(str_replace('_', ' ', $catKey));
          
          $isEligible = !empty($election['is_eligible']);
          $hasParticipated = !empty($election['has_participated']);
          
          $searchText = strtolower(
            ($election['title'] ?? '') . ' ' .
            ($election['position'] ?? '') . ' ' .
            ($election['department'] ?? '') . ' ' .
            $catName
          );
        ?>
        <article class="election-card" 
                 data-status="<?= Sanitizer::e($election['status']) ?>"
                 data-category="<?= Sanitizer::e($catKey) ?>"
                 data-department="<?= Sanitizer::e($election['department'] ?? '') ?>"
                 data-eligible="<?= $isEligible ? 'true' : 'false' ?>"
                 data-participated="<?= $hasParticipated ? 'true' : 'false' ?>"
                 data-search="<?= Sanitizer::e($searchText) ?>">

          <!-- Card Top Bar: Category Pill & Live Status -->
          <div class="election-card-header">
            <span class="badge badge-purple"><?= Sanitizer::e($catName) ?></span>
            <span class="status-pill <?= Sanitizer::e($election['status']) ?>">
              <span class="status-dot"></span>
              <span>
                <?php
                  if ($isOpen) echo 'VOTING LIVE';
                  elseif ($isResults) echo 'RESULTS OUT';
                  elseif ($isClosed) echo 'CLOSED';
                  elseif ($isDraft) echo 'UPCOMING';
                  else echo ucfirst($election['status']);
                ?>
              </span>
            </span>
          </div>

          <!-- Card Body -->
          <div class="election-card-body">
            <h3 class="election-card-title">
              <a href="<?= View::url('/election?id=' . (int)$election['id']) ?>">
                <?= Sanitizer::e($election['title']) ?>
              </a>
            </h3>

            <div class="election-card-position">
              Position: <strong><?= Sanitizer::e($election['position'] ?? 'Candidate') ?></strong>
              <?php if (!empty($election['department'])): ?>
                &bull; <span style="color: var(--text-muted);"><?= Sanitizer::e($election['department']) ?></span>
              <?php endif; ?>
            </div>

            <!-- Match Details / Timing / Contenders -->
            <div class="election-card-meta">
              <div class="meta-item">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?php if ($isOpen && !empty($election['end_time'])): ?>
                  <span class="countdown-ticker" data-countdown-target="<?= Sanitizer::e($election['end_time']) ?>" data-expired-label="Polls Closed">
                    Calculating...
                  </span>
                <?php elseif (!empty($election['start_time'])): ?>
                  <span>Opens <?= Sanitizer::e(date('M j, Y H:i', strtotime($election['start_time']))) ?></span>
                <?php else: ?>
                  <span>Scheduled by Office</span>
                <?php endif; ?>
              </div>

              <div class="meta-item">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                <span><?= (int)($election['candidate_count'] ?? 0) ?> Approved Candidates</span>
              </div>
            </div>

            <!-- Eligibility State Indicator (When Logged in as Student/Voter) -->
            <?php if (Auth::isLoggedIn() && Auth::isVoter()): ?>
              <div style="margin-top: 0.85rem; padding-top: 0.75rem; border-top: 1px dashed var(--border-subtle); display: flex; align-items: center; justify-content: space-between; font-size: 0.82rem;">
                <?php if ($hasParticipated): ?>
                  <span style="color: var(--brand-purple); font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                    Ballot Cast
                  </span>
                <?php elseif ($isEligible): ?>
                  <span style="color: #16a34a; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 8 12 12 14 14"/></svg>
                    You are eligible to vote
                  </span>
                <?php else: ?>
                  <span style="color: var(--text-muted);">
                    Institutional whitelist restricted
                  </span>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Card Footer -->
          <div class="election-card-footer">
            <div>
              <span class="badge badge-outline" style="font-size: 0.75rem;">
                <?= !empty($election['department']) ? Sanitizer::e($election['department']) : 'Campus-Wide' ?>
              </span>
            </div>

            <div style="display: flex; gap: 0.5rem; align-items: center;">
              <a href="<?= View::url('/election?id=' . (int)$election['id']) ?>" class="btn btn-outline btn-sm" title="View election details and candidates">
                Details
              </a>

              <?php if ($isOpen): ?>
                <?php if ($hasParticipated): ?>
                  <a href="<?= View::url('/confirmation?election_id=' . (int)$election['id']) ?>" class="btn btn-secondary btn-sm">
                    Receipt
                  </a>
                <?php else: ?>
                  <a href="<?= View::url('/ballot?election_id=' . (int)$election['id']) ?>" class="btn btn-primary btn-sm">
                    Vote Now &rarr;
                  </a>
                <?php endif; ?>
              <?php elseif ($isResults): ?>
                <a href="<?= View::url('/results?election_id=' . (int)$election['id']) ?>" class="btn btn-secondary btn-sm">
                  Standings &rarr;
                </a>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Empty State Fallback -->
  <div id="discovery-empty-state" class="empty-state" style="display: <?= empty($elections) ? 'block' : 'none' ?>; background-color: var(--surface-card); border-radius: var(--radius-lg); padding: 3.5rem; text-align: center; border: 1px solid var(--border-subtle); margin-top: 1.5rem;">
    <div style="width: 56px; height: 56px; border-radius: 50%; background-color: var(--surface-lavender); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem auto;">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--brand-purple)" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    </div>
    <h3 style="font-family: var(--font-display); font-size: 1.75rem; color: var(--text-primary); margin-bottom: 0.5rem;">No Matching Elections Found</h3>
    <p style="color: var(--text-muted); max-width: 480px; margin: 0 auto 1.5rem auto;">
      Try adjusting your search criteria, clearing category filters, or selecting a different tab.
    </p>
    <button type="button" class="btn btn-secondary btn-sm" onclick="location.reload()">
      Reset All Filters
    </button>
  </div>

</div>
