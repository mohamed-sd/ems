/**
 * ems-components.js — نواة مكونات الواجهة (UXR-01 §4-4 · UI-01..20 الجزء الحي)
 * ═══════════════════════════════════════════════════════════════════════════
 * الطبقة الحية فوق القشرة (ems-shell.css) والغلاف الحاكم (EmsScreenShell):
 *
 *   حالات (U5): UI-11 Empty · UI-12 NoResults · UI-13 Error · UI-14 Access ·
 *               UI-15 Skeleton · UI-16 Sync · UI-17 ChartGuard
 *   جداول (U6): حارس صفر الأعمدة (UI-05/06) + لغة UI-12 في DataTables
 *   بطاقات (U7): UI-07 KPI (سبعة حقول إلزامًا) · UI-08 Task · UI-09 Approval ·
 *               UI-10 Alert (تنبيهٌ بإجراء)
 *
 * الأحكام الملزمة المنفَّذة:
 *   - UI-11: «لا أصفار عملاقة» — الفارغ يشرح السبب ويعرض زر إنشاء.
 *   - UI-12: تُميَّز عن الفارغة ويُقترح تفريغ الفلاتر.
 *   - UI-13: ما حدث + كيف يُصحح + رمز الخطأ — لا نص تقني خام.
 *   - UI-14: سبب الحجب ومن يمنح — ولا يُصيَّر المحتوى أصلًا (الحجب خادمي).
 *   - UI-16: شريحة مزامنة تقرأ محور الاتصال من EmsScreenShell.
 *   - UI-17: لا رسم بلا بيانات ولا بمحاور افتراضية.
 *   - UI-05/06: لا يُسمح ببلوغ صفر أعمدة ظاهرة.
 *   - UI-07: صفر رقم بلا السبعة (العنوان·القيمة·الوحدة·الفترة·المقارنة·الحالة·التعمق).
 */
