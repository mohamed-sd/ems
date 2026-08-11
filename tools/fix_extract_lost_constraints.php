<?php
/**
 * tools/fix_extract_lost_constraints.php — استخراجُ نصِّ القيودِ المفقودةِ من نسخِ الأساس
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ 38 اسمَ قيدٍ تدّعيه الشجرةُ والقاعدةُ خاليةٌ منه (`fix_missing_checks.php`).
 *   وأحدَ عشرَ منها موجودٌ نصًّا في `database/baseline/auto_pre_up_*.sql` — أي
 *   **فُقد ولم يُخترع**. فيُرمَّم بنصِّه الأصليِّ حرفيًّا، لا بصياغةٍ من عندي:
 *   قيدٌ أُعيد بصياغةٍ مختلفةٍ يحرس شيئًا آخرَ ويُقرأ ترميمًا.
 *
 * ◆ ويُؤخذ من **أحدثِ** نسخةٍ تحمله — فالقيدُ قد يكون عُدِّل قبل أن يُفقد،
 *   وأقدمُ نصٍّ يُرجع صياغةً متجاوزة.
 *
 * التشغيل: php tools/fix_extract_lost_constraints.php [اسم ...]
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/fix_lib.php';
$db = fix_db();

/* الحيُّ الآن — لا يُرمَّم قائم */
$live = array();
foreach (array(
    "SELECT CONSTRAINT_NAME n FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()",
    "SELECT DISTINCT INDEX_NAME n FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND NON_UNIQUE=0",
) as $q) {
    $r = $db->query($q);
    while ($r && ($x = $r->fetch_assoc())) { $live[$x['n']] = true; }
}

$want = array_slice($argv, 1);
if (!$want) {
    $want = array('ck_container_alloc', 'ck_container_parent', 'ck_container_consumed',
                  'ck_swap_differs', 'ck_rotation_cycle', 'ck_sa_standby_zero',
                  'ck_chp_superseded', 'ck_sadv_inst', 'uq_stage_once',
                  'ck_je_balanced', 'ck_je_fx_pair', 'ck_settlement_invoice_diff',
                  'ck_led_qty_positive', 'uq_price_term', 'ck_sup_line_standby',
                  'uq_policy_rule', 'ck_alloc_target', 'ck_cg_nature', 'ck_cg_deduct',
                  'ck_cg_dates', 'ck_cg_state_reason', 'ck_cps_treatment',
                  'ck_cps_advance_link', 'ck_pay_fx_pair', 'ck_cb_actors',
                  'ck_cle_effects', 'ck_cle_claim_article', 'ck_cle_decision',
                  'ck_cle_cancel_tree', 'chk_nav_door');
}

/* نسخُ الأساسِ من الأحدثِ إلى الأقدم */
$dumps = glob($ROOT . '/database/baseline/*.sql');
usort($dumps, static function ($a, $b) { return strcmp(basename($b), basename($a)); });
echo 'نسخُ أساسٍ متاحة: ' . count($dumps) . ' · الأحدثُ: '
   . ($dumps ? basename($dumps[0]) : '—') . "\n\n";

$found = array(); $notFound = array();
foreach ($want as $name) {
    if (isset($live[$name])) { continue; }   // قائمٌ — لا يُرمَّم
    $hit = null;
    foreach ($dumps as $f) {
        $sql = (string) @file_get_contents($f);
        if (strpos($sql, $name) === false) { continue; }
        /* الجدولُ: آخرُ CREATE TABLE قبل موضعِ الاسم */
        $pos = strpos($sql, $name);
        $head = substr($sql, 0, $pos);
        $tbl = '?';
        if (preg_match_all('/CREATE TABLE `([^`]+)`/i', $head, $tm)) {
            $tbl = end($tm[1]);
        }
        /* السطرُ الحاملُ للتعريف — حتى الفاصلةِ أو نهايةِ السطر */
        $lineStart = strrpos($head, "\n");
        $lineEnd = strpos($sql, "\n", $pos);
        $line = trim(substr($sql, $lineStart + 1, ($lineEnd === false ? strlen($sql) : $lineEnd) - $lineStart - 1));
        $line = rtrim($line, ',');
        $hit = array('dump' => basename($f), 'table' => $tbl, 'def' => $line);
        break;   // الأحدثُ يكفي
    }
    if ($hit) { $found[$name] = $hit; } else { $notFound[] = $name; }
}

echo "══ ① قيودٌ وُجد نصُّها — تُرمَّم حرفيًّا (" . count($found) . ") ══\n\n";
foreach ($found as $name => $h) {
    echo '── ' . $name . '   [جدول: ' . $h['table'] . ']' . "\n";
    echo '   المصدر: ' . $h['dump'] . "\n";
    echo '   النص  : ' . $h['def'] . "\n\n";
}

echo "══ ② لا نصَّ لها في نسخِ الأساس (" . count($notFound) . ") ══\n";
echo "   هذه إمّا مُواصفةٌ لم تُبنَ قطُّ، وإمّا اسمٌ في وثيقةٍ لا قيدٌ حقيقيّ.\n";
echo "   **لا تُخترع** — تُعرَض على المالكِ أو تُعلَن ديْنًا.\n";
foreach ($notFound as $n) { echo '   · ' . $n . "\n"; }
