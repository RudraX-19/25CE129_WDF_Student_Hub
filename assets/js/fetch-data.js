/* ================================================
   StudentHub — fetch-data.js  (Practical 6)
   Shared Fetch API utility module.
   Provides: fetchJSON(), localStorage caching.
   ================================================ */

const CACHE_PREFIX = 'studenthub_cache_';
const CACHE_TTL_MS = 5 * 60 * 1000; // 5 minutes

/**
 * Fetch JSON from a URL with localStorage caching (Advanced Extension).
 * @param {string} url - JSON file URL
 * @returns {Promise<any>} Parsed JSON data
 */
async function fetchJSON(url) {
    const cacheKey = CACHE_PREFIX + url;

    // --- Try cache first (Advanced: offline-like display) ---
    try {
        const cached = localStorage.getItem(cacheKey);
        if (cached) {
            const { data, timestamp } = JSON.parse(cached);
            if (Date.now() - timestamp < CACHE_TTL_MS) {
                console.info(`[fetchJSON] Serving from cache: ${url}`);
                return data;
            }
        }
    } catch (_) {
        // Ignore cache read errors
    }

    // --- Fetch from network ---
    const response = await fetch(url);
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}: Failed to load ${url}`);
    }
    const data = await response.json();

    // --- Store in cache ---
    try {
        localStorage.setItem(cacheKey, JSON.stringify({ data, timestamp: Date.now() }));
    } catch (_) {
        // Ignore quota/write errors
    }

    return data;
}

/**
 * Show a loading skeleton inside an element.
 * @param {HTMLElement} container
 * @param {string} message
 */
function showLoading(container, message = 'Loading data…') {
    container.innerHTML = `<div class="fetch-loading" role="status" aria-live="polite">
        <div class="fetch-spinner"></div>
        <p>${message}</p>
    </div>`;
}

/**
 * Show an error message inside an element.
 * @param {HTMLElement} container
 * @param {string} message
 */
function showError(container, message = 'Failed to load data. Please try again.') {
    container.innerHTML = `<div class="fetch-error" role="alert">
        <p>⚠ ${message}</p>
    </div>`;
}

/**
 * Debounce a function call.
 * @param {Function} fn
 * @param {number} delay ms
 */
function debounce(fn, delay = 300) {
    let timer;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
}
