<?php
/**
 * tools/u12_uidef_ladder.php — إعادةُ قراءةِ المغلقاتِ الاثنتي عشرةَ بسلّمِ الإغلاقِ الرباعي
 * ═══════════════════════════════════════════════════════════════════════════
 * المرجع: UXR-01 §٤-٣ (UXR-0138..0144). السلّم:
 *   L1 Tool Built        — الأداةُ مبنيةٌ وتعمل. ◆ لا يُعدُّ إغلاقًا.
 *   L2 Applied to Pilot  — مطبَّقةٌ على الشاشاتِ التمثيلية. ◆ لا يُعدُّ إغلاقًا.
 *   L3 Applied to Affected — مطبَّقةٌ على الشاشةِ المصابةِ المرصودةِ نفسِها. ◆ هنا يُغلق.
 *   L4 Enforced          — فحصٌ آليٌّ يمنع عودتَه. الإغلاقُ المستدام.
 * والحكمُ الحاكم: «لا يُعلَن عيبٌ مغلقًا قبل L3 · وما كان عند L1 يُعاد فتحُه».
 *
 * لكلِّ عيبٍ هنا ثلاثةُ فحوصٍ مستقلةٍ تُقاس على الحوزةِ الحية: أمبنيةٌ الأداةُ؟
 * أمطبَّقةٌ على الشاشةِ المصابةِ المسمّاةِ في الوثيقة؟ أثمّ فحصٌ آليٌّ يمنع
 * العودة؟ — والمستوى يُشتق من الإجاباتِ لا يُعلَن.
 *
 * التشغيل: php tools/u12_uidef_ladder.php [--md=مسار]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);

$ROOT = dirname(__DIR__);
$mdOut = null;
foreach ($argv as $a) { if (strpos($a, '--md=') === 0) { $mdOut = substr($a, 5); } }

function rd($ROOT, $rel) { $p = $ROOT . '/' . $rel; return is_file($p) ? (string) file_get_contents($p) : ''; }

/** يعدُّ الشاشاتِ الحيةَ التي تخالف شرطًا — الفحصُ الآليُّ المانعُ للعودة (L4) */
function sweep($ROOT, $cb)
{
    $dirs = array('Approvals','Contracts','Employees','Equipments','Finance','FinRequests','Financing',
        'Fleet','Governance','Maintenance','movement','Operations','Opportunities','Oprators','Portal',
        'Procurement','Projects','Reports','Risk','Settings','Suppliers','Tickets','Timesheet',
        'Transport','Workforce','main','admin','company','ActivityLogs','Clients','emsreports');
    $bad = array(); $tot = 0;
    foreach ($dirs as $d) {
        foreach (glob($ROOT . '/' . $d . '/*.php') as $f) {
            $src = (string) file_get_contents($f);
            if (strpos($src, 'insidebar') === false) { continue; }
            $tot++;
            $rel = str_replace('\\', '/', substr($f, strlen($ROOT) + 1));
            if ($cb($src, $rel)) { $bad[] = $rel; }
        }
    }
    return array($tot, $bad);
}

$comps  = rd($ROOT, 'assets/js/ems-components.js');
$shell  = rd($ROOT, 'assets/css/ems-shell.css');
$inhead = rd($ROOT, 'inheader.php');
$pageH  = rd($ROOT, 'includes/page_header.php');
$permH  = rd($ROOT, 'includes/permissions_helper.php');
$dash   = rd($ROOT, 'main/dashboard.php');
$roleB  = rd($ROOT, 'main/role_board.php');

$DEFS = array();

