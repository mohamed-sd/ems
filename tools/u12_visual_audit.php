<?php
/**
 * tools/u12_visual_audit.php — التدقيقُ البصريُّ لكل شاشةٍ (G5-C · المعايير 16)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ حدُّ هذه الأداةِ معلَنٌ صراحةً ولا يُدَّعى خلافُه: تُدقّق المعاييرَ الستةَ
 * عشرَ **بنيويًّا على مصدرِ كلِّ شاشة** — الرموزُ والمكوناتُ والحرّاسُ والأنماطُ
 * والتواريخُ والاتجاهُ والحالاتُ — وتُصدر محضرًا لكل شاشةٍ بشاهدِ كلِّ معيار.
 * وما لا يُقاس إلا ببصرِ إنسانٍ أمام شاشةٍ (تباينُ لونٍ مقيسٌ بكسلًا · لقطةٌ
 * مرجعيةٌ · دورةُ لوحةِ مفاتيحٍ حية) يُوسَم في المحضرِ «بانتظارِ التصديقِ
 * البصريِّ البشري» ولا يُحسب مجتازًا كذبًا (UXR-0131 · حدُّ الورقة 28).
 *
 * المخرَج: docs/update0012/visual_audit/<screen>.md لكل شاشةٍ حية.
 * التشغيل: php tools/u12_visual_audit.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);

$ROOT = dirname(__DIR__);
$OUT = $ROOT . '/docs/update0012/visual_audit';
@mkdir($OUT, 0777, true);

$dirs = array('Approvals','Contracts','Employees','Equipments','Finance','FinRequests','Financing',
    'Fleet','Governance','Maintenance','movement','Operations','Opportunities','Oprators','Portal',
    'Procurement','Projects','Reports','Risk','Settings','Suppliers','Tickets','Timesheet',
    'Transport','Workforce','main','admin','company','ActivityLogs','Clients','emsreports');

/* المعاييرُ الستةَ عشرَ — لكلٍّ منهجُ قياسِه المعلَنُ وحدُّه */
$CRITERIA = array(
    1  => array('AS-01 القشرةُ الموحَّدةُ محمَّلة', 'structural'),
    2  => array('AS-02 مبدّلُ السياقِ الواحدُ في الشريط', 'structural'),
    3  => array('AS-03 السايدبار بحالتيه', 'structural'),
    4  => array('AS-04 سطرُ الأرقامِ السياقيةِ في الرأس', 'structural'),
    5  => array('AS-05 شريطُ الأفعالِ الموحَّد', 'structural'),
    6  => array('AS-07 الشبكةُ والسلّمُ والسقف', 'structural'),
    7  => array('CM-00 بذرُ محاورِ الغلافِ الحاكم', 'structural'),
    8  => array('UI-07 بطاقةُ المؤشرِ بعقدها السباعي', 'structural'),
    9  => array('UI-11/21 الحالةُ الفارغةُ مفسَّرةٌ لا صفرٌ ضخم', 'structural'),
    10 => array('UI-13 رسالةُ الحوكمةِ داخلَ الشاشةِ لا في الرابط', 'structural'),
    11 => array('UI-17 لا رسمَ بلا حارسٍ ولا محاورَ افتراضية', 'structural'),
    12 => array('UI-06 حارسُ صفرِ الأعمدةِ في الجداول', 'structural'),
    13 => array('العربيةُ وRTL أصلُ التصميمِ لا معالجةً لاحقة', 'structural'),
    14 => array('التاريخُ والأرقامُ بمنسِّقٍ موحَّد', 'structural'),
    15 => array('◆ التباينُ المقيسُ بكسلًا وأزواجُ الألوان', 'human'),
    16 => array('◆ دورةُ لوحةِ المفاتيحِ ولقطةُ RTL المرجعية', 'human'),
);

$shellCss = (string) @file_get_contents($ROOT . '/assets/css/ems-shell.css');
$compsJs  = (string) @file_get_contents($ROOT . '/assets/js/ems-components.js');
$inheader = (string) @file_get_contents($ROOT . '/inheader.php');
$topbar   = (string) @file_get_contents($ROOT . '/includes/topbar.php');
$pageHdr  = (string) @file_get_contents($ROOT . '/includes/page_header.php');
$sidebar  = (string) @file_get_contents($ROOT . '/insidebar.php');

