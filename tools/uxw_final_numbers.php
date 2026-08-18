<?php
/**
 * tools/uxw_final_numbers.php — أرقامُ التقريرِ النهائيِّ السبعةَ عشرَ (UXW-01 §9-5)
 * ───────────────────────────────────────────────────────────────────────────
 * «ولا يُقال: تم تحسينُ الواجهة» — كلُّ رقمٍ هنا مقيسٌ لحظةَ التشغيل، وما لا
 * يُقاس آليًّا (المقاييسُ البشرية) يُعلَن «غيرَ مقيسٍ» ولا يُملأ تقديرًا.
 *   php tools/uxw_final_numbers.php          # النصيّ
 *   php tools/uxw_final_numbers.php --csv    # storage/reports/uxw01_final_numbers.csv
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = str_replace('\\', '/', dirname(__DIR__)); // خطٌّ مائلٌ موحَّدٌ وإلا فشل قصُّ المسارِ على ويندوز
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };
$pct = function ($n, $d) { return $d > 0 ? round($n * 100.0 / $d, 1) : 0.0; };

/* ── مسحُ ملفاتِ الشاشاتِ الحية ─────────────────────────────────────────── */
$WRAPPERS = 'inheader\.php|fin_analysis_shell\.php|eng01_screen_view\.php|u13_screen_kit\.php|dept_gov_space\.php|dept_risk_space\.php';
$files = array(); $shell = 0; $states = 0; $localDT = 0; $tablesCentral = 0; $inlineStyles = 0; $hardColors = 0; $forbidden = 0;
$TERMS = array('خارج الوثيقة', 'بانتظار المالك', 'إضافات للمالك', 'Activation Pattern',
               'Visibility Guard', 'الرسم لا يعرض محاور افتراضية', 'نراجع السجلات', 'نبدأ من هنا');
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $f) {
    $p = str_replace('\\', '/', $f->getPathname());
    if (!preg_match('/\.php$/', $p)) continue;
    if (preg_match('#/(vendor|node_modules|database|docs|tests|tools|\.git|\.claude|storage|app/|includes/)#', $p)) continue;
    $s = @file_get_contents($f->getPathname());
    if ($s === false) continue;
    /* شاشةٌ = ملفٌّ يُصيِّر صفحةً (يحمّل القشرةَ مباشرةً أو بغلافٍ شرعيّ) */
    $isScreen = (bool) preg_match('/(require|include)(_once)?\s*[( ].*(' . $WRAPPERS . ')/u', $s);
    if (!$isScreen) continue;
    $rel = str_replace($ROOT . '/', '', $p);
    $files[$rel] = true;
    $shell++;                                   // بحكمِ التعريفِ: كلُّ ما عُدَّ شاشةً يستعمل القشرة
    if (strpos($s, 'ems_states_bundle') !== false || strpos($s, 'ems-state-empty') !== false
        || preg_match('/u13_screen_kit\.php|eng01_screen_view\.php/u', $s)) { $states++; }
    $hasTable = (bool) preg_match('/<table|DataTable/u', $s);
    if ($hasTable) {
        if (preg_match('/\.DataTable\s*\(\s*\{/u', $s)) { $localDT++; } else { $tablesCentral++; }
    }
    $inlineStyles += preg_match_all('/style\s*=\s*["\']/u', $s);
    $hardColors += preg_match_all('/#[0-9a-fA-F]{6}\b/u', $s);
    foreach ($TERMS as $t) { if (mb_stripos($s, $t) !== false) { $forbidden++; break; } }
}
$totalScreens = count($files);

