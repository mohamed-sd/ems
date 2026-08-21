<?php
/**
 * tests/injfix01_golden_promotion_proof.php — INJ-FIX-01 · GAP-23
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «١٠/١٠ باجتيازِ بوابةِ الترقيةِ التسعِ — ومنه **الاختبارُ
 *   البشريُّ المستقل** · وليس للمالكِ أن يُرقّي راسبًا في الفقدِ أو الوصولِ أو
 *   الأمنِ أو الاختبارِ البشريّ».
 *
 * ◆ **وهذا الفاحصُ لا يدّعي إغلاقَ البند** — فالبندُ **مفتوحٌ بحقّ**: العاشرُ
 *   اختبارٌ بشريٌّ لا يملكه منفِّذ. وما يحرسه هنا **القاعدةُ غيرُ القابلةِ
 *   للتجاوز**: أن **لا تُرقّى شاشةٌ لم تجتز**، وأن يُسمّى كلُّ راسبٍ بسببِه،
 *   وأن يبقى البندُ البشريُّ **مُعلَنًا لا مطويًّا**.
 *
 * ◆ **فبوابةٌ تُغلق بندًا لا تملك شرطَه تُنتج إغلاقًا كاذبًا** — وهو أسوأُ من
 *   بندٍ مفتوحٍ مُعلَن.
 *
 * التشغيل: php tests/injfix01_golden_promotion_proof.php
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

echo "══ ① الشاشاتُ العشرُ مسجَّلةٌ وحالتُها مكتوبة ══\n";
$rows = array();
$q = $conn->query("SELECT `id`,`screen_file`,`state`,`pattern_state`,`approval_basis`,`owner_note`
                     FROM `gov_golden_approvals` ORDER BY `id`");
while ($q && $x = $q->fetch_assoc()) { $rows[] = $x; }
chk(count($rows) === 10, 'الشاشاتُ الذهبيةُ عشر — ' . count($rows));

$passed = 0; $pend = 0; $noNote = 0;
foreach ($rows as $r) {
    $isPass = ($r['pattern_state'] === 'VISUAL_PATTERN_APPROVED');
    if ($isPass) { $passed++; } else { $pend++; }
    if (trim((string) $r['owner_note']) === '') { $noNote++; }
    printf("     #%-2s %-34s %-24s %s\n", $r['id'], mb_substr($r['screen_file'], 0, 34),
        $r['pattern_state'], $r['approval_basis'] ?: '—');
}
chk($noNote === 0, "لكلِّ شاشةٍ سببٌ مكتوبٌ لحالتِها — بلا={$noNote}");

echo "\n══ ② القاعدةُ غيرُ القابلةِ للتجاوز: لا يُرقّى راسب ══\n";
/* أيُّ شاشةٍ وُسمت مقبولةً بينما لم تجتز؟ — يُعاد تشغيلُ البوابةِ لا يُقرأ الحكمُ المحفوظ */
$out = array(); $code = 0;
@exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/injfix01_golden_promotion_gate.php') . ' 2>&1', $out, $code);
$live = array();
foreach ($out as $line) {
    if (preg_match('/#(\d+)\s+(\S+)\s+(\d)\/9/u', $line, $m)) { $live[(int) $m[1]] = (int) $m[3]; }
}
chk(count($live) === 10, 'أُعيد تشغيلُ البوابةِ على العشرِ — ' . count($live));

$falsePromote = array();
foreach ($rows as $r) {
    $id = (int) $r['id'];
    $score = isset($live[$id]) ? $live[$id] : -1;
    if ($r['pattern_state'] === 'VISUAL_PATTERN_APPROVED' && $score < 9) {
        $falsePromote[] = "#{$id} {$r['screen_file']} ({$score}/9)";
    }
}
chk(count($falsePromote) === 0, '**صفرُ شاشةٍ مُرقّاةٍ لم تجتز التسع** — ' . count($falsePromote)
    . (count($falsePromote) ? ' — ' . implode(' · ', $falsePromote) : ''));

/* ولا شاشةَ `approved` نهائيًّا قبلَ الاختبارِ البشريّ */
$q = $conn->query("SELECT COUNT(*) FROM `gov_golden_approvals` WHERE `state` = 'approved'");
$fin = $q ? (int) $q->fetch_row()[0] : -1;
chk($fin === 0, "صفرُ شاشةٍ مقبولةٍ نهائيًّا قبلَ الاختبارِ البشريّ — {$fin}");

echo "\n══ ③ البندُ البشريُّ مُعلَنٌ لا مطويّ ══\n";
printf("  ◆ اجتازت التسعَ الموضوعية: **%d من 10** · ولم تجتزها: %d\n", $passed, $pend);
echo "  ◆ **والعاشرُ — اختبارٌ بشريٌّ مستقلٌّ — لم يقع لأيٍّ منها.**\n";
echo "     ولا يملكه المنفِّذ: يلزمه مستخدمٌ حقيقيٌّ بحسابِ دورِه يفتح الشاشةَ ويحكم.\n";
echo "     وحساباتُ الأدوارِ مُسجَّلةٌ في العمودِ `test_account` لكلِّ شاشة.\n";
chk(true, '◆ **GAP-23 يبقى مفتوحًا بحقّ** — ولا يُدَّعى إغلاقُه بتسعٍ من عشر');

echo "\n  ◆ وما يحرسه هذا الفاحصُ هو القاعدةُ نفسُها: **لا تُرقّى راسبة**.\n";
echo "     فبوابةٌ تُغلق بندًا لا تملك شرطَه تُنتج إغلاقًا كاذبًا — وهو أسوأُ\n";
echo "     من بندٍ مفتوحٍ مُعلَن.\n";

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);

/* حكمُ الإغلاقِ — عقدُ GAP-56: يُصرَّح به بعدَ القياسِ لا يُستنتَج من الذِّكر */
require_once dirname(__DIR__) . '/tools/lib/gap_verdict.php';
gapv('GAP-23', false, 'تسعُ بواباتٍ موضوعيةٍ من عشرٍ — والعاشرُ اختبارٌ بشريٌّ مستقلٌّ لم يقع لأيِّ شاشة', $bad);

exit($bad === 0 ? 0 : 1);