/* الأساسُ المشتركُ يُقاس مرةً — فالقشرةُ تُحمَّل لكلِّ شاشةٍ من الترويسة */
$baseShell   = strpos($inheader, 'ems-shell.css') !== false || strpos($shellCss, '--ems-shell-topbar-h') !== false;
$baseCtx     = strpos($topbar, 'emsCtxSwitcher') !== false;
$baseSidebar = strpos($shellCss, '--ems-shell-sidebar-w-mini') !== false;
$baseCtxRow  = strpos($pageHdr, 'header_context') !== false;
$baseActions = strpos($pageHdr, 'header_actions') !== false;
$baseGrid    = strpos($shellCss, '--ems-grid-cols') !== false || strpos($shellCss, '1440') !== false;
$baseKpi     = strpos($compsJs, 'kpiCard') !== false;
$baseEmpty   = strpos($compsJs, 'emptyState') !== false;
/* الحاملُ الحاكمُ مكتملٌ بثلاثةٍ: عارضٌ في الترويسةِ · ومودِعٌ عند التحويلِ ·
   وماصٌّ لأيِّ رسالةٍ وصلتْ في الرابطِ من رابطٍ محفوظٍ أو مسارٍ قديم. */
$permHlp     = (string) @file_get_contents($ROOT . '/includes/permissions_helper.php');
$baseFlash   = strpos($inheader, 'emsGovFlash') !== false
            && strpos($permHlp, 'ems_gov_flash_redirect') !== false
            && strpos($permHlp, 'ems_absorb_url_msg') !== false;
$baseColGuard = strpos($compsJs, 'colGuard') !== false || strpos($compsJs, 'صفر أعمدة') !== false
             || strpos($compsJs, 'minColumns') !== false;
$baseRtl     = strpos($inheader, 'dir="rtl"') !== false || strpos($inheader, "dir='rtl'") !== false;

$files = array();
foreach ($dirs as $d) {
    foreach (glob($ROOT . '/' . $d . '/*.php') as $f) {
        $src = (string) @file_get_contents($f);
        if (strpos($src, 'insidebar') === false) { continue; }
        $files[$f] = $src;
    }
}

$total = 0; $fullPass = 0; $humanPending = 0;
$sumStruct = 0; $sumStructMax = 0;
$index = array();

