/* ================================================
   StudentHub — students-fetch.js  (Practical 6)
   Renders Student Profiles from students.json.
   Features: Search by name/email/rollNo,
             Filter by Course & Year,
             Sort by Name/GPA,
             Pagination (6 per page)
   ================================================ */

document.addEventListener('DOMContentLoaded', function () {

    let allStudents = [];
    let filteredStudents = [];
    const PAGE_SIZE = 6;
    let currentPage = 1;

    const container = document.getElementById('students-container');
    const searchInput = document.getElementById('students-search');
    const courseFilter = document.getElementById('students-course');
    const yearFilter = document.getElementById('students-year');
    const sortSelect = document.getElementById('students-sort');
    const paginationContainer = document.getElementById('students-pagination');
    const resultsCount = document.getElementById('students-count');

    if (!container) return;

    const DATA_URL = '../assets/data/students.json';

    showLoading(container, 'Loading student profiles…');

    fetchJSON(DATA_URL)
        .then(data => {
            allStudents = data;
            populateCourseDropdown(data);
            applyFiltersAndRender();
        })
        .catch(err => {
            showError(container, `Could not load student data. ${err.message}`);
        });

    // --- Event Listeners ---
    if (searchInput) searchInput.addEventListener('input', debounce(() => { currentPage = 1; applyFiltersAndRender(); }, 300));
    if (courseFilter) courseFilter.addEventListener('change', () => { currentPage = 1; applyFiltersAndRender(); });
    if (yearFilter) yearFilter.addEventListener('change', () => { currentPage = 1; applyFiltersAndRender(); });
    if (sortSelect) sortSelect.addEventListener('change', () => { currentPage = 1; applyFiltersAndRender(); });

    // Populate course dropdown dynamically from data
    function populateCourseDropdown(data) {
        if (!courseFilter) return;
        const courses = [...new Set(data.map(s => s.course))].sort();
        courses.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c;
            opt.textContent = c;
            courseFilter.appendChild(opt);
        });
    }

    function applyFiltersAndRender() {
        const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const course = courseFilter ? courseFilter.value : '';
        const year = yearFilter ? yearFilter.value : '';
        const sort = sortSelect ? sortSelect.value : 'name-asc';

        filteredStudents = allStudents.filter(s => {
            const matchSearch = !query ||
                s.name.toLowerCase().includes(query) ||
                s.rollNo.toLowerCase().includes(query) ||
                s.email.toLowerCase().includes(query);
            const matchCourse = !course || s.course === course;
            const matchYear = !year || String(s.year) === year;
            return matchSearch && matchCourse && matchYear;
        });

        filteredStudents.sort((a, b) => {
            if (sort === 'name-asc') return a.name.localeCompare(b.name);
            if (sort === 'name-desc') return b.name.localeCompare(a.name);
            if (sort === 'gpa-desc') return b.gpa - a.gpa;
            if (sort === 'gpa-asc') return a.gpa - b.gpa;
            return 0;
        });

        renderPage();
    }

    function renderPage() {
        const total = filteredStudents.length;
        const totalPages = Math.ceil(total / PAGE_SIZE);
        if (currentPage > totalPages) currentPage = Math.max(1, totalPages);

        const start = (currentPage - 1) * PAGE_SIZE;
        const pageItems = filteredStudents.slice(start, start + PAGE_SIZE);

        if (resultsCount) {
            resultsCount.textContent = `Showing ${pageItems.length} of ${total} student${total !== 1 ? 's' : ''}`;
        }

        if (total === 0) {
            container.innerHTML = '<p class="fetch-empty">No students match your search. Try different filters.</p>';
            if (paginationContainer) paginationContainer.innerHTML = '';
            return;
        }

        const gpaClass = gpa => gpa >= 9 ? 'gpa-excellent' : gpa >= 8 ? 'gpa-good' : gpa >= 7 ? 'gpa-average' : 'gpa-low';

        container.innerHTML = pageItems.map(s => `
            <article class="data-card student-card" aria-label="Profile: ${s.name}">
                <div class="student-avatar" aria-hidden="true">${s.name.charAt(0)}</div>
                <div class="student-info">
                    <h3 class="card-title">${s.name}</h3>
                    <p class="card-meta">
                        <span>🎓 ${s.rollNo}</span>
                        <span>📚 ${s.course}</span>
                        <span>📅 Year ${s.year}</span>
                    </p>
                    <p class="card-meta"><span>✉ ${s.email}</span></p>
                    <div class="card-footer">
                        <span class="gpa-badge ${gpaClass(s.gpa)}">GPA: ${s.gpa.toFixed(1)}</span>
                        <span class="status-badge status-${s.status.toLowerCase()}">${s.status}</span>
                    </div>
                </div>
            </article>
        `).join('');

        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
        if (!paginationContainer) return;
        if (totalPages <= 1) { paginationContainer.innerHTML = ''; return; }

        let html = `<nav class="pagination" aria-label="Students pagination"><ul>`;
        html += `<li><button class="page-btn" ${currentPage === 1 ? 'disabled' : ''} data-page="${currentPage - 1}">‹ Prev</button></li>`;
        for (let i = 1; i <= totalPages; i++) {
            html += `<li><button class="page-btn ${i === currentPage ? 'page-active' : ''}" data-page="${i}" ${i === currentPage ? 'aria-current="page"' : ''}>${i}</button></li>`;
        }
        html += `<li><button class="page-btn" ${currentPage === totalPages ? 'disabled' : ''} data-page="${currentPage + 1}">Next ›</button></li>`;
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
});
