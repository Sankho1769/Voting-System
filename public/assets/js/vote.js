/**
 * VerityVote - Ballot Casting and Double-Submission Defense
 */

document.addEventListener('DOMContentLoaded', () => {
  const ballotForm = document.getElementById('ballot-form');
  const candidateCards = document.querySelectorAll('.candidate-card');
  const submitBtn = document.getElementById('btn-submit-ballot');
  const reviewModal = document.getElementById('modal-confirm-vote');
  const selectedCandidateNameEl = document.getElementById('confirm-candidate-name');
  const finalConfirmBtn = document.getElementById('btn-final-cast-vote');

  if (!ballotForm) return;

  // 1. Interactive Candidate Selection
  candidateCards.forEach(card => {
    card.addEventListener('click', () => {
      const radio = card.querySelector('input[type="radio"]');
      if (radio) {
        radio.checked = true;
        candidateCards.forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');

        if (submitBtn) {
          submitBtn.removeAttribute('disabled');
        }
      }
    });

    // Keyboard support for candidate card (Space/Enter)
    card.addEventListener('keydown', (e) => {
      if (e.key === ' ' || e.key === 'Enter') {
        e.preventDefault();
        card.click();
      }
    });
  });

  // 2. Initial Submit Button Click -> Show Confirmation Modal
  if (submitBtn) {
    submitBtn.addEventListener('click', (e) => {
      e.preventDefault();
      const selectedRadio = ballotForm.querySelector('input[name="candidate_id"]:checked');
      if (!selectedRadio) {
        alert('Please select a candidate before proceeding.');
        return;
      }

      const selectedCard = selectedRadio.closest('.candidate-card');
      const candidateName = selectedCard.dataset.candidateName || 'Selected Candidate';

      if (selectedCandidateNameEl) {
        selectedCandidateNameEl.textContent = candidateName;
      }

      if (window.openModal) {
        window.openModal('modal-confirm-vote');
      }
    });
  }

  // 3. Final Confirmation in Modal -> Submit Form with Concurrency and Double-Click Lock
  if (finalConfirmBtn) {
    finalConfirmBtn.addEventListener('click', () => {
      finalConfirmBtn.setAttribute('disabled', 'true');
      finalConfirmBtn.textContent = 'Submitting Ballot...';

      if (submitBtn) {
        submitBtn.setAttribute('disabled', 'true');
      }

      ballotForm.submit();
    });
  }
});
