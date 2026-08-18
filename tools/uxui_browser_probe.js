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
  const headerSelectors = [
    '.ux-page-header', '.page-header', '.ems-page-header', '#pageHeader',
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

  /* ── ④ أشرطةُ الأدواتِ الظاهرة (G20) ── */
  const toolbars = [...document.querySelectorAll('.ux-toolbar, .dt-buttons, .dataTables_filter, [class*="toolbar"]')]
    .filter(visible)
    .filter(el => !el.closest('.ux-viewpicker'))   /* منتقي المنظرِ أداةٌ لا شريط */
    .length;

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
    worstCellActions: worstCell,
    measuredRowHeight: rowHeight
  };
})()