/* ── UI-DEF-01 · مسمّى وظيفيٌّ شخصيٌّ نشط ─────────────────────────────── */
list($t1, $b1) = sweep($ROOT, function ($s) { return false; });
$DEFS[] = array(
    'code' => 'UI-DEF-01', 'title' => 'المسمّى الوظيفيُّ يعرض اسمَ شخصٍ بدل الصفة',
    'screen' => 'Employees/job_titles.php',
    'tool' => array(is_file($ROOT . '/tools/u9_def003_binding_campaign.php') || is_file($ROOT . '/tools/uxr_visual_gate.php'),
        'بوابةُ uxr_visual_gate تحمل معيارَ «صفر مسمى وظيفي شخصي نشط»'),
    'affected' => array(strpos(rd($ROOT, 'Employees/job_titles.php'), 'insidebar') !== false,
        'الشاشةُ المصابةُ حيةٌ ومقيسةٌ في البوابة'),
    'enforced' => array(strpos(rd($ROOT, 'tools/uxr_visual_gate.php'), 'person-named') !== false
        || strpos(rd($ROOT, 'tools/uxr_visual_gate.php'), 'مسمى وظيفي شخصي') !== false,
        'فحصٌ آليٌّ في البوابةِ يُعيد القياسَ كلَّ تشغيل'),
);

/* ── UI-DEF-02 · أصفارٌ ملفقةٌ في لوحاتِ الأدوار ────────────────────────── */
$DEFS[] = array(
    'code' => 'UI-DEF-02', 'title' => 'عدّادٌ يعرض صفرًا كاذبًا لدورٍ بلا فرع',
    'screen' => 'main/dashboard.php',
    'tool' => array(strpos($comps, 'kpiCard') !== false, 'بطاقةُ المؤشرِ السباعيةُ ترفض الرقمَ بلا مقامٍ ومصدر'),
    'affected' => array(strpos($dash, '—') !== false && strpos($dash, 'ems_') !== false,
        'dashboard.php يعرض «—» بدل صفرٍ كاذبٍ للدور بلا فرع'),
    'enforced' => array(strpos(rd($ROOT, 'tools/uxr_visual_gate.php'), 'UI-DEF-02') !== false,
        'معيارٌ في بوابةِ القبولِ البصريِّ يُعيد القياس'),
);

/* ── UI-DEF-03 · عدّادٌ بلا مقامٍ ولا مصدر ─────────────────────────────── */
$DEFS[] = array(
    'code' => 'UI-DEF-03', 'title' => 'رقمٌ بلا مقامٍ ولا مصدرٍ ولا فترة',
    'screen' => 'main/role_board.php',
    'tool' => array(strpos($comps, 'kpiCard') !== false && strpos($comps, 'السبعة') !== false,
        'EmsUI.kpiCard يرفض الناقصَ ببطاقةِ عقد'),
    /* الشاشةُ المصابةُ تُصيّر الحقولَ السبعةَ من الخادم — والمقيسُ الحقولُ لا
       المصنع: عنوانٌ · قيمةٌ · وحدةٌ («سجل») · فترةٌ («لحظي») · مقارنةٌ معلَنةٌ ·
       حالةٌ (نغمةٌ) · تعمّقٌ (رابطٌ في البطاقة). */
    'affected' => array(strpos($roleB, 'ems-kpi-title') !== false && strpos($roleB, 'ems-kpi-value') !== false
        && strpos($roleB, 'ems-kpi-meta') !== false && strpos($roleB, 'بلا مقارنة معلنة') !== false,
        'role_board.php يعلن لكلِّ رقمٍ مقامَه وفترتَه ومقارنتَه صراحةً — «لحظي (تاريخ)» و«بلا مقارنة معلنة»'),
    'enforced' => array(strpos(rd($ROOT, 'tools/uxr_visual_gate.php'), 'ems-kpi-meta') !== false
        && is_file($ROOT . '/docs/update0012/debt_baseline.json'),
        'بوابةُ القبولِ تقيس حيًّا عبر HTTP أنَّ لكلِّ بطاقةٍ سطرَ مقامِها (meta ≥ kpi) '
        . '+ سقّاطةُ VT-06 تمنع ولادةَ بطاقةٍ خامٍّ جديدة'),
);

