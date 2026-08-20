/* ================================================
   StudentHub — faq.js
   Feature: Collapsible FAQ Accordion
   Used on: faq.html
   ================================================ */

document.addEventListener('DOMContentLoaded', function () {

    /* Select all FAQ accordion items */
    const faqItems = document.querySelectorAll('.faq-item');

    faqItems.forEach(function (item) {
        const questionBtn = item.querySelector('.faq-question');
        const answerDiv = item.querySelector('.faq-answer');

        if (!questionBtn || !answerDiv) return;

        /* Initial state: all answers closed */
        answerDiv.hidden = true;
        questionBtn.setAttribute('aria-expanded', 'false');

        questionBtn.addEventListener('click', function () {
            /* Toggle this item */
            const isOpen = !answerDiv.hidden;

            /* Optional: close all others first (accordion behaviour) */
            faqItems.forEach(function (other) {
                const otherBtn = other.querySelector('.faq-question');
                const otherAns = other.querySelector('.faq-answer');
                if (otherAns && other !== item) {
                    otherAns.hidden = true;
                    other.classList.remove('open');
                    if (otherBtn) otherBtn.setAttribute('aria-expanded', 'false');
                }
            });

            /* Toggle current item */
            answerDiv.hidden = isOpen;
            item.classList.toggle('open', !isOpen);
            questionBtn.setAttribute('aria-expanded', String(!isOpen));
        });

        /* Keyboard: Arrow Down opens, Escape closes */
        questionBtn.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !answerDiv.hidden) {
                answerDiv.hidden = true;
                item.classList.remove('open');
                questionBtn.setAttribute('aria-expanded', 'false');
            }
        });
    });
});
