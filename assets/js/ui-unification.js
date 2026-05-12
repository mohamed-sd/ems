(function () {
    'use strict';

    /* =========================================================
       EMS Unified Header & Table Normalizer
       Design: dark bar | right=back | center=title | left=actions
    ========================================================= */

    var inlineActionsId = 'emsInlineActions';
    var sourceHiddenClass = 'ems-source-action-hidden';

    /* ── Inject styles once ─────────────────────────────────── */
    function injectStyles() {
        return;
    }

    /* ── Utilities ──────────────────────────────────────────── */
    function normalizeArabic(text) {
        return (text || '')
            .toString()
            .trim()
            .replace(/[\u064B-\u0652]/g, '')
            .replace(/[\u0640]/g, '')
            .replace(/[أإآ]/g, 'ا')
            .replace(/\s+/g, ' ')
            .toLowerCase();
    }

    function getElementText(el) {
        if (!el) return '';
        if (el.tagName === 'INPUT') return el.value || el.getAttribute('value') || '';
        return el.textContent || '';
    }

    function isVisible(el) {
        if (!el) return false;
        var style = window.getComputedStyle(el);
        return style.display !== 'none' && style.visibility !== 'hidden' && el.offsetParent !== null;
    }

    function isCandidate(el) {
        if (!isVisible(el)) return false;
        if (el.closest('#' + inlineActionsId)) return false;
        if (el.closest('.modal, .dropdown-menu, .dataTables_paginate, .dt-buttons')) return false;
        return true;
    }

    /* ── Header normalization ───────────────────────────────── */
    function normalizeHeaderActions() {
        var headers = document.querySelectorAll('.main .page-header, .main .header');
        if (!headers.length) {
            return false;
        }

        function stripLegacyHeaderClasses(node) {
            if (!node || !node.classList) {
                return;
            }

            var removablePrefixes = [
                'page-header',
                'allheaders',
                'projects-',
                'clients-',
                'suppliers-',
                'contracts-',
                'equipments-',
                'project-users-',
                'users-',
                'pu-',
                'fc-',
                'fleet-',
                'ts-',
                'movement-',
                'header-shell',
                'toolbar',
                'brand',
                'shell'
            ];

            Array.prototype.slice.call(node.classList).forEach(function (cls) {
                var shouldRemove = false;

                if (cls === 'page-title' || cls === 'page-header-actions' || cls === 'pu-page-header-actions') {
                    shouldRemove = true;
                }

                for (var i = 0; i < removablePrefixes.length; i++) {
                    if (cls.indexOf(removablePrefixes[i]) === 0 || cls.indexOf(removablePrefixes[i]) !== -1) {
                        shouldRemove = true;
                        break;
                    }
                }

                if (shouldRemove && cls !== 'add-btn' && cls !== 'btn' && cls !== 'dt-buttons' && cls !== 'dt-button') {
                    node.classList.remove(cls);
                }
            });
        }

        headers.forEach(function (header) {
            if (header.dataset.emsUnifiedHeader === '1') {
                return;
            }

            header.dataset.emsUnifiedHeader = '1';

            var titleCandidate = header.querySelector('.page-title, h1, h2, h3, .title');
            var titleHost = null;

            if (titleCandidate) {
                titleHost = titleCandidate;
                while (titleHost && titleHost.parentElement !== header) {
                    titleHost = titleHost.parentElement;
                }

                if (!titleHost) {
                    titleHost = titleCandidate;
                }
            }

            var actionCandidates = header.querySelectorAll(
                'a, button, input[type="button"], input[type="submit"], .dt-buttons, [id$="ExportButtons"]'
            );
            var seen = [];
            var backAction = null;
            var extraActions = [];

            actionCandidates.forEach(function (item) {
                if (!item || seen.indexOf(item) !== -1) {
                    return;
                }

                seen.push(item);

                if (item.closest('.modal, .dropdown-menu')) {
                    return;
                }

                if (titleHost && titleHost.contains(item) && item !== titleHost) {
                    return;
                }

                var itemText = normalizeArabic(getElementText(item));
                var isBack = item.classList.contains('back-btn')
                    || itemText.indexOf('رجوع') !== -1
                    || itemText.indexOf('الرجوع') !== -1
                    || itemText.indexOf('عودة') !== -1
                    || itemText.indexOf('back') !== -1;

                if (isBack && !backAction) {
                    backAction = item;
                    return;
                }

                if (item === titleHost) {
                    return;
                }

                extraActions.push(item);
            });

            var titleWrap = document.createElement('div');
            titleWrap.className = 'title';

            var actionsWrap = document.createElement('div');
            actionsWrap.className = 'actions';

            var backWrap = document.createElement('div');
            backWrap.className = 'back';

            while (header.firstChild) {
                header.removeChild(header.firstChild);
            }

            if (titleHost) {
                stripLegacyHeaderClasses(titleHost);
                titleHost.classList.add('title-content');
                Array.prototype.slice.call(titleHost.querySelectorAll('*')).forEach(stripLegacyHeaderClasses);
                titleWrap.appendChild(titleHost);
            }

            if (backAction) {
                stripLegacyHeaderClasses(backAction);
                backAction.classList.add('back-btn');
                backWrap.appendChild(backAction);
            }

            extraActions.forEach(function (item) {
                stripLegacyHeaderClasses(item);
                if (item.classList.contains('dt-buttons')) {
                    item.classList.add('header-actions-group');
                }
                actionsWrap.appendChild(item);
            });

            header.className = 'header';
            header.appendChild(actionsWrap);
            header.appendChild(titleWrap);
            header.appendChild(backWrap);

            if (!backAction) {
                backWrap.style.display = 'none';
            }

            if (!extraActions.length) {
                actionsWrap.style.display = 'none';
            }

            if (!titleHost) {
                titleWrap.style.display = 'none';
            }
        });

        return true;
    }

    /* ── Action bar entry point ─────────────────────────────── */
    function ensureActionBar() {
        if (document.querySelector('.movement-page')) {
            return;
        }

        document.querySelectorAll('.main .page-header[data-ems-unified-header], .main .header[data-ems-unified-header]')
            .forEach(function (h) {
                h.removeAttribute('data-ems-unified-header');
            });

        var previouslyHidden = document.querySelectorAll('.' + sourceHiddenClass);
        for (var ph = 0; ph < previouslyHidden.length; ph++) {
            previouslyHidden[ph].classList.remove(sourceHiddenClass);
        }

        var oldInlineActions = document.getElementById(inlineActionsId);
        if (oldInlineActions) oldInlineActions.remove();

        normalizeHeaderActions();
    }

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
    var refreshActionBar = (function () {
        var timer;
        return function () { clearTimeout(timer); timer = setTimeout(ensureActionBar, 120); };
    })();

    function boot() {
        ensureActionBar();
        bootUnifiedTables();

        setTimeout(ensureActionBar, 200);
        setTimeout(ensureActionBar, 700);
        setTimeout(ensureActionBar, 1400);

        window.addEventListener('resize', refreshActionBar);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

})();
