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

    // Notify any chart / canvas / theme-aware listeners that the theme changed,
    // so they can rebuild themselves with the new palette.
    try {
        document.dispatchEvent(new CustomEvent('themeChanged', { detail: { dark: enable } }));
    } catch (e) {
        /* CustomEvent not supported — non-fatal */
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

/* =========================================================
   Phase 6 — Dashboard charts (Chart.js)

   Reads pre-encoded JSON from data-* attributes on #chartData
   (no inline scripts, CSP-safe). Rebuilds both charts on the
   `themeChanged` custom event so colors track the theme.
   The IIFE no-ops on non-dashboard pages.
   ========================================================= */
(function () {
    const chartDataEl = document.getElementById('chartData');
    if (!chartDataEl || typeof Chart === 'undefined') return;

    // Parse data once, fail-soft on malformed JSON
    let filiereLabels = [];
    let filiereData   = [];
    let monthlyLabels = [];
    let monthlyData   = [];
    try {
        filiereLabels = JSON.parse(chartDataEl.dataset.filiereLabels || '[]');
        filiereData   = JSON.parse(chartDataEl.dataset.filiereData   || '[]');
        monthlyLabels = JSON.parse(chartDataEl.dataset.monthlyLabels || '[]');
        monthlyData   = JSON.parse(chartDataEl.dataset.monthlyData   || '[]');
    } catch (e) {
        console.error('Chart data malformed', e);
        return;
    }

    let filiereChart = null;
    let monthlyChart = null;

    /* ----- Theme-aware color helpers ----- */
    function isDark()      { return document.body.classList.contains('dark-mode'); }
    function gridColor()   { return isDark() ? '#30363d' : '#e5e7eb'; }
    function tickColor()   { return isDark() ? '#c9d1d9' : '#6b7280'; }
    function accentColor() { return isDark() ? '#818cf8' : '#4f46e5'; }
    function accentFill()  { return isDark() ? 'rgba(129,140,248,0.18)' : 'rgba(79,70,229,0.12)'; }

    function tooltipOpts() {
        return {
            backgroundColor: isDark() ? '#1c2333' : '#1e1b4b',
            titleColor: '#fff',
            bodyColor: '#e2e8f0',
            padding: 10,
            cornerRadius: 8,
            displayColors: false
        };
    }

    /* Per-bar color picker: cycles through a 5-color palette
       calibrated for each theme so bars stay distinguishable. */
    function barColor(ctx) {
        const i = ctx.dataIndex || 0;
        const colors = isDark()
            ? ['rgba(129,140,248,0.7)',
               'rgba(63,185,80,0.7)',
               'rgba(210,153,34,0.7)',
               'rgba(248,81,73,0.7)',
               'rgba(88,166,255,0.7)']
            : ['rgba(79,70,229,0.75)',
               'rgba(16,185,129,0.75)',
               'rgba(245,158,11,0.75)',
               'rgba(239,68,68,0.75)',
               'rgba(59,130,246,0.75)'];
        return colors[i % colors.length];
    }

    /* Apply theme-aware Chart.js global defaults (font, base color). */
    function applyChartDefaults() {
        if (!Chart || !Chart.defaults) return;
        Chart.defaults.font.family = "'Inter', system-ui, -apple-system, sans-serif";
        Chart.defaults.color = tickColor();
    }

    /* ----- Bar chart: students per filière ----- */
    function buildFiliereChart() {
        const canvas = document.getElementById('filiereChart');
        if (!canvas || filiereLabels.length === 0) return;

        // Destroy previous instance to prevent memory leaks on rebuild.
        if (filiereChart) {
            filiereChart.destroy();
            filiereChart = null;
        }

        filiereChart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: filiereLabels,
                datasets: [{
                    label: 'Étudiants',
                    data: filiereData,
                    backgroundColor: barColor,
                    borderRadius: 6,
                    maxBarThickness: 48
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: Object.assign(tooltipOpts(), {
                        callbacks: {
                            label: function (ctx) {
                                const v = ctx.parsed.y;
                                return ' ' + v + ' étudiant' + (v > 1 ? 's' : '');
                            }
                        }
                    })
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: tickColor(), precision: 0 },
                        grid:  { color: gridColor() }
                    },
                    x: {
                        ticks: { color: tickColor() },
                        grid:  { display: false }
                    }
                }
            }
        });
    }

    /* ----- Line chart: monthly registrations ----- */
    function buildMonthlyChart() {
        const canvas = document.getElementById('monthlyChart');
        if (!canvas || monthlyLabels.length === 0) return;

        if (monthlyChart) {
            monthlyChart.destroy();
            monthlyChart = null;
        }

        monthlyChart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Inscriptions',
                    data: monthlyData,
                    borderColor: accentColor(),
                    backgroundColor: accentFill(),
                    pointBackgroundColor: accentColor(),
                    pointBorderColor: isDark() ? '#0d1117' : '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    tension: 0.35,
                    fill: true,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: Object.assign(tooltipOpts(), {
                        callbacks: {
                            label: function (ctx) {
                                const v = ctx.parsed.y;
                                return ' ' + v + ' inscription' + (v > 1 ? 's' : '');
                            }
                        }
                    })
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: tickColor(), precision: 0 },
                        grid:  { color: gridColor() }
                    },
                    x: {
                        ticks: { color: tickColor() },
                        grid:  { color: gridColor() }
                    }
                }
            }
        });
    }

    /* ----- Initial render ----- */
    applyChartDefaults();
    buildFiliereChart();
    buildMonthlyChart();

    /* ----- Theme switch: rebuild both with the new palette ----- */
    document.addEventListener('themeChanged', function () {
        applyChartDefaults();
        buildFiliereChart();
        buildMonthlyChart();
    });
})();