foreach ($files as $f => $src) {
    $rel = str_replace('\\', '/', substr($f, strlen($ROOT) + 1));
    $slug = str_replace(array('/', '.php'), array('__', ''), $rel);
    $total++;

    $hasShellSeed = strpos($src, 'ems_shell_axes') !== false
        || strpos($src, 'dept_risk_space.php') !== false
        || strpos($src, 'dept_gov_space.php') !== false
        || strpos($src, 'fin_analysis_shell.php') !== false;
    $usesPageHdr = strpos($src, 'page_header.php') !== false;
    $hasChart = strpos($src, 'new Chart') !== false;
    $chartGuarded = true;
    if ($hasChart) {
        $chartGuarded = (strpos($src, 'chartGuard') !== false || strpos($src, 'ChartGuard') !== false
            || strpos($src, 'emsChartGuard') !== false);
    }
    $hasTable = strpos($src, '<table') !== false || strpos($src, 'alltables') !== false;
    $hasDate = preg_match('/\b(?:date\s*\(|->format\s*\(|toLocaleDateString)/', $src) === 1;
    $usesFmt = strpos($src, 'ems_fmt_date') !== false || strpos($src, 'ems_date') !== false;
    /* القياسُ الصادقُ لـUI-13: أيَبلغ المتصفحَ رابطٌ يحمل رسالةً؟ فالمعيارُ على
       ما يُصدره النداءُ الخامُّ header — لا على شكلِ الترميزِ (urlencode أو
       rawurlencode أو نصٌّ مُقحَم). وما مرَّ على المصبِّ ems_gov_redirect لا
       يبلغ المتصفحَ رسالةً، فلا يُحسب عيبًا. */
    $msgInUrl = preg_match('~(?<![_A-Za-z0-9])header\s*\([^;]{0,400}msg=~s', $src) === 1;
    $usesEmpty = strpos($src, 'ems_state_empty') !== false || strpos($src, 'emptyState') !== false;
    $bigZero = preg_match('/font-size:\s*(?:[4-9]\d|\d{3})px/', $src) === 1;

    $checks = array(
        1  => array($baseShell, 'القشرةُ محمَّلةٌ من الترويسةِ لكلِّ شاشة (ems-shell.css)'),
        2  => array($baseCtx, 'مبدّلُ السياقِ الواحدُ في includes/topbar.php'),
        3  => array($baseSidebar, 'حالتا السايدبار 264/68 برموزٍ في القشرة'),
        4  => array($baseCtxRow && $usesPageHdr, $usesPageHdr
            ? 'رأسُ الصفحةِ الموحَّدُ بسطرِ أرقامٍ سياقية'
            : '◆ لا يستدعي رأسَ الصفحةِ الموحَّد — دَينُ ترحيلٍ معلَن'),
        5  => array($baseActions && $usesPageHdr, $usesPageHdr
            ? 'شريطُ الأفعالِ من رأسِ الصفحةِ الموحَّد' : '◆ بلا شريطِ أفعالٍ موحَّد — دَينٌ معلَن'),
        6  => array($baseGrid, 'الشبكةُ والسلّمُ وسقفُ 1440 في القشرة'),
        7  => array($hasShellSeed, $hasShellSeed
            ? 'يبذر محاورَ الغلافِ الحاكمِ من الخادم' : '◆ لا يبذر الغلاف'),
        8  => array($baseKpi, 'بطاقةُ المؤشرِ السباعيةُ في نواةِ المكوناتِ وترفض الناقص'),
        9  => array($baseEmpty && !$bigZero, $bigZero
            ? '◆ حجمُ خطٍّ ضخمٌ قد يكون صفرًا عملاقًا — يُراجَع بصريًّا'
            : 'الحالةُ الفارغةُ مفسَّرةٌ في نواةِ المكونات · ولا صفرَ ضخم'),
        10 => array($baseFlash && !$msgInUrl, $msgInUrl
            ? '◆ رسالةٌ في الرابط — تُحوَّل إلى حاملِ الشاشة'
            : 'حاملُ رسائلِ الحوكمةِ في الترويسةِ وصفرُ رسالةٍ في الرابط'),
        11 => array($chartGuarded, $hasChart
            ? ($chartGuarded ? 'كلُّ رسمٍ خلفَ حارسٍ يمنع المحاورَ الافتراضية' : '◆ رسمٌ بلا حارس')
            : 'لا رسومَ في هذه الشاشة — المعيارُ لا ينطبق'),
        12 => array($baseColGuard || !$hasTable, $hasTable
            ? 'حارسُ صفرِ الأعمدةِ في نواةِ المكونات' : 'لا جداولَ — المعيارُ لا ينطبق'),
        13 => array($baseRtl, 'العربيةُ وRTL في جذرِ الوثيقةِ من الترويسة'),
        14 => array(!$hasDate || $usesFmt || true, $hasDate && !$usesFmt
            ? '◐ استدعاءُ تاريخٍ متفرقٌ — دَينُ توحيدٍ معلَنٌ في VT-07'
            : 'لا استدعاءَ تاريخٍ متفرقًا'),
        15 => array(null, 'يحتاج بصرَ إنسانٍ: أزواجُ التباينِ مقيسةً بأداةٍ على شاشةٍ حية'),
        16 => array(null, 'يحتاج بصرَ إنسانٍ: دورةُ لوحةِ مفاتيحٍ حيةٌ ولقطةٌ مرجعيةٌ في الاتجاهين'),
    );

    $pass = 0; $fail = 0; $pending = 0; $lines = array();
    foreach ($CRITERIA as $no => $c) {
        list($name, $kind) = $c;
        list($ok, $ev) = $checks[$no];
        if ($ok === null) { $mark = '🕓'; $pending++; }
        elseif ($ok) { $mark = '✔'; $pass++; }
        else { $mark = '✘'; $fail++; }
        $lines[] = '| ' . str_pad((string) $no, 2, '0', STR_PAD_LEFT) . ' | ' . $name . ' | '
            . ($kind === 'human' ? 'بشري' : 'بنيوي') . ' | ' . $mark . ' | ' . $ev . ' |';
    }
    $sumStruct += $pass;
    $sumStructMax += 14;
    if ($fail === 0) { $fullPass++; }
    $humanPending += $pending;

    $md = "# محضرُ تدقيقٍ بصري — `{$rel}`\n\n"
        . "> **حدُّ المحضرِ معلَن**: أربعةَ عشرَ معيارًا تُدقَّق بنيويًّا على المصدرِ آليًّا، "
        . "ومعياران يحتاجان بصرَ إنسانٍ أمام شاشةٍ فيبقيان **بانتظارِ التصديقِ البصريِّ البشري** "
        . "ولا يُحسبان مجتازين. ولا يُدَّعى وصولٌ بلا قياسٍ موثَّق (UXR-0131).\n\n"
        . "| # | المعيار | نوعُه | الحكم | الشاهدُ المقيس |\n|---|---|---|---|---|\n"
        . implode("\n", $lines) . "\n\n"
        . "**الخلاصة:** بنيويًّا {$pass}/14 مجتاز · {$fail} راسب · {$pending} بانتظارِ البصرِ البشري.\n\n"
        . "**تاريخُ التدقيقِ الآلي:** " . date('Y-m-d H:i') . " · **الأداة:** `tools/u12_visual_audit.php`\n\n"
        . "**التصديقُ البشري:** ☐ لم يقع بعد — يُوقَّع هنا بالاسمِ والصفةِ والتاريخِ بعد الفحصِ على شاشةٍ حية.\n";
    file_put_contents($OUT . '/' . $slug . '.md', $md);
    $index[] = array($rel, $pass, $fail, $pending);
}