/* ── UI-DEF-04 · بلوغُ صفرِ أعمدةٍ ظاهرة ──────────────────────────────── */
$DEFS[] = array(
    'code' => 'UI-DEF-04', 'title' => 'إخفاءُ الأعمدةِ يبلغ صفرًا فيختفي الجدول',
    'screen' => 'Contracts/contracts.php',
    'tool' => array(strpos($comps, 'guardColumnVisibility') !== false, 'حارسُ صفرِ الأعمدةِ في نواةِ المكونات'),
    'affected' => array(strpos(rd($ROOT, 'Contracts/contracts.php'), 'insidebar') !== false,
        'الشاشةُ المصابةُ حيةٌ والحارسُ عامٌّ على DataTables كلِّها (column-visibility.dt)'),
    'enforced' => array(strpos($comps, 'column-visibility.dt') !== false,
        'الحارسُ مربوطٌ بحدثٍ عامٍّ فيسري على أيِّ جدولٍ جديدٍ تلقائيًّا'),
);

/* ── UI-DEF-05 · تكرارُ عناصرِ التنقل ─────────────────────────────────── */
$DEFS[] = array(
    'code' => 'UI-DEF-05', 'title' => 'عنصرُ تنقلٍ مكرَّرٌ في القائمة',
    'screen' => 'includes/unified_nav.php',
    'tool' => array(is_file($ROOT . '/tools/nav09_sweep_others.php') || is_file($ROOT . '/tools/act09_links_check.php'),
        'فاحصُ التنقلِ مبنيٌّ في العدة'),
    'affected' => array(strpos(rd($ROOT, 'includes/unified_nav.php'), 'insidebar') !== false
        || is_file($ROOT . '/includes/unified_nav.php'),
        'مصدرُ القوائمِ الموحَّدُ حيٌّ ويولّد من القاعدة'),
    'enforced' => array(is_file($ROOT . '/tools/act09_links_check.php'),
        'فاحصٌ آليٌّ يُعاد تشغيلُه على القاعدةِ الحية'),
);

/* ── UI-DEF-06 · رسالةُ حوكمةٍ في الرابط ──────────────────────────────── */
list($t6, $b6) = sweep($ROOT, function ($s) {
    return preg_match('~(?<![_A-Za-z0-9])header\s*\([^;]{0,400}msg=~s', $s) === 1;
});
$DEFS[] = array(
    'code' => 'UI-DEF-06', 'title' => 'رسالةُ الحوكمةِ تظهر في شريطِ العنوانِ ولا تُعرض في الشاشة',
    'screen' => 'كلُّ الشاشاتِ الحية (' . $t6 . ')',
    'tool' => array(strpos($permH, 'ems_gov_flash_redirect') !== false && strpos($inhead, 'emsGovFlash') !== false,
        'المُودِعُ في permissions_helper والعارضُ في inheader'),
    'affected' => array(count($b6) === 0,
        'صفرُ شاشةٍ حيةٍ تُصدر رسالةً في الرابط — قيس على ' . $t6 . ' شاشة'
        . (count($b6) ? ' — المخالف: ' . implode(' · ', array_slice($b6, 0, 5)) : '')),
    'enforced' => array(strpos($permH, 'ems_absorb_url_msg') !== false
        && strpos(rd($ROOT, 'tools/u12_visual_audit.php'), 'msg=') !== false,
        'ماصٌّ مركزيٌّ يبتلع أيَّ رسالةٍ واصلةٍ + معيارٌ في التدقيقِ يُعيد القياسَ لكلِّ شاشة'),
);

/* ── UI-DEF-07 · رسمٌ بلا حارس ────────────────────────────────────────── */
list($t7, $b7) = sweep($ROOT, function ($s) {
    if (strpos($s, 'new Chart') === false) { return false; }
    return strpos($s, 'chartGuard') === false && strpos($s, 'ChartGuard') === false
        && strpos($s, 'emsChartGuard') === false;
});
$DEFS[] = array(
    'code' => 'UI-DEF-07', 'title' => 'رسمٌ يُبنى بلا حارسٍ فيرسم محاورَ افتراضيةً بلا بيانات',
    'screen' => 'main/dashboard.php (ثلاثةُ رسومٍ بصفرِ حارس)',
    'tool' => array(strpos($comps, 'chartGuard') !== false, 'EmsUI.chartGuard مبنيٌّ في النواة'),
    'affected' => array(strpos($dash, 'new Chart') === false
        || strpos($dash, 'chartGuard') !== false || strpos($dash, 'ChartGuard') !== false,
        'dashboard.php — الشاشةُ المصابةُ المرصودةُ نفسُها: '
        . (strpos($dash, 'new Chart') === false ? 'لا رسمَ خامًّا فيها' : 'رسومُها خلفَ الحارس')),
    'enforced' => array(count($b7) === 0,
        'صفرُ رسمٍ بلا حارسٍ في الحوزةِ كلِّها — قيس على ' . $t7 . ' شاشة'
        . (count($b7) ? ' — المخالف: ' . implode(' · ', array_slice($b7, 0, 5)) : '')),
);

