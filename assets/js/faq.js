/* ================================================
   StudentHub — faq.js  (Updated: Practical 6)
   Feature: Load FAQs from faqs.json via Fetch API.
             Render as accordion with search & filter.
   ================================================ */

document.addEventListener('DOMContentLoaded', function () {

    const faqContainer = document.getElementById('faq-container');

    // If static accordion already exists (no container), run legacy mode
    if (!faqContainer) {
        initStaticAccordion();
        return;
    }

    const searchInput = document.getElementById('faq-search');
    const categoryFilter = document.getElementById('faq-category');
    const resultsCount = document.getElementById('faq-count');

    let allFaqs = [];

    const DATA_URL = '../assets/data/faqs.json';

    showLoading(faqContainer, 'Loading FAQs…');

    fetchJSON(DATA_URL)
        .then(data => {
            allFaqs = data;
            applyFiltersAndRender();
        })
        .catch(err => {
            showError(faqContainer, `Could not load FAQs. ${err.message}`);
        });

    if (searchInput) searchInput.addEventListener('input', debounce(() => applyFiltersAndRender(), 300));
    if (categoryFilter) categoryFilter.addEventListener('change', () => applyFiltersAndRender());

    function applyFiltersAndRender() {
        const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const category = categoryFilter ? categoryFilter.value : '';

        const filtered = allFaqs.filter(faq => {
            const matchSearch = !query ||
                faq.question.toLowerCase().includes(query) ||
                faq.answer.toLowerCase().includes(query);
            const matchCategory = !category || faq.category === category;
            return matchSearch && matchCategory;
        });

        if (resultsCount) {
            resultsCount.textContent = `${filtered.length} result${filtered.length !== 1 ? 's' : ''} found`;
        }

        if (filtered.length === 0) {
            faqContainer.innerHTML = '<p class="fetch-empty">No FAQs match your search. Try different keywords.</p>';
            return;
        }

        faqContainer.innerHTML = filtered.map((faq, i) => `
            <div class="faq-item" id="faq-${faq.id}">
                <button class="faq-question" id="faq-btn-${faq.id}"
                    aria-expanded="false" aria-controls="faq-ans-${faq.id}">
                    ${faq.question}
                    <span class="faq-arrow" aria-hidden="true">▼</span>
                </button>
                <div class="faq-answer" id="faq-ans-${faq.id}" role="region"
                     aria-labelledby="faq-btn-${faq.id}">
                    <p>${faq.answer}</p>
                    <span class="faq-category-badge">${faq.category}</span>
                </div>
            </div>
        `).join('');

        initStaticAccordion();
    }

    // --- Accordion init (works for both static and dynamically rendered items) ---
    function initStaticAccordion() {
        const faqItems = document.querySelectorAll('.faq-item');
        faqItems.forEach(function (item) {
            const questionBtn = item.querySelector('.faq-question');
            const answerDiv = item.querySelector('.faq-answer');

            if (!questionBtn || !answerDiv) return;

            answerDiv.hidden = true;
            questionBtn.setAttribute('aria-expanded', 'false');

            questionBtn.addEventListener('click', function () {
                const isOpen = !answerDiv.hidden;

                faqItems.forEach(function (other) {
                    const otherBtn = other.querySelector('.faq-question');
                    const otherAns = other.querySelector('.faq-answer');
                    if (otherAns && other !== item) {
                        otherAns.hidden = true;
                        other.classList.remove('open');
                        if (otherBtn) otherBtn.setAttribute('aria-expanded', 'false');
                    }
                });

                answerDiv.hidden = isOpen;
                item.classList.toggle('open', !isOpen);
                questionBtn.setAttribute('aria-expanded', String(!isOpen));
            });

            questionBtn.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !answerDiv.hidden) {
                    answerDiv.hidden = true;
                    item.classList.remove('open');
                    questionBtn.setAttribute('aria-expanded', 'false');
                }
            });
        });
    }
});
