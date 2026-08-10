<?php
/**
 * tools/fix_ui_baseline.php — حزامُ لقطاتٍ نصيٍّ للتحقُّقِ البصريِّ الآليّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الصورةُ لا تُقارَن آليًّا، لكنّ **الأنماطَ المحسوبةَ تُقارَن**. فالحزامُ يلتقط
 *   لكلِّ شاشةٍ بصمةً من القيمِ التي تصنع الشكل: أبعادَ الترويسةِ والشريطِ
 *   الجانبيِّ ومواضعَ عناصرِهما، وألوانَ الخلفياتِ والنصوصِ الفعلية، واتجاهَ
 *   الكتابة. فإن تغيّرت بصمةٌ بعد تعديلٍ لم يُقصد به تغييرُها — انكشف الانحدار.
 *
 * ◆ ولماذا لا يكفي «الصفحةُ تفتح»: توحيدُ الألوانِ والأزرارِ قد يُبقي الصفحةَ
 *   تفتح ويقلب موضعَ الشعارِ أو يُخفي زرًّا. عدُّ العناصرِ يكذب هنا كما كذب في
 *   القوائم — والمقياسُ الصادقُ هو ما يراه المتصفحُ بعد تطبيقِ كلِّ القواعد.
 *
 * التشغيل:
 *   php tools/fix_ui_baseline.php --save=before     ← يطبع قائمةَ الشاشات
 *   (الالتقاطُ نفسُه يجري من المتصفح عبر السكربت المطبوع)
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);

/* الشاشاتُ المرجعية: واحدةٌ من كلِّ نمطٍ بنيويٍّ مختلف — فتغطيةُ الأنماطِ
   أهمُّ من كثرةِ الشاشات. */
$SCREENS = array(
    'main/role_board.php'              => 'لوحةُ دورٍ — قشرةُ insidebar',
    'main/dashboard.php'               => 'اللوحةُ الرئيسة — أنماطٌ إنلاين خاصة',
    'Contracts/contracts.php'          => 'جدولٌ كبيرٌ + DataTables',
    'Timesheet/timesheet.php'          => 'أكثرُ الشاشاتِ أنماطًا موضعيةً (205)',
    'Employees/employees.php'          => 'جدولُ سجلٍّ نمطيّ',
    'Finance/accounts_fin.php'         => 'شاشةُ ماليةٍ بقشرةِ fin',
    'Audit/iaf_charter.php'            => 'شاشةٌ مولَّدةٌ بعُدَّةِ u13',
    'Finance/fin_ratios.php'           => 'قشرةُ التحليلِ fin_analysis_shell',
    'admin/permissions/index.php'      => 'كونسولُ المزوّدِ — قشرةٌ ثالثة',
    'Governance/exceptions.php'        => 'شاشةُ حوكمة',
);

$mode = 'list';
foreach ($argv as $a) { if (strpos($a, '--save=') === 0) { $mode = substr($a, 7); } }

echo "// شاشاتُ المرجع (" . count($SCREENS) . ")\n";
foreach ($SCREENS as $rel => $why) {
    printf("%-36s %s\n", $rel, $why);
}
echo "\n// سكربتُ الالتقاطِ — يُنفَّذ في المتصفحِ على كلِّ شاشة:\n";
echo <<<'JS'
(function(){
  const px = v => Math.round(parseFloat(v)||0);
  const box = el => { if(!el) return null; const r = el.getBoundingClientRect();
    return {x:px(r.x), y:px(r.y), w:px(r.width), h:px(r.height)}; };
  const cs = (el,props) => { if(!el) return null; const c=getComputedStyle(el); const o={};
    props.forEach(p=>o[p]=c[p]); return o; };
  const q = s => document.querySelector(s);
  const topbar = q('.ems-topbar');
  return {
    path: location.pathname,
    dir: getComputedStyle(document.body).direction,
    topbar: box(topbar),
    topbarStyle: cs(topbar, ['direction','backgroundColor','height','display']),
    logo: box(q('.ems-topbar-logo')),
    sidebar: box(q('#sidebar, .sidebar, aside')),
    main: box(q('.main')),
    links: document.querySelectorAll('#sidebar a, .sidebar a, aside a').length,
    buttons: document.querySelectorAll('button, .btn, input[type=submit]').length,
    tables: document.querySelectorAll('table').length,
    inputs: document.querySelectorAll('input,select,textarea').length,
    bodyBg: getComputedStyle(document.body).backgroundColor,
    bodyColor: getComputedStyle(document.body).color,
    hScroll: document.documentElement.scrollWidth > document.documentElement.clientWidth,
    errors: (window.__emsErrors||[]).length
  };
})()
JS;
echo "\n";
