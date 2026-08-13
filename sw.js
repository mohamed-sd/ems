/**
 * sw.js — عاملُ الخدمةِ: قشرةُ التطبيقِ تعمل بلا شبكة (INJ-0378 · INJ-0548)
 * ═══════════════════════════════════════════════════════════════════════════
 * السجلُّ يقول: «ولا يوجد service worker في المستودعِ كلِّه» — فشارةُ المزامنةِ
 * تجميليةٌ لأنَّ الصفحةَ نفسَها لا تُفتح بلا شبكة، ولا معنًى لطابورِ إرسالٍ في
 * شاشةٍ لا تُحمَّل.
 *
 * ── وحدودُه مقصودةٌ ومُعلَنة ────────────────────────────────────────────────
 * ◆ يُخزَّن **الأصولُ الساكنةُ وحدَها** (CSS · JS · خطوط). صفحاتُ PHP لا تُخزَّن:
 *   صفحةٌ محفوظةٌ تحمل بياناتٍ قديمةً تُقرأ حاضرةً — وهذا أخطرُ من غيابِها.
 * ◆ ولا يُخزَّن أيُّ طلبِ POST ولا استجابةٌ غيرُ ناجحة.
 * ◆ والتخزينُ **من الشبكةِ أولًا مع ارتدادٍ إلى المخزون** للأصول: فتحديثُ ملفٍّ
 *   يصل فورًا ولا ينتظر انتهاءَ عمرِ مخزون.
 * ═══════════════════════════════════════════════════════════════════════════
 */
'use strict';

var CACHE = 'ems-shell-v1';

self.addEventListener('install', function (e) {
    self.skipWaiting();
});

self.addEventListener('activate', function (e) {
    e.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.map(function (k) {
                return k === CACHE ? null : caches.delete(k);
            }));
        }).then(function () { return self.clients.claim(); })
    );
});

function isStaticAsset(url) {
    return /\/ems\/assets\/.+\.(css|js|woff2?|ttf|png|jpg|jpeg|svg|gif|ico)(\?|$)/i.test(url);
}

self.addEventListener('fetch', function (e) {
    var req = e.request;
    if (req.method !== 'GET') { return; }                 /* لا POST يُخزَّن ولا يُعترَض */
    if (!isStaticAsset(req.url)) { return; }              /* صفحاتُ PHP لا تُخزَّن */

    e.respondWith(
        fetch(req).then(function (res) {
            if (res && res.status === 200 && res.type === 'basic') {
                var copy = res.clone();
                caches.open(CACHE).then(function (c) { c.put(req, copy); });
            }
            return res;
        }).catch(function () {
            return caches.match(req).then(function (hit) {
                return hit || Response.error();
            });
        })
    );
});
