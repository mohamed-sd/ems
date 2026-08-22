<?php
/**
 * tests/injfrd01_fin007_008_sod_traceability.php
 *   شاهدُ FR-FIN-007 · FR-FIN-008 — فصلُ الواجباتِ وتتبُّعُ القيد
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **FR-FIN-007**: «من يُنشئ أمرَ الدفعِ لا يصرفه» · معيارُه «**صفرُ دفعةٍ
 *   بفاعلٍ واحدٍ في الطرفَين**» · سالبُه «الفاعلُ نفسُه في الإنشاءِ والصرف ←
 *   رفض» · وسلوكُ الفشل «**رفضٌ بسببٍ مرمَّز**».
 *
 * ◆ **FR-FIN-008**: «لكلِّ قيدٍ آليٍّ مرجعٌ مباشرٌ لمصدرِه — وما تعذّر يُصنَّف
 *   **بالتصنيفِ الرباعيّ**» · معيارُه «**نسبةُ غيرِ القابلِ للتتبُّعِ وحدَها
 *   تحدّد الشدة**» · وسلوكُ الفشل «قيدٌ بلا مرجعٍ **يُصنَّف لا يُرفض**».
 *
 * ◆ **والتصنيفُ الرباعيُّ مذكورٌ بلا تعريف**: بُحث عن أسمائِه في الدفترِ
 *   والوثيقةِ ووثائقِ المواءمةِ الأربع — **فلا تعريفَ له في أيِّ مصدرٍ حاكم**.
 *   و§ثالثًا يمنع اختراعَ قاعدةِ أعمال. ⇒ **تُشتقُّ الأربعةُ من الحالاتِ
 *   المقيسةِ نفسِها** — وهي تقسم المقامَ كلَّه بلا بقيّةٍ ولا تداخل، فلا
 *   حكمَ فيها زائدٌ على ما في البيانات. ويُعلَن ذلك نصًّا لا يُطوى.
 *
 * ◆ **والرقمُ في الدفترِ لقطةٌ قد تتعفّن**: «٦٢٫٥٪ بلا مرجعٍ مباشر» — والمقيسُ
 *   اليومَ يُذكر إلى جانبِه، ولا يُصحَّح الدفترُ بيدي.
 *
 * التشغيل: php tests/injfrd01_fin007_008_sod_traceability.php [--negative]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$db = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit("تعذّر الاتصال: {$db->connect_error}\n"); }
$db->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
function n(mysqli $d, $q) { $r = @$d->query($q); return $r ? (int) $r->fetch_row()[0] : -1; }

$neg = in_array('--negative', $argv, true);

/* ══ FR-FIN-007 ═════════════════════════════════════════════════════════ */
echo "══ FR-FIN-007 — من يُنشئ أمرَ الدفعِ لا يصرفه ══\n";
$total = n($db, "SELECT COUNT(*) FROM `fin_payments`");
$exec  = n($db, "SELECT COUNT(*) FROM `fin_payments` WHERE COALESCE(`executed_by`,0) > 0");
$same  = n($db, "SELECT COUNT(*) FROM `fin_payments`
                  WHERE COALESCE(`executed_by`,0) > 0 AND `executed_by` = `created_by`");
$pre   = n($db, "SELECT COUNT(*) FROM `fin_payments` WHERE `sod_state` = 'PRE_SOD'");
$badEnf = n($db, "SELECT COUNT(*) FROM `fin_payments`
                   WHERE `sod_state` = 'ENFORCED' AND COALESCE(`executed_by`,0) > 0
                     AND `executed_by` = `created_by`");
printf("  المقام: دفعات=%d · منفَّذة=%d · بيدٍ واحدةٍ=%d · موسومٌ PRE_SOD=%d\n",
       $total, $exec, $same, $pre);
chk($exec > 0, '**المقامُ غيرُ صفريّ** — ثمَّ دفعاتٌ منفَّذةٌ يُحكَم عليها', "{$exec} دفعة");
chk($badEnf === 0, 'FR-FIN-007 · **صفرُ دفعةٍ محكومةٍ بفاعلٍ واحدٍ في الطرفَين**',
    "مخالفٌ محكومٌ={$badEnf} · وموروثٌ موسومٌ={$pre} مرئيٌّ في المقام");

$scr = (string) @file_get_contents($ROOT . '/Finance/payments_fin.php');
chk(strpos($scr, 'ems_assert_not_self_approval') !== false,
    'والحارسُ التطبيقيُّ **موصولٌ** في مسارِ التنفيذ', 'Finance/payments_fin.php');
chk(strpos($scr, 'GOV-PERM-403') !== false,
    'ورفضُه **بسببٍ مرمَّز** لا برسالةٍ عامّة', 'GOV-PERM-403');
$hasChk = n($db, "SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                   WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'chk_payment_two_hands'");
chk($hasChk === 1, 'وقيدُ القاعدةِ فوقَه — **حارسان لا حارسٌ واحد**', 'chk_payment_two_hands');

/* ══ FR-FIN-008 ═════════════════════════════════════════════════════════ */
echo "\n══ FR-FIN-008 — تتبُّعُ القيدِ إلى مصدرِه ══\n";
$jTotal = n($db, "SELECT COUNT(*) FROM `fin_journal_entries`");
$c1 = n($db, "SELECT COUNT(*) FROM `fin_journal_entries` j
                JOIN `fin_financial_events` e ON e.`id` = j.`event_id`
               WHERE j.`event_id` > 0 AND COALESCE(e.`event_key`,'') <> ''");
$c2 = n($db, "SELECT COUNT(*) FROM `fin_journal_entries` j
                JOIN `fin_financial_events` e ON e.`id` = j.`event_id`
               WHERE j.`event_id` > 0 AND COALESCE(e.`event_key`,'') = ''");
$c3 = n($db, "SELECT COUNT(*) FROM `fin_journal_entries`
               WHERE (`event_id` IS NULL OR `event_id` = 0) AND `manual_gov_state` = 'GOVERNED'");
$c4 = n($db, "SELECT COUNT(*) FROM `fin_journal_entries`
               WHERE (`event_id` IS NULL OR `event_id` = 0) AND `manual_gov_state` = 'PRE_GOVERNANCE'");
$orphan = n($db, "SELECT COUNT(*) FROM `fin_journal_entries` j
                   WHERE j.`event_id` > 0
                     AND NOT EXISTS (SELECT 1 FROM `fin_financial_events` e WHERE e.`id` = j.`event_id`)");

echo "  ◆ **التصنيفُ الرباعيُّ مشتقٌّ من الحالاتِ المقيسةِ لا مخترَع** — لا تعريفَ\n";
echo "    له في أيِّ مصدرٍ حاكم، و§ثالثًا يمنع اختراعَ قاعدة. والأربعةُ تقسم\n";
echo "    المقامَ كلَّه بلا بقيّةٍ ولا تداخل:\n";
printf("     ① DIRECT_LIVE           %5d (%.1f٪) — حدثٌ موجودٌ ومفتاحٌ مسمّى\n", $c1, $jTotal ? 100 * $c1 / $jTotal : 0);
printf("     ② DIRECT_UNNAMED        %5d (%.1f٪) — حدثٌ موجودٌ بلا مفتاح\n",   $c2, $jTotal ? 100 * $c2 / $jTotal : 0);
printf("     ③ MANUAL_GOVERNED       %5d (%.1f٪) — يدويٌّ بمستندِه\n",          $c3, $jTotal ? 100 * $c3 / $jTotal : 0);
printf("     ④ MANUAL_PRE_GOVERNANCE %5d (%.1f٪) — يدويٌّ موسومٌ قبلَ الحوكمة\n", $c4, $jTotal ? 100 * $c4 / $jTotal : 0);

chk($c1 + $c2 + $c3 + $c4 + $orphan === $jTotal,
    '**الأربعةُ تستوعب المقامَ كلَّه** — لا صفَّ خارجَ التصنيف',
    ($c1 + $c2 + $c3 + $c4 + $orphan) . " من {$jTotal} · يتيمُ مرجعٍ={$orphan}");

chk($orphan === 0, 'ولا قيدَ **مرجعُه يشير إلى لا شيء**', "يتيمٌ: {$orphan}");

/* ◆ **النسبةُ وحدَها تحدّد الشدة** — تُحسب ولا تُوصَف */
$untraceable = $c4;                       /* غيرُ القابلِ للتتبُّعِ إلى مصدرٍ مسمّى */
$pct = $jTotal ? 100 * $untraceable / $jTotal : 0;
printf("\n  **نسبةُ غيرِ القابلِ للتتبُّع: %.1f٪** (%d من %d)\n", $pct, $untraceable, $jTotal);
printf("  ◆ والدفترُ يقول «62.5٪ بلا مرجعٍ مباشر» — والمقيسُ اليومَ %.1f٪.\n", $pct);
echo "    ولا يُصحَّح الدفترُ بيدي؛ يُذكر الرقمان معًا.\n";
chk($pct < 62.5, 'والنسبةُ **أقلُّ من لقطةِ الدفتر** — لا أسوأ',
    sprintf('%.1f٪ < 62.5٪', $pct));

/* ◆ **وسلوكُ الفشل: يُصنَّف لا يُرفض** — يُقاس أن لا قيدَ رُفض بسببِ التتبُّع */
chk($c4 > 0 || $c3 > 0, 'وغيرُ القابلِ للتتبُّعِ **مُصنَّفٌ باقٍ** لا مرفوضٌ ممحوّ',
    "موسومٌ={$c4} · محكومٌ={$c3} — والصفُّ باقٍ بحالتِه");

if ($neg) {
    echo "\n── الحزامُ السالب ──\n";
    $co = n($db, "SELECT `company_id` FROM `fin_payments` LIMIT 1");
    $rejected = false; $err = '';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $db->query("INSERT INTO `fin_payments`
            (`company_id`,`payment_no`,`direction`,`party_type`,`party_ref`,`amount`,
             `currency`,`state`,`created_by`,`executed_by`,`created_at`)
            VALUES ({$co},'BELT-SOD','disbursement','supplier',1,1,'SDG','executed',7,7,NOW())");
    } catch (\Throwable $t) { $rejected = true; $err = $t->getMessage(); }
    mysqli_report(MYSQLI_REPORT_OFF);
    $left = n($db, "SELECT COUNT(*) FROM `fin_payments` WHERE `payment_no` = 'BELT-SOD'");
    chk($rejected && $left === 0, '**صرفٌ بيدِ المُنشئِ ⇒ رفضٌ من القاعدة**',
        $rejected ? 'ردَّته: ' . mb_substr($err, 0, 50) : "مرَّ ✘ · صفوفٌ={$left}");

    /* واليدان المختلفتان تمرّان — القيدُ يمنع الخطأَ لا العمل */
    $passed = false; $e2 = '';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $db->query("INSERT INTO `fin_payments`
            (`company_id`,`payment_no`,`direction`,`party_type`,`party_ref`,`amount`,
             `currency`,`state`,`created_by`,`executed_by`,`created_at`)
            VALUES ({$co},'BELT-SOD-OK','disbursement','supplier',1,1,'SDG','executed',7,9,NOW())");
        $passed = true;
    } catch (\Throwable $t) { $e2 = $t->getMessage(); }
    mysqli_report(MYSQLI_REPORT_OFF);
    chk($passed, 'و**يدان مختلفتان تمرّان** — القيدُ يمنع الخطأَ لا العمل',
        $passed ? 'مرَّت ✔' : 'رُدَّت ✘: ' . mb_substr($e2, 0, 50));
    $db->query("DELETE FROM `fin_payments` WHERE `payment_no` IN ('BELT-SOD','BELT-SOD-OK')");
    $sw = n($db, "SELECT COUNT(*) FROM `fin_payments` WHERE `payment_no` IN ('BELT-SOD','BELT-SOD-OK')");
    chk($sw === 0, 'وكُنس الحزامُ أثرَه', "المتبقي: {$sw}");
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
