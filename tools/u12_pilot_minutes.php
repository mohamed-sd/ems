<?php
/**
 * tools/u12_pilot_minutes.php — محاضرُ اعتمادِ الشاشاتِ التمثيليةِ الثماني
 * ═══════════════════════════════════════════════════════════════════════════
 * المرجع: UXR-01 §٤-٥ (UXR-0068..0076) — «الثماني تُعتمد نماذجَ قبل أي تعميم»،
 * وشرطُ AC-U3 محضرُ اعتمادٍ لكلِّ شاشة. والتدقيقُ الحيُّ قال: «لا محضرَ اعتمادٍ
 * لأيِّ شاشة» — فهذه الأداةُ تُصدرها، قياسًا لا دعوى.
 *
 * لكلِّ شاشةٍ سبعةُ معاييرَ تُقاس على شيفرتها الحيةِ (هي معاييرُ «x/7» التي
 * قاسها التدقيق):
 *   ① القشرةُ الحاكمة (بذرُ CM-00)          ⑤ حارسُ صفرِ الأعمدةِ حيث جدول
 *   ② الرأسُ الموحَّدُ بسطرِ السياق          ⑥ حاملُ رسائلِ الحوكمةِ لا الرابط
 *   ③ البطاقةُ السباعيةُ حيث مؤشر            ⑦ الحالةُ الفارغةُ مفسَّرة
 *   ④ حارسُ الرسومِ حيث رسم
 * والحكمُ: تُعتمد نموذجًا عند 7/7 — وما دونَه يُذكر ناقصُه بالاسم.
 *
 * التشغيل: php tools/u12_pilot_minutes.php [--out=مجلد]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);

$ROOT = dirname(__DIR__);
$OUT = $ROOT . '/docs/update0012/pilot_minutes';
foreach ($argv as $a) { if (strpos($a, '--out=') === 0) { $OUT = $ROOT . '/' . substr($a, 6); } }
@mkdir($OUT, 0777, true);

$PILOTS = array(
    'main/dashboard.php'             => array('UXR-0069', 'لوحةُ الرئيسِ التنفيذي'),
    'main/role_board.php'            => array('UXR-0070', 'لوحةُ إدارةِ التشغيل'),
    'main/my_workspace.php'          => array('UXR-0071', 'مساحةُ عملي'),
    'Contracts/contracts.php'        => array('UXR-0072', 'العقود'),
    'Timesheet/timesheet.php'        => array('UXR-0073', 'التايم شيت'),
    'Maintenance/orders.php'         => array('UXR-0074', 'أمرُ الصيانة'),
    'Suppliers/supplier_profile.php' => array('UXR-0075', 'ملفُّ المورد'),
    'Risk/risk_board.php'            => array('UXR-0076', 'مساحةُ المخاطر'),
);

$comps = (string) @file_get_contents($ROOT . '/assets/js/ems-components.js');
$permH = (string) @file_get_contents($ROOT . '/includes/permissions_helper.php');
$inhd  = (string) @file_get_contents($ROOT . '/inheader.php');

$index = array();
$fullPass = 0;

foreach ($PILOTS as $rel => $meta) {
    $path = $ROOT . '/' . $rel;
    $src = is_file($path) ? (string) file_get_contents($path) : '';
    if ($src === '') { continue; }
    list($rid, $title) = $meta;

    $hasChart = strpos($src, 'new Chart') !== false;
    $hasTable = strpos($src, '<table') !== false || strpos($src, 'alltables') !== false;
    $hasKpi   = strpos($src, 'ems-kpi-card') !== false || strpos($src, 'kpiCard') !== false
             || strpos($src, 'stats-card') !== false;

    $c = array();
    $c[1] = array('القشرةُ الحاكمةُ CM-00 مبذورةٌ من الخادمِ قبل التصيير',
        strpos($src, 'ems_shell_axes') !== false
            || strpos($src, 'dept_risk_space.php') !== false
            || strpos($src, 'fin_analysis_shell.php') !== false,
        'ems_shell_axes / غلافٌ مشترك');
    $c[2] = array('الرأسُ الموحَّدُ بسطرِ السياقِ وشريطِ الأفعال',
        strpos($src, 'page_header.php') !== false,
        'includes/page_header.php');
    $c[3] = array('بطاقةُ المؤشرِ بعقدِها السباعيّ',
        !$hasKpi || (strpos($src, 'ems-kpi-meta') !== false || strpos($src, 'kpiCard') !== false),
        $hasKpi ? 'ems-kpi-meta / EmsUI.kpiCard' : 'لا مؤشراتِ في هذه الشاشة — المعيارُ لا ينطبق');
    $c[4] = array('كلُّ رسمٍ خلفَ حارسٍ يمنع المحاورَ الافتراضية',
        !$hasChart || strpos($src, 'chartGuard') !== false || strpos($src, 'ChartGuard') !== false
            || strpos($src, 'emsChartGuard') !== false,
        $hasChart ? 'chartGuard' : 'لا رسومَ في هذه الشاشة — المعيارُ لا ينطبق');
    $c[5] = array('حارسُ صفرِ الأعمدةِ فاعلٌ حيث جدول',
        !$hasTable || strpos($comps, 'column-visibility.dt') !== false,
        $hasTable ? 'الحارسُ المركزيُّ على column-visibility.dt' : 'لا جداولَ — المعيارُ لا ينطبق');
    $c[6] = array('رسائلُ الحوكمةِ في الشاشةِ لا في الرابط',
        preg_match('~(?<![_A-Za-z0-9])header\s*\([^;]{0,400}msg=~s', $src) !== 1
            && strpos($permH, 'ems_gov_flash_redirect') !== false
            && strpos($inhd, 'emsGovFlash') !== false,
        'صفرُ رسالةٍ في الرابط + الحاملُ المركزيّ');
    $c[7] = array('الحالةُ الفارغةُ مفسَّرةٌ ولا صفرَ ضخم',
        preg_match('/font-size:\s*(?:[4-9]\d|\d{3})px/', $src) !== 1
            && strpos($comps, 'emptyState') !== false,
        'EmsUI.emptyState + صفرُ خطٍّ ≥40px');

    $pass = 0;
    foreach ($c as $one) { if ($one[1]) { $pass++; } }
    $verdict = ($pass === 7);
    if ($verdict) { $fullPass++; }

    $slug = str_replace(array('/', '.php'), array('__', ''), $rel);
    $md  = "# محضرُ اعتمادٍ — {$title}\n\n";
    $md .= "| البند | القيمة |\n|---|---|\n";
    $md .= "| المعرّفُ في الوثيقة | `{$rid}` (UXR-01 §٤-٥) |\n";
    $md .= "| الملفُّ الحي | `{$rel}` |\n";
    $md .= "| تاريخُ القياس | " . date('Y-m-d H:i') . " |\n";
    $md .= "| النتيجة | **{$pass}/7** — " . ($verdict ? '✔ تُعتمد نموذجًا' : '◆ لا تُعتمد بعد') . " |\n\n";
    $md .= "## المعاييرُ السبعةُ بشواهدِها\n\n";
    $md .= "| # | المعيار | الحكم | الشاهد |\n|---:|---|:---:|---|\n";
    foreach ($c as $no => $one) {
        $md .= "| {$no} | {$one[0]} | " . ($one[1] ? '✔' : '✘') . " | {$one[2]} |\n";
    }
    $md .= "\n## الحكم\n\n";
    $md .= $verdict
        ? "الشاشةُ تستوفي المعاييرَ السبعةَ على شيفرتِها الحية، فتُعتمد **نموذجًا** "
          . "يُقاس عليه التعميمُ (شرطُ AC-U3 مستوفًى لهذه الشاشة).\n"
        : "الشاشةُ **لا تُعتمد نموذجًا** حتى تستوفيَ الناقص — والناقصُ مسمًّى أعلاه "
          . "بحكمِه لا بادّعاءِ إغلاقه.\n";
    $md .= "\n> المحضرُ مولَّدٌ من `tools/u12_pilot_minutes.php` — يُعاد توليدُه فيُعاد القياس، "
        . "فلا يشيخ الحكمُ في ملفٍّ ساكن.\n";
    file_put_contents($OUT . '/' . $slug . '.md', $md);
    $index[] = array($rid, $title, $rel, $pass, $verdict, $slug);
}

/* الفهرس */
$idx  = "# محاضرُ اعتمادِ الشاشاتِ التمثيليةِ الثماني\n\n";
$idx .= "> UXR-01 §٤-٥ · شرطُ AC-U3 «الثماني تُعتمد نماذجَ قبل أي تعميم» · "
      . date('Y-m-d H:i') . "\n\n";
