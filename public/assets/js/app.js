/**
 * VerityVote - Complete Application JavaScript
 * Visual Identity: Purple / Lavender / Lime / Editorial Serif
 * Discovery Mechanics: CricHeroes Multi-Election Architecture
 */

(function () {
  'use strict';

  // --- 1. Theme Management (Light Lavender Canvas <-> Midnight Purple Dark Mode) ---
  const THEME_STORAGE_KEY = 'verityvote_theme';

  function initTheme() {
    const saved = localStorage.getItem(THEME_STORAGE_KEY);
    const initialTheme = saved || 'light';
    setTheme(initialTheme, false);
  }

  function setTheme(theme, persist = true) {
    document.documentElement.setAttribute('data-theme', theme);
    if (persist) {
      localStorage.setItem(THEME_STORAGE_KEY, theme);
    }
    updateThemeToggleUI(theme);
  }

  function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    setTheme(next, true);
  }

  function updateThemeToggleUI(theme) {
    document.querySelectorAll('[data-action="toggle-theme"]').forEach(btn => {
      const isDark = theme === 'dark';
      btn.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
      const icon = btn.querySelector('.theme-toggle-icon');
      if (icon) {
        icon.innerHTML = isDark
          ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>'
          : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
      }
    });
  }

  // Run theme immediately to avoid flash of wrong theme
  initTheme();

  // --- 2. DOM Ready Logic ---
  document.addEventListener('DOMContentLoaded', () => {
    // Attach theme toggle click handlers
    document.querySelectorAll('[data-action="toggle-theme"]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        toggleTheme();
      });
    });

    // --- 3. Auto-Dismiss / Alert Close ---
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
      const closeBtn = alert.querySelector('.alert-close');
      if (closeBtn) {
        closeBtn.addEventListener('click', () => {
          alert.style.opacity = '0';
          setTimeout(() => alert.remove(), 200);
        });
      }
    });

    // --- 4. Modal Controller ---
    window.openModal = function (modalId) {
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        const focusable = modal.querySelector('button, input, select, textarea, [tabindex="0"]');
        if (focusable) focusable.focus();
      }
    };

    window.closeModal = function (modalId) {
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
      }
    };

    // Close modals on backdrop click or ESC
    document.querySelectorAll('.modal-backdrop').forEach(modal => {
      modal.addEventListener('click', (e) => {
        if (e.target === modal) {
          closeModal(modal.id);
        }
      });
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        document.querySelectorAll('.modal-backdrop.active').forEach(modal => {
          closeModal(modal.id);
        });
      }
    });

    // --- 5. CricHeroes Discovery Engine: Instant Client Filtering & Tabs ---
    const discoveryContainer = document.querySelector('[data-discovery-grid]');
    if (discoveryContainer) {
      const cards = Array.from(discoveryContainer.querySelectorAll('.election-card'));
      const searchInput = document.querySelector('[data-search-input]');
      const tabButtons = document.querySelectorAll('[data-discovery-tab]');
      const categoryPills = document.querySelectorAll('[data-category-pill]');
      const deptFilter = document.querySelector('[data-dept-filter]');
      const emptyState = document.getElementById('discovery-empty-state');
      const counterEl = document.querySelector('[data-discovery-count]');

      let currentTab = 'all';
      let currentCategory = 'all';
      let currentDept = 'all';
      let currentQuery = '';

      function applyFilters() {
        let visibleCount = 0;

        cards.forEach(card => {
          const status = card.dataset.status || '';
          const category = card.dataset.category || '';
          const department = (card.dataset.department || '').toLowerCase();
          const eligible = card.dataset.eligible === 'true';
          const participated = card.dataset.participated === 'true';
          const searchText = (card.dataset.search || card.textContent).toLowerCase();

          // 1. Tab check
          let tabMatch = true;
          if (currentTab === 'live') {
            tabMatch = (status === 'open');
          } else if (currentTab === 'upcoming') {
            tabMatch = (status === 'draft' || status === 'upcoming');
          } else if (currentTab === 'completed') {
            tabMatch = (status === 'closed' || status === 'results_published');
          } else if (currentTab === 'results') {
            tabMatch = (status === 'results_published');
          } else if (currentTab === 'for_you') {
            // For you: elections user is eligible for and hasn't voted in yet, or open
            tabMatch = eligible && !participated;
          }

          // 2. Category check
          let catMatch = (currentCategory === 'all' || category === currentCategory);

          // 3. Dept check
          let deptMatch = (currentDept === 'all' || department.includes(currentDept.toLowerCase()));

          // 4. Text search
          let queryMatch = true;
          if (currentQuery.trim() !== '') {
            queryMatch = searchText.includes(currentQuery.trim().toLowerCase());
          }

          const isVisible = tabMatch && catMatch && deptMatch && queryMatch;
          card.style.display = isVisible ? '' : 'none';
          if (isVisible) visibleCount++;
        });

        if (counterEl) {
          counterEl.textContent = `${visibleCount} election${visibleCount === 1 ? '' : 's'}`;
        }

        if (emptyState) {
          emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
        }
      }

      // Tab handlers
      tabButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          tabButtons.forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
          currentTab = btn.dataset.discoveryTab || 'all';
          applyFilters();
        });
      });

      // Category pill handlers
      categoryPills.forEach(pill => {
        pill.addEventListener('click', (e) => {
          e.preventDefault();
          categoryPills.forEach(p => p.classList.remove('active'));
          pill.classList.add('active');
          currentCategory = pill.dataset.categoryPill || 'all';
          applyFilters();
        });
      });

      // Department dropdown
      if (deptFilter) {
        deptFilter.addEventListener('change', () => {
          currentDept = deptFilter.value;
          applyFilters();
        });
      }

      // Search input with debounce
      if (searchInput) {
        let debounceTimer;
        searchInput.addEventListener('input', () => {
          clearTimeout(debounceTimer);
          debounceTimer = setTimeout(() => {
            currentQuery = searchInput.value;
            applyFilters();
          }, 150);
        });
      }

      // Run initial filter check
      applyFilters();
    }

    // --- 6. Live Countdown Clocks ---
    function initCountdowns() {
      const countdownElements = document.querySelectorAll('[data-countdown-target]');
      if (!countdownElements.length) return;

      function updateAll() {
        const now = new Date().getTime();

        countdownElements.forEach(el => {
          const targetStr = el.dataset.countdownTarget;
          if (!targetStr) return;

          // Normalize datetime string to parse reliably
          const target = new Date(targetStr.replace(' ', 'T')).getTime();
          const diff = target - now;

          if (isNaN(target)) {
            el.textContent = 'Scheduled';
            return;
          }

          if (diff <= 0) {
            el.textContent = el.dataset.expiredLabel || 'Closed';
            return;
          }

          const days = Math.floor(diff / (1000 * 60 * 60 * 24));
          const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
          const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
          const seconds = Math.floor((diff % (1000 * 60)) / 1000);

          if (days > 0) {
            el.textContent = `${days}d ${hours}h ${minutes}m left`;
          } else if (hours > 0) {
            el.textContent = `${hours}h ${minutes}m ${seconds}s left`;
          } else {
            el.textContent = `${minutes}m ${seconds}s left`;
          }
        });
      }

      updateAll();
      setInterval(updateAll, 1000);
    }
    initCountdowns();

    // --- 7. Dropdown & Mobile Menu Controllers ---
    document.querySelectorAll('[data-toggle="dropdown"]').forEach(trigger => {
      trigger.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const menu = trigger.nextElementSibling;
        if (menu) {
          const isExpanded = trigger.getAttribute('aria-expanded') === 'true';
          trigger.setAttribute('aria-expanded', !isExpanded);
          menu.classList.toggle('active');
        }
      });
    });

    // Close all open dropdowns when clicking outside
    document.addEventListener('click', () => {
      document.querySelectorAll('.dropdown-menu.active').forEach(menu => {
        menu.classList.remove('active');
        const trigger = menu.previousElementSibling;
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
      });
    });

    // --- 8. Live Results Polling (Preserved 6-second interval) ---
    const resultsContainer = document.querySelector('[data-live-results]');
    if (resultsContainer) {
      const electionId = resultsContainer.dataset.electionId;
      const apiUrl = resultsContainer.dataset.apiUrl;
      const pollInterval = 6000;

      const pollResults = async () => {
        try {
          const response = await fetch(`${apiUrl}?election_id=${electionId}`, {
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json'
            }
          });

          if (!response.ok) return;

          const data = await response.json();
          if (data && data.success && data.visible && data.results) {
            updateResultsDOM(data.results);
          }
        } catch (err) {
          console.warn('Live poll error:', err);
        }
      };

      const updateResultsDOM = (tallies) => {
        const totalBallotsEl = document.getElementById('stat-total-ballots');
        if (totalBallotsEl) totalBallotsEl.textContent = tallies.total_ballots;

        const turnoutEl = document.getElementById('stat-turnout');
        if (turnoutEl) turnoutEl.textContent = `${tallies.turnout_percentage}%`;

        if (Array.isArray(tallies.candidates)) {
          tallies.candidates.forEach(cand => {
            const row = document.querySelector(`[data-candidate-id="${cand.candidate_id}"]`);
            if (row) {
              const countEl = row.querySelector('.result-count');
              const percentEl = row.querySelector('.result-percent');
              const barEl = row.querySelector('.progress-fill');

              if (countEl) countEl.textContent = `${cand.vote_count} votes`;
              if (percentEl) percentEl.textContent = `(${cand.percentage}%)`;
              if (barEl) barEl.style.width = `${cand.percentage}%`;
            }
          });
        }
      };

      const timer = setInterval(pollResults, pollInterval);
      window.addEventListener('beforeunload', () => clearInterval(timer));
    }

  });
})();
