<?php
/**
 * tools/repair01_w0_gate.php — بوّابةُ المرحلةِ صفر
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **تُعيد القياسَ ولا تقرأ ما خزّنَته**: عدُّ الأشباحِ يُشتقُّ بمسحٍ عوديٍّ
 *   حيٍّ للقرصِ لا من العمودِ `on_disk`، ومطابقةُ الملفّاتِ بإعادةِ التجزئة.
 *   البوّابةُ التي تقرأ مخرَجَ الأداةِ التي تفحصها حشوٌ لا فحص.
 * ◆ **والرسوُّ على البنيةِ لا على العبارة**: لا يُفحص نصٌّ عربيٌّ حرٌّ قد
 *   تطابقه رسالةُ خطأ — بل مقاماتٌ وأعدادٌ ومفاتيح.
 *
 * التشغيل: php tools/repair01_w0_gate.php
 * الخروج : 0 كلُّها خضراء · 1 سقطت واحدةٌ فأكثر
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
$DIR = $ROOT . '/docs/REPAIR01_20260823/';

$pass = 0; $fail = 0; $lines = array();
function gate(&$pass, &$fail, &$lines, $id, $title, $ok, $measured, $expected) {
    if ($ok) { $pass++; $lines[] = sprintf("  ✔ %-8s %-38s %s", $id, $title, $measured); }
    else     { $fail++; $lines[] = sprintf("  ✘ %-8s %-38s المقيس: %s  ·  المتوقَّع: %s", $id, $title, $measured, $expected); }
}
function one($conn, $sql) { $r = $conn->query($sql); if (!$r) { return null; } $x = $r->fetch_row(); return $x ? $x[0] : null; }

/* ── G0-01 تجميدُ المصدر: إعادةُ تجزئةٍ حيّة ── */
$srcOk = 0; $srcBad = 0; $srcMissing = 0;
$onDiskFiles = array_merge(glob($DIR . '*.xlsx'), glob($DIR . '*.docx'));
$rows = array();
$r = $conn->query("SELECT file_name, sha256 FROM repair01_source_files");
while ($x = $r->fetch_assoc()) { $rows[$x['file_name']] = $x['sha256']; }
foreach ($onDiskFiles as $f) {
    $bn = basename($f);
    if (!isset($rows[$bn])) { $srcMissing++; continue; }
    if (hash_file('sha256', $f) === $rows[$bn]) { $srcOk++; } else { $srcBad++; }
}
gate($pass, $fail, $lines, 'G0-01', 'تجميدُ المصدر (تجزئةٌ مُعادة)',
    ($srcOk === 13 && $srcBad === 0 && $srcMissing === 0),
    "مطابق $srcOk · مُبدَّل $srcBad · غيرُ مسجَّل $srcMissing", '13 · 0 · 0');

/* ── G0-02 القرارات: المقامُ والتوزيع ── */
$dTot = (int) one($conn, "SELECT COUNT(*) FROM repair01_decisions");
$dApr = (int) one($conn, "SELECT COUNT(*) FROM repair01_decisions WHERE status='APPROVED'");
$dNeed = (int) one($conn, "SELECT COUNT(*) FROM repair01_decisions WHERE status='NEEDS_OWNER_DECISION'");
gate($pass, $fail, $lines, 'G0-02', 'مقامُ القرارات',
    ($dTot === 108 && $dApr === 92 && $dNeed === 16),
    "$dTot = معتمد $dApr + منتظر $dNeed", '108 = 92 + 16');

/* ── G0-03 اتّساقُ الحجب: لا معتمدٌ حاجب، ولا منتظرٌ بلا تصنيف ── */
$badA = (int) one($conn, "SELECT COUNT(*) FROM repair01_decisions WHERE status='APPROVED' AND blocking_level<>'NONE'");
$badN = (int) one($conn, "SELECT COUNT(*) FROM repair01_decisions WHERE status='NEEDS_OWNER_DECISION' AND blocking_level='NONE'");
$unrev = (int) one($conn, "SELECT COUNT(*) FROM repair01_decisions WHERE status='NEEDS_OWNER_DECISION' AND blocking_reason LIKE '%غيرُ مصنَّفٍ بعد%'");
gate($pass, $fail, $lines, 'G0-03', 'اتّساقُ الحجب',
    ($badA === 0 && $badN === 0 && $unrev === 0),
    "معتمدٌ حاجب $badA · منتظرٌ بلا تصنيف $badN · بلا مراجعة $unrev", '0 · 0 · 0');