/* =========================================================
   Phase 8 — SQL Console (full-power runner)

   Editor enhancements (CSP-safe — no inline JS in PHP):
   - Live line-number gutter
   - Auto-grow textarea height
   - Tab key inserts 4 spaces (no focus loss)
   - Ctrl/Cmd+Enter executes (uses requestSubmit so the destructive
     check still fires)
   - Clear / Copy / Example-fill / History-fill chips
   - Bootstrap modal confirmation for DELETE / DROP / TRUNCATE / ALTER
   - Client-side CSV export of the result table

   The IIFE no-ops on every page that doesn't have the editor.
   ========================================================= */
(function () {
    const editor = document.getElementById('sqlEditor');
    if (!editor) return;

    const form           = document.getElementById('sqlForm');
    const lineNums       = document.getElementById('lineNumbers');
    const clearBtn       = document.getElementById('clearBtn');
    const copyBtn        = document.getElementById('copyBtn');
    const exportBtn      = document.getElementById('exportCsv');
    const exampleBtns    = document.querySelectorAll('.sql-example-chip');
    const confirmModalEl = document.getElementById('sqlConfirmModal');
    const confirmBadge   = document.getElementById('sqlConfirmBadge');
    const confirmPreview = document.getElementById('sqlConfirmPreview');
    const confirmExecBtn = document.getElementById('sqlConfirmExecuteBtn');

    // Statements that require confirmation before execution.
    const DESTRUCTIVE_RE = /^\s*(DELETE|DROP|TRUNCATE|ALTER)\b/i;

    // Map keyword → badge color modifier (mirrors PHP's query_type_meta)
    const TYPE_CLASS = {
        DELETE: 'delete', DROP: 'drop', TRUNCATE: 'truncate', ALTER: 'alter'
    };

    /* ----- Live line-number gutter ----- */
    function updateLineNumbers() {
        const lines = (editor.value.match(/\n/g) || []).length + 1;
        let out = '';
        for (let i = 1; i <= lines; i++) {
            out += i + (i < lines ? '\n' : '');
        }
        if (lineNums) lineNums.textContent = out;
    }

    /* ----- Auto-grow height to fit content ----- */
    function autoGrow() {
        editor.style.height = 'auto';
        editor.style.height = editor.scrollHeight + 'px';
    }

    function refresh() { updateLineNumbers(); autoGrow(); }

    editor.addEventListener('input', refresh);
    refresh(); // initial sizing for any pre-filled value

    /* ----- Keyboard shortcuts -----
       Tab → 4 spaces; Ctrl/Cmd+Enter → submit (via requestSubmit so the
       destructive-query interceptor below still runs). */
    editor.addEventListener('keydown', function (e) {
        if (e.key === 'Tab') {
            e.preventDefault();
            const start = editor.selectionStart;
            const end   = editor.selectionEnd;
            editor.value = editor.value.substring(0, start)
                         + '    '
                         + editor.value.substring(end);
            editor.selectionStart = editor.selectionEnd = start + 4;
            refresh();
            return;
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            if (!form) return;
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();           // fires submit event → confirmation flow
            } else {
                form.dispatchEvent(new Event('submit', { cancelable: true }));
                form.submit();                  // fallback for very old browsers
            }
        }
    });

    /* ----- Destructive-query interceptor -----
       On submit, peek at the first SQL keyword. If it's DELETE / DROP /
       TRUNCATE / ALTER, show the Bootstrap modal instead of submitting.
       The modal's "Execute anyway" button calls form.submit() directly,
       which bypasses this listener (no event fired) so we don't loop. */
    if (form) {
        form.addEventListener('submit', function (e) {
            const sql = (editor.value || '').trim();
            const m   = sql.match(DESTRUCTIVE_RE);
            if (!m) return; // safe — let the form submit normally

            e.preventDefault();
            const kw = m[1].toUpperCase();

            if (confirmBadge) {
                confirmBadge.textContent = kw;
                // Reset & re-apply badge color so the badge matches the keyword
                confirmBadge.className = 'badge-query-type danger-pulse badge-query-type--' +
                                         (TYPE_CLASS[kw] || 'other');
            }
            if (confirmPreview) confirmPreview.textContent = sql;

            if (window.bootstrap && window.bootstrap.Modal && confirmModalEl) {
                const modal = window.bootstrap.Modal.getOrCreateInstance(confirmModalEl);
                modal.show();
            } else {
                // Fallback if Bootstrap modal isn't available
                if (window.confirm('Cette requête est destructive. Continuer ?')) {
                    form.submit();
                }
            }
        });
    }

    if (confirmExecBtn) {
        confirmExecBtn.addEventListener('click', function () {
            if (confirmModalEl && window.bootstrap && window.bootstrap.Modal) {
                const modal = window.bootstrap.Modal.getInstance(confirmModalEl);
                if (modal) modal.hide();
            }
            // form.submit() does NOT fire the submit event → no infinite loop.
            if (form) form.submit();
        });
    }

    /* ----- Clear button ----- */
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            editor.value = '';
            editor.focus();
            refresh();
        });
    }

    /* ----- Copy button ----- */
    if (copyBtn && navigator.clipboard) {
        copyBtn.addEventListener('click', function () {
            if (!editor.value) return;
            navigator.clipboard.writeText(editor.value).then(function () {
                const icon = copyBtn.querySelector('i');
                if (!icon) return;
                icon.className = 'bi bi-check-lg';
                setTimeout(function () { icon.className = 'bi bi-clipboard'; }, 1800);
            }).catch(function () { /* clipboard blocked — silent */ });
        });
    }

    /* ----- Example / history chips: paste into editor & focus ----- */
    exampleBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            editor.value = btn.dataset.sql || '';
            editor.focus();
            refresh();
            editor.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });

    /* ----- CSV export from the result table ----- */
    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            const table = document.getElementById('sqlResultTable');
            if (!table) return;

            const rows = table.querySelectorAll('tr');
            const csv  = [];
            rows.forEach(function (row) {
                const cells = row.querySelectorAll('th, td');
                const line  = [];
                cells.forEach(function (cell) {
                    let v = (cell.textContent || '').trim();
                    line.push('"' + v.replace(/"/g, '""') + '"');
                });
                csv.push(line.join(','));
            });

            // BOM so Excel opens UTF-8 correctly.
            const blob = new Blob(['﻿' + csv.join('\n')], {
                type: 'text/csv;charset=utf-8;'
            });
            const url  = URL.createObjectURL(blob);
            const a    = document.createElement('a');
            const ts   = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
            a.href     = url;
            a.download = 'query-' + ts + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
    }
})();

