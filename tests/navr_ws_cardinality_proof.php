<?php
/**
 * tests/navr_ws_cardinality_proof.php — إثباتُ كارديناليةِ المساحات (§١٨)
 * ═══════════════════════════════════════════════════════════════════════════
 * «يجب إثباتُ التصميمِ باختبار: Role = 1 Primary + 3 Secondary Workspaces».
 *   ① دورُ مِجَسٍّ يقبل PRIMARY واحدةً + **ثلاث** SECONDARY.
 *   ② PRIMARY ثانيةٌ للدورِ نفسِه **تُرَدُّ من القاعدةِ** (`uq_one_primary`).
 *   ③ تكرارُ (workspace, role) يُرَدُّ بالمفتاحِ الأصليّ.
 *   والكنسُ مضمونٌ ويُسمّى فشلُه.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once $ROOT . '/config.php';
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$RID = 999777; /* دورُ مِجَسٍّ لا وجودَ له في roles — الجدولُ بلا FK على role_id */
$pass = 0; $fail = 0;
function ok($c, $l, $d = '')
{
    global $pass, $fail;
    if ($c) { $pass++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $fail++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
register_shutdown_function(function () use ($conn, $RID) {
    $conn->query("DELETE FROM nav_ws_roles WHERE role_id = {$RID}");
    $q = $conn->query("SELECT COUNT(*) FROM nav_ws_roles WHERE role_id = {$RID}");
    if ($q && (int) $q->fetch_row()[0] !== 0) { fwrite(STDERR, "⛔ كنسُ المِجَسِّ فشل\n"); }
});

echo "══ §١٨ — كارديناليةُ المساحات: PRIMARY واحدةٌ وSECONDARY بلا سقف ══\n";

$ws = array('DEP-01', 'DEP-02', 'DEP-03', 'DEP-04');
$ok1 = $conn->query("INSERT INTO nav_ws_roles (workspace_id, role_id, binding, source_ref)
    VALUES ('{$ws[0]}', {$RID}, 'PRIMARY', 'مِجَسُّ إثبات §١٨')");
ok((bool) $ok1, '① PRIMARY الأولى قُبلت');
$sec = 0;
foreach (array($ws[1], $ws[2], $ws[3]) as $w) {
    if ($conn->query("INSERT INTO nav_ws_roles (workspace_id, role_id, binding, source_ref)
        VALUES ('{$w}', {$RID}, 'SECONDARY', 'مِجَسُّ إثبات §١٨')")) { $sec++; }
}
ok($sec === 3, '**ثلاثُ SECONDARY قُبلت للدورِ نفسِه** — السقفُ القديمُ زال', "قُبل {$sec}/3");

$dup = $conn->query("INSERT INTO nav_ws_roles (workspace_id, role_id, binding, source_ref)
    VALUES ('DEP-05', {$RID}, 'PRIMARY', 'مِجَسُّ سالب')");
ok($dup === false && stripos($conn->error, 'uq_one_primary') !== false,
   '② PRIMARY ثانيةٌ **رُدَّت من القاعدةِ باسمِ قيدِها**', mb_substr($conn->error, 0, 50));

$dup2 = $conn->query("INSERT INTO nav_ws_roles (workspace_id, role_id, binding, source_ref)
    VALUES ('{$ws[1]}', {$RID}, 'SECONDARY', 'مِجَسُّ سالب')");
ok($dup2 === false, '③ تكرارُ (workspace, role) رُدَّ بالمفتاحِ الأصليّ');

$q = $conn->query("SELECT COUNT(*) FROM nav_ws_roles WHERE role_id = {$RID}");
ok($q && (int) $q->fetch_row()[0] === 4, 'الحصيلة: 1 PRIMARY + 3 SECONDARY', '4 صفوف');

echo str_repeat('─', 60) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