/* ── G0-04 الحاجبُ الحقيقيّ: بنيويٌّ وجاهزيّةٌ فقط ── */
$hard = (int) one($conn, "SELECT COUNT(*) FROM repair01_decisions
                          WHERE status='NEEDS_OWNER_DECISION'
                            AND blocking_level IN ('STRUCTURAL_TARGET_BLOCKER','READY_TO_BUILD_BLOCKER')");
$cfg = (int) one($conn, "SELECT COUNT(*) FROM repair01_decisions WHERE blocking_level='CONFIG_PENDING'");
gate($pass, $fail, $lines, 'G0-04', 'الحاجبُ الحقيقيُّ للبناء',
    ($hard === 4 && $cfg === 12), "حاجبٌ صلب $hard · إعدادٌ مؤجَّل $cfg", '4 · 12');

/* ── G0-05 الترقيم: 01..17 متّصلٌ بلا ثغرةٍ ولا تكرار ── */
$ord = array();
$r = $conn->query("SELECT display_order FROM repair01_departments WHERE display_order IS NOT NULL ORDER BY display_order");
while ($x = $r->fetch_row()) { $ord[] = (int) $x[0]; }
$outside = (int) one($conn, "SELECT COUNT(*) FROM repair01_departments WHERE sector='OUTSIDE' AND display_order IS NULL");
$contig = ($ord === range(1, 17));
gate($pass, $fail, $lines, 'G0-05', 'ترقيمُ الإدارات 01..17',
    ($contig && $outside === 4),
    "متسلسل " . ($contig ? 'نعم' : 'لا') . " (" . count($ord) . ") · خارجَ التسلسل $outside", 'نعم (17) · 4');

/* ── G0-06 الجسر: كلُّ مسمّى حيٍّ له مقابل ── */
$live = array();
$r = $conn->query("SELECT DISTINCT dept_name FROM gov_screen_cycle");
while ($x = $r->fetch_row()) { $live[] = $x[0]; }
$unbridged = 0;
foreach ($live as $d) {
    $n = (int) one($conn, "SELECT COUNT(*) FROM repair01_dept_crosswalk WHERE legacy_name='" . $conn->real_escape_string($d) . "'");
    if ($n === 0) { $unbridged++; }
}
gate($pass, $fail, $lines, 'G0-06', 'جسرُ المسمّياتِ الحيّة',
    ($unbridged === 0), count($live) . " مسمّى · بلا جسر $unbridged", count($live) . " · 0");

/* ── G0-07 مقامُ الأسطح: يساوي الحيَّ تمامًا ── */
$sN = (int) one($conn, "SELECT COUNT(*) FROM repair01_surfaces");
$gN = (int) one($conn, "SELECT COUNT(*) FROM gov_screen_cycle");
gate($pass, $fail, $lines, 'G0-07', 'مقامُ الأسطح = الحيّ',
    ($sN === $gN && $gN > 0), "$sN / $gN", 'متساويان');

/* ── G0-08 الشبح: مسحٌ عوديٌّ حيٌّ لا قراءةُ عمود ── */
$diskIdx = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $fo) {
    $p = $fo->getPathname();
    if (strpos($p, DIRECTORY_SEPARATOR . '.git') !== false || strpos($p, 'node_modules') !== false) { continue; }
    if (substr($p, -4) === '.php') { $diskIdx[strtolower(basename($p))] = 1; }
}
$reGhost = 0; $reFiles = array();
$r = $conn->query("SELECT screen_file FROM gov_screen_cycle WHERE screen_file<>''");
while ($x = $r->fetch_row()) {
    $bn = strtolower(basename(trim($x[0])));
    $reFiles[$bn] = isset($diskIdx[$bn]) ? 1 : 0;
    if (!isset($diskIdx[$bn])) { $reGhost++; }
}
$stored = (int) one($conn, "SELECT COUNT(*) FROM repair01_surfaces WHERE on_disk=0");
$ghostFiles = count(array_filter($reFiles, function ($v) { return $v === 0; }));
gate($pass, $fail, $lines, 'G0-08', 'الشبحُ بمسحٍ عوديٍّ مُعاد',
    ($reGhost === $stored), "مقيسٌ حيًّا $reGhost · مخزَّن $stored · ملفّاتٌ متفرّدة $ghostFiles", 'متساويان');