/* =========================================================
   Phase 12 — Admin grading form
   - Live statut preview as the admin types a note
   - "Dirty" highlight on changed inputs
   - beforeunload warning if leaving with unsaved changes
   - Listener cleared on form submit so the warning doesn't fire
   The IIFE no-ops on every page that doesn't have the form.
   ========================================================= */
(function () {
    const form = document.getElementById('notesForm');
    if (!form) return;

    let dirty = false;

    /**
     * Map a typed note value to the same statut PHP will derive on save.
     * Returns one of 'inscrit' | 'valide' | 'echoue' | 'invalid'.
     */
    function deriveStatut(raw) {
        const s = (raw || '').trim().replace(',', '.');
        if (s === '') return 'inscrit';
        const n = Number(s);
        if (!Number.isFinite(n) || n < 0 || n > 20) return 'invalid';
        return n >= 10 ? 'valide' : 'echoue';
    }

    function paintPreview(input) {
        const row = input.closest('tr');
        if (!row) return;
        const preview = row.querySelector('.note-statut-preview');
        if (!preview) return;

        const result = deriveStatut(input.value);

        if (result === 'invalid') {
            input.classList.add('is-invalid-soft');
            preview.className = 'badge-statut badge-statut--echoue note-statut-preview';
            preview.textContent = 'Invalide';
            return;
        }
        input.classList.remove('is-invalid-soft');

        // valide / echoue / inscrit → use the matching badge variant + label
        preview.className = 'badge-statut badge-statut--' + result + ' note-statut-preview';
        const labels = { valide: 'Validé', echoue: 'Échoué', inscrit: 'Inscrit' };
        preview.textContent = labels[result];
    }

    form.querySelectorAll('.note-input').forEach(function (inp) {
        const original = inp.dataset.original || '';
        inp.addEventListener('input', function () {
            paintPreview(inp);
            if (inp.value !== original) {
                inp.classList.add('is-dirty');
                dirty = true;
            } else {
                inp.classList.remove('is-dirty');
                // Don't reset `dirty`; another input may still be modified.
            }
        });
    });

    // Browser-native unsaved-changes warning. Modern browsers ignore the
    // returnValue text and show their own generic prompt — that's expected.
    window.addEventListener('beforeunload', function (e) {
        if (!dirty) return undefined;
        e.preventDefault();
        e.returnValue = '';
        return '';
    });

    // Clear the dirty flag right before submitting so the prompt doesn't fire.
    form.addEventListener('submit', function () {
        dirty = false;
    });
})();
