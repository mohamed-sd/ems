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

    // طقمُ الحالات السبعة الموحّد (E-03 §4-1 · قرار المالك 2026-08-06):
    // مسودة · مرفوع · معتمد · معلَّق · مقفَل · ملغى · معكوس — لونٌ ورمزٌ
    // ثابتان في كل المنصة، والحالاتُ التخصصية تُنسب إليه عرضًا.
    var EMS_STATE_ICONS = {
        draft: 'far fa-circle', submitted: 'fas fa-arrow-up', active: 'fas fa-check',
        held: 'far fa-dot-circle', locked: 'fas fa-lock', inactive: 'fas fa-ban',
        reversed: 'fas fa-undo', pending: 'fas fa-arrow-up'
    };
    function mapStatusToken(rawText) {
        if (!rawText) return null;
        var text = rawText.replace(/\s+/g, ' ').trim().toLowerCase();
        if (!text) return null;

        var sets = {
            draft:     ['مسودة', 'مسوّدة', 'draft'],
            submitted: ['مرفوع', 'مقدم', 'مقدّم', 'قيد التنفيذ', 'قيد المراجعة', 'جاري',
                        'بانتظار الاعتماد', 'submitted', 'pending', 'processing', 'in progress', 'in-progress'],
            active:    ['نشط', 'معتمد', 'معتمدة', 'مكتمل', 'مكتملة', 'مدفوع', 'مفتوح', 'محوّلة', 'محولة',
                        'active', 'approved', 'completed', 'paid', 'open', 'converted'],
            held:      ['معلق', 'معلّق', 'معلقة', 'مردود', 'معاد', 'معادة', 'موقوف',
                        'held', 'on hold', 'on-hold', 'returned'],
            locked:    ['مقفل', 'مقفَل', 'مقفلة', 'مغلق', 'مغلقة', 'locked', 'closed'],
            inactive:  ['غير نشط', 'غير معتمد', 'ملغي', 'ملغى', 'ملغاة', 'مرفوض', 'مرفوضة', 'غير مدفوع',
                        'inactive', 'cancelled', 'canceled', 'unpaid', 'rejected'],
            reversed:  ['معكوس', 'معكوسة', 'عُكس', 'reversed']
        };
        for (var token in sets) {
            if (sets[token].indexOf(text) !== -1) return token;
        }
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
                    if (EMS_STATE_ICONS[statusToken]) {   // الطقم: رمزُ الحالة قبل نصّها
                        var ic = document.createElement('i');
                        ic.className = EMS_STATE_ICONS[statusToken];
                        ic.setAttribute('aria-hidden', 'true');
                        chip.appendChild(ic);
                        chip.appendChild(document.createTextNode(' '));
                    }
                    chip.appendChild(document.createTextNode(rawText));
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

    /* ── جدولٌ كالإكسل: كل الأعمدة جنبًا إلى جنبٍ وتمريرٌ أفقي ──────────
       أُزيلت إضافةُ Responsive من النظام كلِّه (قرار المالك 2026-08-09)، ومعها
       طيُّ CMP-03 الذي كان يخفي الأعمدةَ المحقونةَ الفائضة (class="none") إما
       لسطرٍ تابعٍ تحت سهمٍ وإما بـcolumn().visible(false). لا شيء يُخفى الآن:
       الجدولُ يمتدُّ بعرض محتواه ويلفُّه هذا الغلافُ بتمريرٍ أفقيّ.

       الغلافُ أبٌ مباشرٌ لـ<table> دائمًا — لا يلفُّ .dataTables_wrapper — كي
       يبقى صندوقُ البحث وحبّةُ عدد المدخلات والترقيمُ ثابتةً بينما تنزلق
       الأعمدةُ وحدَها. وهذا يفرض تصحيحًا: نحن نلفُّ الجدولَ قبل أن يهيّئه
       DataTables، فإن تهيّأ بعدها أدخل بيننا .dataTables_wrapper فصار غلافُنا
       خارجَه — نفكُّ القديمَ ونعيد اللفَّ داخلَه. */
    /* أيُّ ems-xscroll فوق الجدولِ ليس أباه المباشر فهو غلافٌ أزاحه إدراجُ
       .dataTables_wrapper بعد لفِّنا — يُفكُّ ويُعاد اللفُّ داخلَ الغلاف. */
    function unwrapStaleScrollers(tableEl) {
        var node = tableEl.parentNode;
        var direct = tableEl.parentNode;
        while (node && node !== document.body) {
            var up = node.parentNode;
            if (node !== direct && node.classList && node.classList.contains('ems-xscroll') && up) {
                while (node.firstChild) up.insertBefore(node.firstChild, node);
                up.removeChild(node);
            }
            node = up;
        }
    }

    function ensureHorizontalScroll(tableEl) {
        if (!tableEl.parentNode) return;
        // جداولٌ لها غلافُ تمريرٍ خاصٌّ بها سلفًا — لا نضاعفه
        if (tableEl.closest && tableEl.closest('.table-responsive-wrapper, .dataTables_scrollBody')) return;

        unwrapStaleScrollers(tableEl);

        var host = tableEl.parentNode;
        if (!host) return;
        if (host.classList && host.classList.contains('ems-xscroll')) return;

        var box = document.createElement('div');
        box.className = 'ems-xscroll';
        host.insertBefore(box, tableEl);
        box.appendChild(tableEl);
    }

    function normalizeAllTables() {
        document.querySelectorAll('table').forEach(function (tableEl) {
            ensureUnifiedTableClass(tableEl);
            normalizeSortableHeaders(tableEl);
            normalizeTableSemanticCells(tableEl);
            try { padGovernanceCells(tableEl); } catch (eGov) { /* لا يعطل التوحيد */ }
            try { ensureHorizontalScroll(tableEl); } catch (eXs) { /* لا يعطل التوحيد */ }
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

    /* ══ زرُّ التصدير إلى إكسل لكلِّ جدولِ DataTables ══════════════════════
       قرار المالك 2026-08-09: كلُّ جدولٍ يستعمل DataTables يعرض زرَّ إكسل.
       60 شاشةً كانت تبنيه بنفسها في تهيئتها (`buttons: [...]` مع dom:'Bfrtip')
       و305 شاشاتٍ بلا زرٍّ أصلًا — 253 منها لا تهيّئ جدولَها بنفسها بل تعتمد
       التهيئةَ المركزيةَ هنا. فالموضعُ الصحيحُ للحلِّ هذا الملفُّ وحدَه: نُلحق
       الزرَّ بأيِّ نسخةِ DataTable لا تملكه، فلا يُلمس ملفُّ شاشةٍ واحد.

       تحميلٌ كسول: مكتباتُ الأزرار وJSZip لا تُجلب إلا إذا وُجد جدولٌ يحتاجها،
       والفحصُ على المتغيّرات العامّة لا على وسمِ <script> — لأن 518 شاشةً تحمّل
       هذه الملفاتِ بنفسها، ووسمًا نُفِّذ سلفًا لا يُطلق حدثَ load مرةً أخرى. */
    var vendorState = {};
    function loadVendorOnce(src, done) {
        var st = vendorState[src];
        if (st && st.done) { done(); return; }
        if (st) { st.queue.push(done); return; }
        vendorState[src] = st = { done: false, queue: [done] };
        var s = document.createElement('script');
        s.src = src;
        s.async = false;
        s.onload = s.onerror = function () {
            st.done = true;
            var q = st.queue; st.queue = [];
            q.forEach(function (fn) { try { fn(); } catch (e) {} });
        };
        document.head.appendChild(s);
    }

    function ensureExcelDepsReady(done) {
        var $ = window.jQuery;
        if (!$ || !$.fn || !$.fn.dataTable) return;
        var V = '/ems/assets/vendor/';
        if (!window.JSZip) { loadVendorOnce(V + 'jszip/jszip.min.js', function () { ensureExcelDepsReady(done); }); return; }
        if (!$.fn.dataTable.Buttons) { loadVendorOnce(V + 'datatables/js/dataTables.buttons.min.js', function () { ensureExcelDepsReady(done); }); return; }
        if (!($.fn.dataTable.ext.buttons && $.fn.dataTable.ext.buttons.excelHtml5)) {
            loadVendorOnce(V + 'datatables/js/buttons.html5.min.js', function () { ensureExcelDepsReady(done); }); return;
        }
        done();
    }

    /* أعمدةُ الأفعال لا تُصدَّر: خلاياها أزرارٌ وروابطُ لا بيانات. */
    var ACTION_HEAD = /^(الإجراءات|إجراءات|العمليات|عمليات|الخيارات|خيارات|أدوات|الأدوات|التحكم|actions?|options?|tools?|manage)$/i;
    function isExportableColumn(api, idx) {
        var th = api.column(idx).header();
        if (!th) return true;
        if (th.classList && (th.classList.contains('no-export') || th.hasAttribute('data-no-export'))) return false;
        return !ACTION_HEAD.test((th.textContent || '').trim());
    }

    function exportBaseName() {
        var head = document.querySelector('.main_head h1, .main_head h2, .main_head');
        var name = head ? (head.textContent || '').trim().split('\n')[0].trim() : '';
        if (!name) name = (document.title || 'تصدير').split('|')[0].trim();
        return name.replace(/[\\\/\[\]\*\?:]/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 28) || 'تصدير';
    }

    function ensureExcelButton(tableEl) {
        var $ = window.jQuery;
        if (!$ || !$.fn.dataTable || !$.fn.dataTable.isDataTable(tableEl)) return;
        if (tableEl.dataset.emsXlsx === '1') return;
        var api = $(tableEl).DataTable();
        var container = api.table().container();
        // زرُّ إكسل موجودٌ سلفًا من تهيئة الشاشة — لا نضاعفه
        if (container && container.querySelector('.buttons-excel')) { tableEl.dataset.emsXlsx = 'own'; return; }
        if (!$.fn.dataTable.Buttons) return;
        tableEl.dataset.emsXlsx = '1';

        var title = exportBaseName();
        var btns = new $.fn.dataTable.Buttons(api, {
            buttons: [{
                extend: 'excelHtml5',
                text: '📊 Excel',
                className: 'ems-xlsx-btn',
                title: title,
                filename: title,
                exportOptions: {
                    columns: function (idx) { return isExportableColumn(api, idx); },
                    stripHtml: true,
                    trim: true
                },
                customize: function (xlsx) {
                    /* ورقةٌ من اليمين لليسار — البياناتُ عربيةٌ في كل الشاشات.
                       قالبُ excelHtml5 لا يُصدِر <sheetViews> أصلًا، فضبطُ الخاصية
                       على عنصرٍ موجودٍ لا يفعل شيئًا صامتًا — يلزم إنشاؤه وإدراجُه
                       قبل <cols> لأن ترتيبَ عناصر OOXML مُلزِم. */
                    try {
                        var doc = xlsx.xl.worksheets['sheet1.xml'];
                        var existing = doc.getElementsByTagName('sheetView')[0];
                        if (existing) { existing.setAttribute('rightToLeft', '1'); return; }
                        var ws = doc.getElementsByTagName('worksheet')[0];
                        if (!ws) return;
                        var NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
                        var views = doc.createElementNS(NS, 'sheetViews');
                        var view = doc.createElementNS(NS, 'sheetView');
                        view.setAttribute('workbookViewId', '0');
                        view.setAttribute('rightToLeft', '1');
                        views.appendChild(view);
                        ws.insertBefore(views, ws.firstChild);
                    } catch (e) { /* التصدير يتم بلا اتجاه — لا نُفشله */ }
                }
            }]
        });
        var host = document.createElement('div');
        host.className = 'ems-auto-buttons';
        /* المرساةُ .dataTables_wrapper لا .ems-xscroll: غلافُ التمرير يُفكُّ
           ويُعاد بناؤه داخل الوعاء بعد التهيئة (unwrapStaleScrollers)، فزرٌّ
           مُعلَّقٌ عليه يبقى يتيمًا في .main حين يزول. الوعاءُ ثابت. */
        container = api.table().container();
        if (!container) { tableEl.dataset.emsXlsx = ''; return; }
        container.insertBefore(host, container.firstChild);
        $(host).append(btns.container());
    }

    function attachExcelButtons() {
        var $ = window.jQuery;
        if (!$ || !$.fn || !$.fn.dataTable) return;
        var pending = [];
        $('table').each(function () {
            if ($.fn.dataTable.isDataTable(this) && this.dataset.emsXlsx !== '1' && this.dataset.emsXlsx !== 'own') {
                pending.push(this);
            }
        });
        if (!pending.length) return;
        ensureExcelDepsReady(function () {
            pending.forEach(function (t) { try { ensureExcelButton(t); } catch (e) { /* لا يعطل الصفحة */ } });
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
        // إضافةُ Responsive مرفوعةٌ من النظام: لا تُحمَّل ولا يُنتظر تحميلُها.
        var loadDataTables = function () {
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.dataTable) { done(); return; }
            loadScriptOnce(dtSrc, done);
        };
        if (!window.jQuery) { loadScriptOnce(jquerySrc, loadDataTables); return; }
        loadDataTables();
    }

    function sweepTables() {
        normalizeAllTables();
        ensureDataTablesReady(function () {
            initializeMissingDataTables();
            // بعد التهيئة لا قبلَها: الزرُّ يُلحق بنسخةٍ قائمة — وهذا يشمل
            // الجداولَ التي هيّأتها الشاشةُ بنفسها لا التهيئةَ المركزيةَ فقط.
            attachExcelButtons();
        });
    }

    function bootUnifiedTables() {
        sweepTables();
        setTimeout(sweepTables, 250);
        setTimeout(sweepTables, 900);
        if (window.MutationObserver) {
            var observer = new MutationObserver(sweepTables);
            observer.observe(document.body, { childList: true, subtree: true });
        }
    }

    /* ── الحالات الخمس (E-03 موجة ٤ · emsFiveStates) ──────────────────────
       عقدُ كل شاشة: تحميل · فارغة · خطأ · نجاح · دون اتصال — مركزيًّا:
       · فارغة: رسالة DataTables المعرَّبة (ar.json) على كل جدولٍ موحَّد.
       · نجاح: لافتة ?msg= القائمة في الشاشات (عرف موحّد مسبقًا).
       · تحميل: غلاف «جارٍ التنفيذ…» على كل إرسال POST — يمنع الإرسال المزدوج
         بصريًّا ويصدُق حالَ الشاشة (تعطيل بdata-no-loading على الفورم).
       · خطأ: أي فشل AJAX (jQuery) يرفع لافتةً حمراء برمز الحالة — لا فشل صامت.
       · دون اتصال: لافتة ثابتة عند انقطاع الشبكة وتنبيه عودةٍ أخضر عابر. */
    function bootFiveStates() {
        var css = '#emsFsOffline{position:fixed;top:0;right:0;left:0;z-index:99999;background:#b3261e;color:#fff;' +
            'text-align:center;padding:8px 14px;font-weight:bold;display:none}' +
            '#emsFsOffline.on{display:block}' +
            '#emsFsOffline.back{background:#1e7d32}' +
            '#emsFsError{position:fixed;bottom:14px;right:14px;z-index:99999;background:#b3261e;color:#fff;' +
            'padding:10px 16px;border-radius:8px;max-width:70vw;box-shadow:0 2px 10px rgba(0,0,0,.3);display:none}' +
            '#emsFsLoading{position:fixed;inset:0;z-index:99998;background:rgba(255,255,255,.55);display:none;' +
            'align-items:center;justify-content:center;font-size:18px;font-weight:bold}' +
            '#emsFsLoading.on{display:flex}' +
            '#emsFsLoading .box{background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px 26px;box-shadow:0 2px 14px rgba(0,0,0,.15)}';
        var st = document.createElement('style');
        st.textContent = css;
        document.head.appendChild(st);
        var off = document.createElement('div');
        off.id = 'emsFsOffline';
        off.textContent = 'لا اتصالَ بالشبكة — التغييرات لن تُحفظ حتى يعود الاتصال';
        document.body.appendChild(off);
        var err = document.createElement('div');
        err.id = 'emsFsError';
        document.body.appendChild(err);
        var ld = document.createElement('div');
        ld.id = 'emsFsLoading';
        ld.innerHTML = '<div class="box">جارٍ التنفيذ…</div>';
        document.body.appendChild(ld);

        window.addEventListener('offline', function () {
            off.classList.remove('back');
            off.textContent = 'لا اتصالَ بالشبكة — التغييرات لن تُحفظ حتى يعود الاتصال';
            off.classList.add('on');
        });
        window.addEventListener('online', function () {
            off.classList.add('back');
            off.textContent = 'عاد الاتصال ✅';
            setTimeout(function () { off.classList.remove('on', 'back'); }, 2500);
        });

        // تحميل: إرسال POST يرفع الغلاف (والملاحة تُسقطه تلقائيًّا بتحميل الصفحة)
        document.addEventListener('submit', function (e) {
            var f = e.target;
            if (!f || (f.method || '').toLowerCase() !== 'post') return;
            if (f.hasAttribute('data-no-loading') || f.target === '_blank') return;
            setTimeout(function () { if (!e.defaultPrevented) ld.classList.add('on'); }, 0);
        }, true);
        window.addEventListener('pageshow', function () { ld.classList.remove('on'); });

        // خطأ: فشل AJAX لا يمر صامتًا (jQuery حيثما حضر)
        function hookAjaxError() {
            if (!window.jQuery || !window.jQuery.fn) { return false; }
            window.jQuery(document).ajaxError(function (ev, xhr, settings) {
                if (xhr && xhr.statusText === 'abort') { return; }
                var text = 'تعذر الطلب (' + (xhr && xhr.status ? xhr.status : 'شبكة') + ') — أعد المحاولة أو أبلغ الدعم';
                // مصدرٌ واحدٌ للرسائل: نظام التوست الموحّد إن حضر، واللافتةُ
                // القديمة احتياطًا فحسب — لا لغتان بصريتان لخطأٍ واحد.
                if (window.EmsAlert && window.EmsAlert.error) { window.EmsAlert.error(text); return; }
                err.textContent = text;
                err.style.display = 'block';
                setTimeout(function () { err.style.display = 'none'; }, 6000);
            });
            return true;
        }
        if (!hookAjaxError()) {
            var tries = 0;
            var t = setInterval(function () { if (hookAjaxError() || ++tries > 20) clearInterval(t); }, 500);
        }
    }

    /* ── CM-00 · الغلافُ الحاكم (DEC-E · update0009) ────────────────────────
       إعادةُ نمذجةِ حالاتِ الشاشة من قائمةٍ واحدةٍ إلى خمسةِ محاورَ مستقلةٍ تُركَّب:
         AX-1 البيانات: loading·data·empty·no-results·error   (خدمةُ الجلب)
         AX-2 الصلاحية: full·partial·none                     (محرّكُ الصلاحيات)
         AX-3 التحرير:  editable·readonly·locked              (آلةُ الحالة/إقفالُ الفترة)
         AX-4 الاتصال:  online·offline·syncing·sync-failed    (محرّكُ دون اتصال)
         AX-5 الحداثة:  fresh·stale·cached                    (طابعُ آخرِ جلبٍ ناجح)
       فتركيبةُ الميدان «بياناتٌ + قراءةٌ فقط + دون اتصال + قديمة» تُمثَّل بأربعِ
       قيمٍ متزامنةٍ لا بحالةٍ واحدة. القائمُ (bootFiveStates) يبقى عاملًا —
       CM-00 يركّب فوقه ويبثّ data-ax-* على body وحدث ems:shell للمكونات.
       الشاشةُ تبذر قيمَها بوسوم data-ems-ax-* على body أو نداءً EmsScreenShell.set. */
    window.EmsScreenShell = (function () {
        var VALID = {
            data: ['loading', 'data', 'empty', 'no-results', 'error'],
            permission: ['full', 'partial', 'none'],
            edit: ['editable', 'readonly', 'locked'],
            connection: ['online', 'offline', 'syncing', 'sync-failed'],
            freshness: ['fresh', 'stale', 'cached']
        };
        var axes = {
            data: 'data', permission: 'full', edit: 'editable',
            connection: (typeof navigator !== 'undefined' && navigator.onLine === false) ? 'offline' : 'online',
            freshness: 'fresh'
        };
        function getState() {
            var o = {};
            for (var k in axes) { if (axes.hasOwnProperty(k)) o[k] = axes[k]; }
            return o;
        }
        function apply() {
            var b = document.body;
            if (!b) return;
            for (var k in axes) { if (axes.hasOwnProperty(k)) b.setAttribute('data-ax-' + k, axes[k]); }
            try { document.dispatchEvent(new CustomEvent('ems:shell', { detail: getState() })); } catch (e) { /* IE-safe */ }
        }
        function set(axis, val) {
            if (!VALID[axis] || VALID[axis].indexOf(val) === -1) return false;
            axes[axis] = val;
            apply();
            return true;
        }
        function seed() {
            var b = document.body;
            if (!b) return;
            for (var k in VALID) {
                if (!VALID.hasOwnProperty(k)) continue;
                var v = b.getAttribute('data-ems-ax-' + k);
                if (v && VALID[k].indexOf(v) !== -1) axes[k] = v;
            }
            apply();
        }
        /* محورُ الاتصال من مصدره الحي — يركّب مع لافتة bootFiveStates لا بدلها */
        window.addEventListener('offline', function () { set('connection', 'offline'); });
        window.addEventListener('online', function () { set('connection', 'online'); });
        return { set: set, get: getState, seed: seed, valid: VALID };
    })();

    /* تركيباتُ CM-00 المرئية: القراءةُ فقط تُخفي أفعالَ الكتابة · والقديمةُ تعلن عمرَها */
    function bootShellCss() {
        var css = 'body[data-ax-edit="readonly"] .ems-write-action,body[data-ax-edit="locked"] .ems-write-action{display:none !important}' +
            'body[data-ax-freshness="stale"] #emsAxStale,body[data-ax-freshness="cached"] #emsAxStale{display:block}' +
            '#emsAxStale{display:none;position:fixed;bottom:14px;left:14px;z-index:99997;background:#8a6d00;color:#fff;' +
            'padding:8px 14px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,.25)}';
        var st = document.createElement('style');
        st.textContent = css;
        document.head.appendChild(st);
        var stale = document.createElement('div');
        stale.id = 'emsAxStale';
        stale.textContent = 'البياناتُ المعروضةُ ليست لحظية — حدّث الشاشةَ عند عودة الاتصال';
        document.body.appendChild(stale);
    }

    /* ── Boot ───────────────────────────────────────────────── */
    /* ══ AC-U7 · SH-08 — ربطُ الحقلِ بعنوانِه وقتَ التصيير ═══════════════════
       ◆ لماذا وقتَ التشغيلِ لا في الملفات: القياسُ الحيُّ كشف ما لا يراه الفحصُ
         الساكن — 98 حقلًا في شاشةِ العقودِ و3 معنونةٌ فقط. والسببُ أن العنوانَ
         **موجودٌ ومكتوب** لكنه شقيقُ غلافِ الحقلِ لا جارُه:
           <div class="field"><label>المشروع *</label>
             <div class="control"><select …></div></div>
         فالأداةُ الساكنةُ تبحث عن `<label>` ملاصقٍ للحقلِ فلا تجده، بينما
         قارئُ الشاشةِ لا يجده أيضًا — العيبُ حقيقيٌّ والعنوانُ حاضر.
       ◆ وموضعٌ واحدٌ هنا يغطي كلَّ شاشةٍ تحمّل هذا الملف، بدل تعديلِ مئاتِ
         الملفات — وهو النمطُ نفسُه الذي أعطى زرَّ التصديرِ لـ305 شاشةٍ بصفرِ
         ملفِّ شاشة.
       ◆ ولا يُغيَّر شكلٌ ولا نصّ: يُضاف `for`/`id` أو `aria-label` فقط. */
    function bootFieldLabels() {
        var CTRL = 'input, select, textarea';
        var seq = 0;

        function labelled(el) {
            if (el.getAttribute('aria-label') || el.getAttribute('aria-labelledby')) { return true; }
            if (el.closest('label')) { return true; }
            if (el.id) {
                try { if (document.querySelector('label[for="' + CSS.escape(el.id) + '"]')) { return true; } }
                catch (e) { /* معرِّفٌ لا يقبل الهروب — يُعامَل غيرَ مرتبط */ }
            }
            return false;
        }

        var nodes = document.querySelectorAll(CTRL);
        for (var i = 0; i < nodes.length; i++) {
            var el = nodes[i];
            var t = (el.type || '').toLowerCase();
            if (t === 'hidden' || t === 'submit' || t === 'button' || t === 'reset' || t === 'image') { continue; }
            if (labelled(el)) { continue; }

            // ① عنوانٌ مكتوبٌ في غلافِ الحقل — يُربط بـfor/id (الأفضلُ دلاليًّا)
            var wrap = el.closest('.field, .form-group, .ems-field, .col-form, .mb-3');
            var lab = wrap ? wrap.querySelector('label:not([for])') : null;
            if (lab && lab.textContent.trim() !== '') {
                if (!el.id) { el.id = 'emsl_' + (++seq) + '_' + Math.abs(hash(el.name || String(i))); }
                lab.setAttribute('for', el.id);
                continue;
            }

            // ② حقلٌ داخلَ خليةِ جدول — عنوانُ عمودِه هو عنوانُه
            //    (نمطُ شاشاتِ التحريرِ الشبكيّ: صفٌّ لكلِّ سجلٍّ وحقلٌ لكلِّ عمود،
            //     والعنوانُ المكتوبُ مرةً في الترويسةِ لا يتكرر في كلِّ خلية.)
            var td = el.closest('td');
            if (td && td.parentElement) {
                var tbl = td.closest('table');
                var idx = Array.prototype.indexOf.call(td.parentElement.children, td);
                var th  = tbl ? tbl.querySelectorAll('thead th')[idx] : null;
                if (th && th.textContent.trim() !== '') {
                    el.setAttribute('aria-label', th.textContent.trim().replace(/\s+/g, ' '));
                    continue;
                }
            }

            // ③ لا عنوانَ مكتوبًا — وسمٌ وصفيٌّ من أقربِ نصٍّ متاح
            var txt = (el.getAttribute('placeholder') || el.getAttribute('title') || '').trim();
            if (txt === '' && wrap) {
                var any = wrap.querySelector('label');
                if (any) { txt = any.textContent.trim(); }
            }
            txt = txt.replace(/\s*\*\s*$/, '').replace(/\s+/g, ' ').trim();
            if (txt !== '') { el.setAttribute('aria-label', txt); }
        }

        function hash(s) {
            var h = 0;
            for (var k = 0; k < s.length; k++) { h = ((h << 5) - h + s.charCodeAt(k)) | 0; }
            return h;
        }
    }

    /* ══ AC-U6 · SH-07 — حالةُ الفراغِ مكوِّنًا واحدًا ═══════════════════════
       ◆ «الجدولُ الخالي» ثالثُ الحالاتِ الخمسِ ولم تكن مغطاة: 65 سطحًا من 327.
         والجدولُ الخالي بلا رسالةٍ يُقرأ عطلًا: المستخدمُ لا يعرف أبيانات ناقصةٌ
         أم فلترٌ ضيّقٌ أم صلاحيةٌ حاجبة — فيفتح بلاغًا عن عطلٍ غيرِ موجود.
       ◆ ويُميَّز سببان لا واحد، وهو ما تشترطه الوثيقة:
           **لا نتائج** — ثمة فلترٌ أو بحثٌ فعّال ⇒ العلاجُ توسيعُ الفلتر.
           **لا بيانات** — لا فلترَ أصلًا ⇒ العلاجُ إنشاءُ أولِ سجل.
         والخلطُ بينهما يرسل المستخدمَ في الاتجاهِ الخطأ.
       ◆ ولا يظهر إلا على جدولٍ **خالٍ فعلًا**، فأثرُه على الشاشاتِ العامرةِ صفر.
       ◆ ويُترك ما له حالةُ فراغٍ مكتوبةٌ سلفًا — لا يُزاحَم عملٌ يدويٌّ قائم. */
    function bootEmptyStates() {
        var tables = document.querySelectorAll('table');
        for (var i = 0; i < tables.length; i++) {
            var tb = tables[i];
            var body = tb.tBodies && tb.tBodies[0];
            if (!body) { continue; }
            if (body.rows.length > 0) { continue; }
            if (tb.getAttribute('data-ems-empty') === 'off') { continue; }

            // حالةٌ مكتوبةٌ سلفًا في محيطِ الجدول — تُحترم ولا تُكرَّر
            var host = tb.closest('.card, .panel, .ems-table-wrap, .table-responsive') || tb.parentElement;
            if (host && host.querySelector('.ems-state-empty, .ems-empty')) { continue; }

            var cols = 1;
            var hr = tb.querySelector('thead tr');
            if (hr) { cols = Math.max(1, hr.children.length); }

            // أفلترٌ فعّالٌ الآن؟ — يميّز «لا نتائج» من «لا بيانات»
            var filtered = false;
            var scope = tb.closest('form, .card, .panel, section, .main') || document;
            var ctrls = scope.querySelectorAll('input[type=search], input[type=text], input[type=date], select');
            for (var k = 0; k < ctrls.length; k++) {
                var c = ctrls[k];
                if (c.closest('table') === tb) { continue; }          // حقولُ الجدولِ نفسِه لا تُحتسب
                var v = (c.value || '').trim();
                if (v !== '' && v !== '0' && v.toLowerCase() !== 'all') { filtered = true; break; }
            }

            // زرُّ إنشاءٍ قائمٌ في الصفحة — يُعاد استعمالُه ولا يُخترَع
            var addBtn = document.querySelector('a[href*="add"], a[href*="new"], a[href*="create"], .btn-add, [data-ems-action="add"]');

            var tr = document.createElement('tr');
            tr.className = 'ems-state-empty';
            var td = document.createElement('td');
            td.colSpan = cols;
            td.style.textAlign = 'center';
            td.style.padding = '34px 16px';
            td.style.color = '#6b7280';

            var title = document.createElement('div');
            title.style.fontWeight = '700';
            title.style.marginBottom = '6px';
            title.textContent = filtered ? 'لا نتائجَ مطابقة' : 'لا بياناتٍ بعد';

            var hint = document.createElement('div');
            hint.style.fontSize = '14px';
            hint.textContent = filtered
                ? 'الفلترُ الحاليُّ لا يطابق أيَّ سجلّ — وسّعْ المدى أو امسحِ البحث.'
                : 'لم يُسجَّل شيءٌ في هذه الشاشة حتى الآن.';

            td.appendChild(title);
            td.appendChild(hint);

            if (!filtered && addBtn && addBtn.getAttribute('href')) {
                var a = document.createElement('a');
                a.href = addBtn.getAttribute('href');
                a.textContent = 'إضافةُ أولِ سجل';
                a.style.display = 'inline-block';
                a.style.marginTop = '12px';
                a.className = addBtn.className || '';
                td.appendChild(a);
            }

            tr.appendChild(td);
            body.appendChild(tr);
        }
    }

    /* ══ AC-U5 · SH-06 — منتقي المناظرِ للشاشاتِ العريضة ══════════════════
       ◆ 102 شاشةً تتجاوز عشرينَ عمودًا وأعرضُها ثمانيةٌ وثلاثون. وجدولٌ بهذا
         العرضِ لا يُقرأ: يُمسح أفقيًّا بحثًا عن عمودَين، في كلِّ مرة.
       ◆ ولا يظهر المنتقي إلا حيث يلزم — دونَ العشرينَ لا شيء. فمكوِّنٌ يظهر
         حيث لا حاجةَ له ضجيجٌ يُدرَّب المستخدمُ على تجاهله.
       ◆ والاختيارُ يُحفظ **لصاحبه** لا للجميع، والافتراضيُّ لدورِه يخدم من لم
         يخصّص — فحاجةُ المحاسبِ للأعمدةِ غيرُ حاجةِ المشرف. */
    var VIEW_MIN_COLS = 20;

    function bootSavedViews() {
        var tables = document.querySelectorAll('table');
        for (var i = 0; i < tables.length; i++) {
            var tb = tables[i];
            var head = tb.querySelector('thead tr');
            if (!head || head.children.length < VIEW_MIN_COLS) { continue; }
            if (tb.getAttribute('data-ems-views') === 'off') { continue; }
            mountPicker(tb, head);
        }

        function mountPicker(tb, head) {
            var host = tb.closest('.card, .panel, .ems-table-wrap, .table-responsive') || tb.parentElement;
            if (!host || host.querySelector('.ems-view-picker')) { return; }

            var wrap = document.createElement('div');
            wrap.className = 'ems-view-picker';
            wrap.style.cssText = 'display:flex;gap:8px;align-items:center;margin:0 0 10px;flex-wrap:wrap';

            var lab = document.createElement('label');
            lab.textContent = 'المنظر:';
            lab.style.fontWeight = '600';

            var sel = document.createElement('select');
            sel.className = 'form-select';
            sel.style.maxWidth = '260px';
            var id = 'emsview_' + Math.abs(Date.now() % 100000);
            sel.id = id;
            lab.setAttribute('for', id);

            var opt = document.createElement('option');
            opt.value = ''; opt.textContent = 'كل الأعمدة (' + head.children.length + ')';
            sel.appendChild(opt);

            wrap.appendChild(lab);
            wrap.appendChild(sel);
            host.insertBefore(wrap, host.firstChild);

            function applyColumns(cols) {
                var show = (cols === null) ? null : {};
                if (cols) { for (var k = 0; k < cols.length; k++) { show[cols[k]] = true; } }
                var rows = tb.querySelectorAll('tr');
                for (var r = 0; r < rows.length; r++) {
                    var cells = rows[r].children;
                    for (var c = 0; c < cells.length; c++) {
                        cells[c].style.display = (show === null || show[c]) ? '' : 'none';
                    }
                }
            }

            sel.addEventListener('change', function () {
                var o = sel.options[sel.selectedIndex];
                var raw = o.getAttribute('data-cols');
                applyColumns(raw ? JSON.parse(raw) : null);
            });

            var screen = (location.pathname.replace(/^\/ems\//, '') || '').split('?')[0];
            fetch('/ems/includes/saved_views_endpoint.php?do=list&screen=' + encodeURIComponent(screen),
                  { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j || !j.ok || !j.data || !j.data.length) { return; }
                    var toApply = null;
                    j.data.forEach(function (v) {
                        if (v.columns === null) { return; }        // «الكل» موجودٌ سلفًا
                        var o = document.createElement('option');
                        o.value = String(v.id);
                        o.textContent = v.name + (v.mine ? '' : ' (الدور)');
                        o.setAttribute('data-cols', JSON.stringify(v.columns));
                        sel.appendChild(o);
                        if (v.default && toApply === null) { toApply = o; }
                    });
                    if (toApply) { sel.value = toApply.value; sel.dispatchEvent(new Event('change')); }
                })
                .catch(function (e) {
                    // تعذُّرُ جلبِ المناظرِ يترك «كلَّ الأعمدة» عاملًا — لا يُعطَّل الجدول.
                });
        }
    }

    function boot() {
        bootUnifiedHeaders();
        bootUnifiedTables();
        try { bootFieldLabels(); } catch (eFl) { /* الوصولية لا تُسقط الشاشة */ }
        try { bootEmptyStates(); } catch (eEs) { /* حالةُ الفراغِ لا تُسقط الشاشة */ }
        try { bootSavedViews(); } catch (eSv) { /* المنتقي لا يُسقط الجدول */ }
        try { bootFiveStates(); } catch (eFs) { /* لا يعطل التوحيد */ }
        try { bootShellCss(); window.EmsScreenShell.seed(); } catch (eSh) { /* CM-00 لا يعطل القائم */ }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

})();
