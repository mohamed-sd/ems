/**
 * includes/js/ems-autosave.js — المسودةُ التلقائية (M-46)
 * ---------------------------------------------------------------------------
 * UI-01 §3: «المسودةُ التلقائية كل 30 ثانية في النماذج الطويلة» — كان
 * القياسُ: «لا autosave في أي نموذج».
 *
 * يلتقط آليًّا كلَّ نموذجٍ «طويلٍ» (≥ 6 حقولَ إدخالٍ) أو موسومٍ صراحةً
 * بـdata-autosave، ويحفظ قيمَه في localStorage كل 30 ثانية بمفتاح
 * (المسار × معرّف النموذج)، ويعرض شريطَ استرجاعٍ عند العودة — ويمسح
 * المسودةَ عند الإرسال الناجح. حقولُ كلمات المرور وCSRF لا تُحفظ أبدًا.
 */
(function () {
    'use strict';
    var INTERVAL_MS = 30000;

    function keyOf(form, idx) {
        return 'ems_draft:' + location.pathname + ':' + (form.id || form.getAttribute('name') || 'f' + idx);
    }
    function fields(form) {
        return Array.prototype.filter.call(
            form.querySelectorAll('input, textarea, select'),
            function (el) {
                if (!el.name) return false;
                var t = (el.type || '').toLowerCase();
                if (t === 'password' || t === 'hidden' || t === 'file' || t === 'submit') return false;
                if (/csrf|token/i.test(el.name)) return false;
                return true;
            });
    }
    function capture(form) {
        var data = {};
        fields(form).forEach(function (el) {
            if (el.type === 'checkbox' || el.type === 'radio') {
                if (el.checked) data[el.name] = el.value;
            } else if (el.value !== '') {
                data[el.name] = el.value;
            }
        });
        return data;
    }
    function restore(form, data) {
        fields(form).forEach(function (el) {
            if (!(el.name in data)) return;
            if (el.type === 'checkbox' || el.type === 'radio') {
                el.checked = (el.value === data[el.name]);
            } else if (el.value === '') {
                el.value = data[el.name];
            }
        });
    }
    function banner(form, key, data, savedAt) {
        var bar = document.createElement('div');
        bar.className = 'alert alert-info';
        bar.style.cssText = 'display:flex;justify-content:space-between;align-items:center;gap:8px';
        bar.innerHTML = '<span>📝 لديك مسودةٌ محفوظةٌ محليًّا (' + savedAt + ') — لم يضِع ما كتبت.</span>';
        var use = document.createElement('button');
        use.type = 'button'; use.className = 'btn-primary'; use.textContent = 'استرجعها';
        use.onclick = function () { restore(form, data); bar.remove(); };
        var drop = document.createElement('button');
        /* ف١٢-٢ · بوابة G17: «رئيسيٌّ — **واحدٌ لا غير**». والاسترجاعُ هو الفعلُ
           الأرجحُ لمن حُفظت مسودتُه، والتجاهلُ فعلٌ مساندٌ ⇒ ثانويّ. وكانا
           رئيسيَّين معًا فصارا زرَّين متساويَي الوزنِ في لافتةٍ واحدة — وهو
           عينُ ما يُلغي معنى الرئيسيّ. (وأثرُه مركزيٌّ: اللافتةُ تظهر في كلِّ
           شاشةٍ فيها حفظٌ تلقائيّ.) */
        drop.type = 'button'; drop.className = 'btn-secondary'; drop.textContent = 'تجاهلها';
        drop.onclick = function () { localStorage.removeItem(key); bar.remove(); };
        bar.appendChild(use); bar.appendChild(drop);
        form.parentNode.insertBefore(bar, form);
    }
    function attach(form, idx) {
        var key = keyOf(form, idx);
        try {
            var saved = localStorage.getItem(key);
            if (saved) {
                var parsed = JSON.parse(saved);
                if (parsed && parsed.data && Object.keys(parsed.data).length) {
                    banner(form, key, parsed.data, parsed.at || '');
                }
            }
        } catch (e) { /* مسودةٌ تالفةٌ تُتجاهل */ }
        var timer = setInterval(function () {
            var data = capture(form);
            if (Object.keys(data).length) {
                try {
                    localStorage.setItem(key, JSON.stringify(
                        { data: data, at: new Date().toLocaleTimeString() }));
                } catch (e) { /* مساحةٌ ممتلئة — الحفظُ المحليُّ ترفٌ لا يعطّل */ }
            }
        }, INTERVAL_MS);
        form.addEventListener('submit', function () {
            clearInterval(timer);
            try { localStorage.removeItem(key); } catch (e) {}
        });
    }
    function boot() {
        var forms = document.querySelectorAll('form');
        Array.prototype.forEach.call(forms, function (form, idx) {
            var explicit = form.hasAttribute('data-autosave');
            var noAuto = form.hasAttribute('data-no-autosave');
            if (noAuto) return;
            if (explicit || fields(form).length >= 6) { attach(form, idx); }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else { boot(); }
})();
