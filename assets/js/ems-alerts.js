/* ═══════════════════════════════════════════════════════════════════════
   نظام الرسائل الموحَّد — EMS Alerts
   قرار المالك 2026-08-09: توحيدُ كل ما يظهر للمستخدم من رسائل.

   القياسُ قبل العمل (فحصٌ آليٌّ على الشجرة الحية):
     · 261 نداءَ alert() أصليًّا في 45 ملفًّا — نافذةُ متصفحٍ حاجزةٌ قبيحة
     · 397 لافتةَ ?msg= في 172 ملفًّا — بأشكالٍ متفرقةٍ لكل وحدة
     · 275 لافتةَ bootstrap في 179 ملفًّا
   فالموضعُ الصحيحُ للتوحيد هذا الملفُّ وحدَه: **صفرُ ملفِّ شاشةٍ يُعدَّل**،
   ويُلتقط كلُّ ما سبق من مصدره.

   يُحمَّل في <head> **بلا defer** عمدًا: 50 رسالةً يطبعها الخادمُ داخل
   <script>alert(…)</script> في متن الصفحة، فلو تأخّر التحميلُ لسبقتنا وخرجت
   بنافذة المتصفح. والتوستُ يُصفُّ في طابورٍ حتى يجهز <body> ثم يُفرَغ.
   ═══════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';
    if (window.EmsAlert) return;

    var LIFE = { success: 4000, info: 5000, warning: 7000, error: 0 }; // 0 = لا يزول تلقائيًّا
    var GLYPH = { success: '✓', error: '✕', warning: '!', info: 'i' }; // للّافتات السطرية (نصٌّ عبر attr)
    var TITLE = { success: 'تم بنجاح', error: 'خطأ', warning: 'تنبيه', info: 'معلومة' };

    /* ── أيقوناتٌ متجهةٌ داخليّة ────────────────────────────────────────
       SVG لا Font Awesome: الأيقونةُ جزءٌ من عقد الرسالة، فلا تُترك رهنَ
       خطٍّ خارجيٍّ قد يتأخر أو يسقط فتخرج الرسالةُ بلا دلالةٍ بصرية. */
    function svg(inner) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" ' +
               'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + inner + '</svg>';
    }
    var ICON = {
        create:  svg('<path d="M12 5v14"/><path d="M5 12h14"/>'),
        update:  svg('<path d="M16.6 3.4a2.1 2.1 0 0 1 3 3L8 18l-4.2 1.2L5 15z"/>'),
        remove:  svg('<path d="M3.5 6h17"/><path d="M9 6V3.8h6V6"/>' +
                     '<path d="M5.8 6l.9 13.2a1 1 0 0 0 1 .9h8.6a1 1 0 0 0 1-.9L18.2 6"/>'),
        restore: svg('<path d="M3.5 12a8.5 8.5 0 1 0 2.6-6.1"/><path d="M3.5 4.5V10H9"/>'),
        send:    svg('<path d="M21 3 10.5 13.5"/><path d="M21 3l-6.8 18-3.7-7.5L3 9.8z"/>'),
        success: svg('<path d="M4.5 12.5l5 5 10-11"/>'),
        error:   svg('<path d="M6.5 6.5l11 11"/><path d="M17.5 6.5l-11 11"/>'),
        warning: svg('<path d="M10.3 3.9 2.6 17.4A1.8 1.8 0 0 0 4.2 20h15.6a1.8 1.8 0 0 0 1.6-2.6L13.7 3.9a1.9 1.9 0 0 0-3.4 0z"/>' +
                     '<path d="M12 9v4.6"/><path d="M12 17.1v.01"/>'),
        info:    svg('<circle cx="12" cy="12" r="9"/><path d="M12 11.2v5"/><path d="M12 7.7v.01"/>')
    };

    /* ── طبقةُ الفعل ───────────────────────────────────────────────────
       النتيجةُ شيءٌ والفعلُ شيءٌ آخر: «تمت الإضافة» و«تم الحذف» كلاهما نجاحٌ،
       وكانا يخرجان بلونٍ واحدٍ وعلامةِ صحٍّ واحدة فلا يفرّق المستخدمُ بينهما.
       الفعلُ يُستنتج من النص ويُبدّل النغمةَ والأيقونةَ والعنوان.

       يُطبَّق **على النجاح وحدَه**: «لا تملك صلاحية حذف» فيها «حذف» وهي منعٌ
       لا حذف، فلو استُنتج الفعلُ قبل النتيجة لخرج المنعُ بلون الحذف. */
    var ACTIONS = [
        { key: 'remove',  re: /حذف|إزالة|أُزيل|ازالة|شطب|أرشفة|ارشفة/,               title: 'تم الحذف',      icon: 'remove'  },
        { key: 'restore', re: /استعادة|استرجاع|استُعيد|استعيد|تراجع/,                 title: 'تمت الاستعادة', icon: 'restore' },
        { key: 'create',  re: /إضافة|اضافة|أُضيف|اضيف|إنشاء|انشاء|تسجيل|إدراج|ادراج/, title: 'تمت الإضافة',   icon: 'create'  },
        { key: 'update',  re: /تعديل|تحديث|عُدِّل|عدل|تغيير|نقل|إسناد|اسناد/,          title: 'تم التعديل',    icon: 'update'  },
        { key: 'send',    re: /إرسال|ارسال|أُرسل|ارسل|إشعار|رفع|تصدير|استيراد/,       title: 'تم الإرسال',    icon: 'send'    }
    ];

    function detectAction(text) {
        var s = String(text || '');
        for (var i = 0; i < ACTIONS.length; i++) { if (ACTIONS[i].re.test(s)) return ACTIONS[i]; }
        return null;
    }
    var MAX_VISIBLE = 4;
    var STASH_KEY = 'emsAlertStash';
    var STASH_TTL = 10000; // رسالةٌ أقدمُ من ذلك لا تُعاد — لئلّا يطاردَ المستخدمَ خبرٌ بائد

    var host = null;
    var queue = [];
    var live = [];   // {el, key, count, timer}

    /* ── استنتاجُ النوع من نصِّ الرسالة ────────────────────────────────
       الترتيب مقصود: الخطأ أولًا ثم التحذير ثم النجاح — فرسالةٌ مثل
       «لا يمكن الحذف بنجاح» خطأٌ لا نجاح، والرمزُ أقوى دلالةً من الكلمة. */
    var RE_ERROR   = /[❌⛔🚫]|خطأ|فشل|تعذّر|تعذر|لا يمكن|غير مصرح|غير مصرّح|مرفوض|ممنوع|غير صالح|لم يتم/;
    var RE_WARNING = /[⚠️⚠]|تحذير|تنبيه|انتباه|يرجى|برجاء|يجب/;
    var RE_SUCCESS = /[✅✔️✔🎉]|بنجاح|تم الحفظ|تم التعديل|تم الحذف|تمت|أُضيف|اضيف|حُفظ|حفظ/;

    function inferType(text) {
        var s = String(text || '');
        if (RE_ERROR.test(s)) return 'error';
        if (RE_WARNING.test(s)) return 'warning';
        if (RE_SUCCESS.test(s)) return 'success';
        return 'info';
    }

    function normalizeType(t) {
        t = String(t || '').toLowerCase();
        if (t === 'danger' || t === 'err' || t === 'error') return 'error';
        if (t === 'success' || t === 'ok') return 'success';
        if (t === 'warning' || t === 'warn') return 'warning';
        if (t === 'info') return 'info';
        return null;
    }

    /* ── بناءُ الحاوية عند أول حاجةٍ إليها ─────────────────────────────── */
    function ensureHost() {
        if (host && host.isConnected) return host;
        if (!document.body) return null;
        host = document.createElement('div');
        host.className = 'ems-toast-host';
        host.setAttribute('role', 'region');
        host.setAttribute('aria-label', 'رسائل النظام');
        document.body.appendChild(host);
        return host;
    }

    function dismiss(rec) {
        if (!rec || rec.gone) return;
        rec.gone = true;
        if (rec.timer) clearTimeout(rec.timer);
        /* الشطبُ من live **متزامنٌ** ونزعُ العقدة مؤجَّلٌ لانتهاء حركة الخروج.
           لو أُجِّل الشطبُ معها لبقي طولُ live ثابتًا داخل حلقة تقليم السقف
           في show() فلا تنتهي أبدًا ويتجمّد المتصفّح عند التوست الخامس —
           وقع فعلًا في الفحص. */
        live = live.filter(function (r) { return r !== rec; });
        var el = rec.el;
        el.classList.add('is-leaving');
        var drop = function () { if (el.parentNode) el.parentNode.removeChild(el); };
        // إن أُلغيت الحركة (تقليل الحركة) لا يصل حدثُ النهاية — مؤقّتٌ يضمن الإزالة
        el.addEventListener('animationend', drop, { once: true });
        setTimeout(drop, 260);
    }

    function show(opts) {
        var o = opts || {};
        var text = String(o.text != null ? o.text : '').trim();
        if (!text) return null;
        var type = normalizeType(o.type) || inferType(text);
        var sticky = o.sticky != null ? !!o.sticky : LIFE[type] === 0;
        var life = o.life != null ? o.life : LIFE[type];

        /* الفعلُ يُنقّح النجاحَ وحدَه — النتيجةُ تُحسم أولًا ثم يُسأل عن الفعل */
        var act = (type === 'success') ? (o.action ? { key: o.action, title: null, icon: o.action } : detectAction(text)) : null;
        var tone = act ? act.key : type;
        var iconKey = act ? act.icon : type;
        var heading = o.title != null ? o.title : (act && act.title ? act.title : TITLE[type]);

        if (!ensureHost()) { queue.push(o); return null; }

        /* رسالةٌ مطابقةٌ حيّةٌ الآن: نرفع عدّادَها ونجدّد عمرَها بدل تكديس
           بطاقاتٍ متطابقةٍ تُغرق الشاشة (نمطٌ شائعٌ في حلقات التحقّق). */
        var key = tone + '|' + text + '|' + (o.code || '');
        var dup = null;
        live.forEach(function (r) { if (r.key === key && !r.gone) dup = r; });
        if (dup) {
            dup.count++;
            dup.el.setAttribute('data-count', String(dup.count));
            var cnt = dup.el.querySelector('.ems-toast__count');
            if (cnt) cnt.textContent = '×' + dup.count;
            if (dup.timer) { clearTimeout(dup.timer); dup.timer = setTimeout(function () { dismiss(dup); }, life || 4000); }
            return dup.el;
        }

        // حدٌّ صريحٌ للدورات: حارسٌ ثانٍ كي لا تتحوّل حلقةُ التقليم إلى تجميدٍ أبدًا
        for (var guard = 0; live.length >= MAX_VISIBLE && guard < MAX_VISIBLE + 8; guard++) dismiss(live[0]);

        var el = document.createElement('div');
        el.className = 'ems-toast';
        el.setAttribute('data-type', type);
        el.setAttribute('data-tone', tone);   // النغمةُ: نوعٌ أو فعلٌ حين يكون نجاحًا
        if (act) el.setAttribute('data-action', act.key);
        el.setAttribute('data-count', '1');
        if (sticky) el.setAttribute('data-sticky', '1');
        // الخطأُ والتحذيرُ يقاطعان قارئَ الشاشة؛ النجاحُ والمعلومةُ ينتظران دورَهما
        el.setAttribute('role', (type === 'error' || type === 'warning') ? 'alert' : 'status');
        el.setAttribute('aria-live', (type === 'error' || type === 'warning') ? 'assertive' : 'polite');
        if (!sticky && life) el.style.setProperty('--ems-toast-life', life + 'ms');

        var icon = document.createElement('span');
        icon.className = 'ems-toast__icon';
        icon.setAttribute('aria-hidden', 'true');
        // ثابتٌ من الملف لا من بيانات المستخدم — لا مجالَ لحقن
        icon.innerHTML = ICON[iconKey] || ICON[type] || ICON.info;

        var body = document.createElement('div');
        body.className = 'ems-toast__body';
        var h = document.createElement('p');
        h.className = 'ems-toast__title';
        h.textContent = heading;
        var cnt2 = document.createElement('span');
        cnt2.className = 'ems-toast__count';
        cnt2.textContent = '×1';
        h.appendChild(cnt2);
        var p = document.createElement('p');
        p.className = 'ems-toast__text';
        p.textContent = text;   // نصٌّ لا HTML — لا حقنَ من رسالةٍ مصدرُها الخادم
        body.appendChild(h);
        body.appendChild(p);

        // إرشادُ التصحيح: «كيف يُصحَّح» بعد «ما حدث» (عقد UI-13)
        if (o.hint) {
            var hint = document.createElement('p');
            hint.className = 'ems-toast__hint';
            hint.textContent = o.hint;
            body.appendChild(hint);
        }
        // رمزُ الحوكمة: يذكره المستخدمُ للدعم فيُعرف الموضعُ بلا وصف
        if (o.code) {
            var code = document.createElement('code');
            code.className = 'ems-toast__code';
            code.textContent = o.code;
            body.appendChild(code);
        }

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'ems-toast__close';
        close.setAttribute('aria-label', 'إغلاق الرسالة');
        close.textContent = '✕';

        el.appendChild(icon);
        el.appendChild(body);
        el.appendChild(close);
        if (!sticky && life) {
            var lifeBar = document.createElement('span');
            lifeBar.className = 'ems-toast__life';
            lifeBar.setAttribute('aria-hidden', 'true');
            el.appendChild(lifeBar);
        }

        host.appendChild(el);
        var rec = { el: el, key: key, count: 1, timer: null, gone: false, type: type, text: text, born: Date.now() };
        live.push(rec);
        close.addEventListener('click', function () { dismiss(rec); });
        if (!sticky && life) {
            rec.timer = setTimeout(function () { dismiss(rec); }, life);
            // إيقافُ العدِّ عند المرور بالفأرة — المستخدمُ يقرأ، فلا يُسحب منه النص
            el.addEventListener('mouseenter', function () { if (rec.timer) { clearTimeout(rec.timer); rec.timer = null; } });
            el.addEventListener('mouseleave', function () { if (!rec.gone && !rec.timer) rec.timer = setTimeout(function () { dismiss(rec); }, 1200); });
        }
        return el;
    }

    function flushQueue() {
        if (!queue.length || !ensureHost()) return;
        var q = queue.slice();
        queue = [];
        q.forEach(function (o) { show(o); });
    }

    /* ── البقاءُ عبر الانتقال ──────────────────────────────────────────
       61 نداءَ alert() من 261 يعقبه انتقالٌ فوريّ (location/submit) خلال
       ثلاثة أسطر. النافذةُ الأصليةُ كانت تحجز حتى يقرأ المستخدم؛ التوستُ لا
       يحجز، فلولا هذا لضاعت الرسالةُ مع الصفحة. نخزّن ما كان ظاهرًا لحظةَ
       المغادرة ونعيد عرضَه على الصفحة التالية. */
    function stashLive() {
        try {
            var keep = live.filter(function (r) { return !r.gone; })
                .slice(-3)
                .map(function (r) { return { type: r.type, text: r.text, at: Date.now() }; });
            if (keep.length) sessionStorage.setItem(STASH_KEY, JSON.stringify(keep));
        } catch (e) { /* التخزين ممنوعٌ أحيانًا — لا نُفشل الصفحة */ }
    }

    function replayStash() {
        var raw;
        try { raw = sessionStorage.getItem(STASH_KEY); sessionStorage.removeItem(STASH_KEY); } catch (e) { return; }
        if (!raw) return;
        try {
            JSON.parse(raw).forEach(function (m) {
                if (Date.now() - (m.at || 0) > STASH_TTL) return;
                show({ type: m.type, text: m.text });
            });
        } catch (e) { /* مخزونٌ تالف — يُهمل */ }
    }

    window.addEventListener('pagehide', stashLive);

    /* ── التقاطُ alert() الأصلية ───────────────────────────────────────
       لا نلمس confirm(): هي متزامنةٌ تُعيد قيمةً منطقية، وأيُّ بديلٍ رسوميٍّ
       غيرُ متزامن، فاستبدالُها يكسر 166 موضعًا تعتمد على قيمتها المرتجعة.
       تُترك كما هي، ويُتاح EmsAlert.confirm() بديلًا اختياريًّا لمن يُعاد كتابته. */
    var nativeAlert = window.alert ? window.alert.bind(window) : null;
    window.alert = function (msg) {
        try { show({ text: String(msg) }); }
        catch (e) { if (nativeAlert) nativeAlert(msg); }
    };
    window.EmsAlert = window.EmsAlert || {};
    window.EmsAlert.native = nativeAlert;

    /* ── تبنّي الرسائل السطرية القائمة ─────────────────────────────────
       ① .success-message — لافتةُ ?msg= الموروثة في 63 شاشة: عابرةٌ بطبعها
          (نتيجةُ فعلٍ للتوّ) فتُحوَّل توستًا وتُنزع من التخطيط.
       ② .alert.alert-* — مختلطة: كثيرٌ منها شرحٌ ثابتٌ داخل البطاقات لا
          نتيجةُ فعل. لا تُحوَّل — تبقى مكانَها وتُعطى أيقونةً ونغمةً فحسب.
          تحويلُها توستًا كان سيبتلع محتوًى دائمًا لا علاقةَ له بفعلٍ للتوّ. */
    function adoptInline(root) {
        var scope = root || document;

        scope.querySelectorAll('.success-message:not([data-ems-done])').forEach(function (el) {
            el.setAttribute('data-ems-done', '1');
            var text = (el.textContent || '').trim();
            if (!text) return;
            var type = el.classList.contains('is-success') ? 'success'
                     : el.classList.contains('is-error') ? 'error'
                     : inferType(text);
            show({ type: type, text: text });
            el.setAttribute('hidden', 'hidden');
            el.style.display = 'none';
        });

        var sel = '.alert.alert-success, .alert.alert-danger, .alert.alert-warning,' +
                  '.alert.alert-info, .alert.alert-err, .alert.alert-ok';
        scope.querySelectorAll(sel).forEach(function (el) {
            // الحارسُ الصنفُ لا الأيقونة: لافتةٌ لها <i> خاصٌّ بها لا تنال
            // data-ems-glyph أصلًا، فحارسٌ عليه يعيد معالجتَها كلَّ تغيُّرِ DOM.
            if (el.classList.contains('ems-inline-alert')) return;
            var t = el.classList.contains('alert-success') || el.classList.contains('alert-ok') ? 'success'
                  : el.classList.contains('alert-danger') || el.classList.contains('alert-err') ? 'error'
                  : el.classList.contains('alert-warning') ? 'warning' : 'info';
            el.classList.add('ems-inline-alert');
            el.setAttribute('data-type', t);
            // أيقونةُ الشاشة إن وُجدت تكفي — لا نضاعفها
            if (!el.querySelector(':scope > i, :scope > svg')) el.setAttribute('data-ems-glyph', GLYPH[t]);
            else el.setAttribute('data-ems-noglyph', '1');
        });
    }

    /* ── الواجهة العامة ────────────────────────────────────────────────── */
    window.EmsAlert.show = show;
    window.EmsAlert.success = function (t, o) { return show(Object.assign({ type: 'success', text: t }, o || {})); };
    window.EmsAlert.error = function (t, o) { return show(Object.assign({ type: 'error', text: t }, o || {})); };
    window.EmsAlert.warning = function (t, o) { return show(Object.assign({ type: 'warning', text: t }, o || {})); };
    window.EmsAlert.info = function (t, o) { return show(Object.assign({ type: 'info', text: t }, o || {})); };
    window.EmsAlert.dismissAll = function () { live.slice().forEach(dismiss); };
    window.EmsAlert.adopt = adoptInline;
    window.EmsAlert.inferType = inferType;

    function boot() {
        ensureHost();
        replayStash();
        flushQueue();
        adoptInline(document);
        // شاشاتٌ تحقن لافتاتِها بعد AJAX — نتبنّاها متى ظهرت
        if (window.MutationObserver) {
            new MutationObserver(function () { adoptInline(document); })
                .observe(document.body, { childList: true, subtree: true });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && live.length) window.EmsAlert.dismissAll();
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
