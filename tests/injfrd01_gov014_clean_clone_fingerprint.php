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
$q = $conn->query("SELECT drill_no, verdict, finished_at
                     FROM `dr_drills` WHERE drill_kind = 'fresh_install'
                    ORDER BY finished_at DESC LIMIT 1");
$d = $q ? $q->fetch_assoc() : null;
ok($d !== null, '② ثمَّ تمرينُ تثبيتٍ من الصفرٍ مقيَّد', $d ? $d['drill_no'] : 'لا قيد');
ok($d !== null && $d['verdict'] === 'pass', 'وحكمُه ناجح', $d ? $d['verdict'] : '—');

/* ⛔ عدمُ التقادم: التمرينُ بعد آخرِ هجرةٍ مطبَّقة */
$q = $conn->query("SELECT MAX(`applied_at`) FROM `schema_migrations`");
$lastMig = $q ? (string) $q->fetch_row()[0] : '';
$fresh = ($d !== null && $lastMig !== '' && strtotime($d['finished_at']) >= strtotime($lastMig));
ok($fresh, '**والتمرينُ غيرُ متقادم** — تاريخُه بعد آخرِ هجرةٍ مطبَّقة',
   ($d ? mb_substr($d['finished_at'], 0, 16) : '؟') . ' ≥ ' . mb_substr($lastMig, 0, 16));

echo str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
