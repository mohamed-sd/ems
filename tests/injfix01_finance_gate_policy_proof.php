<?php
/**
 * tests/injfix01_finance_gate_policy_proof.php — INJ-FIX-01 · GAP-15
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «قرارٌ معلَن … ثم **إنفاذٌ يطابق القرارَ المكتوب**».
 *
 * ◆ **والقرارُ لا يُقرأ من وثيقةٍ بل من سجلٍّ يُفحَص**: كلُّ سلّمٍ بسقفٍ نقديٍّ
 *   له حكمٌ — بوابةٌ إلزاميةٌ أو سجلُّ طلباتٍ **بسلّمٍ مُغطٍّ يحمل بوابةً فعلًا**.
 *
 * ◆ **والسقّاطة**: سلّمٌ جديدٌ بسقفٍ نقديٍّ **بلا حكمٍ يُرسِّب** — فالقرارُ يشمل
 *   ما يأتي لا ما مضى فقط.
 *
 * التشغيل: php tests/injfix01_finance_gate_policy_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $prt = 3306;
if (strpos($h, ':') !== false) { list($h, $prt) = explode(':', $h); $prt = (int) $prt; }
$conn = new mysqli($h, ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER'),
    ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS'),
    ems_env('DB_NAME'), $prt);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($cond, $msg)
{
    global $ok, $bad;
    if ($cond) { $ok++; echo "  ✔ {$msg}\n"; } else { $bad++; echo "  ✘ {$msg}\n"; }
}

/* ══ ① القرارُ مكتوبٌ لكلِّ سلّمٍ بسقفٍ نقديّ ═══════════════════════════════ */
echo "══ ① لكلِّ سلّمٍ بسقفٍ نقديٍّ قرارٌ مكتوب ══\n";
$r = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gov_finance_gate_policy'");
if (!$r || (int) $r->fetch_row()[0] === 0) {
    chk(false, 'سجلُّ السياسةِ غيرُ موجود — تُشغَّل الهجرة 2027_09_12');
    echo "\nالنتيجة: {$ok} نجاح · {$bad} رسوب\n"; exit(1);
}
$unruled = array();
$q = $conn->query("SELECT l.`ladder_code`, l.`name_ar` FROM `gov_ladders` l
                    WHERE l.`cap_kind` = 'amount'
                      AND NOT EXISTS (SELECT 1 FROM `gov_finance_gate_policy` p
                                       WHERE p.`ladder_code` = l.`ladder_code`)");
while ($q && $x = $q->fetch_assoc()) { $unruled[] = $x['ladder_code']; }
$capN = (int) $conn->query("SELECT COUNT(*) FROM `gov_ladders` WHERE `cap_kind`='amount'")->fetch_row()[0];
chk(count($unruled) === 0, "**صفرُ سلّمٍ بسقفٍ نقديٍّ بلا حكم** — من {$capN}"
    . (count($unruled) ? ' — ' . implode(' · ', $unruled) : ''));
$noWhy = (int) $conn->query("SELECT COUNT(*) FROM `gov_finance_gate_policy`
                              WHERE COALESCE(`reason`,'') = ''")->fetch_row()[0];
chk($noWhy === 0, "لكلِّ حكمٍ سببٌ مكتوب — بلا={$noWhy}");

/* ══ ② الإنفاذُ يطابق القرار ══════════════════════════════════════════════ */
echo "\n══ ② الإنفاذُ يطابق القرارَ المكتوب ══\n";
$viol = array(); $mand = 0; $reg = 0;
$q = $conn->query("SELECT `ladder_code`,`policy`,`covered_by` FROM `gov_finance_gate_policy` ORDER BY `ladder_code`");
$rows = array();
while ($q && $x = $q->fetch_assoc()) { $rows[] = $x; }
foreach ($rows as $x) {
    $L = $conn->real_escape_string($x['ladder_code']);
    $g = (int) $conn->query("SELECT COUNT(*) FROM `gov_ladder_steps`
                              WHERE `ladder_code`='{$L}' AND `is_finance_gate`=1")->fetch_row()[0];
    if ($x['policy'] === 'MANDATORY_GATE') {
        $mand++;
        if ($g === 0) { $viol[] = "{$x['ladder_code']}: إلزاميٌّ **بلا بوابة**"; }
    } else {
        $reg++;
        $cov = $conn->real_escape_string((string) $x['covered_by']);
        if ($cov === '') { $viol[] = "{$x['ladder_code']}: سجلُّ طلباتٍ **بلا سلّمٍ مُغطٍّ مُسمًّى**"; continue; }
        $cg = (int) $conn->query("SELECT COUNT(*) FROM `gov_ladder_steps`
                                   WHERE `ladder_code`='{$cov}' AND `is_finance_gate`=1")->fetch_row()[0];
        if ($cg === 0) { $viol[] = "{$x['ladder_code']}: مُغطّيه {$cov} **بلا بوابةٍ فعلية**"; }
    }
    printf("     %-8s %-18s %-10s بوابات=%d\n", $x['ladder_code'], $x['policy'],
        $x['covered_by'] ? '⇐ ' . $x['covered_by'] : '', $g);
}
printf("     إلزاميٌّ %d · سجلُّ طلباتٍ %d\n", $mand, $reg);
chk(count($viol) === 0, '**صفرُ مخالفةٍ بين القرارِ والإنفاذ** — ' . count($viol)
    . (count($viol) ? ' — ' . implode(' · ', $viol) : ''));

/* ══ ③ ولا يُغطّي سجلُّ طلباتٍ نفسَه ═══════════════════════════════════════ */
echo "\n══ ③ لا دائرةَ تغطية ══\n";
$circ = array();
foreach ($rows as $x) {
    if ($x['policy'] !== 'REQUEST_REGISTER') { continue; }
    if ((string) $x['covered_by'] === (string) $x['ladder_code']) { $circ[] = $x['ladder_code']; }
    foreach ($rows as $y) {
        if ($y['ladder_code'] === $x['covered_by'] && $y['policy'] === 'REQUEST_REGISTER') {
            $circ[] = $x['ladder_code'] . '⇐' . $y['ladder_code'];
        }
    }
}
chk(count($circ) === 0, 'لا سجلَّ طلباتٍ يُغطّيه سجلُّ طلباتٍ آخر — ' . count($circ)
    . (count($circ) ? ' — ' . implode(' · ', $circ) : ''));
echo "  ◆ فالتغطيةُ تنتهي دائمًا عندَ **بوابةٍ فعليةٍ** لا عندَ إحالةٍ أخرى.\n";
echo "  ◆ والقرار: البوابةُ إلزاميةٌ **عندَ إلزامِ المالِ وإبرائِه**، وسجلُّ طلباتٍ\n";
echo "     فيما قبلَهما وبعدَهما — ورقابتُه موروثةٌ من بوابةِ الحلقةِ المُلزِمة.\n";

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
