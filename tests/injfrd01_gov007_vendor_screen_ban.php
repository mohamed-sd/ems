<?php
/**
 * tests/injfrd01_gov007_vendor_screen_ban.php — FR-GOV-007 · GAP-67
 * ═══════════════════════════════════════════════════════════════════════════
 * «لا يُسجَّل سطحًا أيُّ مسارٍ تحت مجلَّدِ المكتباتِ الخارجيّة — قاعدةٌ مانعةٌ
 * دائمةٌ لا معالجةُ حالتَين».
 *   ① صفوفُ `vendor/` القائمةُ كلُّها محكومةٌ بوسمِ `VENDOR_NOT_A_SCREEN`.
 *   ② القاعدةُ المانعةُ قائمةٌ في المستوعِبِ نفسِه (`repair01_ingest`).
 *   ③ السالبُ بالحقن: صفُّ `vendor/` بلا حكمٍ ⇒ **العدّادُ يتحرّك** ثم يُكنس.
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

$PROBE = 'SCR-G07P';
$pass = 0; $fail = 0;
function ok($c, $l, $d = '')
{
    global $pass, $fail;
    if ($c) { $pass++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $fail++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
function unruledVendor(mysqli $c)
{
    $q = $c->query("SELECT COUNT(*) FROM `repair01_screen_registry`
                     WHERE `route` LIKE '%vendor/%'
                       AND COALESCE(`ghost_verdict`,'') NOT IN ('VENDOR_NOT_A_SCREEN')");
    return $q ? (int) $q->fetch_row()[0] : -1;
}
register_shutdown_function(function () use ($conn, $PROBE) {
    $conn->query("DELETE FROM `repair01_screen_registry` WHERE `screen_id` = '{$PROBE}'");
    $q = $conn->query("SELECT COUNT(*) FROM `repair01_screen_registry` WHERE `screen_id` = '{$PROBE}'");
    if ($q && (int) $q->fetch_row()[0] !== 0) {
        fwrite(STDERR, "⛔ كنسُ المِجَسِّ فشل — احذفْ صفَّ {$PROBE} يدويًّا\n");
    }
});

echo "══ FR-GOV-007 — لا سطحَ تحت مجلَّدِ المكتباتِ الخارجيّة ══\n";

/* ── ① المقيَّدُ محكوم ───────────────────────────────────────────────────── */
$q = $conn->query("SELECT COUNT(*) FROM `repair01_screen_registry` WHERE `route` LIKE '%vendor/%'");
$den = $q ? (int) $q->fetch_row()[0] : -1;
$un = unruledVendor($conn);
ok($un === 0, "① كلُّ صفِّ `vendor/` القائمِ محكومٌ بوسمِ VENDOR_NOT_A_SCREEN — المقام {$den}", "بلا حكم={$un}");

/* ── ② القاعدةُ المانعةُ في المستوعِب ───────────────────────────────────── */
$src = (string) @file_get_contents($ROOT . '/tools/repair01_ingest.php');
ok(strpos($src, "'/vendor/'") !== false && strpos($src, 'continue') !== false,
   '② القاعدةُ المانعةُ قائمةٌ في `repair01_ingest` نفسِه — المسحُ يتخطّى المكتبات');

/* ── ③ السالبُ بالحقن ────────────────────────────────────────────────────── */
$ins = $conn->query("INSERT INTO `repair01_screen_registry` (`screen_id`, `route`)
                     VALUES ('{$PROBE}', 'vendor/gov007/_probe.php')");
ok((bool) $ins, '③ حُقن صفُّ `vendor/` بلا حكم', $ins ? $PROBE : $conn->error);
$un2 = unruledVendor($conn);
ok($un2 === $un + 1, '**العدّادُ تحرّك بالحقن** — الكاشفُ يعَضُّ', "{$un} ⇒ {$un2}");
$conn->query("DELETE FROM `repair01_screen_registry` WHERE `screen_id` = '{$PROBE}'");
ok(unruledVendor($conn) === $un, 'كُنس المِجَسُّ وعاد العدّاد', (string) $un);

echo str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
