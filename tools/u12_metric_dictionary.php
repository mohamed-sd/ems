<?php
/**
 * tools/u12_metric_dictionary.php — قاموسُ المقاييسِ السداسيُّ بتاريخِ قياسِه
 * ═══════════════════════════════════════════════════════════════════════════
 * لماذا: أرقامُ الحوزةِ تُتلى في تقاريرَ شتّى بمقاماتٍ مختلفةٍ فتتناقض ظاهريًّا
 * وهي صحيحةٌ كلُّها — «203 شاشة» و«354 شاشة» و«346» ليست خلافًا بل ستةُ مقاييسَ
 * لستةِ أشياءَ مختلفة. هذا القاموسُ يسمّي كلَّ مقياسٍ ويحدُّ مقامَه ويقيسه حيًّا،
 * فلا يُقارَن رقمٌ بغيرِ مقامِه بعد اليوم.
 *
 * المقاييسُ الستة:
 *   ① تعريفُ شاشة        — ما تعرّفه الوثيقةُ شاشةً (سجلٌّ في القاموس)
 *   ② ظهورُ تنقّل        — مواضعُ الظهورِ في القوائم (شاشةٌ قد تظهر مرارًا)
 *   ③ مثيلُ مسار         — وحداتُ الصلاحياتِ المسجَّلة (modules)
 *   ④ وحدةُ تبنٍّ        — ملفٌّ حيٌّ يُصيَّر للمستخدم (مقامُ G5-B)
 *   ⑤ وحدةُ عمودٍ حاكم   — الأعمدةُ الحاكمةُ المسجَّلة
 *   ⑥ تعريفُ فعل         — الأفعالُ في قاموسِ الأفعال
 *
 * التشغيل: php tools/u12_metric_dictionary.php [--md=مسار]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
$mdOut = null;
foreach ($argv as $a) { if (strpos($a, '--md=') === 0) { $mdOut = substr($a, 5); } }

$cfg = array('host' => 'localhost', 'port' => 3307, 'user' => 'root', 'pass' => '', 'db' => 'equipation_manage');
$envF = $ROOT . '/.env';
if (is_file($envF)) {
    foreach (file($envF, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
        if ($ln === '' || $ln[0] === '#' || strpos($ln, '=') === false) { continue; }
        list($k, $v) = explode('=', $ln, 2);
        $k = trim($k); $v = trim($v);
        if ($k === 'DB_HOST') { $hp = explode(':', $v); $cfg['host'] = $hp[0]; if (isset($hp[1])) { $cfg['port'] = (int) $hp[1]; } }
        if ($k === 'DB_USER') { $cfg['user'] = $v; }
        if ($k === 'DB_PASS') { $cfg['pass'] = $v; }
        if ($k === 'DB_NAME') { $cfg['db']   = $v; }
    }
}
$db = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['db'], $cfg['port']);
if ($db->connect_errno) { exit('تعذّر الاتصال: ' . $db->connect_error . "\n"); }
$db->set_charset('utf8mb4');

function n($db, $sql) { $r = @$db->query($sql); if (!$r) { return null; } $x = $r->fetch_row(); return $x ? (int) $x[0] : 0; }

/* ④ وحدةُ التبنّي — تُقاس من القرص لا من القاعدة */
$dirs = array('Approvals','Contracts','Employees','Equipments','Finance','FinRequests','Financing',
    'Fleet','Governance','Maintenance','movement','Operations','Opportunities','Oprators','Portal',
    'Procurement','Projects','Reports','Risk','Settings','Suppliers','Tickets','Timesheet',
    'Transport','Workforce','main','admin','company','ActivityLogs','Clients','emsreports');
$live = 0;
foreach ($dirs as $d) {
    foreach (glob($ROOT . '/' . $d . '/*.php') as $f) {
        if (strpos((string) file_get_contents($f), 'insidebar') !== false) { $live++; }
    }
}

