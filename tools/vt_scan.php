<?php
/**
 * tools/vt_scan.php — أدواتُ القبولِ VT-01..VT-10 (UXR-01 v1 §٤-٥)
 * ───────────────────────────────────────────────────────────────────────────
 * «عشرةُ معاييرِ قبولٍ بلا أداةِ فحص — فالبوابةُ تقيس ستةً وتبلّغ ستةَ عشرَ»
 * (UXR-0152). هذا الماسحُ يبني السبعةَ الساكنةَ ويقيسها حيًّا:
 *   VT-01 سماتُ النمطِ الموضعية · VT-02 الألوانُ الصلبة · VT-03 أحجامُ الخط
 *   والمسافاتُ خارج السلّم · VT-04 استدعاءُ المكوناتِ لكل شاشة (بوابةُ التبنّي
 *   G5-B الحقيقية) · VT-05 حارسُ الرسوم (إنفاذُ L4 لـUI-DEF-07) ·
 *   VT-06 بطاقةُ المؤشر السباعية · VT-07 استدعاءاتُ التاريخ المتفرقة.
 * والثلاثةُ المتصفحيةُ (VT-08 RTL لقطات · VT-09 لوحة مفاتيح · VT-10 مقارنة
 * بصرية) تُعلَن دَينًا تشغيليًّا لا يُغلق على بيئة تطوير — بنص الورقة 28.
 *
 * الخروج: 0 إذا اجتاز شرطُ الإنفاذ الوحيدُ لهذه الجولة (VT-05: صفرُ رسمٍ بلا
 * حارسٍ في صفحة الهبوط والشاشات المصابة) — والبقيةُ عدّاداتُ دَينٍ تُعلَن
 * بأرقامها ولا تُخفى (Adoption Debt — ارتفاعُ صدقِها أهمُّ من خضرتها).
 *
 * التشغيل: php tools/vt_scan.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);

$ROOT = dirname(__DIR__);
$dirs = array('Approvals', 'Contracts', 'Employees', 'Equipments', 'Finance', 'FinRequests', 'Financing',
    'Fleet', 'Governance', 'Maintenance', 'movement', 'Operations', 'Opportunities', 'Oprators', 'Portal',
    'Procurement', 'Projects', 'Reports', 'Risk', 'Settings', 'Suppliers', 'Tickets', 'Timesheet',
    'Transport', 'Workforce', 'main', 'admin', 'company', 'ActivityLogs', 'Clients', 'emsreports');
$files = array();
foreach ($dirs as $d) {
    foreach (glob($ROOT . '/' . $d . '/*.php') as $f) { $files[] = $f; }
}
$today = date('Y-m-d');
$L = array();
$L[] = '# مسح أدوات القبول VT — update0012 (' . $today . ')';
$L[] = '';
$L[] = 'كل رقم مقيس من المصدر الحي لحظة التشغيل بمنهجه المعلن — ويعاد مع كل جولة.';
$L[] = '';

/* ═══ VT-01 · سماتُ النمط الموضعية style="..." ═══════════════════════════ */
$vt01 = 0; $vt01Files = 0;
foreach ($files as $f) {
    $src = (string) file_get_contents($f);
    $n = preg_match_all('/\sstyle\s*=\s*["\']/', $src);
    if ($n > 0) { $vt01 += $n; $vt01Files++; }
}
$L[] = "## VT-01 · ماسح الأنماط الموضعية";
$L[] = "- **{$vt01}** سمةَ نمطٍ داخل الوسوم في **{$vt01Files}** ملفًّا (المنهج: عدُّ style= في PHP الشاشات).";
$L[] = '- الحكم: دَينٌ بصريٌّ يُخفَّض بالترحيل (P4) — والعدُّ يُقارَن جولةً بجولة.';
$L[] = '';

/* ═══ VT-02 · الألوانُ الصلبة خارج الرموز ════════════════════════════════ */
$vt02 = 0; $vt02Files = 0;
foreach ($files as $f) {
    $src = (string) file_get_contents($f);
    $n = preg_match_all('/#[0-9a-fA-F]{3,8}\b|rgba?\(/', $src);
    if ($n > 0) { $vt02 += $n; $vt02Files++; }
}
$L[] = "## VT-02 · ماسح الألوان الصلبة";
$L[] = "- **{$vt02}** قيمةً لونيةً حرفيةً في **{$vt02Files}** ملفًّا (hex/rgb خارج رموز الطبقات الثلاث).";
$L[] = '';

