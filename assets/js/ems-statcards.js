/* ═══════════════════════════════════════════════════════════════════════════
   ems-statcards.js — عونُ توحيدِ بطاقةِ الإحصاء
   ───────────────────────────────────────────────────────────────────────────
   ◆ **المشكلةُ المقيسة**: ٨٤ صفحةً فيها بطاقاتُ إحصاءٍ تكتبها بـ**٤٣ اسمًا
     مختلفًا**، و**١٨ منها اسمٌ يتيمٌ يظهر مرةً واحدةً في النظامِ كلِّه**. فلا
     خُطّافَ واحدًا يمسكها جميعًا، وإعادةُ تسميتِها في ٨٤ ملفًّا تعديلٌ واسعٌ
     عالي الخطر لا يشتري إلا ما يشتريه وسمٌ عند التصيير.

   ◆ **الحل**: يُوسَم كلُّ ما هو بطاقةُ إحصاءٍ بخُطّافٍ واحدٍ `ems-statcard`،
     وتُوسَم أجزاؤها **بالدورِ لا بالاسم**:
        الأيقونةُ = عنصرٌ محتواه رمزٌ (`<i>`/`<svg>`) بلا نصّ
        القيمةُ   = عنصرٌ نصُّه رقمٌ (أو `—`/`%`) أو صنفُه يذكر قيمةً
        العنوانُ  = العنصرُ النصِّيُّ الباقي
        وما زاد   = `ems-statcard__meta` — **لا يُحذف** (قاعدةُ صفرِ الفقد)
     ثم يُرتَّب الثلاثةُ ترتيبَ الأصل: أيقونةٌ ⇐ قيمةٌ ⇐ عنوانٌ ⇐ تابع.

   ◆ **الترتيبُ نقلٌ لا استنساخ** (`appendChild`) فتبقى المعرِّفاتُ والمستمعاتُ
     والعقدُ نفسُها — وشفرةُ الصفحةِ التي تحدّث رقمًا بمعرِّفِه تعمل كما كانت.

   ◆ **عاطلٌ عند التكرار** بوسمِ `data-ems-statcard`، ويلتقط ما يُبنى بعدَ
     نداءٍ شبكيٍّ بمراقبٍ خفيف.
   ═══════════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    /* عائلةُ اسمِ البطاقة · وأسماءُ الأجزاءِ التي ليست بطاقةً · والمستثنى */
    var FAM  = /(^|-)(stat|stats|kpi|metric|metrics|tile)(-|$)|(^|-)summary-(card|item|box)(-|$)/i;
    /* ◆ **`state` و`status` اسما جزءٍ لا اسمَ بطاقة** (قرارُ المالك 2026-08-17
         · `Maintenance/orders.php`). المقيسُ: بطاقةُ `ems_kpi_card` تحوي
         `<span class="ems-kpi-state">` — و`state` لم تكن في هذه القائمة، فطابق
         الاسمَ **عائلةُ البطاقات** عبر `-kpi-`، فصار الجزءُ مرشَّحَ بطاقة،
         و«مَن ضمَّ مرشَّحًا فهو حاوية» فوُسمت البطاقةُ `ems-statgrid` بدل
         `ems-statcard`. فخرجت بطاقاتُ الصيانةِ بيضاءَ بلا حدٍّ وباستدارةِ 12
         بينما الموحَّدةُ رماديةٌ بحدٍّ واستدارةِ 35 — «تختلف عن الموحَّدة».
         والعلاجُ في القائمةِ لا في الشاشة: كلُّ شاشةٍ تستعمل `ems_kpi_card`
         تُصحَّح معها. */
    var PART = /-(icon|ico|img|title|value|val|label|lbl|num|number|text|desc|caption|cap|foot|head|header|meta|badge|body|sub|note|link|arrow|trend|delta|unit|row|col|wrap|inner|content|line|main|info|left|right|top|bottom|bar|dot|chip|pill|tag|hint|grid|section|container|list|state|status)$/i;
    var SKIP = /^(counter-field|counter-input-group|counter-separator|counter-seg|required-indicator|rtl-number)$/i;
    var VALC = /(^|[\s-])(value|val|num|number|count|figure|amount)($|[^a-z])/i;
    var LBLC = /(^|[\s-])(title|label|lbl|cap|caption|name|desc)($|[^a-z])/i;
    /* نصٌّ يُقرأ رقمًا: أرقامٌ عربيةٌ ولاتينيةٌ وفواصلُ ونِسَبٌ وشرطةُ «لا قيمة» */
    var NUMTXT = /^[\s\d٠-٩.,٫٬%+\-−–—/:]+$/;
    /* اسمٌ يمنع الورقةَ من أن تُعدَّ بطاقةً — فهو اسمُ جزءٍ لا اسمُ بطاقة */
    var LEAFBAN = /(^|[\s-])(value|val|num|number|count|title|label|lbl|caption|cap|text|desc)($|[^a-z])/i;
    /* «عنوانٌ: قيمة» في سطرٍ واحد — يُفصَل ليصيرَ للبطاقةِ قيمةٌ وعنوان */
    var ONELINE = /^(.{1,80}?)\s*[:：]\s*([\d٠-٩][\d٠-٩\s.,٫٬]*\s*%?)$/;

    function famToken(el) {
        if (!el || !el.classList) { return null; }
        /* ◆ **الورقةُ قد تكون بطاقةً وقد تكون جزءًا**: عنصرٌ بلا أبناءَ عناصرَ
           يكون بطاقةً إن كان اسمُه اسمَ بطاقةٍ (`metric-box` في
           `Reports/contractall.php` يكتب «التقدم الزمني: ١٢٫٥ ٪» سطرًا واحدًا)،
           ويكون **جزءًا** إن كان اسمُه اسمَ قيمةٍ أو عنوان. ولولا هذا الفرزُ
           لأُلبِس رقمٌ داخلَ بطاقةٍ ثوبَ الصندوقِ فظهر صندوقٌ داخلَ صندوق. */
        if (el.children.length === 0 && LEAFBAN.test(el.className || '')) { return null; }
        for (var i = 0; i < el.classList.length; i++) {
            var t = el.classList[i];
            if (SKIP.test(t) || PART.test(t)) { continue; }
            if (FAM.test(t)) { return t; }
        }
        return null;
    }
    function txt(el) { return (el.textContent || '').replace(/\s+/g, ' ').trim(); }
    function isIconOnly(el) {
        if (!el || el.nodeType !== 1) { return false; }
        if (!el.querySelector('i, svg, img')) { return false; }
        return txt(el) === '';
    }

    /* هل هذا العنصرُ بطاقةٌ أم شبكةٌ تحوي بطاقات؟ */
    function isGrid(el) {
        var n = 0, k;
        for (k = 0; k < el.children.length; k++) { if (famToken(el.children[k])) { n++; } }
        return n >= 2;
    }

    /* أجزاءُ البطاقةِ: أوراقُ الشجرةِ النصّيةُ والرمزية */
    function collectParts(card) {
        var parts = [];
        (function walk(el, d) {
            for (var i = 0; i < el.children.length; i++) {
                var c = el.children[i];
                if (c.tagName === 'SCRIPT' || c.tagName === 'STYLE') { continue; }
                if (isIconOnly(c)) { parts.push(c); continue; }
                /* عنصرٌ حاوٍ: ينزل فيه إن كان له أبناءٌ عناصر، وإلا فهو ورقة */
                var hasElemKids = false, j;
                for (j = 0; j < c.children.length; j++) {
                    if (c.children[j].tagName !== 'I' && c.children[j].tagName !== 'SVG'
                        && c.children[j].tagName !== 'IMG' && c.children[j].tagName !== 'BR') { hasElemKids = true; break; }
                }
                if (hasElemKids && d < 4) { walk(c, d + 1); continue; }
                if (txt(c) !== '' || c.querySelector('i, svg, img')) { parts.push(c); }
            }
        })(card, 0);
        return parts;
    }

    function normalize(card) {
        if (card.getAttribute('data-ems-statcard') === '1') { return; }
        card.setAttribute('data-ems-statcard', '1');
        card.classList.add('ems-statcard');

        /* ◆ بطاقةُ السطرِ الواحد: «التقدم الزمني: ١٢٫٥ ٪» نصٌّ بلا أجزاء. تُفصَل
           إلى قيمةٍ وعنوانٍ فتنال شكلَ الأصلِ بدلَ أن تبقى شريطَ نصٍّ باهت.
           والفصلُ **إضافةٌ لا حذف**: النصُّ نفسُه يبقى بحرفِه موزَّعًا. */
        if (card.children.length === 0) {
            var raw = txt(card), mm = raw.match(ONELINE);
            if (mm) {
                card.textContent = '';
                var vEl = document.createElement('div');
                vEl.className = 'ems-statcard__value';
                vEl.textContent = mm[2].trim();
                var tEl = document.createElement('div');
                tEl.className = 'ems-statcard__title';
                tEl.textContent = mm[1].trim();
                card.appendChild(vEl);
                card.appendChild(tEl);
                return;
            }
        }

        var parts = collectParts(card);
        if (!parts.length) { return; }

        var icon = null, value = null, title = null, meta = [], i, p, t;

        /* ① الأيقونة */
        for (i = 0; i < parts.length; i++) { if (isIconOnly(parts[i])) { icon = parts[i]; break; } }

        /* ② القيمة — بالصنفِ أولًا، ثم بالنصِّ الرقميّ، ثم بأكبرِ خطّ */
        for (i = 0; i < parts.length; i++) {
            p = parts[i]; if (p === icon) { continue; }
            if (VALC.test(p.className || '')) { value = p; break; }
        }
        if (!value) {
            for (i = 0; i < parts.length; i++) {
                p = parts[i]; if (p === icon) { continue; }
                t = txt(p);
                if (t !== '' && NUMTXT.test(t)) { value = p; break; }
            }
        }
        if (!value) {
            var big = -1;
            for (i = 0; i < parts.length; i++) {
                p = parts[i]; if (p === icon) { continue; }
                if (LBLC.test(p.className || '')) { continue; }
                var fs = parseFloat(getComputedStyle(p).fontSize) || 0;
                if (fs > big) { big = fs; value = p; }
            }
        }

        /* ③ العنوان — بالصنفِ أولًا، ثم أولُ نصٍّ غيرِ رقميّ */
        for (i = 0; i < parts.length; i++) {
            p = parts[i]; if (p === icon || p === value) { continue; }
            if (LBLC.test(p.className || '')) { title = p; break; }
        }
        if (!title) {
            for (i = 0; i < parts.length; i++) {
                p = parts[i]; if (p === icon || p === value) { continue; }
                t = txt(p);
                if (t !== '' && !NUMTXT.test(t)) { title = p; break; }
            }
        }

        /* ④ ما بقي تابعٌ — ولا يُحذف */
        for (i = 0; i < parts.length; i++) {
            p = parts[i];
            if (p !== icon && p !== value && p !== title) { meta.push(p); }
        }

        if (icon)  { icon.classList.add('ems-statcard__icon'); }
        if (value) { value.classList.add('ems-statcard__value'); }
        if (title) { title.classList.add('ems-statcard__title'); }
        for (i = 0; i < meta.length; i++) { meta[i].classList.add('ems-statcard__meta'); }

        /* ⑤ الترتيبُ ترتيبَ الأصل — نقلًا لا استنساخًا */
        try {
            if (icon)  { card.appendChild(icon); }
            if (value) { card.appendChild(value); }
            if (title) { card.appendChild(title); }
            for (i = 0; i < meta.length; i++) { card.appendChild(meta[i]); }
        } catch (e) { /* الترتيبُ تحسينٌ لا شرط */ }
    }

    var busy = false;
    function bootAll() {
        if (busy) { return; }
        busy = true;
        try {
            var all = document.querySelectorAll('.main [class]'), i, j, el;
            /* ① المرشَّحون: اسمٌ من العائلةِ **ونصٌّ غيرُ فارغ**. والفارغُ يُسقَط
               لأنه غلافٌ لا بطاقة (`.kpi` الفارغةُ في `Reports/deriver.php`). */
            var cands = [];
            for (i = 0; i < all.length; i++) {
                el = all[i];
                if (!famToken(el)) { continue; }
                if (txt(el) === '') { continue; }
                cands.push(el);
            }
            /* ② **مَن ضمَّ مرشَّحًا فهو حاويةٌ لا بطاقة** — وإلا ظهر صندوقٌ داخلَ
               صندوق. قِيس: `.shot-stat-panel` في `main/dashboard.php` تضمُّ
               `.shot-stat-card`، و`isGrid` وحدَه لا يكشفها لأنها تضمُّ واحدةً. */
            var cards = [];
            for (i = 0; i < cands.length; i++) {
                var isBox = isGrid(cands[i]);
                if (!isBox) {
                    for (j = 0; j < cands.length; j++) {
                        if (j !== i && cands[i].contains(cands[j])) { isBox = true; break; }
                    }
                }
                if (isBox) { cands[i].classList.add('ems-statgrid'); continue; }
                cards.push(cands[i]);
            }
            for (i = 0; i < cards.length; i++) {
                try { normalize(cards[i]); } catch (e) { }
            }
            /* ③ **حاويةُ البطاقاتِ تصير الشبكةَ الموحَّدة**. ولا يكفي أن تُلتقط
               باسمِها: اسمُ الحاويةِ ينتهي غالبًا بـ`-grid` وهو من لواحقِ
               «الجزء» فيُنفى من عائلةِ البطاقات (`shot-stat-grid` في
               `main/dashboard.php`). فتُلتقط **بموضعِها**: أبٌ فيه بطاقتان
               فأكثر. ولولا ذلك بقيت أعمدةُ الشاشةِ القديمةُ فخرجت البطاقةُ
               بعرضِ ٤٣ بكسلًا يفيض محتواها. */
            /* ◆ **وحاملُ البطاقةِ الواحدةِ ليس شبكةً — الشبكةُ جدُّه**
                 (قرارُ المالك 2026-08-17 · `Approvals/hours_approval.php`)
               ◆ **المقيسُ**: الشاشةُ تكتب `row > col-sm-6 col-lg-3 > stat-card`
                 أربعَ مرّات. وكلُّ عمودٍ يضمُّ بطاقةً **واحدة**، فكان الشرطُ
                 `>= 1` يُعلن **العمودَ** شبكةً. وقاعدةُ الشبكةِ تفرض
                 `width:auto !important` وأربعةَ أعمدةٍ داخلَه — فينتفخ العمودُ
                 من ٢٢٢ بكسلًا إلى ٧٣٢، ويلفُّ الصفُّ فيصير كلُّ بطاقةٍ في سطر.
                 قِيس قبلَ التحميلِ وبعدَه في المتصفّحِ نفسِه: أربعُ بطاقاتٍ على
                 محورٍ واحدٍ (y=50) قبلَ الجافاسكربت، وأربعةُ صفوفٍ بعدَه.
               ◆ **والتعليقُ أعلاه كان يقول الصوابَ والشرطُ يخالفه**: «أبٌ فيه
                 بطاقتان فأكثر». فصار الشرطُ يقولُ ما يقصده:
                   · أبٌ فيه بطاقتان فأكثر ⇐ هو الشبكة.
                   · أبٌ فيه بطاقةٌ واحدةٌ وله إخوةٌ يحملون بطاقاتٍ مثلَه ⇐
                     الشبكةُ **جدُّه** (صفُّ الأعمدة)، والعمودُ يُترك كما هو.
                   · أبٌ فيه بطاقةٌ واحدةٌ بلا إخوةٍ كذلك ⇐ يبقى الحكمُ القديم،
                     فلا تنكشف الأعمدةُ القديمةُ الضيّقةُ التي عولجت أصلًا. */
            var seen = [];
            /* غلافٌ رفيع: يحمل بطاقةً واحدةً ولا عنصرَ آخرَ معها — كعمودِ بوتستراب. */
            var thinWrap = function (el) {
                if (!el || el.nodeType !== 1) { return false; }
                if (el.querySelectorAll('.ems-statcard').length !== 1) { return false; }
                return el.children.length === 1 && el.children[0].classList.contains('ems-statcard');
            };
            var cardCount = function (el) {
                return (el && el.nodeType === 1) ? el.querySelectorAll('.ems-statcard').length : 0;
            };
            for (i = 0; i < cards.length; i++) {
                var par = cards[i].parentElement;
                if (!par || par === document.body || seen.indexOf(par) !== -1) { continue; }
                seen.push(par);

                var own = par.querySelectorAll(':scope > .ems-statcard').length;
                if (own >= 2) { par.classList.add('ems-statgrid'); continue; }
                if (own < 1) { continue; }

                /* ◆ **صفُّ الأغلفةِ الرفيعة**: `row > col > card` أربعَ مرّات.
                     الشبكةُ الحقيقيةُ هي **الصفُّ** لا العمود. ولا يُتسلَّق إليه
                     إلا بشرطٍ ضيّقٍ يمنع الصعودَ إلى حاويةِ الصفحة:
                       · الأبُ نفسُه غلافٌ رفيع، و
                       · للجَدِّ **غلافان رفيعان فأكثر**، و
                       · **كلُّ** ابنٍ في الجَدِّ يحمل بطاقةً هو غلافٌ رفيعٌ مثلُه.
                     الشرطُ الثالثُ هو المانع: في حاويةِ صفحةٍ أبناؤها يحملون
                     4 و2 و1 و3 بطاقاتٍ لا تتحقّق «كلُّها واحدة»، فلا يُتسلَّق —
                     وقد قِيس هذا التسلُّطُ الخاطئُ على `.main` قبلَ إضافةِ الشرط. */
                var gp = par.parentElement, thin = 0, holders = 0, uniform = true;
                if (thinWrap(par) && gp && gp !== document.body) {
                    for (j = 0; j < gp.children.length; j++) {
                        var ch = gp.children[j];
                        if (cardCount(ch) === 0) { continue; }
                        holders++;
                        if (thinWrap(ch)) { thin++; } else { uniform = false; }
                    }
                }
                if (uniform && thin >= 2 && thin === holders) {
                    if (seen.indexOf(gp) === -1) { seen.push(gp); }
                    gp.classList.add('ems-statgrid');
                } else {
                    par.classList.add('ems-statgrid');
                }
            }
        } finally { busy = false; }
    }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', bootAll); }
    else { bootAll(); }

    if (window.MutationObserver) {
        var mo = new MutationObserver(function (recs) {
            for (var i = 0; i < recs.length; i++) {
                if (recs[i].addedNodes && recs[i].addedNodes.length) { bootAll(); return; }
            }
        });
        var start = function () { mo.observe(document.body, { childList: true, subtree: true }); };
        if (document.body) { start(); } else { document.addEventListener('DOMContentLoaded', start); }
    }
    window.EmsStatCards = { boot: bootAll };
})();