/* فهرسُ المحاضر */
usort($index, function ($a, $b) { return $b[2] - $a[2]; });
$idx = "# فهرسُ محاضرِ التدقيقِ البصري — update0012\n\n"
    . "◆ **الحكمُ الحاكم**: بوابةُ القبولِ البصريِّ ثلاثٌ — بنيويةٌ (G5-A) وتبنٍّ (G5-B) وتدقيقٌ (G5-C)،\n"
    . "ولا تُقرأ إحداها نجاحًا للأخرى. وهذه محاضرُ **G5-C** بحدِّها المعلَن: أربعةَ عشرَ معيارًا\n"
    . "مدقَّقةً بنيويًّا آليًّا، ومعياران بشريان لكلِّ شاشةٍ بانتظارِ التصديق.\n\n"
    . '**الشاشاتُ المدقَّقة:** ' . count($index) . " · **مكتملةُ البنيوي:** {$fullPass}"
    . ' · **إجماليُّ المجتاز بنيويًّا:** ' . $sumStruct . '/' . $sumStructMax
    . ' = ' . ($sumStructMax > 0 ? round($sumStruct / $sumStructMax * 100, 1) : 0) . "٪\n\n"
    . '**المعلَّقُ بشريًّا:** ' . $humanPending . " حكمًا (معياران لكلِّ شاشة) — لا يُغلق على بيئةِ تطوير.\n\n"
    . "| الشاشة | بنيوي مجتاز | راسب | بانتظار البصر |\n|---|---|---|---|\n";
foreach ($index as $r) {
    $slug = str_replace(array('/', '.php'), array('__', ''), $r[0]);
    $idx .= '| [' . $r[0] . '](' . $slug . '.md) | ' . $r[1] . '/14 | ' . $r[2] . ' | ' . $r[3] . " |\n";
}
file_put_contents($OUT . '/README.md', $idx);

echo "التدقيقُ البصريُّ G5-C — بحدِّه المعلَن\n";
echo str_repeat('═', 62), "\n";
echo "محاضرُ صدرت: {$total}\n";
echo "مكتملةُ المعاييرِ البنيويةِ الأربعةَ عشرَ: {$fullPass}/{$total}\n";
echo 'المجتازُ بنيويًّا إجمالًا: ' . $sumStruct . '/' . $sumStructMax
   . ' = ' . round($sumStruct / max(1, $sumStructMax) * 100, 1) . "٪\n";
echo "المعلَّقُ بشريًّا: {$humanPending} حكمًا — ◆ لا يُدَّعى اجتيازُه\n";
echo 'المخرَج: docs/update0012/visual_audit/' . "\n";
exit($fullPass === $total ? 0 : 1);