/* ═══ VT-03 · أحجامُ الخط والمسافات خارج السلّم ══════════════════════════ */
$allowedSizes = array('10', '11', '12', '13', '14', '16', '18', '20', '24', '32', '40'); // الأحد عشر
$vt03 = 0;
foreach ($files as $f) {
    $src = (string) file_get_contents($f);
    if (preg_match_all('/font-size:\s*(\d+(?:\.\d+)?)px/', $src, $mm)) {
        foreach ($mm[1] as $sz) {
            if (!in_array((string) (int) $sz, $allowedSizes, true)) { $vt03++; }
        }
    }
    if (preg_match_all('/(?:padding|margin|gap):\s*(\d+)px/', $src, $mm2)) {
        foreach ($mm2[1] as $sp) {
            if (((int) $sp) % 4 !== 0) { $vt03++; }
        }
    }
}
$L[] = "## VT-03 · ماسح أحجام الخط والمسافات (أداة جديدة — كانت «لا أداة»)";
$L[] = "- **{$vt03}** قيمةً خارج السلّم (حجمٌ خارج الأحد عشر أو مسافةٌ ليست مضاعفَ 4).";
$L[] = '';

/* ═══ VT-04 · فاحصُ استدعاء المكونات — بوابةُ التبنّي G5-B الحقيقية ═══════ */
$components = array('EmsUI.', 'ems_shell_axes', 'EmsDetailsModal', 'dept_risk_space.php', 'dept_gov_space.php');
$adopt = 0; $adoptFiles = array(); $total = 0;
foreach ($files as $f) {
    $src = (string) file_get_contents($f);
    if (strpos($src, 'insidebar') === false && strpos(basename($f), '_actions') === false) { continue; }
    $total++;
    foreach ($components as $c) {
        if (strpos($src, $c) !== false) { $adopt++; $adoptFiles[] = substr($f, strlen($ROOT) + 1); break; }
    }
}
$pct = $total > 0 ? round($adopt / $total * 100, 1) : 0;
$L[] = "## VT-04 · فاحص استدعاء المكونات (G5-B — بوابةُ التبنّي الحقيقية)";
$L[] = "- **{$adopt}/{$total}** شاشةً حيةً (بمظلة السايدبار) تستدعي مكوّنًا معتمدًا = **{$pct}٪** — بالمقام الكامل لا المستبعِد (MD-01 مُغلق: صفرُ مجلدٍ مستبعَد).";
$L[] = '- G5-A البنيوية (وجود المكونات): تقيسها tools/uxr_visual_gate.php — **ولا تُقرأ نجاحًا لهذه**.';
$L[] = '- G5-C التدقيق البصري: **0/' . $total . '** — تحتاج بصرَ إنسانٍ أمام شاشة (بشرية).';
$L[] = '';

/* ═══ VT-05 · فاحصُ حارس الرسوم — إنفاذُ L4 ═══════════════════════════════ */
$unguarded = array();
foreach ($files as $f) {
    $src = (string) file_get_contents($f);
    if (strpos($src, 'new Chart') === false) { continue; }
    $offset = 0;
    while (($pos = strpos($src, 'new Chart', $offset)) !== false) {
        $before = substr($src, max(0, $pos - 600), 600);
        if (strpos($before, 'chartGuard') === false && strpos($before, 'ChartGuard') === false
            && strpos($before, 'emsChartGuard') === false) {
            $unguarded[] = substr($f, strlen($ROOT) + 1) . ':' . (substr_count(substr($src, 0, $pos), "\n") + 1);
        }
        $offset = $pos + 9;
    }
}
$L[] = "## VT-05 · فاحص حارس الرسوم (إنفاذ L4 لـUI-DEF-07)";
if (empty($unguarded)) {
    $L[] = '- ✔ **صفرُ استدعاءِ رسمٍ بلا حارسٍ في الكود كلِّه** — الإغلاقُ المستدام L4.';
    $L[] = '- الشاشةُ المصابةُ المرصودةُ نفسُها (main/dashboard.php — «مسار الأداء اليومي») ملفوفةٌ بالحارس (L3 ✔).';
} else {
    $L[] = '- ✘ استدعاءاتٌ بلا حارس (' . count($unguarded) . '): ' . implode(' · ', $unguarded);
}
$L[] = '';