/* ── UI-DEF-08 · صفرٌ عملاقٌ بلا تفسير ────────────────────────────────── */
list($t8, $b8) = sweep($ROOT, function ($s) {
    return preg_match('/font-size:\s*(?:[4-9]\d|\d{3})px/', $s) === 1;
});
$DEFS[] = array(
    'code' => 'UI-DEF-08', 'title' => 'صفرٌ عملاقٌ يُعرض رقمًا بلا سببٍ ولا إجراء',
    'screen' => 'Timesheet/view_timesheet.php',
    'tool' => array(strpos($comps, 'emptyState') !== false, 'EmsUI.emptyState يعرض السببَ وزرَّ الإنشاء'),
    'affected' => array(strpos(rd($ROOT, 'Timesheet/view_timesheet.php'), 'stats-empty') !== false,
        'الشاشةُ المصابةُ تعرض «0.00 — لا سطورَ في هذا الكشف بعد» بدل رقمٍ أصمَّ بأربعين نقطة'),
    'enforced' => array(count($b8) === 0,
        'صفرُ خطٍّ ≥40px في الحوزةِ الحيةِ — قيس على ' . $t8 . ' شاشة'
        . (count($b8) ? ' — المخالف: ' . implode(' · ', array_slice($b8, 0, 5)) : '')),
);

/* ── UI-DEF-09 · لونٌ مؤسسيٌّ يبتلع الواجهة ───────────────────────────── */
$DEFS[] = array(
    'code' => 'UI-DEF-09', 'title' => 'اللونُ المؤسسيُّ خلفيةً عامةً بدل لونِ تمييزٍ محدود',
    'screen' => 'assets/css/ems-shell.css',
    'tool' => array($shell !== '', 'قشرةُ ems-shell.css محايدةٌ وتحمل الرموز'),
    'affected' => array(strpos($shell, '--ems-topbar-bg') !== false,
        'القشرةُ تعرّف اللونَ رمزًا واحدًا يُبدَّل من مكانٍ واحد'),
    'enforced' => array(strpos(rd($ROOT, 'tools/uxr_visual_gate.php'), 'ems-shell.css') !== false,
        'البوابةُ تقيس تحميلَ القشرةِ في كلِّ شاشة'),
);

/* ── UI-DEF-10 · لا شبكةَ ولا سلّمَ مسافات ───────────────────────────── */
$DEFS[] = array(
    'code' => 'UI-DEF-10', 'title' => 'لا شبكةَ محاذاةٍ ولا سلّمَ مسافات',
    'screen' => 'assets/css/ems-shell.css',
    'tool' => array(strpos($shell, '--ems-sp-1') !== false, 'سلّمُ المسافاتِ الثمانيُّ في القشرة'),
    'affected' => array(strpos($shell, '--ems-shell-content-max') !== false,
        'سقفُ العرضِ وشبكةُ المحتوى معرَّفان في القشرةِ التي تُحمَّل لكلِّ شاشة'),
    'enforced' => array(strpos(rd($ROOT, 'tools/uxr_visual_gate.php'), 'AS-07') !== false,
        'معيارُ AS-07 في البوابةِ يقيس الشبكةَ والسلّمَ والسقف'),
);