/* ⑤ الأعمدةُ الحاكمةُ — سجلٌّ في الشيفرةِ يُقرأ بلا تحميلِ بيئةِ الويب */
$govCols = 0;
$govSrc = (string) @file_get_contents($ROOT . '/includes/gov_columns.php');
if (preg_match('~function\s+ems_gov_registry\s*\(\s*\)\s*\{(.*?)\n\}~su', $govSrc, $gm)) {
    $govCols = preg_match_all("~^\s*'[a-z0-9_]+'\s*=>\s*array\(~mi", $gm[1]);
}

$M = array(
    array('①', 'تعريفُ شاشة', 'nav09_file_map',
        n($db, "SELECT COUNT(*) FROM nav09_file_map"),
        'صفٌّ لكلِّ ملفٍّ قانونيٍّ في خريطةِ الملفات — بحالاتِه (حيّ · مُحال · قريبًا)',
        'يُقاس عليه اكتمالُ التغطيةِ المستنديّة'),
    array('②', 'ظهورُ تنقّل', 'nav_items (active = 1)',
        n($db, "SELECT COUNT(*) FROM nav_items WHERE active = 1"),
        'موضعُ ظهورٍ في القوائم — والشاشةُ الواحدةُ قد تظهر في أكثرَ من موضع',
        'لا يصلح مقامًا للتبنّي: يعدُّ الظهورَ لا الشاشة'),
    array('③', 'مثيلُ مسار', 'modules',
        n($db, "SELECT COUNT(*) FROM modules"),
        'وحدةُ صلاحياتٍ مسجَّلةٌ برمزِ مسارِها — بابُ المنعِ والسماح',
        'مقامُ «صفر وجهة بلا وحدة صلاحيات»'),
    array('④', 'وحدةُ تبنٍّ', 'القرصُ الحي (يستدعي insidebar)',
        $live,
        'ملفٌّ حيٌّ يُصيَّر للمستخدمِ فعلًا — وهو المقامُ الوحيدُ لبواباتِ الواجهة',
        'مقامُ G5-B وG5-C وسجلاتِ الدَّينِ الستة'),
    /* السجلُّ الحاكمُ للأعمدةِ مصدرُه شيفرةٌ لا جدول: includes/gov_columns.php
       ⇐ ems_gov_registry(). وعدُّه من جدولِ صفوفِ العرضِ خطأُ مقامٍ صريح. */
    array('⑤', 'وحدةُ عمودٍ حاكم', 'includes/gov_columns.php ⇐ ems_gov_registry()',
        $govCols,
        'عمودٌ حاكمٌ معرَّفٌ بتسميتِه ودرجتِه وسببِ حكمِه — أساسُ «صفر شاشة بلا عمود حاكم»',
        'لا يُقارَن بعددِ الشاشات: وحداتُه أعمدةٌ لا شاشات'),
    array('⑥', 'تعريفُ فعل', 'nav09_action_map',
        n($db, "SELECT COUNT(*) FROM nav09_action_map"),
        'فعلٌ بعقدِه السداسيِّ وأعمدةِ عمقِه ⑤⑩⑫',
        'مقامُ أحكامِ الحارسِ والعطالةِ والاختبار'),
);

