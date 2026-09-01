<?php
/**
 * tests/injfrd01_gov014_clean_clone_fingerprint.php — FR-GOV-014 · GAP-18 ②
 * ═══════════════════════════════════════════════════════════════════════════
 * «الحكمُ ذو الشقَّين لا يُغلق بشقٍّ واحد»:
 *   ① الدفترُ يطابق القرصَ — بوّابةُ `repair01_migration_gate` 4/4.
 *   ② بصمةُ المخطَّطِ تُعاد إنتاجُها من استنساخٍ نظيف — آخرُ تمرينِ
 *     `fresh_install` في سجلِّ `dr_drills` ناجحٌ **وغيرُ متقادمٍ**: تاريخُه
 *     **بعد** آخرِ هجرةٍ بنيويّةٍ مطبَّقة. ⛔ فتمرينٌ سبق هجرةً لاحقةً
 *     **ليس على المخطَّطِ الحاليِّ** ولو كان حكمُه pass — وهذا عينُ ما وقع
 *     بين DR-2026-0003 وهجراتِ 2028_01_29..31 فأُعيد التمرينُ DR-2026-0004.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once $ROOT . '/config.php';
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$pass = 0; $fail = 0;
function ok($c, $l, $d = '')
{
    global $pass, $fail;
    if ($c) { $pass++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $fail++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

echo "══ FR-GOV-014 — الشقّان معًا: دفترٌ يطابق القرصَ وبصمةٌ تُستنسخ نظيفًا ══\n";

/* ── ① الشقُّ الأول ─────────────────────────────────────────────────────── */
$out = array(); $rc = 0;
@exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/repair01_migration_gate.php') . ' 2>&1', $out, $rc);
ok($rc === 0, '① الدفترُ يطابق القرصَ — بوّابةُ الهجراتِ خضراء', "rc={$rc}");

/* ── ② الشقُّ الثاني ───────────────────────────────────────────────────── */
/* ◆ **الأحدثُ بترتيبِ الوقوعِ لا بساعةِ الحائط**: كان الفرزُ `finished_at DESC`،
     فإن رجعت ساعةُ الجهازِ إلى الوراء (وقد رجعت: 65 صفًّا في دفترِ الهجراتِ
     بختمٍ أعلى من `NOW()`) **اختِير تمرينٌ أقدمُ وقوعًا لأنَّ ختمَه أعلى** —
     فيُحاكَم العملُ بتمرينٍ ليس آخرَه. والمعرِّفُ التسلسليُّ **ترتيبُ وقوعٍ لا
     يرجع**، وهو المقصودُ بـ«آخرِ تمرين». (وهي عينُ قاعدةِ W16: `frozen_at`
     ساعةُ حائطٍ وترتيبُ الحقائقِ غيرُها.) */
$hasHash = false;
$q = $conn->query("SHOW COLUMNS FROM `dr_drills` LIKE 'migration_set_hash'");
if ($q && $q->num_rows) { $hasHash = true; }
$q = $conn->query("SELECT drill_no, verdict, finished_at"
    . ($hasHash ? ", migration_set_hash" : "") . "
                     FROM `dr_drills` WHERE drill_kind = 'fresh_install'
                    ORDER BY `id` DESC LIMIT 1");
$d = $q ? $q->fetch_assoc() : null;
ok($d !== null, '② ثمَّ تمرينُ تثبيتٍ من الصفرٍ مقيَّد', $d ? $d['drill_no'] : 'لا قيد');
ok($d !== null && $d['verdict'] === 'pass', 'وحكمُه ناجح', $d ? $d['verdict'] : '—');

/* ⛔ عدمُ التقادم — **بمجموعةِ الهجراتِ لا بساعةِ الحائط**:
     «غيرُ متقادم» تعني أنَّ التمرينَ تحقّق من **المجموعةِ التي عندك الآن**،
     والختمُ تعبيرٌ ضعيفٌ عنها **ويكذب إذا رجعتِ الساعة** — وقد رجعت
     11.4 ساعةً فصار 65 صفًّا في الدفترِ بختمٍ أعلى من `NOW()`.
     ⭐ **والبصمةُ أشدُّ من الختم**: هجرةٌ تضيف **عمودًا** لا تحرّك
     عددَ الكائنات، وتحرّكُ البصمةَ.
     ⛔ **ولا يُرخى الحكمُ على المحاضرِ القديمة**: محضرٌ بلا بصمةٍ
     يبقى محكومًا بقاعدةِ الختمِ كما كان. */
$liveHash = null;
if ($hasHash && is_file($ROOT . '/tools/lib/migration_set_hash.php')) {
    require_once $ROOT . '/tools/lib/migration_set_hash.php';
    $h = ems_migration_set_hash($conn);
    $liveHash = $h['hash'];
}
if ($liveHash !== null && $d !== null && !empty($d['migration_set_hash'])) {
    $fresh = ($d['migration_set_hash'] === $liveHash);
    $freshWhy = 'بصمةُ المجموعة ' . substr((string) $d['migration_set_hash'], 0, 10)
              . ($fresh ? ' تطابق الحيَّة' : ' تخالف الحيَّة ' . substr($liveHash, 0, 10));
} else {
    $q = $conn->query("SELECT MAX(`applied_at`) FROM `schema_migrations`");
    $lastMig = $q ? (string) $q->fetch_row()[0] : '';
    $fresh = ($d !== null && $lastMig !== '' && strtotime($d['finished_at']) >= strtotime($lastMig));
    $freshWhy = 'بلا بصمةٍ — حُكم بالختم';
}
ok($fresh, '**والتمرينُ غيرُ متقادم** — تحقّق من مجموعةِ الهجراتِ الحاليّة', $freshWhy);

echo str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
