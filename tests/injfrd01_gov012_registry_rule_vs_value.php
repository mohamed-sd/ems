<?php
/**
 * tests/injfrd01_gov012_registry_rule_vs_value.php — FR-GOV-012 · GAP-73
 * ═══════════════════════════════════════════════════════════════════════════
 * «قيمةُ الملكيّةِ تتبع قاعدةَ حكمِها المكتوبةَ في الصفِّ نفسِه» — عندَ الكشفِ
 * كانت أربعةُ صفوفٍ (SCR-0505 · 0508 · 0515 · 0532) قاعدتُها «إعادةُ ملكيةٍ
 * من مكتبِ الرئيسِ إلى إدارتِها» ومالكُها `EX-CEO` — سجلٌّ يناقض نصَّه.
 *
 * ثلاثةُ فحوص:
 *   ① المقامُ يُطبع — كلُّ صفٍّ يحمل قاعدةَ «إعادةِ الملكيةِ من مكتبِ الرئيس».
 *   ② صفرُ صفٍّ يناقضها — مالكُه `EX-CEO` أو فارغٌ وقاعدتُه تُخرجه منه.
 *   ③ السالبُ بالحقن — صفُّ مِجَسٍّ قاعدتُه الإعادةُ ومالكُه `EX-CEO`
 *     ⇒ **العدّادُ يتحرّك بواحد** · ثم يُكنس ويُثبَت كنسُه — لا يُبتلع فشلُه.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once $ROOT . '/config.php';
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$PROBE_ID = 'SCR-G12P';
$pass = 0; $fail = 0;
function ok($c, $l, $d = '')
{
    global $pass, $fail;
    if ($c) { $pass++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $fail++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
/** عدُّ المناقضِ — القاعدةُ تقول «إلى إدارتِها» والقيمةُ ما زالت مكتبَ الرئيسِ أو فراغًا. */
function contradictions(mysqli $c)
{
    $q = $c->query("SELECT COUNT(*) FROM `repair01_screen_registry`
                     WHERE `verdict_rule` LIKE '%إعادة ملكية%مكتب الرئيس%'
                       AND (COALESCE(`owner_code`,'') = '' OR `owner_code` = 'EX-CEO')");
    return $q ? (int) $q->fetch_row()[0] : -1;
}

/* كنسُ المِجَسِّ مضمونٌ ولو انفجر فحص — والفشلُ يُسمّى */
register_shutdown_function(function () use ($conn, $PROBE_ID) {
    $conn->query("DELETE FROM `repair01_screen_registry` WHERE `screen_id` = '{$PROBE_ID}'");
    $q = $conn->query("SELECT COUNT(*) FROM `repair01_screen_registry` WHERE `screen_id` = '{$PROBE_ID}'");
    if ($q && (int) $q->fetch_row()[0] !== 0) {
        fwrite(STDERR, "⛔ كنسُ المِجَسِّ فشل — احذفْ صفَّ {$PROBE_ID} يدويًّا\n");
    }
});

echo "══ FR-GOV-012 — قيمةُ الملكيّةِ تتبع قاعدتَها المكتوبةَ في الصفِّ نفسِه ══\n";

/* ── ① المقام ────────────────────────────────────────────────────────────── */
$q = $conn->query("SELECT COUNT(*) FROM `repair01_screen_registry`
                    WHERE `verdict_rule` LIKE '%إعادة ملكية%مكتب الرئيس%'");
$den = $q ? (int) $q->fetch_row()[0] : -1;
ok($den > 0, '① المقامُ غيرُ صفريّ — صفوفٌ تحمل قاعدةَ الإعادة', "{$den} صفًّا");

/* ── ② صفرُ مناقض ────────────────────────────────────────────────────────── */
$bad = contradictions($conn);
ok($bad === 0, '② صفرُ صفٍّ يناقض قاعدتَه المكتوبة — والمقيسُ عندَ الكشفِ كان 4', "مناقضٌ={$bad} من {$den}");

/* ── ③ السالبُ بالحقن ────────────────────────────────────────────────────── */
$ins = $conn->query("INSERT INTO `repair01_screen_registry` (`screen_id`, `route`, `owner_code`, `verdict_rule`)
                     VALUES ('{$PROBE_ID}', '_gov012_probe.php', 'EX-CEO',
                             'RPR-W15 §٤-٢ · إعادة ملكية من مكتب الرئيس إلى إدارتها — مِجَسُّ سالبِ FR-GOV-012')");
ok((bool) $ins, '③ حُقن صفُّ مِجَسٍّ يناقض قاعدتَه', $ins ? $PROBE_ID : $conn->error);
$bad2 = contradictions($conn);
ok($bad2 === $bad + 1, '**العدّادُ تحرّك بالحقن** — الكاشفُ يعَضُّ لا يجامل', "{$bad} ⇒ {$bad2}");

$conn->query("DELETE FROM `repair01_screen_registry` WHERE `screen_id` = '{$PROBE_ID}'");
$q = $conn->query("SELECT COUNT(*) FROM `repair01_screen_registry` WHERE `screen_id` = '{$PROBE_ID}'");
$left = $q ? (int) $q->fetch_row()[0] : -1;
ok($left === 0, 'كُنس المِجَسُّ — صفرُ بقايا', "متبقٍّ={$left}");
$bad3 = contradictions($conn);
ok($bad3 === $bad, 'وعاد العدّادُ إلى قيمتِه', "{$bad3}");

echo str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
