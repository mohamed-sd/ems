(function () {
    'use strict';

    /* =========================================================
       EMS Table Normalizer
       (Header normalization was retired: every sidebar page now
        ships the final .header / .main_head structure in its own
        markup — see includes/page_header.php and the per-page
        pre-built `.header` blocks. Styling lives in
        assets/css/ems.main.all.style.css.)
    ========================================================= */

    /* ── Tables ─────────────────────────────────────────────── */
    function ensureUnifiedTableClass(tableEl) {
        if (!tableEl || tableEl.tagName !== 'TABLE') return;
        tableEl.classList.add('alltables', 'alltable');
        if (tableEl.hasAttribute('style')) tableEl.removeAttribute('style');
    }

    function normalizeAllTables() {
        document.querySelectorAll('table').forEach(ensureUnifiedTableClass);
    }

    function initializeMissingDataTables() {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.dataTable) return;
        var $ = window.jQuery;
        $('table').each(function () {
            var table = this;
            var $table = $(table);
            ensureUnifiedTableClass(table);
            if (table.classList.contains('dtr-details')) return;
            if (table.classList.contains('no-datatable')) return;
            if (table.dataset.noDt) return;
            if (!table.tHead || !table.tBodies || !table.tBodies.length) return;
            if ($.fn.dataTable.isDataTable(table)) return;
            if ($table.closest('.dataTables_wrapper').length) return;
            if ($table.closest('.modal').length) return;
            $table.addClass('display');
            try {
                $table.DataTable({
                    responsive: true,
                    autoWidth: false,
                    language: { url: '/ems/assets/i18n/datatables/ar.json' }
                });
            } catch (e) { /* legacy tables may be initialized later */ }
        });
    }

    function loadScriptOnce(src, done) {
        var existing = document.querySelector('script[src="' + src + '"]');
        if (existing) {
            if (typeof done === 'function') {
                if (existing.dataset.loaded === '1') { done(); }
                else { existing.addEventListener('load', function () { existing.dataset.loaded = '1'; done(); }, { once: true }); }
            }
            return;
        }
        var script = document.createElement('script');
        script.src = src;
        script.async = false;
        script.onload = function () { script.dataset.loaded = '1'; if (typeof done === 'function') done(); };
        document.head.appendChild(script);
    }

    function ensureDataTablesReady(done) {
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.dataTable) { done(); return; }
        var jquerySrc = '/ems/assets/vendor/jquery-3.7.1.min.js';
        var dtSrc = '/ems/assets/vendor/datatables/js/jquery.dataTables.min.js';
        var dtResponsiveSrc = '/ems/assets/vendor/datatables/js/dataTables.responsive.min.js';
        var loadDataTables = function () {
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.dataTable) { done(); return; }
            loadScriptOnce(dtSrc, function () { loadScriptOnce(dtResponsiveSrc, done); });
        };
        if (!window.jQuery) { loadScriptOnce(jquerySrc, loadDataTables); return; }
        loadDataTables();
    }

    function bootUnifiedTables() {
        normalizeAllTables();
        ensureDataTablesReady(initializeMissingDataTables);
        setTimeout(function () { normalizeAllTables(); ensureDataTablesReady(initializeMissingDataTables); }, 250);
        setTimeout(function () { normalizeAllTables(); ensureDataTablesReady(initializeMissingDataTables); }, 900);
        if (window.MutationObserver) {
            var observer = new MutationObserver(function () {
                normalizeAllTables();
                ensureDataTablesReady(initializeMissingDataTables);
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
    }

    /* ── Boot ───────────────────────────────────────────────── */
    function boot() {
        bootUnifiedTables();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

})();
