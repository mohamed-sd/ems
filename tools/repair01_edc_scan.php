<?php
/**
 * tools/repair01_edc_scan.php — جردُ دَينِ المؤسّسةِ قبل إغلاقِه
 * ═══════════════════════════════════════════════════════════════════════════
 * **أمرُ المالك 2026-08-26 · البند 50**: `Enterprise Debt Closure` تُغلق فيها
 * خمسةَ عشرَ بندًا سمّاها بأسمائها. **وأمرُه الثاني · القراران ④ و⑤** يضيفان
 * عمليّتَين معتمَدتَين: `Canonical Name Reconciliation` و
 * `Legacy Surface Forensic Review`.
 *
 * ◆ **والجردُ قبل الإغلاقِ قاعدةُ الحملةِ نفسِها** — فمرحلةٌ تبدأ بالإصلاحِ قبل
 *   القياسِ تُغلق ما تراه وتترك ما لا تراه، **ثمَّ تُعلن صفرًا من مقامٍ مجهول**.
 *
 * ◆ **ولكلِّ بندٍ مقامُه هو**: لا يُجمع «اسمٌ ناقص» مع «سطحٌ يتيم» في نسبةٍ
 *   واحدة ⛔ فنسبةٌ مجمَّعةٌ تُخفي أيَّ بندٍ لم يتحرّك.
 *
 * التشغيل: php tools/repair01_edc_scan.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$n = function ($sql) use ($conn) { $r = @$conn->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; };

$LIVE = "on_disk = 1 AND ownership_verdict NOT IN ('RETIRE')";
$rows = array(); $tot = 0;
$item = function ($no, $title, $count, $note = '') use (&$rows, &$tot) {
    $rows[] = array($no, $title, $count, $note);
    if ($count > 0) { $tot += $count; }
};

echo "\n═══ جردُ دَينِ المؤسّسة — أمرُ المالك · البند 50 ═══\n";
echo "     ◆ لكلِّ بندٍ مقامُه ⛔ ولا نسبةَ واحدةً مجمَّعة\n\n";

/* ── بنودُ البند 50 بأسمائها ─────────────────────────────────────────────── */
$item('1',  'معرّفٌ معياريٌّ ناقص',
    $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE COALESCE(screen_id,'') = '' AND $LIVE"));
$item('2',  'مسمًّى عربيٌّ معياريٌّ ناقص',
    $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE COALESCE(canonical_label_ar,'') = '' AND $LIVE"));
$item('3',  'حارسُ عرضٍ ناقص',
    $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE COALESCE(guard_kind,'') = '' AND $LIVE"));
$item('4',  'سياسةُ صلاحيةٍ ناقصة',
    $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE COALESCE(permission_policy,'') = '' AND $LIVE"));
$item('5',  'مالكٌ مجهول',
    $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE COALESCE(owner_code,'') = '' AND $LIVE"));
$item('6',  'مصدرٌ مكرَّرٌ لحقيقةٍ واحدة',
    $n("SELECT COUNT(*) FROM (SELECT LOWER(screen_file) f FROM repair01_screen_registry
         WHERE surface_kind = 'SOURCE' GROUP BY f HAVING COUNT(DISTINCT owner_code) > 1) z"));
$item('7',  'شاشةٌ يتيمةٌ بلا حكمِ ملكيّة',
    $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE COALESCE(ownership_verdict,'') = '' AND $LIVE"));
$item('8',  'موروثُ الماليةِ والخزينة',
    $n("SELECT COUNT(*) FROM repair01_screen_registry
         WHERE owner_code IN ('DEP-05','DEP-06') AND finance_debt_class = 'LEGACY_READ_ONLY'"),
    'مُصنَّفٌ ومُسيَّجٌ سلفًا');
$item('9',  'تكرارُ الخزينةِ — سطحانِ لمعنًى واحد',
    $n("SELECT COUNT(*) FROM (SELECT canonical_label_ar l FROM repair01_screen_registry
         WHERE owner_code IN ('DEP-05','DEP-06') AND canonical_label_ar <> '' AND $LIVE
         GROUP BY l HAVING COUNT(*) > 1) z"));
/* ⚠ **التداخلُ يُقاس بما لم يُحسَم لا بما حُسم**: نسختي الأولى عدَّت
     **الإسقاطاتِ المُعلَنةَ** دَينًا — وهي **الحلُّ** الذي كتبَته W13.5، فكان
     العدّادُ يرتفع كلَّما أُصلح شيء. **ومقياسٌ يعدُّ العلاجَ مرضًا يقلب اتّجاهَ
     التقدّم.** فالدَّينُ هنا: سطحٌ **بلا تصنيفٍ** في زوجٍ متقاطع، أو سطحٌ
     `SOURCE` عند غيرِ مالكِ حقيقتِه. */
$pair = function ($a, $b, $ownerOfTruth) use ($n, $LIVE) {
    return $n("SELECT COUNT(*) FROM repair01_screen_registry
                WHERE owner_code IN ('$a','$b') AND $LIVE
                  AND (surface_kind = ''
                       OR (surface_kind = 'SOURCE' AND owner_code <> '$ownerOfTruth'
                           AND LOWER(screen_file) IN (SELECT f FROM (
                               SELECT LOWER(screen_file) f FROM repair01_screen_registry
                                WHERE owner_code = '$ownerOfTruth' AND surface_kind = 'SOURCE') z)))");
};
$item('10', 'تداخلُ الموارد والقوى — غيرُ محسوم', $pair('DEP-07', 'DEP-13', 'DEP-07'));
$item('11', 'تداخلُ المشترياتِ والمخازن — غيرُ محسوم', $pair('DEP-16', 'DEP-17', 'DEP-17'));
$item('12', 'تداخلُ التمويلِ والخزينة — غيرُ محسوم', $pair('DEP-03', 'DEP-06', 'DEP-06'));
$item('13', 'تبويباتٌ لم تُدمَج في أبيها بعد',
    $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE ownership_verdict = 'TAB_CHILD' AND on_disk = 1"));
$item('14', 'أشباحٌ بلا قرارٍ من الستّة',
    $n("SELECT COUNT(*) FROM repair01_target_gaps WHERE ghost_disposition = ''"));
$item('15', 'مسارٌ متقاعدٌ ما زال مسجَّلًا حيًّا',
    $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE ownership_verdict = 'RETIRE' AND on_disk = 1"));

/* ── العمليّتانِ المعتمَدتانِ في القرارَين ④ و⑤ ────────────────────────────── */
$item('④', 'اسمٌ معروضٌ غيرُ معتمَد',
    $n("SELECT COUNT(*) FROM nav_canonical WHERE status <> 'APPROVED'"),
    'Canonical Name Reconciliation');
$item('⑤', 'سطحٌ موروثٌ ينتظر التحليلَ الجنائيّ',
    $n("SELECT COUNT(*) FROM repair01_screen_registry
         WHERE ownership_verdict = 'LEGACY' AND on_disk = 1"),
    'Legacy Surface Forensic Review');

/* ── وما رصدَته الحملةُ ولم يسمِّه الأمرُ صراحةً ──────────────────────────── */
$ROOT2 = $ROOT;
$idx = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT2, FilesystemIterator::SKIP_DOTS));
foreach ($it as $p) {
    if (substr($p->getFilename(), -4) !== '.php') { continue; }
    $s = strtr($p->getPathname(), DIRECTORY_SEPARATOR, '/');
    if (strpos($s, '/.git/') !== false) { continue; }
    if (!isset($idx[$p->getFilename()])) { $idx[$p->getFilename()] = $s; }
}
$noFilter = 0; $growth = 0;
$r = $conn->query("SELECT screen_file FROM repair01_screen_registry
                    WHERE origin REGEXP '^W[0-9]+$' AND on_disk = 1");
while ($r && ($x = $r->fetch_row())) {
    $b = basename($x[0]);
    if (!isset($idx[$b])) { continue; }
    $growth++;
    if (strpos(file_get_contents($idx[$b]), 'ems-filters') === false) { $noFilter++; }
}
$item('◆', 'سطحُ نموٍّ بلا صندوقِ فلترة', $noFilter, "من $growth · رصدُ الحملةِ لا نصُّ الأمر");

/* ── الطباعة ─────────────────────────────────────────────────────────────── */
foreach ($rows as $x) {
    printf("  %s %-3s %-42s %6s  %s\n",
        ($x[2] === 0 ? '✔' : ($x[2] < 0 ? '⚠' : '◆')), $x[0], $x[1], $x[2], $x[3]);
}
echo "\n────────────────────────────────────────────────────────────────────────\n";
$open = 0;
foreach ($rows as $x) { if ($x[2] > 0) { $open++; } }
printf("بنودٌ مُغلَقةٌ سلفًا: %d من %d · **باقٍ عملٌ في %d بندًا · مجموعُ الصفوف %d**\n",
    count($rows) - $open, count($rows), $open, $tot);
echo "⛔ ولا يُعلَن `Owner Approved Baseline` قبلَها — البند 58.\n";
