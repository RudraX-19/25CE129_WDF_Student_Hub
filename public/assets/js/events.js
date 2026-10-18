/* ================================================
   StudentHub — events.js
   Feature: Modal popup for event registration
   Used on: events.html
   ================================================ */

document.addEventListener('DOMContentLoaded', function () {

    const overlay = document.getElementById('event-modal');
    const closeBtn = overlay ? overlay.querySelector('.modal-close') : null;
    const openBtns = document.querySelectorAll('.open-modal-btn');
    const modalTitle = document.getElementById('modal-event-name');

    /* Safety check */
    if (!overlay) return;

    /* ── Open modal ────────────────────────────────── */
    openBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            /* Pass event name from data-event attribute */
            const eventName = btn.getAttribute('data-event') || 'Selected Event';
            if (modalTitle) modalTitle.textContent = eventName;

            overlay.classList.add('open');
            overlay.removeAttribute('aria-hidden');

            /* Move focus into modal for accessibility */
            if (closeBtn) closeBtn.focus();
        });
    });

    /* ── Close modal (X button) ────────────────────── */
    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            closeModal();
        });
    }

    /* ── Close modal (click outside box) ──────────── */
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeModal();
    });

    /* ── Close modal (ESC key) ─────────────────────── */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('open')) {
            closeModal();
        }
    });

    /* ── Form submit (demo — prevent default) ──────── */
    const regForm = document.getElementById('event-register-form');
    if (regForm) {
        regForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const name = document.getElementById('reg-name').value.trim();
            /* Show a simple success message */
            const msgEl = document.getElementById('modal-success-msg');
            if (msgEl) {
                msgEl.textContent = 'Thank you, ' + name + '! Your registration has been received.';
                msgEl.hidden = false;
            }
            regForm.reset();
            /* Auto-close after 2 seconds */
            setTimeout(closeModal, 2000);
        });
    }

    /* ── Helper ────────────────────────────────────── */
    function closeModal() {
        overlay.classList.remove('open');
        overlay.setAttribute('aria-hidden', 'true');
        /* Return focus to the button that opened the modal */
        const lastOpened = document.querySelector('.open-modal-btn[data-last-focused]');
        if (lastOpened) {
            lastOpened.focus();
            lastOpened.removeAttribute('data-last-focused');
        }
    }
});
