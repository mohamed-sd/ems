/* ============================================================================
 * ems-screen-about.js — مُصيِّرُ بطاقة «عن الشاشة» الموحَّد
 * ----------------------------------------------------------------------------
 * الطبقات مفصولة: PHP يملك **المحتوى** (includes/screen_contract.php يُصدره في
 * <template>)، وهذا الملفُّ يملك **الموضعَ والسلوك**، وملفُّ الأنماط يملك الشكل.
 *
 * لماذا يوجد أصلًا (قرار المالك 2026-08-09):
 *   كانت البطاقةُ تُطبع في موضع ندائها، و312 شاشةً من 358 تناديها **قبل فتح
 *   `<div class="main">`، فتُصيَّر خارجَ عمودِ المحتوى معلَّقةً على الحافة.
 *   ومحتوى <template> لا يُصيَّر أينما وُضع، فبطل أثرُ الموضع الخاطئ بنيويًّا،
 *   وصار لنا حرّيةُ الوضعِ في المكان الصحيح: تحتَ الرأس الموحَّد مباشرةً.
 *
 * السلوك (قرار المالك):
 *   • أولُ فتحٍ **لكلِّ شاشةٍ على حدة** ⇐ البطاقةُ مفتوحةٌ تشرح.
 *   • بعده ⇐ مطويّة، ويستدعيها زرُّ «عن الشاشة» في رأس الصفحة.
 *   • الإغلاقُ فوريٌّ (زرٌّ · Escape) ولا يحجب الصفحةَ لحظةً — فليست نافذةً
 *     modal: المستخدمُ يقرأ ويُكمل، لا يقرأ ثم يتخلّص من حاجز.
 *
 * الذاكرةُ محليةٌ لكل متصفحٍ وشاشة (localStorage) — لا صفَّ قاعدةٍ لتفضيلٍ
 * بصريٍّ لا يخصّ الحوكمة.
 * ==========================================================================*/