(function () {
    'use strict';

    function h(tag, cls, html) {
        var el = document.createElement(tag);
        if (cls) { el.className = cls; }
        if (html !== undefined) { el.innerHTML = html; }
        return el;
    }
    function esc(s) {
        return String(s === undefined || s === null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    var EmsUI = {};

    /* ── UI-11: Empty State — لا بيانات بعد (سبب + زر إنشاء) ────────────── */
    EmsUI.emptyState = function (opts) {
        opts = opts || {};
        return h('div', 'ems-state ems-state-empty',
            '<i class="fas fa-inbox" aria-hidden="true"></i>' +
            '<div class="ems-state-title">لا بيانات بعد</div>' +
            '<div class="ems-state-reason">' + esc(opts.reason || 'لم يُنشأ أي سجل في هذا النطاق حتى الآن') + '</div>' +
            (opts.createHref
                ? '<a class="ems-btn-create" href="' + esc(opts.createHref) + '"><i class="fa fa-plus"></i> ' + esc(opts.createLabel || 'إنشاء أول سجل') + '</a>'
                : ''));
    };

    /* ── UI-12: No Results — تُميَّز عن الفارغة وتقترح تفريغ الفلاتر ────── */
    EmsUI.noResults = function (opts) {
        opts = opts || {};
        var el = h('div', 'ems-state ems-state-noresults',
            '<i class="fas fa-magnifying-glass-minus" aria-hidden="true"></i>' +
            '<div class="ems-state-title">لا نتائج لبحثك</div>' +
            '<div class="ems-state-reason">جرّب توسيع البحث أو تفريغ الفلاتر المفعَّلة</div>' +
            '<button type="button" class="ems-btn-clear-filters">تفريغ الفلاتر</button>');
        el.querySelector('.ems-btn-clear-filters').addEventListener('click', function () {
            if (typeof opts.onClear === 'function') { opts.onClear(); }
        });
        return el;
    };

    /* ── UI-13: Error State — ما حدث وكيف يُصحح ورمز الخطأ ──────────────── */
    EmsUI.errorState = function (opts) {
        opts = opts || {};
        return h('div', 'ems-state ems-state-error',
            '<i class="fas fa-triangle-exclamation" aria-hidden="true"></i>' +
            '<div class="ems-state-title">' + esc(opts.text || 'تعذر إتمام العملية') + '</div>' +
            '<div class="ems-state-reason">' + esc(opts.hint || 'أعد المحاولة، وإن تكرر الخطأ أبلغ الدعم بالرمز أدناه') + '</div>' +
            '<code class="ems-state-code">' + esc(opts.code || 'ERR-GEN') + '</code>');
    };

    /* ── UI-14: Access State — سبب الحجب ومن يمنح (المحتوى لا يُصيَّر خادميًّا) ─ */
    EmsUI.accessState = function (opts) {
        opts = opts || {};
        return h('div', 'ems-state ems-state-access',
            '<i class="fas fa-lock" aria-hidden="true"></i>' +
            '<div class="ems-state-title">هذا المحتوى محجوب عنك</div>' +
            '<div class="ems-state-reason">' + esc(opts.reason || 'صلاحيتك الحالية لا تشمل هذا النطاق') +
            ' — يمنحها: <b>' + esc(opts.grantor || 'مدير الصلاحيات') + '</b></div>');
    };

    /* ── UI-15: Loading Skeleton — هيكل يشبه المحتوى (لا شاشة بيضاء ولا دوّار) ─ */
    EmsUI.skeleton = function (opts) {
        opts = opts || {};
        var rows = Math.max(1, Math.min(12, opts.rows || 5));
        var html = '';
        for (var i = 0; i < rows; i++) {
            html += '<div class="ems-skel-row"><span class="ems-skel-cell w20"></span>' +
                    '<span class="ems-skel-cell w40"></span><span class="ems-skel-cell w25"></span></div>';
        }
        return h('div', 'ems-skeleton', html);
    };

    /* ── UI-16: Sync State — شريحةٌ ظاهرة تقرأ محور الاتصال من الغلاف الحاكم ─ */
    EmsUI.syncChip = function (opts) {
        opts = opts || {};
        var el = h('span', 'ems-sync-chip', '');
        function paint() {
            var conn = (window.EmsScreenShell && window.EmsScreenShell.get)
                ? (window.EmsScreenShell.get('connection') || 'online') : (navigator.onLine ? 'online' : 'offline');
            var pending = opts.pendingCount ? opts.pendingCount() : 0;
            var map = {
                online:  ['ems-sync-online',  'fa-wifi',        'متصل'],
                offline: ['ems-sync-offline', 'fa-plane-up',    'دون اتصال'],
                syncing: ['ems-sync-syncing', 'fa-rotate',      'قيد المزامنة'],
                failed:  ['ems-sync-failed',  'fa-circle-xmark','فشلت المزامنة']
            };
            var m = map[conn] || map.online;
            el.className = 'ems-sync-chip ' + m[0];
            el.innerHTML = '<i class="fas ' + m[1] + '"></i> ' + m[2] +
                (pending > 0 ? ' · معلَّق: <b>' + pending + '</b>' : '');
        }
        paint();
        window.addEventListener('online', paint);
        window.addEventListener('offline', paint);
        document.addEventListener('ems:ax-changed', paint);
        return el;
    };

    /* ── UI-17: Chart Guard — لا رسم بلا بيانات ولا بمحاور افتراضية ─────── */
    EmsUI.chartGuard = function (container, series, renderFn, emptyOpts) {
        var total = 0;
        (series || []).forEach(function (s) {
            (s && s.data ? s.data : []).forEach(function (v) { total += Math.abs(parseFloat(v) || 0); });
        });
        if (total === 0) {
            container.innerHTML = '';
            container.appendChild(EmsUI.emptyState(emptyOpts || { reason: 'لا بيانات في الفترة المعروضة — الرسم لا يُعرض بمحاور افتراضية' }));
            return null;
        }
        return renderFn();
    };

    /* ── UI-07: KPI Card — السبعة إلزامًا: صفر رقم بلا مقامه ومصدره ─────── */
    EmsUI.kpiCard = function (k) {
        k = k || {};
        var missing = ['title', 'value', 'unit', 'period', 'comparison', 'status', 'drillHref']
            .filter(function (f) { return k[f] === undefined || k[f] === null || k[f] === ''; });
        if (missing.length) {
            // الحكم UXR-0054: لا يُصيَّر رقم ناقص العقد — تُعرض حالة خطأ مطوّر
            return EmsUI.errorState({
                text: 'بطاقة مؤشر ناقصة العقد',
                hint: 'الحقول الغائبة: ' + missing.join('، ') + ' — املأ السبعة كاملة',
                code: 'UI-07-CONTRACT'
            });
        }
        var toneCls = { ok: 'ems-kpi-ok', warn: 'ems-kpi-warn', err: 'ems-kpi-err', neutral: '' }[k.status] || '';
        var card = h('a', 'ems-kpi-card ' + toneCls,
            '<div class="ems-kpi-title">' + esc(k.title) + '</div>' +
            '<div class="ems-kpi-value">' + esc(k.value) + ' <small>' + esc(k.unit) + '</small></div>' +
            '<div class="ems-kpi-meta"><span>' + esc(k.period) + '</span><span>' + esc(k.comparison) + '</span></div>');
        card.href = k.drillHref;
        card.title = 'تعمّق: ' + k.title;
        return card;
    };

    /* ── UI-08: Task Card — المهمة ومهلتها ومصدرها وإجراؤها ─────────────── */
    EmsUI.taskCard = function (t) {
        t = t || {};
        var el = h('div', 'ems-task-card',
            '<div class="ems-task-main"><b>' + esc(t.label) + '</b>' +
            '<small>المصدر: ' + esc(t.source || '—') + (t.due ? ' · المهلة: ' + esc(t.due) : '') + '</small></div>' +
            '<a class="ems-btn-secondary" href="' + esc(t.href || '#') + '">' + esc(t.actionLabel || 'افتح') +
            (t.count ? ' (' + esc(t.count) + ')' : '') + '</a>');
        return el;
    };

    /* ── UI-09: Approval Card — ما ينتظر قراره بسقفه وأثر تأخيره ────────── */
    EmsUI.approvalCard = function (a) {
        a = a || {};
        return h('div', 'ems-approval-card',
            '<i class="fas fa-stamp" aria-hidden="true"></i>' +
            '<div class="ems-task-main"><b>' + esc(a.label) + '</b>' +
            '<small>' + (a.cap ? 'السقف: ' + esc(a.cap) + ' · ' : '') +
            'أثر التأخير: ' + esc(a.delayImpact || 'تعطُّل الخطوة التالية') + '</small></div>' +
            '<a class="ems-btn-primary" href="' + esc(a.href || '#') + '">قرِّر' +
            (a.count ? ' (' + esc(a.count) + ')' : '') + '</a>');
    };

    /* ── UI-10: Alert List — تنبيه بإجراء لا إحاطة فقط ──────────────────── */
    EmsUI.alertList = function (alerts) {
        var wrap = h('ul', 'ems-alert-list', '');
        (alerts || []).forEach(function (al) {
            var li = h('li', 'ems-alert-item ems-alert-' + (al.tone || 'warn'),
                '<span class="ems-alert-text">' + esc(al.label) +
                (al.count !== undefined ? ' <b>' + esc(al.count) + '</b>' : '') + '</span>' +
                '<a class="ems-btn-secondary" href="' + esc(al.href || '#') + '">' + esc(al.actionLabel || 'عالج') + '</a>');
            wrap.appendChild(li);
        });
        return wrap;
    };

    /* ── U6: حارس صفر الأعمدة (UI-05/06) — على DataTables وEmsColumnGroups ─ */
    function visibleColCount(api) {
        var n = 0;
        api.columns().every(function () { if (this.visible()) { n++; } });
        return n;
    }
    if (window.jQuery && jQuery.fn && jQuery.fn.dataTable) {
        jQuery(document).on('column-visibility.dt', function (e, settings, column, state) {
            if (state !== false) { return; }
            var api = new jQuery.fn.dataTable.Api(settings);
            if (visibleColCount(api) === 0) {
                // الحارس: أعد إظهار العمود فورًا — لا يُسمح ببلوغ صفر أعمدة
                api.column(column).visible(true);
                if (window.console) { console.warn('EmsUI: حارس صفر الأعمدة أعاد العمود ' + column); }
            }
        });
    }
    EmsUI.guardColumnVisibility = function (api, columnIdx, wantVisible) {
        if (!wantVisible && visibleColCount(api) <= 1) { return false; }
        api.column(columnIdx).visible(wantVisible);
        return true;
    };

    window.EmsUI = EmsUI;

    /* لغة UI-12 لجداول DataTables المهيأة بعدنا (تكامل غير كاسر):
       النص يفرّق «لا بيانات بعد» عن «لا نتائج لبحثك» تلقائيًّا. */
    if (window.jQuery && jQuery.fn && jQuery.fn.dataTable && jQuery.fn.dataTable.defaults) {
        var L = jQuery.fn.dataTable.defaults.language = jQuery.fn.dataTable.defaults.language || {};
        if (!L.zeroRecords) { L.zeroRecords = 'لا نتائج لبحثك — جرّب تفريغ الفلاتر أو توسيع البحث'; }
        if (!L.emptyTable)  { L.emptyTable  = 'لا بيانات بعد — لم يُنشأ أي سجل في هذا النطاق'; }
    }
})();
