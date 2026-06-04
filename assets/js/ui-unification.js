(function () {
    'use strict';

    /* =========================================================
       EMS UI Normalizer
       - Table normalization keeps legacy tables aligned.
       - Header normalization adds shared helper classes to
         legacy page headers so one CSS system can style them.
    ========================================================= */

    /* ── Headers ────────────────────────────────────────────── */
    function elementContainsHeaderActions(el) {
        if (!el || el.nodeType !== 1) return false;
        return !!el.querySelector('a, button, input[type="button"], input[type="submit"], .dt-buttons');
    }

    function classifyHeaderChild(child) {
        if (!child || child.nodeType !== 1) return;

        if (child.classList.contains('head_actions') || child.classList.contains('head_back') || child.classList.contains('head-title')) {
            return;
        }

        if (child.classList.contains('actions')) {
            child.classList.add('head_actions');
            return;
        }

        if (child.classList.contains('back')) {
            child.classList.add('head_back');
            return;
        }

        if (
            child.classList.contains('title') ||
            child.classList.contains('title-content') ||
            child.classList.contains('page-title') ||
            /^H[1-6]$/.test(child.tagName)
        ) {
            child.classList.add('head-title');
            return;
        }

        if (child.querySelector('.back-btn')) {
            child.classList.add('head_back');
            return;
        }

        if (elementContainsHeaderActions(child)) {
            child.classList.add('head_actions');
        }
    }

    function normalizeHeaderTitle(headerEl) {
        if (!headerEl || headerEl.nodeType !== 1) return;
        if (headerEl.querySelector(':scope > .head-title')) return;

        Array.prototype.forEach.call(headerEl.children, classifyHeaderChild);

        var titleCandidates = Array.prototype.find.call(headerEl.children, function (child) {
            return child.classList.contains('head-title');
        });

        if (titleCandidates) return;

        var fallbackTitle = Array.prototype.find.call(headerEl.children, function (child) {
            return /^H[1-6]$/.test(child.tagName) || child.classList.contains('page-title');
        });

        if (fallbackTitle) {
            fallbackTitle.classList.add('head-title');
        }
    }

    function normalizePageHeader(headerEl) {
        if (!headerEl || headerEl.nodeType !== 1) return;
        if (headerEl.dataset.emsHeaderNormalized === '1') return;

        headerEl.classList.add('ems-header-shell');
        normalizeHeaderTitle(headerEl);
        headerEl.dataset.emsHeaderNormalized = '1';
    }

    function normalizeAllHeaders() {
        var selectors = [
            '.main_head',
            '.main .header',
            '.main .page-header',
            '.main .allheaders',
            '.main .rpt-topbar',
            '.main .d-top',
            '.main .contacts-header',
            '.main .broadcast-header'
        ];

        document.querySelectorAll(selectors.join(',')).forEach(normalizePageHeader);
    }

    function bootUnifiedHeaders() {
        normalizeAllHeaders();
        setTimeout(normalizeAllHeaders, 250);
        setTimeout(normalizeAllHeaders, 900);
        if (window.MutationObserver) {
            var headerObserver = new MutationObserver(function () {
                normalizeAllHeaders();
            });
            headerObserver.observe(document.body, { childList: true, subtree: true });
        }
    }

    /* ── Tables ─────────────────────────────────────────────── */
    function ensureUnifiedTableClass(tableEl) {
        if (!tableEl || tableEl.tagName !== 'TABLE') return;
        tableEl.classList.add('alltables', 'alltable');
        if (tableEl.hasAttribute('style')) tableEl.removeAttribute('style');
    }

    function normalizeSortableHeaders(tableEl) {
        if (!tableEl || !tableEl.tHead) return;
        var sortableSelector = 'th.sorting, th.sorting_asc, th.sorting_desc';
        tableEl.querySelectorAll(sortableSelector).forEach(function (th) {
            var text = (th.textContent || '').replace(/\s+/g, ' ').trim();
            if (text && !th.getAttribute('title')) {
                th.setAttribute('title', text);
            }
        });
    }

    function mapStatusToken(rawText) {
        if (!rawText) return null;
        var text = rawText.replace(/\s+/g, ' ').trim().toLowerCase();
        if (!text) return null;

        var activeTokens = ['نشط', 'معتمد', 'مكتمل', 'مدفوع', 'مفتوح', 'active', 'approved', 'completed', 'paid', 'open'];
        var pendingTokens = ['قيد التنفيذ', 'قيد المراجعة', 'جاري', 'pending', 'processing', 'in progress', 'in-progress'];
        var inactiveTokens = ['غير نشط', 'غير معتمد', 'ملغي', 'مغلق', 'غير مدفوع', 'inactive', 'cancelled', 'canceled', 'closed', 'unpaid', 'rejected'];

        if (activeTokens.indexOf(text) !== -1) return 'active';
        if (pendingTokens.indexOf(text) !== -1) return 'pending';
        if (inactiveTokens.indexOf(text) !== -1) return 'inactive';
        return null;
    }

    function normalizeTableSemanticCells(tableEl) {
        if (!tableEl || !tableEl.tBodies) return;

        tableEl.querySelectorAll('tbody td').forEach(function (td) {
            var childrenCount = td.children ? td.children.length : 0;

            if (childrenCount === 0) {
                var rawText = (td.textContent || '').replace(/\s+/g, ' ').trim();
                var statusToken = mapStatusToken(rawText);
                if (statusToken) {
                    td.textContent = '';
                    var chip = document.createElement('span');
                    chip.className = 'ems-status-chip';
                    chip.setAttribute('data-status', statusToken);
                    chip.textContent = rawText;
                    td.appendChild(chip);
                }
            }

            td.querySelectorAll('a').forEach(function (a) {
                if (
                    a.classList.contains('btn') ||
                    a.classList.contains('action-btn') ||
                    a.classList.contains('eye-btn') ||
                    a.classList.contains('edit-btn') ||
                    a.classList.contains('delete-btn') ||
                    a.classList.contains('btn-view') ||
                    a.classList.contains('btn-edit') ||
                    a.classList.contains('btn-delete')
                ) {
                    return;
                }
                a.classList.add('ems-link-chip');
            });
        });
    }

    function normalizeAllTables() {
        document.querySelectorAll('table').forEach(function (tableEl) {
            ensureUnifiedTableClass(tableEl);
            normalizeSortableHeaders(tableEl);
            normalizeTableSemanticCells(tableEl);
        });
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
        bootUnifiedHeaders();
        bootUnifiedTables();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

})();
