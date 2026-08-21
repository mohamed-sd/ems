<?php
/**
 * tests/injfix01_delegation_state_proof.php — INJ-FIX-01 · GAP-03
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «ثمانيةُ سيناريوهاتِ تفويضٍ وتصعيدٍ حقيقيةٍ بأثرٍ مقروء · أو
 *   تقاعدٌ رسميٌّ معلَن». والمقيسُ **ثلاثةٌ حيّةٌ لا ثمانية** — فالبندُ **لا يُغلق**.
 *
 * ◆ **وهذا الفاحصُ يمنع أمرَين**: أن **تتقادمَ** حالةٌ مُعلَنةٌ فتكذب، وأن
 *   **يُدَّعى الإغلاقُ** بثلاثةٍ من ثمانية. فيرسُب على الأولِ ويُصرّح بالثاني.
 *
 * التشغيل: php tests/injfix01_delegation_state_proof.php
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

echo "══ ① سجلُّ الحالةِ قائم ══\n";
$r = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gov_delegation_state'");
if (!$r || (int) $r->fetch_row()[0] === 0) {
    chk(false, 'سجلُّ حالةِ التفويضِ غيرُ موجود — تُشغَّل الهجرة 2027_09_06');
    echo "\nالنتيجة: {$ok} نجاح · {$bad} رسوب\n"; exit(1);
}
$rows = array();
$q = $conn->query("SELECT `pathway`,`store`,`rows_live`,`verdict` FROM `gov_delegation_state`");
while ($q && $x = $q->fetch_assoc()) { $rows[] = $x; }
chk(count($rows) >= 5, 'السجلُّ يغطّي مساراتِ التفويضِ والتصعيد — ' . count($rows));

echo "\n══ ② الحالةُ المُعلَنةُ تطابق المقيسَ حيًّا — فلا تتقادم ══\n";
$stale = array();
foreach ($rows as $x) {
    $t = preg_replace('/[^A-Za-z0-9_]/', '', $x['store']);
    $w = '1';
    if ($t === 'work_escalations')  { $w = 'resolved_at IS NULL'; }
    if ($t === 'work_delegations')  { $w = "status='active' AND (ends_at IS NULL OR ends_at >= NOW())"; }
    $r = $conn->query("SELECT COUNT(*) FROM `{$t}` WHERE {$w}");
    $live = $r ? (int) $r->fetch_row()[0] : -1;
    $decl = (int) $x['rows_live'];
    $vOk  = ($live > 0) === ($x['verdict'] === 'LIVE_READABLE');
    printf("     %-11s %-22s مُعلَن %-6d حيٌّ %-6d %s\n",
        $x['pathway'], $x['store'], $decl, $live, $vOk ? '✔' : '✘');
    if (!$vOk) { $stale[] = $x['store']; }
}
chk(count($stale) === 0, 'كلُّ حكمٍ مُعلَنٍ يطابق واقعَه — ' . count($stale) . ' متقادمًا'
    . (count($stale) ? ' — ' . implode(' · ', $stale) : ''));

echo "\n══ ③ لا يُدَّعى الإغلاقُ بأقلَّ من المعيار ══\n";
$live = 0; $dormant = 0;
foreach ($rows as $x) { if ($x['verdict'] === 'LIVE_READABLE') { $live++; } else { $dormant++; } }
printf("  مساراتٌ حيّةٌ مقروءةٌ بأثر: **%d** · خاملة: %d · **والمعيارُ ثمانية**\n", $live, $dormant);
chk($live < 8, "لم يُدَّعَ الإغلاق: {$live} < 8 — والبندُ **يبقى مفتوحًا** بحقّ");
echo "  ◆ والشطرُ الأولُ من الادعاءِ **مردودٌ بالقياس**: التصعيدُ مبنيٌّ وله قارئُ\n";
echo "     إنتاجٍ وبياناتٌ حيّة. **والشطرُ الثاني قائم**: صفرُ تفويضٍ نافذ.\n";
echo "  ◆ وتقاعدُ التفويضِ **قرارُ مالكٍ لا حكمُ منفِّذ** — BLOCKED_OWNER_INPUT.\n";

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
