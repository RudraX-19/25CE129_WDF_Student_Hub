/* ================================================
   StudentHub — main.js
   Features:
     1. Hamburger menu (mobile nav toggle)
     2. Dark / Light theme with localStorage
     3. Notification banner dismiss
   ================================================ */

document.addEventListener('DOMContentLoaded', function () {

    /* ── 1. HAMBURGER MENU ──────────────────────────────
       Injects a hamburger <button> into the main nav
       and toggles the ul visibility on mobile.         */

    const nav = document.querySelector('nav[aria-label="Main Navigation"]');
    const navUl = nav ? nav.querySelector('ul') : null;

    if (nav && navUl) {
        // Create and inject button
        const hamburger = document.createElement('button');
        hamburger.className = 'hamburger';
        hamburger.id = 'hamburger-btn';
        hamburger.innerHTML = '&#9776;'; // ☰
        hamburger.setAttribute('aria-label', 'Toggle navigation menu');
        hamburger.setAttribute('aria-expanded', 'false');
        nav.insertBefore(hamburger, navUl);

        // Toggle nav on click
        hamburger.addEventListener('click', function () {
            const isOpen = navUl.classList.toggle('nav-open');
            hamburger.setAttribute('aria-expanded', String(isOpen));
            hamburger.innerHTML = isOpen ? '&times;' : '&#9776;';
        });

        // Close nav when a link is clicked (UX: prevents nav staying open)
        navUl.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                navUl.classList.remove('nav-open');
                hamburger.innerHTML = '&#9776;';
                hamburger.setAttribute('aria-expanded', 'false');
            });
        });
    }

    /* ── 2. DARK / LIGHT THEME ──────────────────────────
       Injects a theme toggle button into the header.
       Persists preference in localStorage.            */

    const header = document.querySelector('header');
    if (header) {
        // Create toggle button
        const themeBtn = document.createElement('button');
        themeBtn.id = 'theme-toggle';

        // Apply saved preference immediately (before paint)
        const savedTheme = localStorage.getItem('studenthub-theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const isDarkOnLoad = savedTheme === 'dark' || (!savedTheme && prefersDark);

        if (isDarkOnLoad) {
            document.body.classList.add('dark-mode');
            themeBtn.innerHTML = '&#9728;'; // ☀
            themeBtn.setAttribute('aria-label', 'Switch to light mode');
        } else {
            themeBtn.innerHTML = '&#127769;'; // 🌙
            themeBtn.setAttribute('aria-label', 'Switch to dark mode');
        }

        // Inject into existing .user-info or directly into header
        const userInfo = header.querySelector('.user-info');
        if (userInfo) {
            userInfo.insertBefore(themeBtn, userInfo.firstChild);
        } else {
            header.appendChild(themeBtn);
        }

        // Click handler
        themeBtn.addEventListener('click', function () {
            const nowDark = document.body.classList.toggle('dark-mode');
            localStorage.setItem('studenthub-theme', nowDark ? 'dark' : 'light');
            themeBtn.innerHTML = nowDark ? '&#9728;' : '&#127769;';
            themeBtn.setAttribute('aria-label', nowDark ? 'Switch to light mode' : 'Switch to dark mode');
        });
    }

    /* ── 3. NOTIFICATION BANNER DISMISS ────────────────
       Adds a close button handler for any notification
       banner that exists on the current page.         */

    const closeBannerBtn = document.querySelector('.close-banner');
    const banner = document.querySelector('.notification-banner');

    if (closeBannerBtn && banner) {
        closeBannerBtn.addEventListener('click', function () {
            banner.setAttribute('aria-hidden', 'true');
            banner.classList.add('hidden');
        });
    }

});