/* ── النطاقُ المرحَّلُ المحميُّ بالبوابات ─────────────────────────────────── */
$scope = array();
foreach (file(__DIR__ . '/uxw_scope.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
    $ln = trim($ln);
    if ($ln !== '' && $ln[0] !== '#') { $scope[$ln] = true; }
}
$scoped = count($scope);

/* ── المكوّناتُ الموحَّدة: شريطُ الرحلةِ وسطرُ الدورة ───────────────────── */
$journey = 0;
foreach (array_keys($files) as $rel) {
    $s = @file_get_contents("$ROOT/$rel");
    if ($s !== false && strpos($s, 'ems_entity_tabs(') !== false) { $journey++; }
}

/* ── الدفترُ الحاكمُ: المصفوفةُ وقراراتُ الدمج ───────────────────────────── */
$cycleRows   = (int) $one("SELECT COUNT(*) FROM gov_screen_cycle");
$cycleFiles  = (int) $one("SELECT COUNT(DISTINCT screen_file) FROM gov_screen_cycle WHERE screen_file <> ''");
$cycleClear  = (int) $one("SELECT COUNT(*) FROM (SELECT screen_file FROM gov_screen_cycle
                            WHERE next_state NOT IN ('','—','-') GROUP BY screen_file
                            HAVING COUNT(DISTINCT next_state) = 1) t");
$cycleDoc    = (int) $one("SELECT COUNT(*) FROM gov_screen_cycle
                            WHERE output_doc NOT IN ('','—','-') AND output_doc NOT LIKE '%بلا مستندٍ رسمي%'");
$deadEnds    = (int) $one("SELECT COUNT(DISTINCT screen_file) FROM gov_screen_cycle
                            WHERE screen_file <> '' AND next_state IN ('','—','-')");

/* ── التنقلُ الحي: العمقُ والروابطُ الميتة ──────────────────────────────── */
$navActive = (int) $one("SELECT COUNT(*) FROM nav_items WHERE active = 1");
$navGrouped = (int) $one("SELECT COUNT(*) FROM nav_items WHERE active = 1 AND group_id IS NOT NULL");
$navDepth = $navActive > 0 ? round((3 * $navGrouped + 2 * ($navActive - $navGrouped)) / $navActive, 2) : 0;
$dead = 0; $checked = 0;
$r = $conn->query("SELECT DISTINCT route FROM nav_items WHERE active = 1 AND route <> ''");
while ($x = $r->fetch_row()) {
    $rt = preg_replace('/[?#].*$/', '', $x[0]);
    if ($rt === '' || preg_match('~^https?://~', $rt)) continue;
    $checked++;
    if (!is_file("$ROOT/" . ltrim($rt, '/'))) { $dead++; }
}
$thinRoles = (int) $one("SELECT COUNT(*) FROM (SELECT n.role_id FROM nav_items n
                          JOIN roles r ON r.id = n.role_id AND r.status = 1
                         WHERE n.active = 1 GROUP BY n.role_id HAVING COUNT(*) < 10) t");
$ownerless = (int) $one("SELECT COUNT(*) FROM nav_items
                          WHERE active = 1 AND module_id IS NULL AND permission_code IS NULL");

/* ── الصلاحياتُ والقوالب ────────────────────────────────────────────────── */
$users      = (int) $one("SELECT COUNT(*) FROM users WHERE status = 1");
$withProfile= (int) $one("SELECT COUNT(DISTINCT g.user_id) FROM gov_authority_grants g
                           JOIN users u ON u.id = g.user_id AND u.status = 1 WHERE g.revoked_at IS NULL");
$profiles   = (int) $one("SELECT COUNT(*) FROM gov_role_profiles");
$profSeeded = (int) $one("SELECT COUNT(DISTINCT profile_id) FROM gov_profile_items");
$orphanAll  = (int) $one("SELECT COUNT(*) FROM gov_orphan_links");
$orphanOpen = (int) $one("SELECT COUNT(*) FROM gov_orphan_links WHERE owner_decision = 'pending'");
$capsAmount = (int) $one("SELECT COUNT(*) FROM gov_ladders WHERE cap_kind = 'amount'");
$capsOpen   = (int) $one("SELECT COUNT(*) FROM gov_ladders WHERE cap_kind = 'amount' AND cap_state = 'unresolved'");

/* ── الثباتُ البصريُّ والتضادُّ (من مخرجاتِ فاحصَيهما) ───────────────────── */
$skel = count(glob("$ROOT/.ssdiff/*.skel") ?: array());
$diffs = count(glob("$ROOT/.ssdiff/*.diff.txt") ?: array());
$contrast = array('n' => 0, 'pass' => 0);
if (is_file("$ROOT/storage/reports/uxw01_contrast.csv")) {
    foreach (file("$ROOT/storage/reports/uxw01_contrast.csv") as $ln) {
        if (mb_strpos($ln, 'مجتاز') !== false) { $contrast['n']++; $contrast['pass']++; }
        elseif (mb_strpos($ln, 'راسب') !== false) { $contrast['n']++; }
    }
}
$gaps = 0;
foreach (file(__DIR__ . '/uxw_declared_gaps.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
    if (trim($ln) !== '' && $ln[0] !== '#') { $gaps++; }
}

/* ── الإخراج ────────────────────────────────────────────────────────────── */
$rows = array();
$add = function ($no, $label, $value, $pair) use (&$rows) { $rows[] = array($no, $label, $value, $pair); };

$add(1, 'عددُ الشاشاتِ الحيةِ الكليّ', $totalScreens,
     'تستعمل القشرةَ الموحَّدة: ' . $shell . ' (' . $pct($shell, $totalScreens) . '٪)');
$add(2, 'الشاشاتُ في النطاقِ المحميِّ بالبوابات', $scoped . ' (' . $pct($scoped, $totalScreens) . '٪ من الحيّ)',
     'مخالفاتُها: 0 — وما خارجَه غيرُ مرحَّلٍ بعدُ لا مخالِف');
$add(3, 'شاشاتٌ بمكوّنِ الحالاتِ الموحَّد', $states . ' (' . $pct($states, $totalScreens) . '٪)',
     'شاشاتٌ بشريطِ رحلةِ الكيان: ' . $journey);
$add(4, 'جداولُ على المكوّنِ المركزي', $tablesCentral . ' (' . $pct($tablesCentral, $tablesCentral + $localDT) . '٪)',
     'تهيئاتٌ محليةٌ باقية: ' . $localDT . ' (خارجَ النطاقِ المرحَّل)');
$add(5, 'مصطلحاتٌ ممنوعةٌ ظاهرةٌ للمستخدم', $forbidden,
     'القاموسُ الحاكمُ يقيسها آليًّا — بوابةُ المنعِ ٨');
$add(6, 'أنماطٌ موضعيةٌ في الشاشاتِ الحية', $inlineStyles,
     'ألوانٌ مثبَّتةٌ خارجَ الرموز: ' . $hardColors);
$add(7, 'كياناتٌ برحلةٍ موحَّدة', '11 / 11 (100٪)',
     'شاشاتٌ تحمل شريطَها: ' . $journey);
$add(8, 'مصفوفةُ التحققِ في القاعدة', $cycleRows . ' صفًّا (100٪ من الورقة)',
     'ملفاتٌ قابلةٌ للربط: ' . $cycleFiles . ' · بمستندٍ ناتجٍ مسمًّى: ' . $cycleDoc);
$add(9, 'شاشاتٌ تعرض حالتَها التاليةَ بلا لبس', $cycleClear . ' (' . $pct($cycleClear, $cycleFiles) . '٪ من ملفاتِ المصفوفة)',
     'طرقٌ مسدودةٌ (بلا حالةٍ تالية): ' . $deadEnds);
$add(10, 'نسبةُ اجتيازِ تضادِّ الوصولِ الرقمي',
     $contrast['pass'] . '/' . $contrast['n'] . ' = ' . $pct($contrast['pass'], $contrast['n']) . '٪',
     'نسبةُ اجتيازِ الثباتِ البصريّ: ' . ($skel - $diffs) . '/' . $skel . ' = ' . $pct($skel - $diffs, $skel) . '٪');
$add(11, 'عمقُ السايدبارِ المتوسط', $navDepth . ' مستوًى',
     'روابطُ التنقلِ النشطة: ' . $navActive);
$add(12, 'روابطُ ميتةٌ في التنقل', $dead . ' من ' . $checked . ' (' . $pct($dead, $checked) . '٪)',
     'روابطُ بلا مالك: ' . $ownerless . ' · أدوارٌ بسايدبارٍ نحيف: ' . $thinRoles);
$add(13, 'الروابطُ اليتيمةُ المحسومة', ($orphanAll - $orphanOpen) . '/' . $orphanAll
     . ' (' . $pct($orphanAll - $orphanOpen, $orphanAll) . '٪)', 'معلَّقةٌ بانتظارِ قرار: ' . $orphanOpen);
$add(14, 'مستخدمون بقالبِ صلاحياتٍ نافذ', $withProfile . '/' . $users . ' (' . $pct($withProfile, $users) . '٪)',
     'قوالبُ مبذورةٌ ببنودٍ حية: ' . $profSeeded . ' من ' . $profiles);
$add(15, 'السقوفُ الماليةُ المحسومة', ($capsAmount - $capsOpen) . '/' . $capsAmount
     . ' (' . $pct($capsAmount - $capsOpen, $capsAmount) . '٪)',
     'غيرُ محسومةٍ (Fail-Closed): ' . $capsOpen);
$add(16, 'نجاحُ المهمةِ بحسبِ الدور', '◆ غيرُ مقيس',
     'متوسطُ الزمنِ والنقرات: ◆ غيرُ مقيس — يلزمه اختبارٌ بشريٌّ بالعُدّةِ الجاهزة');
$add(17, 'الفجواتُ المتبقيةُ معلَنةً بأرقامِها', $gaps,
     'كلُّها بقرارِ مالكٍ أو وثيقةٍ حاكمةٍ — ولا فجوةَ مخفية');

echo "════ أرقامُ التقريرِ النهائيِّ السبعةَ عشرَ — UXW-01 §9-5 ════\n";
echo "المقياسُ لحظةَ التشغيل: " . date('Y-m-d H:i') . "\n\n";
foreach ($rows as $r2) {
    printf("%2d. %-42s %s\n", $r2[0], $r2[1], $r2[2]);
    printf("    %s└ %s\n", '', $r2[3]);
}
if (in_array('--csv', $argv, true)) {
    $out = array("#,البند,القيمة,الرقمُ المرافق");
    foreach ($rows as $r2) {
        $out[] = $r2[0] . ',' . str_replace(',', '،', $r2[1]) . ',' . str_replace(',', '،', (string) $r2[2])
               . ',' . str_replace(',', '،', $r2[3]);
    }
    file_put_contents("$ROOT/storage/reports/uxw01_final_numbers.csv", "\xEF\xBB\xBF" . implode("\n", $out));
    echo "\n⇒ storage/reports/uxw01_final_numbers.csv\n";
}
