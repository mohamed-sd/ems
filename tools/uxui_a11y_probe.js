/* ═══════════════════════════════════════════════════════════════════════════
   tools/uxui_a11y_probe.js — الوصولُ الرقميُّ: الفحوصُ الاثنا عشرَ في DOM حيّ
   ───────────────────────────────────────────────────────────────────────────
   ◆ بوابةُ الترقيةِ البند ٣ (ف١٦-٢): «الفحوصُ الاثنا عشرَ مقيسةً · WCAG 2.2 AA».
     والتضادُّ (الفحصُ ①) مقيسٌ سلفًا في `tools/uxw_a11y_contrast.php` على رموزِ
     النظام؛ وهذه تقيس الأحدَ عشرَ الباقيةَ **على الشجرةِ الحيّةِ بعد JS** —
     فما يبنيه السكربتُ لا يراه فحصُ النصّ (درسٌ مُثبَتٌ في هذه الجولة).
   ◆ ولا يُعلَن مطابقٌ ما لم يُقَس: كلُّ فحصٍ يرجع عددَ المخالفاتِ وعيّنةً منها.
   ═══════════════════════════════════════════════════════════════════════════ */
(function uxuiA11y() {
  const vis = el => {
    const r = el.getBoundingClientRect(), c = getComputedStyle(el);
    return r.width > 0 && r.height > 0 && c.visibility !== 'hidden' && c.display !== 'none';
  };
  const sample = (arr, n = 3) => arr.slice(0, n).map(e =>
    e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') +
    (e.className ? '.' + String(e.className).trim().split(/\s+/)[0] : ''));
  const named = el => {
    const t = (el.getAttribute('aria-label') || el.getAttribute('title') ||
               el.getAttribute('alt') || el.innerText || '').trim();
    if (t) return true;
    const lb = el.getAttribute('aria-labelledby');
    if (lb && document.getElementById(lb.split(/\s+/)[0])) return true;
    if (el.id && document.querySelector('label[for="' + CSS.escape(el.id) + '"]')) return true;
    return !!el.closest('label');
  };

  const R = {};

  /* ② لغةُ الصفحةِ واتجاهُها معلنان */
  const html = document.documentElement;
  R.lang_dir = { lang: html.getAttribute('lang') || null, dir: html.getAttribute('dir') || null,
                 violations: (html.getAttribute('lang') && html.getAttribute('dir')) ? 0 : 1 };

  /* ③ لكلِّ صفحةٍ عنوانٌ رئيسٌ واحد */
  const h1 = [...document.querySelectorAll('h1')].filter(vis);
  R.single_h1 = { count: h1.length, violations: h1.length === 1 ? 0 : 1 };

  /* ④ تسلسلُ العناوينِ بلا قفزة */
  const heads = [...document.querySelectorAll('h1,h2,h3,h4,h5,h6')].filter(vis);
  let jumps = 0, prev = 0, jsample = [];
  heads.forEach(h => {
    const lv = +h.tagName[1];
    if (prev && lv > prev + 1) { jumps++; if (jsample.length < 3) jsample.push('h' + prev + '→h' + lv); }
    prev = lv;
  });
  R.heading_order = { headings: heads.length, violations: jumps, sample: jsample };

  /* ⑤ كلُّ ضابطٍ له اسمٌ مقروءٌ لقارئِ الشاشة */
  const ctrls = [...document.querySelectorAll('button,a[href],input,select,textarea')].filter(vis);
  const unnamed = ctrls.filter(e => {
    if (e.tagName === 'INPUT' && ['hidden'].includes(e.type)) return false;
    return !named(e);
  });
  R.control_names = { controls: ctrls.length, violations: unnamed.length, sample: sample(unnamed) };

  /* ⑥ كلُّ حقلٍ مرتبطٌ بتسميته */
  const fields = [...document.querySelectorAll('input:not([type=hidden]),select,textarea')].filter(vis);
  const unlabeled = fields.filter(e => !named(e));
  R.field_labels = { fields: fields.length, violations: unlabeled.length, sample: sample(unlabeled) };

  /* ⑦ كلُّ صورةٍ لها بديلٌ نصيٌّ أو معلَنةٌ زخرفة */
  const imgs = [...document.querySelectorAll('img')].filter(vis);
  const noAlt = imgs.filter(e => !e.hasAttribute('alt'));
  R.img_alt = { images: imgs.length, violations: noAlt.length, sample: sample(noAlt) };

  /* ⑧ الجداولُ لها ترويسةٌ معلنةٌ لقارئِ الشاشة */
  const tables = [...document.querySelectorAll('table')].filter(vis);
  const noTh = tables.filter(t => !t.querySelector('th'));
  R.table_headers = { tables: tables.length, violations: noTh.length };

  /* ⑨ لا فخَّ تركيزٍ ولا ترتيبَ يدويٍّ موجب (tabindex > 0) */
  const badTab = [...document.querySelectorAll('[tabindex]')].filter(e => +e.getAttribute('tabindex') > 0);
  R.tabindex = { violations: badTab.length, sample: sample(badTab) };

  /* ⑩ رابطُ التخطّي إلى المحتوى موجود */
  const skip = [...document.querySelectorAll('a[href^="#"]')].filter(a =>
    /تخطّ|تخطي|skip/i.test(a.textContent || '') || /skip/i.test(a.className || ''));
  R.skip_link = { found: skip.length, violations: skip.length ? 0 : 1 };

  /* ⑪ المعالمُ المعلنةُ (main/nav) موجودة */
  const main = document.querySelector('main,[role=main]');
  const nav = document.querySelector('nav,[role=navigation]');
  R.landmarks = { main: !!main, nav: !!nav, violations: (main ? 0 : 1) + (nav ? 0 : 1) };

  /* ⑫ زرٌّ بلا نصٍّ ظاهرٍ يلزمه تلميحٌ نصيّ (ف١٢-٢: الأيقونيُّ بتلميحٍ إلزاميّ) */
  const iconOnly = [...document.querySelectorAll('button,a[href]')].filter(vis)
    .filter(e => !(e.innerText || '').trim());
  const iconNoTip = iconOnly.filter(e => !e.getAttribute('title') && !e.getAttribute('aria-label'));
  R.icon_tooltips = { iconOnly: iconOnly.length, violations: iconNoTip.length, sample: sample(iconNoTip) };

  /* ═══════════════════════════════════════════════════════════════════════
     ⑬ حالةُ التركيزِ مرئيةٌ — **تُقاس والعنصرُ مركَّزٌ لا وهو ساكن**
     ─────────────────────────────────────────────────────────────────────────
     ◆ كان يُقرأ `getComputedStyle` على العنصرِ **الساكن** — و`outline` في
       الحالةِ الساكنةِ `none` في كلِّ متصفّحٍ لكلِّ عنصرٍ تقريبًا. فأبلغ القياسُ
       **48 مخالفةً من 72** على `Risk/risk_register.php` وفي المصدرِ 23 قاعدةَ
       `:focus-visible` عاملة — عيبُ قياسٍ لا عيبُ منتج.
     ◆ **والشرطُ اللازمُ ضغطةُ Tab حقيقيةٌ أولًا**: كروم يتتبّع آخرَ نمطيةِ
       إدخال، فبعد الفأرةِ لا يُطابق `:focus-visible` زرًّا وإن رُكِّز برمجيًّا.
       فيُطلَب من المُشغِّلِ ضغطُ Tab مرةً واحدةً قبل النداء — ويُعلَن ذلك في
       المخرَجِ (`keyboardModality`) فلا يُقرأ رقمٌ بلا شرطِه.
     ◆ **والمخالفةُ تُحسب حيث يُطابق `:focus-visible` ولا مؤشِّرَ**: عنصرٌ لا
       يُطابقها أصلًا لا يُتوقَّع له مؤشِّرٌ، فعدُّه مخالفةً كذبٌ في الاتجاهِ
       المعاكس. وقد قِيس بعدَ الإصلاح: 72 عنصرًا · 63 مطابقًا · **صفرُ مخالفة**.
     ═══════════════════════════════════════════════════════════════════════ */
  let focusKilled = 0;
  const fvBad = [];
  let fvMatched = 0;
  const prevFocus = document.activeElement;
  ctrls.slice(0, 120).forEach(e => {
    try { e.focus({ preventScroll: true }); } catch (x) {}
    const isFv = e.matches(':focus-visible');
    if (isFv) { fvMatched++; }
    const c = getComputedStyle(e);
    const noIndicator = (c.outlineStyle === 'none' || c.outlineWidth === '0px')
                        && (c.boxShadow === 'none' || !c.boxShadow);
    if (isFv && noIndicator) {
      focusKilled++;
      if (fvBad.length < 3) { fvBad.push(e.tagName.toLowerCase() + '.' + String(e.className).trim().split(/\s+/)[0]); }
    }
  });
  try { prevFocus && prevFocus.focus && prevFocus.focus({ preventScroll: true }); } catch (x) {}
  R.focus_visible = {
    sampled: Math.min(ctrls.length, 120),
    matchedFocusVisible: fvMatched,
    keyboardModality: fvMatched > 0,   /* صفرٌ هنا يعني: لم تُضغط Tab — والقياسُ لاغٍ */
    violations: focusKilled,
    sample: fvBad
  };

  const total = Object.values(R).reduce((s, x) => s + (x.violations || 0), 0);
  return { url: location.pathname, viewport: { w: innerWidth, h: innerHeight },
           checks: R, totalViolations: total };
})()