/* ── G0-09 المنشأ: لا صفَّ بلا مرجعِ خليّة ── */
$noSrc = 0;
foreach (array('repair01_decisions', 'repair01_surfaces', 'repair01_target_gaps',
               'repair01_requirements', 'repair01_fields', 'repair01_events', 'repair01_ownership') as $t) {
    $noSrc += (int) one($conn, "SELECT COUNT(*) FROM `$t` WHERE src_ref=''");
}
gate($pass, $fail, $lines, 'G0-09', 'المنشأُ مع كلِّ صفّ',
    ($noSrc === 0), "بلا مرجع $noSrc", '0');

/* ── G0-10 المقامُ الصادق: منشورٌ ومحسوبٌ لا مُدَّعى ── */
$built = $sN - $stored;
$pctGhost = $sN ? round(100 * $stored / $sN, 1) : 0;
gate($pass, $fail, $lines, 'G0-10', 'المقامُ الصادقُ للمبنيّ',
    ($built > 0 && $built < $sN), "مبنيٌّ $built من $sN · شبحٌ {$pctGhost}%", 'مبنيٌّ < الكلّ');

/* ── G0-11 محورُ الحجبِ ذو القيمتين (RPR-PATCH-01 §3) ──
     كلُّ DEC-OPEN مصنَّفٌ · و`STRUCTURAL` وحدَه يمنع فتحَ البوّابة. */
$openTot   = (int) one($conn, "SELECT COUNT(*) FROM repair01_decisions WHERE decision_id LIKE 'DEC-OPEN-%'");
$openTyped = (int) one($conn, "SELECT COUNT(*) FROM repair01_decisions WHERE decision_id LIKE 'DEC-OPEN-%' AND blocker_type IS NOT NULL");
$struct    = (int) one($conn, "SELECT COUNT(*) FROM repair01_decisions WHERE blocker_type='STRUCTURAL'");
$thresh    = (int) one($conn, "SELECT COUNT(*) FROM repair01_decisions WHERE blocker_type='THRESHOLD'");
$gateBlock = (int) one($conn, "SELECT COUNT(*) FROM repair01_decisions WHERE blocker_type='STRUCTURAL' AND status='NEEDS_OWNER_DECISION'");
/* THRESHOLD لا يجوز أن يمنع: أيُّ عتبةٍ موسومةٍ حاجبًا بنيويًّا خطأُ تصنيف */
$badThresh = (int) one($conn, "SELECT COUNT(*) FROM repair01_decisions
                               WHERE blocker_type='THRESHOLD' AND blocking_level='STRUCTURAL_TARGET_BLOCKER'");
gate($pass, $fail, $lines, 'G0-11', 'محورُ الحجبِ مصنَّفٌ ومتّسق',
    ($openTot === 18 && $openTyped === 18 && $struct + $thresh === 18 && $badThresh === 0),
    "DEC-OPEN $openTot مصنَّفٌ $openTyped · STRUCTURAL $struct · THRESHOLD $thresh · عتبةٌ موسومةٌ بنيويّةً $badThresh",
    '18 · 18 · مجموعُهما 18 · 0');

/* ── الطباعة ── */
echo "\n═══════════ بوّابةُ المرحلةِ صفر — REPAIR01 ═══════════\n";
foreach ($lines as $l) { echo $l . "\n"; }
echo "───────────────────────────────────────────────────────\n";
printf("W0 gate: %d/%d  ·  ghosts %d  ·  gate-blockers %d (STRUCTURAL) · thresholds %d  ·  built %d/%d\n",
    $pass, $pass + $fail, $stored, $gateBlock, $thresh, $built, $sN);
echo ($fail === 0 ? "الحكم: خضراء ✔\n" : "الحكم: سقطت $fail ✘\n");
exit($fail === 0 ? 0 : 1);
