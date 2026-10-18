/* ================================================
   StudentHub — events-fetch.js  (Practical 6)
   Renders Events from events.json using Fetch API.
   Features: Search, Filter by Category/Status,
             Sort by Date/Title, Pagination (6/page)
   ================================================ */

document.addEventListener('DOMContentLoaded', function () {

    // --- State ---
    let allEvents = [];
    let filteredEvents = [];
    const PAGE_SIZE = 6;
    let currentPage = 1;

    // --- DOM References ---
    const container = document.getElementById('events-container');
    const searchInput = document.getElementById('events-search');
    const categoryFilter = document.getElementById('events-category');
    const statusFilter = document.getElementById('events-status');
    const sortSelect = document.getElementById('events-sort');
    const paginationContainer = document.getElementById('events-pagination');
    const resultsCount = document.getElementById('events-count');

    if (!container) return; // Not on events page

    const DATA_URL = '../assets/data/events.json';

    // --- 1. Fetch Data ---
    showLoading(container, 'Loading events…');

    fetchJSON(DATA_URL)
        .then(data => {
            allEvents = data;
            applyFiltersAndRender();
        })
        .catch(err => {
            showError(container, `Could not load events. ${err.message}`);
        });

    // --- 2. Event Listeners ---
    if (searchInput) {
        searchInput.addEventListener('input', debounce(() => {
            currentPage = 1;
            applyFiltersAndRender();
        }, 300));
    }

    if (categoryFilter) categoryFilter.addEventListener('change', () => { currentPage = 1; applyFiltersAndRender(); });
    if (statusFilter) statusFilter.addEventListener('change', () => { currentPage = 1; applyFiltersAndRender(); });
    if (sortSelect) sortSelect.addEventListener('change', () => { currentPage = 1; applyFiltersAndRender(); });

    // --- 3. Filter + Sort + Paginate ---
    function applyFiltersAndRender() {
        const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const category = categoryFilter ? categoryFilter.value : '';
        const status = statusFilter ? statusFilter.value : '';
        const sort = sortSelect ? sortSelect.value : 'date-asc';

        // Filter
        filteredEvents = allEvents.filter(ev => {
            const matchSearch = !query ||
                ev.title.toLowerCase().includes(query) ||
                ev.description.toLowerCase().includes(query) ||
                ev.venue.toLowerCase().includes(query);
            const matchCategory = !category || ev.category === category;
            const matchStatus = !status || ev.status === status;
            return matchSearch && matchCategory && matchStatus;
        });

        // Sort
        filteredEvents.sort((a, b) => {
            if (sort === 'date-asc') return new Date(a.date) - new Date(b.date);
            if (sort === 'date-desc') return new Date(b.date) - new Date(a.date);
            if (sort === 'title-asc') return a.title.localeCompare(b.title);
            if (sort === 'title-desc') return b.title.localeCompare(a.title);
            return 0;
        });

        renderPage();
    }

    // --- 4. Render current page ---
    function renderPage() {
        const total = filteredEvents.length;
        const totalPages = Math.ceil(total / PAGE_SIZE);
        if (currentPage > totalPages) currentPage = Math.max(1, totalPages);

        const start = (currentPage - 1) * PAGE_SIZE;
        const pageItems = filteredEvents.slice(start, start + PAGE_SIZE);

        // Results count
        if (resultsCount) {
            resultsCount.textContent = `Showing ${pageItems.length} of ${total} event${total !== 1 ? 's' : ''}`;
        }

        if (total === 0) {
            container.innerHTML = '<p class="fetch-empty">No events match your search. Try different filters.</p>';
            if (paginationContainer) paginationContainer.innerHTML = '';
            return;
        }

        // Render cards
        container.innerHTML = pageItems.map(ev => `
            <article class="data-card event-card" aria-label="${ev.title}">
                <div class="card-badge badge-${ev.category.toLowerCase().replace(/\s+/g, '-')}">${ev.category}</div>
                <h3 class="card-title">${ev.title}</h3>
                <p class="card-meta">
                    <span>📅 ${formatDate(ev.date)}</span>
                    <span>📍 ${ev.venue}</span>
                    <span>🎟 ${ev.seats} seats</span>
                </p>
                <p class="card-desc">${ev.description}</p>
                <div class="card-footer">
                    <span class="status-badge status-${ev.status}">${capitalize(ev.status)}</span>
                    ${ev.status === 'upcoming' ? `<button class="open-modal-btn btn-register" data-event="${ev.title}" aria-label="Register for ${ev.title}">Register Now</button>` : '<span class="btn-disabled">Completed</span>'}
                </div>
            </article>
        `).join('');

        renderPagination(totalPages);
        attachModalButtons();
    }

    // --- 5. Pagination ---
    function renderPagination(totalPages) {
        if (!paginationContainer) return;
        if (totalPages <= 1) { paginationContainer.innerHTML = ''; return; }

        let html = `<nav class="pagination" aria-label="Events pagination"><ul>`;
        html += `<li><button class="page-btn" ${currentPage === 1 ? 'disabled' : ''} data-page="${currentPage - 1}" aria-label="Previous page">‹ Prev</button></li>`;

        for (let i = 1; i <= totalPages; i++) {
            html += `<li><button class="page-btn ${i === currentPage ? 'page-active' : ''}" data-page="${i}" aria-label="Page ${i}" ${i === currentPage ? 'aria-current="page"' : ''}>${i}</button></li>`;
        }

        html += `<li><button class="page-btn" ${currentPage === totalPages ? 'disabled' : ''} data-page="${currentPage + 1}" aria-label="Next page">Next ›</button></li>`;
        html += `</ul></nav>`;
        paginationContainer.innerHTML = html;

        paginationContainer.querySelectorAll('.page-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!btn.disabled) {
                    currentPage = parseInt(btn.dataset.page);
                    applyFiltersAndRender();
                    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    }

    // --- 6. Re-attach modal buttons after render ---
    function attachModalButtons() {
        container.querySelectorAll('.open-modal-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const eventName = btn.dataset.event;
                const modal = document.getElementById('event-modal');
                const modalEventName = document.getElementById('modal-event-name');
                if (modal && modalEventName) {
                    modalEventName.textContent = eventName;
                    modal.classList.add('open');
                    modal.setAttribute('aria-hidden', 'false');
                    modal.querySelector('.modal-close')?.focus();
                }
            });
        });
    }

    // --- Helpers ---
    function formatDate(dateStr) {
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'long', year: 'numeric' });
    }

    function capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
});
