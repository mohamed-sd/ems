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

    /* ═══════════════════════════════════════════════════════════════════════
       UI-04 · منتقي المناظر المحفوظة (Saved Views)
       ─────────────────────────────────────────────────────────────────────
       الحكم (UXR-0051): «منتقي المنظرِ الشخصيِّ والمعتمَد — إلزاميٌّ في الشاشات
       الطويلة». والمنظرُ حزمةُ حالةٍ لا زينةً: فلاترُ الشاشةِ وأعمدتُها الظاهرةُ
       وترتيبُها. صنفان لا يختلطان:
         · معتمَد (approved): يأتي من الخادمِ ولا يعدّله المستخدم — يُميَّز بوسم.
         · شخصيّ (personal): يحفظه المستخدمُ لنفسِه في مخزنِ متصفّحه.
       ولا يُسمّى منظرٌ باسمٍ مكرَّرٍ في صنفه، ولا يُحذف معتمَدٌ من الواجهة.
       ═══════════════════════════════════════════════════════════════════════ */
    var VIEWS_NS = 'emsViews:';

    function viewsKey(screenKey) { return VIEWS_NS + String(screenKey || 'unknown'); }

    function readPersonal(screenKey) {
        try {
            var raw = window.localStorage.getItem(viewsKey(screenKey));
            var arr = raw ? JSON.parse(raw) : [];
            return Object.prototype.toString.call(arr) === '[object Array]' ? arr : [];
        } catch (e) { return []; }
    }

    function writePersonal(screenKey, list) {
        try { window.localStorage.setItem(viewsKey(screenKey), JSON.stringify(list)); return true; }
        catch (e) { return false; }
    }

    EmsUI.savedViews = function (opts) {
        opts = opts || {};
        var screenKey = opts.screenKey;
        if (!screenKey) { throw new Error('EmsUI.savedViews: screenKey إلزامي — المنظر يخص شاشةً بعينها'); }
        if (typeof opts.capture !== 'function' || typeof opts.apply !== 'function') {
            throw new Error('EmsUI.savedViews: capture وapply إلزاميان — بلا التقاطٍ وتطبيقٍ لا منظرَ بل زينة');
        }
        var approved = opts.approved || [];   // [{name, state}] من الخادم

        var el = h('div', 'ems-saved-views');
        var sel = h('select', 'ems-views-select');
        var btnSave = h('button', 'ems-views-save', '<i class="fa fa-bookmark"></i> احفظ المنظر');
        var btnDel = h('button', 'ems-views-del', '<i class="fa fa-trash"></i> احذف');
        btnSave.type = 'button'; btnDel.type = 'button';
        btnDel.disabled = true;

        function repaint(keep) {
            var personal = readPersonal(screenKey);
            sel.innerHTML = '<option value="">— المنظر الحالي (غير محفوظ) —</option>';
            var i;
            for (i = 0; i < approved.length; i++) {
                sel.innerHTML += '<option value="a:' + i + '">◆ ' + esc(approved[i].name) + ' (معتمَد)</option>';
            }
            for (i = 0; i < personal.length; i++) {
                sel.innerHTML += '<option value="p:' + i + '">' + esc(personal[i].name) + ' (شخصي)</option>';
            }
            if (keep) { sel.value = keep; }
            btnDel.disabled = String(sel.value).charAt(0) !== 'p';
        }

        sel.addEventListener('change', function () {
            var v = String(sel.value);
            btnDel.disabled = v.charAt(0) !== 'p';
            if (v === '') { return; }
            var idx = parseInt(v.slice(2), 10);
            var src = v.charAt(0) === 'a' ? approved : readPersonal(screenKey);
            if (src[idx]) { opts.apply(src[idx].state); }
        });

        btnSave.addEventListener('click', function () {
            var name = window.prompt('اسمُ المنظر (يظهر لك وحدَك):', '');
            if (name === null) { return; }
            name = String(name).trim();
            if (name === '') { return; }
            var personal = readPersonal(screenKey);
            var at = -1, i;
            for (i = 0; i < personal.length; i++) { if (personal[i].name === name) { at = i; break; } }
            var entry = { name: name, state: opts.capture(), at: new Date().toISOString() };
            if (at >= 0) { personal[at] = entry; } else { personal.push(entry); at = personal.length - 1; }
            writePersonal(screenKey, personal);
            repaint('p:' + at);
        });

        btnDel.addEventListener('click', function () {
            var v = String(sel.value);
            if (v.charAt(0) !== 'p') { return; }
            var personal = readPersonal(screenKey);
            personal.splice(parseInt(v.slice(2), 10), 1);
            writePersonal(screenKey, personal);
            repaint('');
        });

        el.appendChild(sel);
        el.appendChild(btnSave);
        el.appendChild(btnDel);
        repaint('');
        return el;
    };

    /* ═══════════════════════════════════════════════════════════════════════
       UI-19 · التأكيد اللحظي والترقيم
       ─────────────────────────────────────────────────────────────────────
       الحكم (UXR-0066): «تأكيدٌ لحظيٌّ · وترقيمٌ يُظهر الإجمالي · ولا معلومةَ
       لا تتكرر في التوست». فالتوستُ يؤكّد فعلًا وقع، ولا يكون الحاملَ الوحيدَ
       لمعلومةٍ لا تُقرأ في مكانٍ آخر — ولذلك يلزمه `echoedAt`: أين تُقرأ
       المعلومةُ نفسُها بعد اختفائه. والترقيمُ يعلن الإجمالَ لا الصفحةَ وحدَها.
       ═══════════════════════════════════════════════════════════════════════ */
    function toastHost() {
        var host = document.getElementById('emsToastHost');
        if (!host) {
            host = h('div', 'ems-toast-host');
            host.id = 'emsToastHost';
            host.setAttribute('role', 'status');
            host.setAttribute('aria-live', 'polite');
            document.body.appendChild(host);
        }
        return host;
    }

    EmsUI.toast = function (opts) {
        opts = opts || {};
        if (!opts.text) { throw new Error('EmsUI.toast: text إلزامي'); }
        if (!opts.echoedAt) {
            throw new Error('EmsUI.toast: echoedAt إلزامي — «لا معلومةَ لا تتكرر في التوست» (UI-19): '
                + 'صرّح أين تُقرأ المعلومةُ نفسُها بعد اختفائه');
        }
        var kind = opts.kind === 'error' ? 'error' : (opts.kind === 'warn' ? 'warn' : 'ok');
        var el = h('div', 'ems-toast ems-toast-' + kind,
            '<i class="fa ' + (kind === 'ok' ? 'fa-circle-check' : (kind === 'warn' ? 'fa-triangle-exclamation' : 'fa-circle-xmark')) + '"></i>'
            + '<span class="ems-toast-text">' + esc(opts.text) + '</span>'
            + '<span class="ems-toast-echo">تجدها في: ' + esc(opts.echoedAt) + '</span>'
            + (opts.code ? '<code class="ems-toast-code">' + esc(opts.code) + '</code>' : '')
            + '<button type="button" class="ems-toast-close" aria-label="إغلاق">&times;</button>');
        el.querySelector('.ems-toast-close').addEventListener('click', function () { el.remove(); });
        toastHost().appendChild(el);
        var ms = typeof opts.ms === 'number' ? opts.ms : 6000;
        if (ms > 0) { window.setTimeout(function () { el.remove(); }, ms); }
        return el;
    };

    EmsUI.pagination = function (opts) {
        opts = opts || {};
        var total = Number(opts.total);
        if (!isFinite(total) || total < 0) {
            throw new Error('EmsUI.pagination: total إلزامي ورقميّ — «ترقيمٌ يُظهر الإجمالي» (UI-19)');
        }
        var size = Math.max(1, Number(opts.pageSize) || 10);
        var page = Math.max(1, Number(opts.page) || 1);
        var pages = Math.max(1, Math.ceil(total / size));
        if (page > pages) { page = pages; }
        var from = total === 0 ? 0 : ((page - 1) * size) + 1;
        var to = Math.min(total, page * size);

        var el = h('div', 'ems-pagination',
            '<span class="ems-pg-count">' + from + '–' + to + ' من <b>' + total + '</b>'
            + (opts.totalLabel ? ' ' + esc(opts.totalLabel) : ' سجلًّا') + '</span>'
            + '<button type="button" class="ems-pg-prev"' + (page <= 1 ? ' disabled' : '') + '>السابق</button>'
            + '<span class="ems-pg-pos">صفحة ' + page + ' من ' + pages + '</span>'
            + '<button type="button" class="ems-pg-next"' + (page >= pages ? ' disabled' : '') + '>التالي</button>');
        function go(p) { if (typeof opts.onPage === 'function') { opts.onPage(p); } }
        el.querySelector('.ems-pg-prev').addEventListener('click', function () { go(page - 1); });
        el.querySelector('.ems-pg-next').addEventListener('click', function () { go(page + 1); });
        return el;
    };

    /* ═══════════════════════════════════════════════════════════════════════
       UI-20 · لوحة التصدير والاستيراد
       ─────────────────────────────────────────────────────────────────────
       الحكم (UXR-0067 · UX-01 §١١-٢/٣): الخياراتُ الثمانيةُ الواعيةُ بالمنظر ·
       والفحوصُ الثمانيةَ عشرَ بمعاينةٍ تصنّف كلَّ صفٍّ · وسجلُّ تصديرٍ بتسعةِ
       بنودٍ إلزامًا. اللوحةُ هنا تعلن الثلاثةَ صراحةً: تعرض الخياراتِ المسموحةَ
       بصلاحيةِ المستخدمِ لا كلَّها، وتبني سجلَّ التصديرِ التساعيَّ قبل التنفيذ،
       وترفض استيرادًا بلا معاينةٍ مصنَّفة.
       ═══════════════════════════════════════════════════════════════════════ */
    EmsUI.EXPORT_OPTIONS = [
        { key: 'selected',    label: 'السجلاتُ المحددة',                 perm: 'PRM-EXP' },
        { key: 'filtered',    label: 'نتائجُ البحثِ والفلاترِ الحالية',   perm: 'PRM-EXP' },
        { key: 'visibleCols', label: 'الأعمدةُ الظاهرةُ في العرض الحالي', perm: 'PRM-EXP' },
        { key: 'viewCols',    label: 'أعمدةُ المنظرِ المختار',            perm: 'PRM-EXP' },
        { key: 'allCols',     label: 'جميعُ الأعمدةِ المسموحة',           perm: 'PRM-EXP' },
        { key: 'template',    label: 'قالبُ تصديرٍ محفوظ',                perm: 'PRM-EXP' },
        { key: 'external',    label: 'ملفٌّ خارجيٌّ منقَّح',              perm: 'PRM-EXPX' },
        { key: 'archive',     label: 'الملفُّ الكاملُ للأرشفة',           perm: 'PRM-EXPB' }
    ];

    EmsUI.IMPORT_CHECKS = [
        'المعرّفات والمفاتيح', 'الحقول الإلزامية', 'أنواع البيانات', 'تنسيق التواريخ',
        'العملات والوحدات', 'القوائم المرجعية', 'منع التكرار بمفاتيح المطابقة',
        'العلاقات مع سجلات أخرى', 'صلاحية المستخدم', 'نطاق الكيان والمشروع والموقع',
        'سريان العقود والوثائق', 'الحقول الحساسة', 'السجلات المقفلة',
        'الفترات المحاسبية المقفلة', 'التعارض بين الصفوف', 'الترتيب المنطقي للتواريخ',
        'السجل التابع بلا أم', 'حرّاس الشاشة كلها بالرمز نفسه'
    ];

    EmsUI.IMPORT_CLASSES = ['سليم', 'تحذير', 'مرفوض', 'مكرّر', 'ناقص', 'خارج النطاق'];

    /** سجلُّ التصديرِ التساعيُّ — يُبنى قبل التنفيذِ ويرفض النقص */
    EmsUI.exportManifest = function (m) {
        m = m || {};
        var need = ['viewName', 'columns', 'filters', 'period', 'selection',
                    'user', 'at', 'opId', 'excludedByPermission'];
        var missing = [];
        for (var i = 0; i < need.length; i++) {
            if (m[need[i]] === undefined || m[need[i]] === null) { missing.push(need[i]); }
        }
        if (missing.length) {
            throw new Error('EmsUI.exportManifest: سجلُّ التصديرِ تسعةُ بنودٍ إلزامًا — الناقص: ' + missing.join('، '));
        }
        return {
            viewName: m.viewName, columns: m.columns, filters: m.filters, period: m.period,
            selection: m.selection, user: m.user, at: m.at, opId: m.opId,
            excludedByPermission: m.excludedByPermission
        };
    };

    EmsUI.ioPanel = function (opts) {
        opts = opts || {};
        var grants = opts.grants || [];       // ['PRM-EXP', ...]
        var el = h('div', 'ems-io-panel');
        var allowed = [], denied = [];
        for (var i = 0; i < EmsUI.EXPORT_OPTIONS.length; i++) {
            var o = EmsUI.EXPORT_OPTIONS[i];
            (grants.indexOf(o.perm) >= 0 ? allowed : denied).push(o);
        }
        var html = '<div class="ems-io-title"><i class="fa fa-file-export"></i> التصدير — الخيارات الثمانية</div>'
                 + '<div class="ems-io-opts">';
        for (i = 0; i < allowed.length; i++) {
            html += '<label><input type="radio" name="emsIoOpt" value="' + esc(allowed[i].key) + '"> '
                 + esc(allowed[i].label) + '</label>';
        }
        html += '</div>';
        if (denied.length) {
            /* UI-14: لا يُخفى الممنوعُ صامتًا — يُقال ومن يمنحه */
            html += '<div class="ems-io-denied">خيارات خلف صلاحية: ';
            for (i = 0; i < denied.length; i++) {
                html += '<span>' + esc(denied[i].label) + ' <code>' + esc(denied[i].perm) + '</code></span>';
            }
            html += ' — يمنحها مدير الصلاحيات</div>';
        }
        html += '<div class="ems-io-manifest">كلُّ ملفٍ مصدَّر يحمل سجلَّه التساعي: المنظر · الأعمدة · '
              + 'الفلاتر · الفترة · السجلات وعددها · المستخدم وصفته · وقت التصدير · رقم العملية · '
              + 'الحقول المستبعَدة بالصلاحية.</div>'
              + '<div class="ems-io-title"><i class="fa fa-file-import"></i> الاستيراد — '
              + EmsUI.IMPORT_CHECKS.length + ' فحصًا ولا استيرادَ بلا معاينة</div>'
              + '<ol class="ems-io-checks">';
        for (i = 0; i < EmsUI.IMPORT_CHECKS.length; i++) {
            html += '<li>' + esc(EmsUI.IMPORT_CHECKS[i]) + '</li>';
        }
        html += '</ol><div class="ems-io-classes">المعاينةُ تصنّف كلَّ صفٍّ إلى: '
              + esc(EmsUI.IMPORT_CLASSES.join(' · ')) + '</div>';
        el.innerHTML = html;
        return el;
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