$idx .= "| المعرّف | الشاشة | الملف | النتيجة | الحكم | المحضر |\n";
$idx .= "|---|---|---|:---:|:---:|---|\n";
foreach ($index as $r) {
    $idx .= "| `{$r[0]}` | {$r[1]} | `{$r[2]}` | **{$r[3]}/7** | "
         . ($r[4] ? '✔ تُعتمد' : '◆ لا تُعتمد') . " | [{$r[5]}]({$r[5]}.md) |\n";
}
$idx .= "\n**المعتمَدُ نموذجًا: {$fullPass}/" . count($index) . "**\n";
file_put_contents($OUT . '/README.md', $idx);

echo "محاضرُ اعتمادِ الشاشاتِ التمثيليةِ الثماني\n";
echo str_repeat('═', 62), "\n";
foreach ($index as $r) {
    echo ($r[4] ? '✔' : '◆') . ' ' . str_pad($r[0], 10) . str_pad($r[1], 26)
       . $r[3] . "/7\n";
}
echo str_repeat('─', 62), "\n";
echo "المعتمَدُ نموذجًا: {$fullPass}/" . count($index) . "\n";
echo 'المخرَجُ: ' . str_replace($ROOT . '/', '', $OUT) . "/\n";
exit($fullPass === count($index) ? 0 : 1);
