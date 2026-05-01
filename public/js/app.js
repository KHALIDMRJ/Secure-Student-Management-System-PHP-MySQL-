/* =========================================================
   Gestion des étudiants — Phase 3 client script
   - Dark mode toggle (applied pre-paint to avoid flash)
   - Sidebar toggle (mobile drawer + backdrop + Esc)
   - Toast notifications from PHP flash payload
   - Live filter on the students table
   - Delete confirmation guard
   - Active sidebar link highlighter
   ========================================================= */

console.log('Dark mode JS loaded');

/* =========================================================
   Dark mode — defined FIRST so it can run before DOMContentLoaded
   to apply the saved preference before paint (no flash).
   ========================================================= */
const DARK_KEY = 'studentms_dark_mode';

/**
 * Apply (or remove) dark mode.
 *
 * @param {boolean} enable  Whether to turn dark mode on.
 * @param {boolean} animate When true, briefly attach `.theme-transitioning`
 *                          to <body> so colours/borders ease between themes.
 *                          Pass false on the pre-paint call so the saved
 *                          theme appears instantly with no flash.
 */
function applyDarkMode(enable, animate) {
    if (animate && document.body) {
        document.body.classList.add('theme-transitioning');
        // Slightly longer than the longest CSS transition (0.25s) so every
        // property finishes interpolating before the class is removed.
        setTimeout(function () {
            document.body.classList.remove('theme-transitioning');
        }, 300);
    }

    if (document.body) {
        document.body.classList.toggle('dark-mode', enable);
    }

    const icon = document.getElementById('darkModeIcon');
    if (icon) {
        icon.className = enable ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
    }

    // The toggle itself gets a "lit" state when dark mode is active.
    const btn = document.getElementById('darkModeToggle');
    if (btn) {
        btn.classList.toggle('btn-dark-active', enable);
    }

    try {
        localStorage.setItem(DARK_KEY, enable ? '1' : '0');
    } catch (e) {
        /* localStorage unavailable (private mode, quota) — silent no-op */
    }
}

// Run immediately (before DOMContentLoaded) to avoid a flash of light theme.
// `animate=false` ensures the saved theme paints instantly, with no transition.
try {
    applyDarkMode(localStorage.getItem(DARK_KEY) === '1', false);
} catch (e) {
    /* no-op */
}