(function (window, document) {
  'use strict';

  var TPL_SEL  = '.ems-about-tpl';
  var PANEL_ID = 'emsAboutPanel';
  var BTN_ID   = 'emsAboutToggle';
  var NS       = 'ems_about_seen:';

  function seenKey(k) { return NS + k; }

  function wasSeen(k) {
    try { return window.localStorage.getItem(seenKey(k)) === '1'; }
    catch (e) { return false; }          // وضعُ التصفح الخاص — تُعرض كأنها أولُ مرة
  }

  function markSeen(k) {
    try { window.localStorage.setItem(seenKey(k), '1'); } catch (e) { /* لا يضرّ */ }
  }

  /** مرساةُ الوضع: الرأسُ الموحَّد داخلَ عمودِ المحتوى — وارتدادٌ إن غاب. */
  function findAnchor() {
    var main = document.querySelector('.main');
    if (!main) { return null; }
    var head = main.querySelector(':scope > .main_head') || main.querySelector('.main_head');
    return { main: main, head: head };
  }

  /** عنوانُ البطاقة من عنوان الصفحة نفسِه — فلا اسمَ يُكرَّر في مصدرين. */
  function screenTitle() {
    var h = document.querySelector('.main .head-title');
    if (!h) { return 'عن هذه الشاشة'; }
    var t = (h.textContent || '').replace(/\s+/g, ' ').trim();
    return t !== '' ? ('عن: ' + t) : 'عن هذه الشاشة';
  }

  function buildPanel(key, bodyHtml) {
    var sec = document.createElement('section');
    sec.className = 'ems-about';
    sec.id = PANEL_ID;
    sec.setAttribute('role', 'region');
    sec.setAttribute('aria-label', 'تعريف الشاشة');
    sec.hidden = true;

    var head = document.createElement('div');
    head.className = 'ems-about__head';
    head.innerHTML =
      '<span class="ems-about__ico" aria-hidden="true"><i class="fa fa-circle-question"></i></span>' +
      '<h2 class="ems-about__title"></h2>';
    head.querySelector('.ems-about__title').textContent = screenTitle();

    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'ems-about__close';
    close.setAttribute('aria-label', 'إغلاق التعريف');
    close.title = 'فهمت — أغلِق';
    close.innerHTML = '<i class="fa fa-xmark" aria-hidden="true"></i>';
    head.appendChild(close);

    var body = document.createElement('div');
    body.className = 'ems-about__body';
    body.innerHTML = bodyHtml;      // مصدرُه PHP وقد هرَّب كلَّ قيمةٍ عند الإصدار

    sec.appendChild(head);
    sec.appendChild(body);

    close.addEventListener('click', function () { hide(sec, key); });
    return sec;
  }

  function show(panel) {
    panel.hidden = false;
    panel.classList.add('is-open');
    var btn = document.getElementById(BTN_ID);
    if (btn) { btn.setAttribute('aria-expanded', 'true'); }
  }

  function hide(panel, key) {
    panel.classList.remove('is-open');
    panel.hidden = true;
    markSeen(key);
    var btn = document.getElementById(BTN_ID);
    if (btn) {
      btn.setAttribute('aria-expanded', 'false');
      btn.focus();                 // لا يضيع موضعُ لوحة المفاتيح بعد الإغلاق
    }
  }

  /** زرُّ الاستدعاء في الرأس — بهيئة أزرار الرأس الدائرية نفسِها. */
  function buildToggle(panel, key) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.id = BTN_ID;
    btn.className = 'ems-head-circle ems-about-btn';
    btn.title = 'عن الشاشة';
    btn.setAttribute('aria-label', 'عن الشاشة');
    btn.setAttribute('aria-controls', PANEL_ID);
    btn.setAttribute('aria-expanded', 'false');
    btn.innerHTML = '<i class="fa fa-circle-question" aria-hidden="true"></i>';
    btn.addEventListener('click', function () {
      if (panel.hidden) {
        show(panel);
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      } else {
        hide(panel, key);
      }
    });
    return btn;
  }

  function init() {
    /* الترجيحُ **بالمصدر لا بالموضع**، وترتيبُه: السجلُّ ← نصُّ الملف ← الاشتقاق.
       (لولاه لابتلع المشتقُّ النصَّ المصوغَ في 24 شاشةً تناديه في صدرها ثم
       تصوغ نصَّها لاحقًا؛ والسجلُّ فوقهما لأنه المرجعُ المحرَّر بقرار المالك.) */
    var all = document.querySelectorAll(TPL_SEL);
    var tpl = document.querySelector(TPL_SEL + '[data-src="registry"]')
           || document.querySelector(TPL_SEL + '[data-src="manual"]')
           || document.querySelector(TPL_SEL + '[data-src="auto"]');
    if (!tpl || !tpl.content) { return; }              // شاشةٌ بلا تعريف — لا زرَّ ولا بطاقة

    var anchor = findAnchor();
    if (!anchor) { return; }

    var key  = tpl.getAttribute('data-screen-key') || (location.pathname || 'screen');
    var html = tpl.innerHTML;
    if (!html.replace(/\s|&nbsp;/g, '')) { return; }   // تعريفٌ فارغ = لا بطاقة

    var panel = buildPanel(key, html);

    // الموضع: تحتَ الرأس الموحَّد مباشرةً (فوقَ أولِ محتوى)، أو صدرَ العمود
    if (anchor.head && anchor.head.parentNode) {
      anchor.head.insertAdjacentElement('afterend', panel);
    } else {
      anchor.main.insertBefore(panel, anchor.main.firstChild);
    }

    // الزرُّ في الرأس إن وُجد؛ وإلا فوق البطاقة نفسِها كي لا تُفقد الاستعادة
    var btn = buildToggle(panel, key);
    var actions = anchor.head ? anchor.head.querySelector('.head_actions') : null;
    if (actions) {
      actions.appendChild(btn);
    } else {
      var bar = document.createElement('div');
      bar.className = 'ems-about__floatbar';
      bar.appendChild(btn);
      panel.parentNode.insertBefore(bar, panel);
    }

    /* ── INJ-0441 · الشرحُ لا يسبق البياناتِ في شاشةِ بيانات ────────────────────
         «أولُ فتحٍ يشرح» قاعدةٌ صحيحةٌ في شاشةِ نموذجٍ أو لوحة. أمَّا شاشةُ
         **جدول** فالمستخدمُ جاءها ليقرأ صفوفَها: بطاقةُ الشرحِ المفتوحةُ تدفع
         الجدولَ إلى 31.5٪ من ارتفاعِ الصفحة (مقيسٌ على شاشةِ العقود)، ونصُّ
         القبولِ يشترط ألّا يتجاوز 30٪.
         فالبطاقةُ تبقى مطويّةً حيث يوجد جدولُ بيانات، وزرُّها في الرأسِ يُبرَز
         بنبضةٍ واحدةٍ ليُعرف مكانُه — فالشرحُ متاحٌ بضغطةٍ لا مفقود. */
    var hasDataTable = false;
    try {
        var tbs = document.querySelectorAll('table');
        for (var ti = 0; ti < tbs.length; ti++) {
            if (tbs[ti].querySelector('thead th')) { hasDataTable = true; break; }
        }
    } catch (eDt) { hasDataTable = false; }

    if (!wasSeen(key)) {
        if (hasDataTable) {
            var hintBtn = document.getElementById(BTN_ID);
            if (hintBtn) { hintBtn.classList.add('ems-about-btn--hint'); }
        } else {
            show(panel);                                // شاشةٌ بلا جدول: أولُ فتحٍ يشرح
        }
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !panel.hidden) { hide(panel, key); }
    });

    // التنظيف: القوالبُ أدّت دورَها (المختارُ والمرفوض) ولا داعيَ لبقائها
    Array.prototype.forEach.call(all, function (t) {
      if (t.parentNode) { t.parentNode.removeChild(t); }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(window, document);