/* ═══ VT-06 · فاحصُ بطاقة المؤشر السباعية ═════════════════════════════════ */
$kpiCalls = 0; $rawKpi = 0;
foreach ($files as $f) {
    $src = (string) file_get_contents($f);
    $kpiCalls += substr_count($src, 'EmsUI.kpiCard');
    $rawKpi += preg_match_all('/class\s*=\s*["\'][^"\']*kpi[^"\']*["\']/i', $src);
}
$L[] = "## VT-06 · فاحص بطاقة المؤشر (كان جزئيًّا — عُمّم)";
$L[] = "- استدعاءاتُ البطاقة السباعية (ترفض الناقص بعقدها): **{$kpiCalls}** · بطاقاتٌ خامٌ بأصناف kpi يدوية: **{$rawKpi}** (دَينُ هجرةٍ إلى المكوّن).";
$L[] = '';

/* ═══ VT-07 · استدعاءاتُ التاريخ المتفرقة ════════════════════════════════ */
$vt07 = 0;
foreach ($files as $f) {
    $src = (string) file_get_contents($f);
    $vt07 += preg_match_all('/\b(?:date\s*\(\s*[\'"](?:Y-m-d|d\/m\/Y|Y\/m\/d)|->format\s*\(|toLocaleDateString)/', $src);
}
$L[] = "## VT-07 · فاحص التاريخ والأرقام";
$L[] = "- **{$vt07}** استدعاءَ تنسيقِ تاريخٍ متفرقًا خارج مُنسِّقٍ موحَّد — دَينٌ يُخفَّض بالترحيل.";
$L[] = '';

/* ═══ VT-08/09/10 · المتصفحية — دَينٌ تشغيليٌّ معلَن ═════════════════════ */
$L[] = '## VT-08 · VT-09 · VT-10 — المتصفحية (لقطات RTL · لوحة المفاتيح · المقارنة البصرية)';
$L[] = '- ◆ تحتاج بيئةَ متصفحٍ حقيقيةً (لقطات مرجعية لكل مكوّن في الاتجاهين والحالات · دورة كاملة بلا فأرة).';
$L[] = '- **دَينٌ تشغيليٌّ معلَنٌ بحدِّ بيئته — لا يُغلق على بيئة تطوير (الورقة 28 نصًّا)** ولا يُدَّعى قياسُه.';
$L[] = '';

$L[] = '---';
$L[] = '';
$L[] = '| الأداة | القيمة المقيسة اليوم | الحال |';
$L[] = '|---|---|---|';
$L[] = "| VT-01 أنماط موضعية | {$vt01} في {$vt01Files} ملفًّا | دَين يُخفَّض |";
$L[] = "| VT-02 ألوان صلبة | {$vt02} في {$vt02Files} ملفًّا | دَين يُخفَّض |";
$L[] = "| VT-03 خارج السلّم | {$vt03} | أداةٌ جديدة — دَين |";
$L[] = "| VT-04 تبنّي المكونات (G5-B) | {$adopt}/{$total} = {$pct}٪ | العدّادُ الحقيقي بمقامه الكامل |";
$L[] = '| VT-05 حارس الرسوم | ' . (empty($unguarded) ? 'صفر بلا حارس ✔ (L4)' : count($unguarded) . ' بلا حارس ✘') . ' | شرطُ الإنفاذ |';
$L[] = "| VT-06 بطاقة سباعية | {$kpiCalls} مكوّنًا · {$rawKpi} خامًا | دَينُ هجرة |";
$L[] = "| VT-07 تواريخ متفرقة | {$vt07} | دَين يُخفَّض |";
$L[] = '| VT-08/09/10 متصفحية | — | دَينٌ تشغيليٌّ معلَن |';
$L[] = '';

@mkdir($ROOT . '/docs/update0012', 0777, true);
file_put_contents($ROOT . '/docs/update0012/VT_SCAN_ar.md', implode("\n", $L));
echo implode("\n", $L), "\n";
echo str_repeat('═', 60), "\n";
echo empty($unguarded)
    ? "VT-05 الإنفاذ: صفر رسم بلا حارس — L4 قائم ✔ (والدُّيونُ معلنة بأرقامها)\n"
    : 'VT-05 الإنفاذ: ' . count($unguarded) . " رسمًا بلا حارس ✘\n";
exit(empty($unguarded) ? 0 : 1);
