/**
 * assets/js/ems-outbox.js — صندوقُ الإرسالِ دونَ اتصال (INJ-0378 · INJ-0548)
 * ═══════════════════════════════════════════════════════════════════════════
 * **العيب**: شاشاتُ الميدانِ تعرض شارةَ مزامنةٍ تقرأ `navigator.onLine` وحسب،
 * ولا وجودَ لتخزينٍ محليٍّ ولا لعاملِ خدمةٍ في المستودعِ كلِّه — فالشارةُ
 * **تجميلية**، و«الحفظُ قبل الشبكة» وعدٌ بلا آلة. وتأكيدُ الوصولِ من موقعٍ بلا
 * شبكةٍ **يضيع**.
 *
 * **المبنيّ** — ثلاثةٌ لا يُغني بعضُها عن بعض:
 *   ① **طابور** في `IndexedDB`: النموذجُ الموسومُ `data-ems-outbox="1"` يُحفظ
 *      محليًّا حين لا شبكةَ (أو حين يسقط الطلبُ سقوطَ شبكةٍ لا سقوطَ خادم).
 *   ② **مفتاحُ عطالةٍ من العميل** (`ems_idem`): يُولَّد مرةً واحدةً ويُعاد إرسالُه
 *      كما هو — فالخادمُ يعرف أنَّ الإعادةَ **هي هي** ولا يكتب مرتين.
 *   ③ **تصريفٌ عند عودةِ الاتصال**: يُرسَل ما في الطابورِ واحدًا واحدًا، ويُحذف
 *      عند النجاحِ أو عند رفضٍ نهائيٍّ (4xx غيرِ 409). و**409 تعارُضٌ يُعرض** ولا
 *      يُحذف: الصفُّ عُدِّل من جهازٍ آخر، والقرارُ للمستخدمِ لا للآلة.
 *
 * ◆ ولا اعتراضَ عامًّا: يعمل على النماذجِ الموسومةِ صراحةً وحدَها. اعتراضُ كلِّ
 *   نموذجٍ في النظامِ يحوّل عطلًا في الشبكةِ إلى كتابةٍ مؤجَّلةٍ لم يطلبها أحد.
 * ═══════════════════════════════════════════════════════════════════════════
 */