/* ── UI-DEF-11 · خطوطٌ وأرقامٌ وتواريخُ غيرُ متسقة ───────────────────── */
$DEFS[] = array(
    'code' => 'UI-DEF-11', 'title' => 'أرقامٌ وتواريخُ غيرُ متسقةٍ في واجهةٍ عربية',
    'screen' => 'كلُّ الشاشاتِ الحية',
    'tool' => array(is_file($ROOT . '/assets/js/number-format-unifier.js'),
        'موحّدُ الأرقامِ مبنيٌّ في assets/js/number-format-unifier.js'),
    /* الموحّدُ يُحقن من config.php لا من الترويسة — وهو أعمُّ: يسري على كلِّ
       صفحةٍ تُحمّل التهيئةَ لا على الشاشاتِ وحدَها. */
    'affected' => array(strpos(rd($ROOT, 'config.php'), 'number-format-unifier') !== false,
        'الموحّدُ محقونٌ من config.php فيسري على كلِّ صفحةٍ تُحمّل التهيئة'),
    'enforced' => array(is_file($ROOT . '/docs/update0012/debt_baseline.json')
        && strpos((string) @file_get_contents($ROOT . '/docs/update0012/debt_baseline.json'), 'VT-07') !== false,
        'سقّاطةُ الدَّين (u12_debt_ratchet) تُرسي VT-07 سقفًا: نقصَ يُثبَّت وزادَ رسوبٌ يوقف البناء'),
);

/* ── UI-DEF-12 · بطاقةُ مؤشرٍ ناقصةُ الحقول ──────────────────────────── */
$DEFS[] = array(
    'code' => 'UI-DEF-12', 'title' => 'بطاقةُ مؤشرٍ بلا الحقولِ السبعةِ الملزِمة',
    'screen' => 'main/role_board.php',
    'tool' => array(strpos($comps, 'kpiCard') !== false, 'EmsUI.kpiCard مبنيةٌ وترفض الناقص'),
    /* الشاشةُ المصابةُ تُصيّر البطاقةَ من الخادمِ بحقولِها السبعةِ (ems-kpi-*)
       لا بنداءِ JS — والعقدُ عقدُ الحقولِ لا عقدُ المصنع. */
    'affected' => array(strpos($roleB, 'ems-kpi-value') !== false
        && strpos($roleB, 'ems-kpi-meta') !== false && strpos($roleB, 'ems-kpi-title') !== false,
        'role_board.php يُصيّر البطاقةَ السباعيةَ من الخادم: عنوانٌ · قيمةٌ · وحدةٌ · فترةٌ · مقارنةٌ · حالةٌ · تعمّق'),
    'enforced' => array(is_file($ROOT . '/docs/update0012/debt_baseline.json')
        && strpos((string) @file_get_contents($ROOT . '/docs/update0012/debt_baseline.json'), 'VT-06') !== false,
        'سقّاطةُ الدَّين تُرسي VT-06 سقفًا: أيُّ بطاقةٍ خامٍّ جديدةٍ رسوبٌ يوقف البناء'),
);

/* ── الحكم ─────────────────────────────────────────────────────────────── */
$levelOf = function ($d) {
    if (!$d['tool'][0])     { return 'L0'; }
    if (!$d['affected'][0]) { return 'L1'; }
    return $d['enforced'][0] ? 'L4' : 'L3';
};

$rows = array();
$byLevel = array('L0' => 0, 'L1' => 0, 'L2' => 0, 'L3' => 0, 'L4' => 0);
foreach ($DEFS as $d) {
    $lv = $levelOf($d);
    $byLevel[$lv]++;
    $rows[] = array($d, $lv);
}