/* أعدادٌ مساندةٌ تُذكر مع القاموسِ لأنها تُخلط به كثيرًا */
$aux = array(
    array('الأفعالُ المربوطةُ بصفحةٍ حية', n($db, "SELECT COUNT(*) FROM nav09_action_map WHERE state = 'bound_page'")),
    array('الأفعالُ ذاتُ حارسٍ مقيسٍ ⑤ = yes', n($db, "SELECT COUNT(*) FROM nav09_action_map WHERE guard_verified = 'yes'")),
    array('الأفعالُ ذاتُ عطالةٍ مقيسةٍ ⑩ = yes', n($db, "SELECT COUNT(*) FROM nav09_action_map WHERE idempotency_verified = 'yes'")),
    array('الحساباتُ القانونيةُ في دليلِ الحسابات', n($db, "SELECT COUNT(*) FROM fin_chart_of_accounts WHERE is_canonical = 1")),
    array('الجداولُ في القاعدةِ الحية', n($db, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()")),
);

$when = date('Y-m-d H:i');
echo "قاموسُ المقاييسِ السداسيُّ — كلُّ رقمٍ بمقامِه\n";
echo str_repeat('═', 78), "\n";
echo "تاريخُ القياس: {$when}  ·  القاعدة: {$cfg['db']}:{$cfg['port']}\n\n";
foreach ($M as $r) {
    echo $r[0] . ' ' . str_pad($r[1], 22) . str_pad((string) ($r[3] === null ? '—' : $r[3]), 8)
       . 'المصدر: ' . $r[2] . "\n";
    echo '   ' . $r[4] . "\n";
    echo '   ◆ ' . $r[5] . "\n\n";
}
echo str_repeat('─', 78), "\n";
echo "أعدادٌ مساندةٌ تُخلط بالقاموسِ كثيرًا:\n";
foreach ($aux as $a2) { echo '  · ' . str_pad($a2[0], 42) . ($a2[1] === null ? '—' : $a2[1]) . "\n"; }

if ($mdOut !== null) {
    $md  = "# قاموسُ المقاييسِ السداسيُّ — كلُّ رقمٍ بمقامِه\n\n";
    $md .= "> تاريخُ القياس: **{$when}** · القاعدةُ الحية `{$cfg['db']}` على المنفذ `{$cfg['port']}`\n>\n";
    $md .= "> ستةُ أرقامٍ تصف ستةَ أشياءَ مختلفة. تناقضُها الظاهريُّ خلطُ مقاماتٍ لا خطأَ قياس — "
        . "ولا يُقارَن رقمٌ بغيرِ مقامِه.\n\n";
    $md .= "| # | المقياس | العدد | المصدرُ الحي | ما يعنيه | حدُّ استعماله |\n";
    $md .= "|:--:|---|---:|---|---|---|\n";
    foreach ($M as $r) {
        $md .= '| ' . $r[0] . ' | **' . $r[1] . '** | **' . ($r[3] === null ? '—' : $r[3]) . '** | `'
            . $r[2] . '` | ' . $r[4] . ' | ' . $r[5] . " |\n";
    }
    $md .= "\n## أعدادٌ مساندةٌ تُخلط بالقاموس\n\n| البند | العدد |\n|---|---:|\n";
    foreach ($aux as $a2) { $md .= '| ' . $a2[0] . ' | ' . ($a2[1] === null ? '—' : $a2[1]) . " |\n"; }
    $md .= "\n## قاعدةُ الاستعمال\n\n";
    $md .= "1. **بواباتُ الواجهةِ كلُّها** (G5-A/B/C · سجلاتُ الدَّينِ الستة · محاضرُ التدقيق) "
        . "مقامُها ④ **وحدةُ التبنّي** — الملفُّ الحيُّ الذي يُصيَّر. لا ① ولا ②.\n";
    $md .= "2. **أحكامُ الصلاحيات** مقامُها ③ — وحدةُ الصلاحياتِ المسجَّلة.\n";
    $md .= "3. **أحكامُ الأفعالِ** (الحارسُ ⑤ · العطالةُ ⑩ · الاختبارُ ⑫) مقامُها ⑥.\n";
    $md .= "4. **② ظهورُ التنقّل** لا يصلح مقامًا لشيء: يعدُّ مواضعَ الظهورِ لا الأشياءَ الظاهرة.\n";
    $md .= "5. كلُّ رقمٍ يُنشر يُذكر معه **مقياسُه وتاريخُ قياسِه** — والرقمُ بلا مقامٍ لا يُقبل.\n";
    @mkdir(dirname($mdOut), 0777, true);
    file_put_contents($mdOut, $md);
    echo "\nالمخرَجُ: " . $mdOut . "\n";
}
exit(0);
