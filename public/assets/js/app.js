/**
 * VerityVote - Core Application JavaScript (Vanilla JS)
 */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Auto-dismiss flash alerts or handle dismiss buttons
  document.querySelectorAll('.alert-dismissible').forEach(alert => {
    const closeBtn = alert.querySelector('.alert-close');
    if (closeBtn) {
      closeBtn.addEventListener('click', () => {
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 200);
      });
    }
  });

  // 2. Modal Controller
  window.openModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add('active');
      modal.setAttribute('aria-hidden', 'false');
      const focusable = modal.querySelector('button, input, [tabindex="0"]');
      if (focusable) focusable.focus();
    }
  };

  window.closeModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.remove('active');
      modal.setAttribute('aria-hidden', 'true');
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

  // 3. Live Results Polling (Sensible 6-second interval)
  const resultsContainer = document.querySelector('[data-live-results]');
  if (resultsContainer) {
    const electionId = resultsContainer.dataset.electionId;
    const apiUrl = resultsContainer.dataset.apiUrl;
    let pollInterval = 6000;

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
      // Update total ballots and turnout
      const totalBallotsEl = document.getElementById('stat-total-ballots');
      if (totalBallotsEl) totalBallotsEl.textContent = tallies.total_ballots;

      const turnoutEl = document.getElementById('stat-turnout');
      if (turnoutEl) turnoutEl.textContent = `${tallies.turnout_percentage}%`;

      // Update candidate rows
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

    // Run poll
    const timer = setInterval(pollResults, pollInterval);
    window.addEventListener('beforeunload', () => clearInterval(timer));
  }
});