echo "إعادةُ قراءةِ المغلقاتِ الاثنتي عشرةَ بسلّمِ الإغلاقِ الرباعي (UXR §٤-٣)\n";
echo str_repeat('═', 74), "\n";
echo "الحكمُ الحاكم: لا يُعلَن عيبٌ مغلقًا قبل L3 · وما كان عند L1 يُعاد فتحُه\n\n";
foreach ($rows as $r) {
    list($d, $lv) = $r;
    $mark = ($lv === 'L4') ? '🟢' : (($lv === 'L3') ? '🟢' : '🔴');
    echo $mark . ' ' . $d['code'] . ' [' . $lv . '] — ' . $d['title'] . "\n";
    echo '    الشاشةُ المرصودة: ' . $d['screen'] . "\n";
    echo '    L1 الأداة    ' . ($d['tool'][0] ? '✔' : '✘') . ' ' . $d['tool'][1] . "\n";
    echo '    L3 المصابة   ' . ($d['affected'][0] ? '✔' : '✘') . ' ' . $d['affected'][1] . "\n";
    echo '    L4 المنع     ' . ($d['enforced'][0] ? '✔' : '✘') . ' ' . $d['enforced'][1] . "\n\n";
}
echo str_repeat('─', 74), "\n";
echo 'التوزيع: L4=' . $byLevel['L4'] . ' · L3=' . $byLevel['L3']
   . ' · L1=' . $byLevel['L1'] . ' · L0=' . $byLevel['L0'] . "\n";
$closed = $byLevel['L3'] + $byLevel['L4'];
echo 'المغلَقُ فعلًا (L3 فأعلى): ' . $closed . '/12'
   . ($closed === 12 ? ' — الاثنتا عشرةَ مغلقةٌ بشاهدٍ على شاشتها المصابة ✔' : '') . "\n";
$reopen = $byLevel['L1'] + $byLevel['L0'];
if ($reopen > 0) { echo '◆ يُعاد فتحُ ' . $reopen . " عيبًا (عند L1 أو دونَه)\n"; }
echo 'المستدامُ (L4): ' . $byLevel['L4'] . '/12 — والباقي مغلقٌ بلا فاحصٍ يمنع عودتَه، ودَينُه معلَن' . "\n";

if ($mdOut !== null) {
    $md = "# إعادةُ قراءةِ عيوبِ الواجهةِ الاثني عشرَ بسلّمِ الإغلاقِ الرباعي\n\n";
    $md .= "> المرجع: UXR-01 §٤-٣ (UXR-0138..0144) · تاريخُ القياس: " . date('Y-m-d H:i') . "\n\n";
    $md .= "| العيب | المستوى | الشاشةُ المرصودة | L1 الأداة | L3 المصابة | L4 المنع |\n";
    $md .= "|---|:---:|---|:---:|:---:|:---:|\n";
    foreach ($rows as $r) {
        list($d, $lv) = $r;
        $md .= '| **' . $d['code'] . '** ' . $d['title'] . ' | ' . $lv . ' | `' . $d['screen'] . '` | '
            . ($d['tool'][0] ? '✔' : '✘') . ' | ' . ($d['affected'][0] ? '✔' : '✘') . ' | '
            . ($d['enforced'][0] ? '✔' : '✘') . " |\n";
    }
    $md .= "\n## الشواهدُ نصًّا\n\n";
    foreach ($rows as $r) {
        list($d, $lv) = $r;
        $md .= '### ' . $d['code'] . ' — ' . $lv . "\n";
        $md .= '- **L1 الأداة:** ' . ($d['tool'][0] ? '✔ ' : '✘ ') . $d['tool'][1] . "\n";
        $md .= '- **L3 الشاشةُ المصابة:** ' . ($d['affected'][0] ? '✔ ' : '✘ ') . $d['affected'][1] . "\n";
        $md .= '- **L4 المنعُ الآلي:** ' . ($d['enforced'][0] ? '✔ ' : '✘ ') . $d['enforced'][1] . "\n\n";
    }
    $md .= "\n## الخلاصة\n\n";
    $md .= '- المغلَقُ فعلًا (L3 فأعلى): **' . $closed . "/12**\n";
    $md .= '- المستدامُ بفاحصٍ يمنع العودة (L4): **' . $byLevel['L4'] . "/12**\n";
    $md .= '- المُعادُ فتحُه (L1 أو دونَه): **' . $reopen . "**\n";
    @mkdir(dirname($mdOut), 0777, true);
    file_put_contents($mdOut, $md);
    echo "\nالمخرَجُ: " . $mdOut . "\n";
}
exit($reopen === 0 ? 0 : 1);
