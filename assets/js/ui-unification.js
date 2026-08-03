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

    /* ── CMP-03 ②: حشو خلايا أعمدة الحوكمة المحقونة ─────────────
       رؤوس <th data-gov="…"> تُحقن حرفيًّا في ملفات الشاشات (tools/cmp03_apply.php)
       بينما حلقات PHP التي ترسم الصفوف لا تعرفها؛ فنحشو هنا خلايا كل صفٍّ بقيمة
       السياق العام (window.emsGovCtx من includes/gov_columns.php) أو «—»
       (توصية المالك ③: العمود بلا مصدرٍ بعدُ يظهر بمكانه فارغًا). يجري الحشو
       داخل normalizeAllTables قبل حارس structureMismatch وقبل تهيئة DataTables،
       فلا يستثنى الجدول ولا يُرمى «Incorrect column count». */
    function padGovernanceCells(tableEl) {
        if (!tableEl.tHead || !tableEl.tHead.rows.length) return;
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.dataTable &&
            window.jQuery.fn.dataTable.isDataTable && window.jQuery.fn.dataTable.isDataTable(tableEl)) return;
        var headerRow = tableEl.tHead.rows[tableEl.tHead.rows.length - 1];
        var govIdx = [];
        for (var i = 0; i < headerRow.cells.length; i++) {
            if ((headerRow.cells[i].colSpan || 1) !== 1) return; // ترويسة مجمّعة — لا نتدخل
            // الحوكمة (الموجة ②) والوظيفي المحقون (الموجة ⑤) كلاهما يُحشى
            if (headerRow.cells[i].hasAttribute('data-gov') || headerRow.cells[i].hasAttribute('data-fn')) govIdx.push(i);
        }
        if (!govIdx.length) return;
        var expected = headerRow.cells.length;
        var ctxValues = (window.emsGovCtx && window.emsGovCtx.values) || {};
        var sections = [];
        for (var s = 0; s < tableEl.tBodies.length; s++) sections.push(tableEl.tBodies[s]);
        if (tableEl.tFoot) sections.push(tableEl.tFoot);
        sections.forEach(function (section) {
            for (var r = 0; r < section.rows.length; r++) {
                var row = section.rows[r];
                // صفُّ عنصرٍ نائبٍ ممتدٌّ بخليةٍ واحدة: نمدّ colspan بدل الحشو
                if (row.cells.length === 1 && (row.cells[0].colSpan || 1) >= expected - govIdx.length && expected > 1) {
                    if ((row.cells[0].colSpan || 1) < expected) row.cells[0].colSpan = expected;
                    continue;
                }
                if (row.cells.length !== expected - govIdx.length) continue; // ليس صفًّا قابلًا للحشو
                for (var g = 0; g < govIdx.length; g++) {
                    var pos = govIdx[g];
                    var th = headerRow.cells[pos];
                    var key = th.getAttribute('data-gov');
                    var cell = row.insertCell(Math.min(pos, row.cells.length));
                    cell.className = 'ems-gov-cell';
                    if (section.tagName === 'TFOOT') { cell.textContent = ''; continue; }
                    var v = ctxValues[key];
                    if (v === undefined || v === null || v === '') {
                        cell.textContent = '—';
                        cell.classList.add('ems-gov-empty');
                    } else {
                        cell.textContent = v;
                    }
                }
            }
        });
    }

    /* ── CMP-03 توصية المالك ①: طي الأعمدة المحقونة فوق 22 ─────────────
       الرؤوس المحقونة الفائضة تحمل class="none":
       - جدول DataTables بإضافة Responsive الحية → Responsive نفسها تطويها
         لسطرٍ تابعٍ (زر التوسيع أول الصف) — لا نتدخل.
       - جدول DataTables بلا Responsive (تهيئة ذاتية قديمة) → نطويها عبر
         column().visible(false) ونضع زرَّ «الأعمدة المطوية» فوق الجدول.
       - جدول ساكن → نطويها بصنف CSS وبالزر نفسه. */
    function collapseInjectedOverflow(tableEl) {
        var overs = tableEl.querySelectorAll('th.none[data-gov], th.none[data-fn]');
        if (!overs.length) return;
        var $ = window.jQuery;
        var isDT = $ && $.fn && $.fn.dataTable && $.fn.dataTable.isDataTable && $.fn.dataTable.isDataTable(tableEl);
        if (isDT) {
            var api = $(tableEl).DataTable();
            if (api.responsive) { tableEl.dataset.emsXcol = 'responsive'; return; }
            if (tableEl.dataset.emsXcol === 'api') return;
            tableEl.dataset.emsXcol = 'api';
            /* الفهارس لا العقد: column().visible(false) ينزع الرأس من الـDOM
               فتموت العقدة المخزنة — الفهرس ثابت في نموذج DataTables */
            var idxs = [];
            var headerRow = overs[0].parentNode;
            Array.prototype.forEach.call(overs, function (th) {
                idxs.push(Array.prototype.indexOf.call(headerRow.cells, th));
            });
            tableEl.dataset.emsXcolIdxs = idxs.join(',');
            idxs.forEach(function (i) { try { api.column(i).visible(false, false); } catch (e) {} });
            try { api.columns.adjust(); } catch (e) {}
            ensureOverflowToggle(tableEl, overs.length);
        } else {
            if (tableEl.dataset.emsXcol) return;
            tableEl.dataset.emsXcol = 'css';
            cssToggleOverflow(tableEl, overs, false);
            ensureOverflowToggle(tableEl, overs.length);
        }
    }

    function cssToggleOverflow(tableEl, overs, show) {
        var headerRow = overs[0].parentNode;
        var idxs = Array.prototype.map.call(overs, function (th) {
            return Array.prototype.indexOf.call(headerRow.cells, th);
        });
        Array.prototype.forEach.call(overs, function (th) { th.classList.toggle('ems-xcol-off', !show); });
        var sections = Array.prototype.slice.call(tableEl.tBodies);
        if (tableEl.tFoot) sections.push(tableEl.tFoot);
        sections.forEach(function (section) {
            for (var r = 0; r < section.rows.length; r++) {
                var row = section.rows[r];
                if (row.cells.length !== headerRow.cells.length) continue;
                idxs.forEach(function (i) { row.cells[i].classList.toggle('ems-xcol-off', !show); });
            }
        });
    }

    function xcolLabel(btn, shown, count) {
        btn.innerHTML = '<i class="fa fa-columns"></i> ' +
            (shown ? 'طيّ الأعمدة الإضافية' : 'الأعمدة المطوية (' + count + ')');
    }

    function ensureOverflowToggle(tableEl, count) {
        if (tableEl.dataset.emsXcolBtn) return;
        tableEl.dataset.emsXcolBtn = '1';
        var host = tableEl.parentNode;
        var wrapper = window.jQuery ? window.jQuery(tableEl).closest('.dataTables_wrapper')[0] : null;
        if (wrapper) host = wrapper.parentNode;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ems-xcol-toggle';
        btn.dataset.emsXcolCount = String(count);
        btn.__emsXcolTable = tableEl; // ربط مباشر — التفويض يقرأه
        xcolLabel(btn, false, count);
        host.insertBefore(btn, wrapper || tableEl);
    }

    /* تفويض على الوثيقة: يصمد أمام أي إعادة رسمٍ أو التصاق مستمعين */
    document.addEventListener('click', function (ev) {
        var btn = ev.target && ev.target.closest ? ev.target.closest('.ems-xcol-toggle') : null;
        if (!btn) return;
        ev.preventDefault();
        var tableEl = btn.__emsXcolTable;
        if (!tableEl) return;
        var count = parseInt(btn.dataset.emsXcolCount || '0', 10);
        var shown = btn.dataset.emsXcolShown === '1';
        shown = !shown;
        btn.dataset.emsXcolShown = shown ? '1' : '0';
        if (tableEl.dataset.emsXcol === 'api' && window.jQuery && window.jQuery.fn.dataTable &&
            window.jQuery.fn.dataTable.isDataTable(tableEl)) {
            var api = window.jQuery(tableEl).DataTable();
            (tableEl.dataset.emsXcolIdxs || '').split(',').forEach(function (s) {
                if (s === '') return;
                try { api.column(parseInt(s, 10)).visible(shown, false); } catch (e) {}
            });
            try { api.columns.adjust(); } catch (e) {}
        } else {
            var overs = tableEl.querySelectorAll('th.none[data-gov], th.none[data-fn]');
            cssToggleOverflow(tableEl, overs, shown);
        }
        xcolLabel(btn, shown, count);
    }, true);

    function normalizeAllTables() {
        document.querySelectorAll('table').forEach(function (tableEl) {
            ensureUnifiedTableClass(tableEl);
            normalizeSortableHeaders(tableEl);
            normalizeTableSemanticCells(tableEl);
            try { padGovernanceCells(tableEl); } catch (eGov) { /* لا يعطل التوحيد */ }
            try { collapseInjectedOverflow(tableEl); } catch (eXc) { /* لا يعطل التوحيد */ }
        });
    }

    function initializeMissingDataTables() {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.dataTable) return;
        var $ = window.jQuery;
        // performance-boost.js يضبط errMode='none' عبر مؤقّتٍ دوري (250ms)، وقد نسبقه حين
        // نحمّل DataTables ديناميكياً من هنا — فيخرج أيُّ خطأ تهيئة في نافذة alert حاجزة
        // يضطرّ المستخدم لإغلاقها عند كل فتح للصفحة. نضبطه بأنفسنا قبل أي تهيئة.
        if ($.fn.dataTable.ext) $.fn.dataTable.ext.errMode = 'none';
        $('table').each(function () {
            var table = this;
            var $table = $(table);
            ensureUnifiedTableClass(table);
            if (table.classList.contains('dtr-details')) return;
            if (table.classList.contains('no-datatable')) return;
            if (table.dataset.noDt) return;
            if (table.dataset.emsDtSkip === '1') return; // فُحص سابقاً وتقرّر استثناؤه
            if (!table.tHead || !table.tBodies || !table.tBodies.length) return;
            if ($.fn.dataTable.isDataTable(table)) return;
            if ($table.closest('.dataTables_wrapper').length) return;
            if ($table.closest('.modal').length) return;
            // إصلاح tn/18: أزِل صفّ العنصر النائب «لا توجد بيانات» (خليةٌ واحدةٌ تمتدّ على
            // كل الأعمدة) قبل التهيئة، وإلا يرمي DataTables «Incorrect column count» على
            // الجداول الفارغة. لا يمسّ صفوف البيانات الحقيقية (عدد خلاياها = عدد العناوين)؛
            // وعند فراغ الجدول يعرض DataTables رسالة الفراغ المعرّبة من ar.json.
            var structureMismatch = false;
            try {
                var theadEl = table.tHead, tbodyEl = table.tBodies[0], colCount = 0;
                if (theadEl && theadEl.rows.length) {
                    var headerRow = theadEl.rows[theadEl.rows.length - 1];
                    for (var hc = 0; hc < headerRow.cells.length; hc++) {
                        colCount += headerRow.cells[hc].colSpan || 1;
                    }
                }
                if (tbodyEl && colCount > 1 && tbodyEl.rows.length === 1 &&
                    tbodyEl.rows[0].cells.length === 1 &&
                    (tbodyEl.rows[0].cells[0].colSpan || 1) >= colCount) {
                    tbodyEl.removeChild(tbodyEl.rows[0]);
                }
                // حارس البنية (tn/18 من مصدرٍ ثالث): جدولٌ فيه صفوفُ أقسامٍ أو مجاميعَ
                // ممتدّة بـcolspan داخل tbody — كالقوائم المالية — لا يوافق *عددُ خلايا*
                // صفوفه ترويستَه، فيرمي DataTables «Incorrect column count». لا نهيّئه
                // أصلاً: يبقى جدولاً ساكناً صحيحَ العرض بدل تهيئةٍ فاشلةٍ تُبعثر صفوفه.
                // المقارنةُ بعدد الخلايا لا بمجموع colspan: خليةٌ واحدةٌ تمتدّ على كلّ
                // الأعمدة تساوي الترويسةَ عرضاً وتخالفها عدداً — وهي عينُ ما يرفضه DataTables.
                // نقتصر على ترويسةٍ ورقيّةٍ صريحة (بلا colspan) كي لا نستثني الترويسات
                // المجمَّعة التي يتولّاها DataTables بنفسه.
                var leafCols = 0, plainHeader = true;
                if (theadEl && theadEl.rows.length) {
                    var leafRow = theadEl.rows[theadEl.rows.length - 1];
                    leafCols = leafRow.cells.length;
                    for (var lc = 0; lc < leafRow.cells.length; lc++) {
                        if ((leafRow.cells[lc].colSpan || 1) !== 1) { plainHeader = false; break; }
                    }
                }
                if (tbodyEl && plainHeader && leafCols > 1) {
                    for (var br = 0; br < tbodyEl.rows.length; br++) {
                        if (tbodyEl.rows[br].cells.length !== leafCols) { structureMismatch = true; break; }
                    }
                }
            } catch (ePlaceholder) { /* تجاهل: لا يؤثّر على التهيئة */ }
            // نُعلّم الجدول مرةً واحدة كي لا يُعاد فحصُ صفوفه مع كل تغيّرٍ في الـDOM.
            if (structureMismatch) { table.dataset.emsDtSkip = '1'; return; }
            $table.addClass('display');
            try {
                $table.DataTable({
                    responsive: true,
                    autoWidth: false,
                    language: { url: '/ems/assets/i18n/datatables/ar.json' },
                    // إصلاح tn/18 من stateSave: performance-boost.js يفعّل stateSave عمومياً،
                    // فإن تغيّر عدد أعمدة الجدول منذ آخر زيارة تبقى حالةٌ محفوظةٌ تالفةٌ في
                    // localStorage فترمي «Incorrect column count» حتى على جدولٍ سليمٍ ببيانات.
                    // نرفض الحالة المحفوظة إن لم يطابق عددُ أعمدتها الجدولَ الحالي (نحفظ الباقي).
                    stateLoadParams: function (settings, data) {
                        if (data && data.columns && settings.aoColumns &&
                            data.columns.length !== settings.aoColumns.length) {
                            return false;
                        }
                    }
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
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.dataTable) {
                // CMP-03 ①(توصية): صفحةٌ حمّلت DataTables بنفسها بلا إضافة Responsive —
                // نحمّلها كي يعمل انهيارُ class="none" لسطرٍ تابعٍ في الجداول المهيأة لاحقًا.
                if (!window.jQuery.fn.dataTable.Responsive) { loadScriptOnce(dtResponsiveSrc, done); return; }
                done(); return;
            }
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