/* =========================================================
   Everything else waits for the DOM so getElementById works.
   ========================================================= */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    initDarkModeToggle();
    initSidebar();
    renderFlashToasts();
    initTableFilter();
    initDeleteConfirm();
    initActiveSidebarLink();

    /* ----- Dark mode click binding ----- */
    function initDarkModeToggle() {
        const toggleBtn = document.getElementById('darkModeToggle');
        if (!toggleBtn) return;

        toggleBtn.addEventListener('click', function () {
            applyDarkMode(!document.body.classList.contains('dark-mode'), true);
        });
    }

    /* ----- Sidebar (mobile drawer) ----- */
    function initSidebar() {
        const sidebar  = document.getElementById('sidebar');
        const toggle   = document.getElementById('sidebarToggle');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (!sidebar || !toggle || !backdrop) return;

        const open = function () {
            sidebar.classList.add('show');
            backdrop.classList.add('show');
            toggle.setAttribute('aria-expanded', 'true');
        };
        const close = function () {
            sidebar.classList.remove('show');
            backdrop.classList.remove('show');
            toggle.setAttribute('aria-expanded', 'false');
        };

        toggle.addEventListener('click', function () {
            sidebar.classList.contains('show') ? close() : open();
        });
        backdrop.addEventListener('click', close);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && sidebar.classList.contains('show')) close();
        });

        // Auto-close drawer when crossing back to desktop breakpoint.
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 992) close();
        });
    }

    /* ----- Toast notifications from PHP flash payload ----- */
    function renderFlashToasts() {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const raw = document.body.getAttribute('data-flashes');
        if (!raw) return;

        let flashes = [];
        try { flashes = JSON.parse(raw) || []; } catch (e) { flashes = []; }
        if (!Array.isArray(flashes) || flashes.length === 0) return;

        flashes.forEach(function (f) {
            showToast(f.type || 'info', f.msg || '');
        });

        // Strip ?ajout=ok / ?modifier=ok / ?supprimer=ok so refresh doesn't re-toast.
        if (window.history && window.history.replaceState) {
            try {
                const url = new URL(window.location.href);
                ['ajout', 'modifier', 'supprimer'].forEach(function (k) {
                    url.searchParams.delete(k);
                });
                window.history.replaceState({}, document.title, url.pathname + url.search + url.hash);
            } catch (e) { /* no-op */ }
        }
    }

    function showToast(type, message) {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const icons = {
            success: 'bi-check-circle-fill',
            error:   'bi-exclamation-octagon-fill',
            info:    'bi-info-circle-fill'
        };
        const iconClass = icons[type] || icons.info;

        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.setAttribute('role', 'status');
        toast.innerHTML =
            '<span class="toast-icon"><i class="bi ' + iconClass + '"></i></span>' +
            '<div class="toast-body"></div>' +
            '<button type="button" class="toast-close" aria-label="Fermer">' +
                '<i class="bi bi-x-lg"></i>' +
            '</button>';
        toast.querySelector('.toast-body').textContent = message;

        const dismiss = function () {
            toast.style.transition = 'opacity 0.18s, transform 0.18s';
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(8px)';
            setTimeout(function () { toast.remove(); }, 200);
        };
        toast.querySelector('.toast-close').addEventListener('click', dismiss);
        setTimeout(dismiss, 4500);

        container.appendChild(toast);
    }

    /* ----- Live filter on students table ----- */
    function initTableFilter() {
        const input = document.getElementById('studentFilter');
        const table = document.querySelector('.students-table');
        if (!input || !table) return;

        const emptyHint = document.getElementById('filterEmpty');
        const rows = table.querySelectorAll('tbody tr');

        input.addEventListener('input', function () {
            const q = input.value.trim().toLowerCase();
            let visible = 0;
            rows.forEach(function (row) {
                const text = (row.textContent || '').toLowerCase();
                const match = q === '' || text.indexOf(q) !== -1;
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            if (emptyHint) emptyHint.hidden = visible !== 0;
        });
    }

    /* ----- Delete confirmation guard ----- */
    function initDeleteConfirm() {
        const form = document.querySelector('form[data-confirm]');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            const msg = form.getAttribute('data-confirm') || 'Êtes-vous sûr ?';
            if (!window.confirm(msg)) {
                e.preventDefault();
            }
        });
    }

    /* ----- Active sidebar link highlighter -----
       Matches ?page=<name> in the URL against sidebar links and adds .active.
       Server-side highlighting may already apply — this is a defensive client
       fallback and a no-op when the active class is already present. */
    function initActiveSidebarLink() {
        const links = document.querySelectorAll('.sidebar-menu a');
        if (!links.length) return;

        const params = new URLSearchParams(window.location.search);
        const current = params.get('page') || 'index';

        links.forEach(function (link) {
            const href = link.getAttribute('href') || '';
            const match = href.match(/[?&]page=([^&]+)/);
            const target = match ? match[1] : null;
            if (target === current) {
                link.classList.add('active');
            }
        });
    }
});

/* =========================================================
   Phase 5 — search / filter UX on the student list page.
   Wrapped in IIFEs so they don't pollute global scope; each
   block no-ops when its target elements aren't on the page.
   ========================================================= */

// === SEARCH DEBOUNCE ===
// Auto-submit the filter form 500ms after the user stops typing.
(function () {
    const searchInput = document.getElementById('search');
    const filterForm  = document.getElementById('filterForm');
    if (!searchInput || !filterForm) return;

    let debounceTimer;
    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            // Reset to page 1 when the search term changes.
            const pInput = filterForm.querySelector('input[name="p"]');
            if (pInput) pInput.value = '1';
            filterForm.submit();
        }, 500);
    });
})();

// === FILIÈRE BADGE CLICK ===
// Clicking a filière pill in the table sets the filter and submits.
(function () {
    document.querySelectorAll('.badge-filiere').forEach(function (badge) {
        badge.addEventListener('click', function (e) {
            e.preventDefault();
            const filterForm = document.getElementById('filterForm');
            if (!filterForm) return;

            const select = filterForm.querySelector('select[name="filiere"]');
            if (select) {
                select.value = badge.textContent.trim();
                filterForm.submit();
            }
        });
    });
})();

// === SELECT AUTO-SUBMIT (CSP-compatible) ===
// The PHP view sets onchange="..." on #filiere and #perpage as a fallback.
// Our CSP has no 'unsafe-inline' in script-src, so those inline handlers
// are blocked. Re-bind them via addEventListener so the selects still
// auto-submit when the user picks a value.
(function () {
    const filterForm = document.getElementById('filterForm');
    if (!filterForm) return;

    ['filiere', 'perpage'].forEach(function (id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('change', function () {
            filterForm.submit();
        });
    });
})();
