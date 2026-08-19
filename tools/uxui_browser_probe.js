/* ═══════════════════════════════════════════════════════════════════════════
   tools/uxui_browser_probe.js — مسبارُ القياسِ البصريِّ في متصفحٍ حقيقيّ
   ───────────────────────────────────────────────────────────────────────────
   ◆ قرارُ المالك (2026-08-18 · ثالثًا بند ٢): «G19 بقياسِ متصفحٍ حقيقيٍّ
     (getBoundingClientRect) على الدقتَين. وحتى يُصلح قياسُها **لا تُحسب ضمن
     نسبةِ المرور»** — فهذا المسبارُ يُصلحها: أرقامٌ من محرّكِ عرضٍ لا تقديرٌ
     من بنيةِ الوسوم.
   ◆ ويقيس معه البند ٤ (الاستجابة): صفرُ تمريرٍ أفقيٍّ للصفحةِ على المكتبيّ،
     والتمريرُ داخلَ الجدولِ وحدَه مسموحٌ ومقصود.
   ◆ يُلصَق في وحدةِ تحكّمِ المتصفحِ أو يُنفَّذ عبر javascript_exec، ويرجع JSON.
   ═══════════════════════════════════════════════════════════════════════════ */
(function uxuiProbe() {
  const de = document.documentElement, body = document.body;
  const HEADER_MAX = 96;   /* ف٨-٣ · بوابة G19 */

  /* ── ① الترويسة: أطولُ مرشَّحٍ مُصيَّرٍ فعلًا ── */
  /* ═══════════════════════════════════════════════════════════════════════
     صنفُ الترويسةِ الحقيقيُّ أولًا — و«أولُ ابنٍ» احتياطٌ لا أصل
     ─────────────────────────────────────────────────────────────────────────
     ◆ كشفه قياسٌ حيٌّ على `Maintenance/orders.php`: الترويسةُ قُرئت `null`
       لأن أولَ ابنٍ في الغلافِ كان `.ems-state-loading` بارتفاعِ صفر — والترويسةُ
       بعدَه. فالشاشةُ تبدو **بلا ترويسة** وهي ذاتُ ترويسةٍ سليمةٍ (59px).
     ◆ و`.main_head` هو الصنفُ الذي يُصدره `includes/page_header.php` فعلًا
       (موثَّقٌ في ترويسةِ الملف). فيُقدَّم، ويبقى «أولُ ابنٍ» احتياطًا لما لا
       يمرُّ بالمكوّن.
     ◆ ولا يُبطل هذا ما قِيس قبلَه: على الشاشاتِ التي قِيست كان «أولُ ابنٍ»
       **هو `.main_head` نفسَه** (59px عند top=74 في كلتَيهما) — عنصرٌ واحدٌ
       بمنتقيَين، لا رقمان.
     ═══════════════════════════════════════════════════════════════════════ */
  const headerSelectors = [
    '.main_head', '.ux-page-header', '.page-header', '.ems-page-header', '#pageHeader',
    '.ems-unified-page-shell > :first-child'
  ];
  let header = null;
  for (const sel of headerSelectors) {
    const el = document.querySelector(sel);
    if (!el) continue;
    const r = el.getBoundingClientRect();
    if (r.height > 0 && (!header || r.height > header.height)) {
      header = { selector: sel, height: Math.round(r.height), width: Math.round(r.width), top: Math.round(r.top) };
    }
  }

  /* ── ② التمريرُ الأفقيُّ للصفحةِ — والجدولُ مستثنًى بنصِّ ف١٢-١ ── */
  const pageScrollW = Math.max(de.scrollWidth, body.scrollWidth);
  const pageClientW = de.clientWidth;
  const overflowing = [];
  document.querySelectorAll('*').forEach(el => {
    const r = el.getBoundingClientRect();
    if (r.width <= pageClientW + 2) return;
    const cs = getComputedStyle(el);
    if (cs.overflowX === 'auto' || cs.overflowX === 'scroll') return;  /* تمريرٌ داخليٌّ مقصود */
    if (el.closest('.dataTables_scrollBody, .table-responsive, [data-ux-scroll]')) return;
    if (overflowing.length < 6) {
      overflowing.push(el.tagName.toLowerCase() + (el.className ? '.' + String(el.className).trim().split(/\s+/).slice(0, 2).join('.') : ''));
    }
  });

  /* ── ③ الزرُّ الرئيسيُّ الواحد (G17) — من المُصيَّرِ الظاهرِ لا من المصدر ── */
  const visible = el => {
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    return r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none';
  };
  const primaries = [...document.querySelectorAll('.ux-btn--primary, .btn-primary, .ems-btn-primary')]
    .filter(visible).length;

  /* ═══════════════════════════════════════════════════════════════════════
     ④ أشرطةُ الأدواتِ الظاهرة (G20) — **صفوفٌ مكدَّسةٌ لا عناصرُ مرشَّحة**
     ─────────────────────────────────────────────────────────────────────────
     ◆ كان العدُّ عناصرَ فأخطأ مرتين، والقياسُ الحيُّ كشفهما في `my_tasks`:
       ① ثلاثةُ عناصرَ **في صفٍّ واحد** (`top=411` للثلاثة) عُدَّت ثلاثةَ
         أشرطةٍ مكدَّسة — والبوابةُ تسأل عن **التكديس** لا عن عددِ الحاويات.
       ② و`dt-buttons` **داخلَ** `ems-auto-buttons` فعُدَّ الأبُ وابنُه شريطَين.
     ◆ فالمقياسُ الصحيح: **عددُ النطاقاتِ الأفقيةِ المتمايزة** بعد إسقاطِ
       المتداخل. وشريطان في صفٍّ واحدٍ ليسا تكديسًا — التكديسُ ما يأكل ارتفاعًا.
     ═══════════════════════════════════════════════════════════════════════ */
  const barEls = [...document.querySelectorAll('.ux-toolbar, .dt-buttons, .dataTables_filter, [class*="toolbar"]')]
    .filter(visible)
    .filter(el => !el.closest('.ux-viewpicker'));      /* منتقي المنظرِ أداةٌ لا شريط */
  const outerBars = barEls.filter(el => !barEls.some(o => o !== el && o.contains(el)));
  /* ◆ **والقياسُ لكلِّ جدولٍ على حدةٍ لا للصفحة**: كشفه قياسٌ حيٌّ على
       `Finance/approvals_inbox.php` — **أربعةُ جداولٍ** لكلٍّ شريطُه، فقُرئت
       «أربعةَ أشرطةٍ مكدَّسة» وهي شريطٌ واحدٌ لكلِّ جدول. وبوابةُ G20 تسأل:
       أفوقَ الجدولِ الواحدِ شريطٌ أم شريطان؟ فالمقامُ **أقصى ما فوقَ جدولٍ
       واحد** لا مجموعُ الصفحة. */
  const groupOf = el => {
    const w = el.closest('.dataTables_wrapper, .ems-table-wrap, [data-ux-table]');
    return w || document.body;
  };
  const perGroup = new Map();
  outerBars.forEach(el => {
    const g = groupOf(el);
    if (!perGroup.has(g)) { perGroup.set(g, new Set()); }
    perGroup.get(g).add(Math.round(el.getBoundingClientRect().top / 8));
  });
  let toolbars = 0;
  perGroup.forEach(s => { if (s.size > toolbars) { toolbars = s.size; } });
  const toolbarDetail = outerBars.map(el => ({
    cls: String(el.className).trim().split(/\s+/).slice(0, 2).join('.'),
    top: Math.round(el.getBoundingClientRect().top)
  }));
  const toolbarGroups = perGroup.size;

  /* ── ⑤ إجراءاتُ خليةِ الجدولِ (G18) — أظهرُ خليةٍ ── */
  let worstCell = 0;
  document.querySelectorAll('td').forEach(td => {
    const n = [...td.querySelectorAll('a,button')].filter(visible)
      .filter(e => /btn|action/i.test(String(e.className)) || e.hasAttribute('onclick')).length;
    if (n > worstCell) worstCell = n;
  });

  /* ── ⑥ كثافةُ الصفِّ المقيسة (ف٩-٣: 36px قياسيًّا) ── */
  const firstRow = document.querySelector('tbody tr');
  const rowHeight = firstRow ? Math.round(firstRow.getBoundingClientRect().height) : null;

  return {
    url: location.pathname,
    viewport: { w: innerWidth, h: innerHeight },
    dpr: devicePixelRatio,
    header: header,
    headerWithinLimit: header ? header.height <= HEADER_MAX : null,
    headerLimit: HEADER_MAX,
    pageScrollW: pageScrollW,
    pageClientW: pageClientW,
    hasHorizontalPageScroll: pageScrollW > pageClientW + 1,
    overflowingElements: overflowing,
    visiblePrimaryButtons: primaries,
    visibleToolbars: toolbars,
    toolbarBands: toolbarDetail,
    worstCellActions: worstCell,
    measuredRowHeight: rowHeight
  };
})()
