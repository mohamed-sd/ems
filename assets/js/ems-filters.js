/* ═══════════════════════════════════════════════════════════════════════════
   ems-filters.js — عونُ صندوقِ الفلاترِ الموحَّد
   ───────────────────────────────────────────────────────────────────────────
   ◆ **لماذا يوجد أصلًا**: الشاشاتُ الأربعُ والثلاثون تكتب حقولَها بأحدَ عشرَ
     شكلًا. منها ما يلفُّ الحقلَ في `col-md-3` أو `form-group` أو `ems-col-3`،
     ومنها ما **لا يلفُّه أصلًا** بل يكتب `<label>` و`<input>` أخوَين مباشرَين
     في النموذج (`Contracts/client_statement.php` · `Fleet/readiness_board.php`
     · `Maintenance/failure_report.php` …). والتخطيطُ العموديُّ (عنوانٌ فوقَ
     ضابط) يستحيل بلا غلافٍ يجمعهما — فيصنعه العونُ عند التصيير.

   ◆ **وهل النقلُ آمن؟** قِيس قبلَ كتابةِ سطرٍ: مسحُ الأربعِ والثلاثين وملفَّي
     النمطِ الكبيرَين عن محدِّدِ «ابنٍ مباشرٍ» يمسُّ حقلًا ⇒ **صفر**. فلا محدِّدَ
     ينكسر بإدخالِ غلاف. ولو ظهر لاحقًا فالبديلُ مكتوبٌ: يُلَفُّ في PHP.

   ◆ **أربعةُ أعمال**:
     ① لفُّ كلِّ عنوانٍ مع ضابطِه في `.filter-field` — أو وسمُ الغلافِ القائم.
     ② جمعُ الأزرارِ في `.filter-actions` واحدةٍ في ذيلِ الصفّ.
     ③ عدّادُ الفلاترِ المفعَّلة في الترويسة — قدرةٌ كانت في `.ems-filterbar`
        وكان يجب ألّا تُفقد بالتوحيد.
     ④ وسمُ `is-alone` حين تنزل الأزرارُ سطرًا وحدَها، فتُلغى إزاحتُها
        التلقائيةُ ولا تبقى حفرةٌ قبلَها. والقياسُ **بالقاعِ لا بالرأس**:
        الصفُّ محاذًى `flex-end` فعناصرُ السطرِ تشترك في القاعِ وتختلف رؤوسُها.

   ◆ **يعمل بلا هذا العون**: النمطُ يحمل سنَدًا موضعيًّا للأشكالِ الملفوفة،
     فلو سقط السكربتُ بقيت الشاشاتُ ذاتُ الأغلفةِ صحيحة. والخاسرُ وحدَه
     شكلُ «العنوانِ الأخِ» — وهو ثمانِ شاشاتٍ من أربعٍ وثلاثين.
   ═══════════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var GRID_WRAP  = '.row, .form-grid, .ems-grid, .form-row, .filter-grid';
    var CTRL       = 'input:not([type=hidden]):not([type=submit]):not([type=button]):not([type=reset]):not([type=image]), select, textarea';
    var CTRL_TAGS  = { INPUT: 1, SELECT: 1, TEXTAREA: 1 };
    var LABEL_TAGS = { LABEL: 1, STRONG: 1, B: 1, SPAN: 1, SMALL: 1, EM: 1 };
    var SKIP_TAGS  = { SCRIPT: 1, STYLE: 1, TEMPLATE: 1, BR: 1 };

    function isControl(el) {
        if (!el || !CTRL_TAGS[el.tagName]) { return false; }
        if (el.tagName !== 'INPUT') { return true; }
        var t = (el.getAttribute('type') || 'text').toLowerCase();
        return t !== 'hidden' && t !== 'submit' && t !== 'button' && t !== 'reset' && t !== 'image';
    }
    function isAction(el) {
        if (!el || el.nodeType !== 1) { return false; }
        if (el.tagName === 'BUTTON') { return true; }
        if (el.tagName === 'A') { return true; }
        if (el.tagName === 'INPUT') {
            var t = (el.getAttribute('type') || '').toLowerCase();
            return t === 'submit' || t === 'button' || t === 'reset' || t === 'image';
        }
        return false;
    }
    function isBlank(node) {
        return node.nodeType === 3 && !/\S/.test(node.nodeValue);
    }

    /* المضيفُ الصفّيّ: النموذجُ الملفوفُ إن وُجد، وإلا الجسم، وإلا الصندوق */
    function hostOf(box) {
        var body = box.querySelector(':scope > .filter-body');
        if (!body) { return box; }
        var form = body.querySelector(':scope > form');
        return form || body;
    }

    /* ── ① بناءُ الحقولِ والأفعالِ في مضيفٍ واحد ────────────────────────── */
    function normalize(host, depth) {
        var nodes = Array.prototype.slice.call(host.childNodes);
        var group = [], hasCtrl = false, actions = [], i, n;

        function flush() {
            var k;
            if (hasCtrl && group.length) {
                var wrap = document.createElement('div');
                wrap.className = 'filter-field';
                group[0].parentNode.insertBefore(wrap, group[0]);
                for (k = 0; k < group.length; k++) { wrap.appendChild(group[k]); }
            } else if (group.length) {
                /* عنوانٌ يتيمٌ لم يتبعْه ضابط — ملاحظةٌ لا حقل. ولولا وسمُه
                   لالتقطه السنَدُ الموضعيُّ فأعطاه عرضَ حقلٍ كامل (`<small>
                   اختصارات:` في `Portal/my_achievement.php`). */
                for (k = 0; k < group.length; k++) {
                    if (group[k].nodeType === 1) { group[k].classList.add('filter-note'); }
                }
            }
            group = []; hasCtrl = false;
        }

        for (i = 0; i < nodes.length; i++) {
            n = nodes[i];

            if (isBlank(n)) { continue; }
            if (n.nodeType === 3) { group.push(n); continue; }   /* نصٌّ عنوانًا */
            if (n.nodeType !== 1) { continue; }
            if (SKIP_TAGS[n.tagName]) { continue; }

            if (n.classList.contains('filter-actions')) { flush(); continue; }
            if (n.classList.contains('filter-title'))   { flush(); continue; }

            /* غلافُ شبكةٍ يذوب في النمط ⇒ ننزل فيه ولا نعدُّه حقلًا */
            if (depth < 3 && n.matches && n.matches(GRID_WRAP)) { flush(); normalize(n, depth + 1); continue; }

            if (isAction(n)) { flush(); actions.push(n); continue; }

            if (isControl(n)) { group.push(n); hasCtrl = true; flush(); continue; }

            if (LABEL_TAGS[n.tagName] && !n.querySelector(CTRL)) { group.push(n); continue; }

            /* ◆ **غلافُ أزرارٍ**: عنصرٌ فيه أفعالٌ ولا ضابطَ فيه — وهو الشكلُ
               الغالبُ في الشاشاتِ (`<div class="pal-filter-go"><button…>`).
               ولولا هذا الفرعُ لبقيَ بلا وسمٍ فأخذَ عرضَ محتواه وحدَه وتركَ
               حفرةً. قِيس أثرُه: ثمانِ شاشاتٍ امتلأت سطورُها بين ٤٩٪ و٧٧٪. */
            if (!n.querySelector(CTRL) && n.querySelector('button, a, input[type="submit"], input[type="button"], input[type="reset"]')) {
                flush();
                n.classList.add('filter-actions');
                continue;
            }

            /* عنصرٌ حاوٍ: إن كان فيه ضابطٌ واحدٌ فهو غلافُ حقلٍ قائم */
            if (n.querySelector && n.querySelector(CTRL)) {
                flush();
                var inner = n.querySelectorAll(CTRL).length;
                if (inner > 1 && depth < 3) {
                    /* أكثرُ من ضابطٍ ⇒ غلافُ مجموعةٍ لا حقل: انزلْ درجةً */
                    var kids = 0, c;
                    for (c = 0; c < n.children.length; c++) {
                        if (n.children[c].querySelector && n.children[c].querySelector(CTRL)) { kids++; }
                    }
                    if (kids > 1) { normalize(n, depth + 1); continue; }
                }
                n.classList.add('filter-field');
                continue;
            }

            /* عنصرٌ بلا ضابطٍ ولا فعلٍ — ملاحظةٌ نصّيةٌ داخلَ الصندوق (مثالُه
               «النافذة ٣٠ يومًا» في `fleet_calendar`). يُوسَم فيمتدَّ على ما بقي
               من السطرِ بدلَ أن يقف بعرضِ نصِّه ويترك حفرةً بعدَه. */
            flush();
            n.classList.add('filter-note');
        }
        flush();

        /* ── ② جمعُ الأفعال ────────────────────────────────────────────── */
        if (actions.length) {
            var bar = host.querySelector(':scope > .filter-actions');
            if (!bar) {
                bar = document.createElement('div');
                bar.className = 'filter-actions';
            }
            for (i = 0; i < actions.length; i++) { bar.appendChild(actions[i]); }
            host.appendChild(bar);
        }
    }

    /* ── ③ عدُّ المفعَّل ────────────────────────────────────────────────── */
    function activeCount(host) {
        var f = host.querySelectorAll(CTRL), n = 0, i, el, v;
        for (i = 0; i < f.length; i++) {
            el = f[i];
            if (el.disabled || el.offsetParent === null) { continue; }
            if (el.type === 'checkbox' || el.type === 'radio') { if (el.checked) { n++; } continue; }
            v = (el.value || '').trim();
            if (el.tagName === 'SELECT') { if (el.selectedIndex > 0 && v !== '' && v !== '0') { n++; } continue; }
            if (v !== '' && v !== '0') { n++; }
        }
        return n;
    }

    function refreshCount(box, host) {
        var title = box.querySelector(':scope > .filter-title');
        if (!title) { return; }
        var badge = title.querySelector(':scope > .filter-count');
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'filter-count';
            title.appendChild(badge);
        }
        var n = activeCount(host);
        badge.textContent = n === 0 ? 'بلا فلاتر'
                          : (n === 1 ? 'فلترٌ مفعَّل' : n + ' فلاتر مفعَّلة');
        box.classList.toggle('has-active', n > 0);
        box.setAttribute('data-ems-filter-count', String(n));
    }

    /* ── ④ سطرُ الأزرارِ المنفرد ───────────────────────────────────────── */
    function refreshAlone(box, host) {
        var acts = host.querySelector('.filter-actions');
        if (!acts) { return; }
        acts.classList.remove('is-alone');
        var fields = host.querySelectorAll('.filter-field');
        if (!fields.length) { return; }
        var aBot = acts.getBoundingClientRect().bottom, last = -Infinity, i, b;
        for (i = 0; i < fields.length; i++) {
            b = fields[i].getBoundingClientRect().bottom;
            if (b > last) { last = b; }
        }
        if (aBot - last > 2) { acts.classList.add('is-alone'); }
    }

    function boot(box) {
        if (!box || box.getAttribute('data-ems-filter-ready') === '1') { return; }
        box.setAttribute('data-ems-filter-ready', '1');
        var host = hostOf(box);
        try { normalize(host, 0); }    catch (e) { }
        try { refreshCount(box, host); } catch (e) { }
        try { refreshAlone(box, host); } catch (e) { }

        host.addEventListener('input',  function () { try { refreshCount(box, host); } catch (e) { } });
        host.addEventListener('change', function () { try { refreshCount(box, host); } catch (e) { } });
        box.__emsFilterRelayout = function () { try { refreshAlone(box, host); } catch (e) { } };
    }

    /* حارسُ إعادةِ الدخول: `normalize` يُدخل عقدًا فيوقظ المراقبَ الذي يستدعي
       `bootAll` — والصندوقُ المُهيَّأُ يُتخطّى، لكنَّ الحارسَ يوفّر المسحَ أصلًا. */
    var busy = false;
    function bootAll() {
        if (busy) { return; }
        busy = true;
        try {
            var boxes = document.querySelectorAll('.main .filter'), i;
            for (i = 0; i < boxes.length; i++) { boot(boxes[i]); }
        } finally { busy = false; }
    }

    var rt = null;
    function onResize() {
        if (rt) { clearTimeout(rt); }
        rt = setTimeout(function () {
            var boxes = document.querySelectorAll('.main .filter'), i;
            for (i = 0; i < boxes.length; i++) {
                if (typeof boxes[i].__emsFilterRelayout === 'function') { boxes[i].__emsFilterRelayout(); }
            }
        }, 120);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootAll);
    } else {
        bootAll();
    }
    window.addEventListener('resize', onResize);
    /* شاشةٌ تبني صندوقَها بعدَ نداءٍ شبكيّ — يلتقطها مراقبٌ خفيف */
    if (window.MutationObserver) {
        var mo = new MutationObserver(function (recs) {
            for (var i = 0; i < recs.length; i++) {
                if (recs[i].addedNodes && recs[i].addedNodes.length) { bootAll(); return; }
            }
        });
        var start = function () { mo.observe(document.body, { childList: true, subtree: true }); };
        if (document.body) { start(); } else { document.addEventListener('DOMContentLoaded', start); }
    }
    window.EmsFilters = { boot: bootAll };
})();