(function () {
    'use strict';

    var DB_NAME = 'ems_outbox';
    var STORE = 'queue';
    var VERSION = 1;

    function openDb() {
        return new Promise(function (resolve, reject) {
            if (!window.indexedDB) { reject(new Error('no-indexeddb')); return; }
            var rq = window.indexedDB.open(DB_NAME, VERSION);
            rq.onupgradeneeded = function (e) {
                var db = e.target.result;
                if (!db.objectStoreNames.contains(STORE)) {
                    db.createObjectStore(STORE, { keyPath: 'idem' });
                }
            };
            rq.onsuccess = function (e) { resolve(e.target.result); };
            rq.onerror = function () { reject(rq.error); };
        });
    }

    function tx(mode, fn) {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var t = db.transaction(STORE, mode);
                var st = t.objectStore(STORE);
                var out = fn(st);
                t.oncomplete = function () { resolve(out && out.result !== undefined ? out.result : out); };
                t.onerror = function () { reject(t.error); };
            });
        });
    }

    /** مفتاحُ عطالةٍ يُولَّد مرةً واحدةً ويبقى مع الطلبِ في كلِّ إعادة */
    function newIdem() {
        if (window.crypto && window.crypto.randomUUID) { return window.crypto.randomUUID(); }
        var s = '';
        for (var i = 0; i < 32; i++) { s += Math.floor(Math.random() * 16).toString(16); }
        return s;
    }

    var EmsOutbox = {};

    EmsOutbox.enqueue = function (rec) {
        return tx('readwrite', function (st) { st.put(rec); return rec.idem; });
    };
    EmsOutbox.all = function () {
        return tx('readonly', function (st) { return st.getAll(); });
    };
    EmsOutbox.remove = function (idem) {
        return tx('readwrite', function (st) { st.delete(idem); return idem; });
    };
    EmsOutbox.count = function () {
        return EmsOutbox.all().then(function (r) { return (r || []).length; });
    };

    /* ── لافتةُ الحالة: «بانتظار المزامنة» بعددِها ─────────────────────────── */
    function chip() {
        var el = document.getElementById('emsOutboxChip');
        if (el) { return el; }
        el = document.createElement('div');
        el.id = 'emsOutboxChip';
        el.className = 'ems-outbox-chip';
        el.setAttribute('role', 'status');
        document.body.appendChild(el);
        return el;
    }
    function paint(n, conflict) {
        var el = chip();
        if (conflict) {
            el.textContent = 'تعارُضٌ في ' + conflict + ' — عُدِّل الصفُّ من جهازٍ آخر';
            el.setAttribute('data-state', 'conflict');
            el.style.display = 'block';
            return;
        }
        if (!n) { el.style.display = 'none'; el.removeAttribute('data-state'); return; }
        el.textContent = n === 1 ? 'إدخالٌ واحدٌ بانتظار المزامنة' : (n + ' إدخالاتٍ بانتظار المزامنة');
        el.setAttribute('data-state', 'pending');
        el.style.display = 'block';
    }
    EmsOutbox.refreshChip = function () {
        return EmsOutbox.count().then(function (n) { paint(n, null); return n; }).catch(function () { return 0; });
    };

    /* ── الإرسال: مباشرةً إن أمكن، وإلا إلى الطابور ────────────────────────── */
    EmsOutbox.send = function (rec) {
        return fetch(rec.url, {
            method: 'POST',
            body: new URLSearchParams(rec.data),
            credentials: 'same-origin',
            headers: { 'X-Ems-Outbox': '1' }
        }).then(function (r) {
            if (r.status === 409) { return { ok: false, conflict: true, status: 409 }; }
            if (r.status >= 500) { return { ok: false, retry: true, status: r.status }; }
            return { ok: r.status < 400, status: r.status, drop: r.status >= 400 };
        }).catch(function () {
            return { ok: false, retry: true, network: true };
        });
    };

    /* تصريفُ الطابورِ واحدًا واحدًا — والفشلُ الشبكيُّ يوقف الجولةَ ولا يفرغها */
    var draining = false;
    EmsOutbox.drain = function () {
        if (draining || !navigator.onLine) { return Promise.resolve(0); }
        draining = true;
        var sent = 0;
        return EmsOutbox.all().then(function (rows) {
            var chain = Promise.resolve();
            (rows || []).forEach(function (rec) {
                chain = chain.then(function () {
                    return EmsOutbox.send(rec).then(function (res) {
                        if (res.ok || res.drop) { sent++; return EmsOutbox.remove(rec.idem); }
                        if (res.conflict) { paint(0, rec.label || 'إدخال'); return null; }
                        return Promise.reject(new Error('retry'));
                    });
                });
            });
            return chain.then(function () { return sent; }, function () { return sent; });
        }).then(function (n) {
            draining = false;
            EmsOutbox.refreshChip();
            return n;
        }, function () { draining = false; return sent; });
    };

    /* ── ربطُ النماذجِ الموسومةِ صراحةً ─────────────────────────────────────── */
    function bind(form) {
        if (form.getAttribute('data-ems-outbox-bound') === '1') { return; }
        form.setAttribute('data-ems-outbox-bound', '1');
        form.addEventListener('submit', function (ev) {
            /* المفتاحُ يُولَّد **مرةً واحدةً** لهذا النموذجِ ويبقى مع إعاداتِه */
            var f = form.querySelector('[name="ems_idem"]');
            if (!f) {
                f = document.createElement('input');
                f.type = 'hidden';
                f.name = 'ems_idem';
                form.appendChild(f);
            }
            if (!f.value) { f.value = newIdem(); }

            if (navigator.onLine) { return; }        /* شبكةٌ حاضرةٌ ⇒ المسارُ العاديّ */
            ev.preventDefault();
            var data = {};
            var fd = new FormData(form);
            fd.forEach(function (v, k) { if (typeof v === 'string') { data[k] = v; } });
            EmsOutbox.enqueue({
                idem: f.value,
                url: form.getAttribute('action') || location.pathname,
                data: data,
                label: form.getAttribute('data-ems-outbox-label') || 'إدخال',
                at: Date.now ? Date.now() : new Date().getTime()
            }).then(function () {
                f.value = '';                        /* الإدخالُ التالي مفتاحُه الخاص */
                EmsOutbox.refreshChip();
                if (window.EmsAlert && EmsAlert.info) {
                    EmsAlert.info('حُفظ محليًّا — بانتظار المزامنة عند عودة الشبكة');
                }
                form.reset();
            });
        });
    }

    function boot() {
        var forms = document.querySelectorAll('form[data-ems-outbox="1"]');
        for (var i = 0; i < forms.length; i++) { bind(forms[i]); }
        EmsOutbox.refreshChip();
        if (navigator.onLine) { EmsOutbox.drain(); }
    }

    window.addEventListener('online', function () {
        if (window.EmsScreenShell) { try { EmsScreenShell.set('connection', 'syncing'); } catch (e) { /* لا يضرّ */ } }
        EmsOutbox.drain().then(function () {
            if (window.EmsScreenShell) { try { EmsScreenShell.set('connection', 'online'); } catch (e) { /* لا يضرّ */ } }
        });
    });
    window.addEventListener('offline', function () { EmsOutbox.refreshChip(); });

    window.EmsOutbox = EmsOutbox;
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
