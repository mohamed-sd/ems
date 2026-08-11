<?php
/**
 * tools/fix_lost_constraints_plan.php — نصُّ كلِّ قيدٍ مفقودٍ كاملًا + مخالفوه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ `mysqldump` يكتب كلَّ `CONSTRAINT` في **سطرٍ مستقلّ**، فالاستخراجُ بالسطرِ
 *   دقيقٌ حيث فشل التعبيرُ النمطيُّ (بتَر عند أولِ قوسٍ مغلق).
 *
 * ◆ ولا يُضاف قيدٌ قبل قياسِ **مخالفيه الأحياء**: قيدٌ يُضاف على بيانةٍ مخالفةٍ
 *   إمّا يفشل (فيُقرأ عطلًا) وإمّا يُغري بتعديلِ بياناتٍ ماليةٍ لإرضائه — وذاك
 *   أسوأُ من ثغرة. فالمخالفُ **يُعلَن ويُعرَض** ولا يُمَسّ.
 *
 * ◆ والقياسُ بنفيِ شرطِ القيد: `WHERE NOT (<clause>)`. وصفوفٌ تُرجع NULL من
 *   الشرطِ ليست مخالفةً (منطقُ SQL الثلاثيّ) — تمامًا كما تفعل القاعدةُ نفسُها.
 *
 * التشغيل: php tools/fix_lost_constraints_plan.php [--md=<path>]
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/fix_lib.php';
$db = fix_db();
$mdOut = null;
foreach ($argv as $a) { if (strpos($a, '--md=') === 0) { $mdOut = substr($a, 5); } }

$WANT = array(
    'ck_container_alloc', 'ck_container_parent', 'ck_container_consumed', 'ck_swap_differs',
    'ck_rotation_cycle', 'ck_sa_standby_zero', 'ck_chp_superseded', 'ck_sadv_inst',
    'ck_je_balanced', 'ck_je_fx_pair', 'ck_settlement_invoice_diff', 'ck_led_qty_positive',
    'ck_sup_line_standby', 'ck_alloc_target', 'ck_cg_nature', 'ck_cg_deduct', 'ck_cg_dates',
    'ck_cg_state_reason', 'ck_cps_treatment', 'ck_cps_advance_link', 'ck_pay_fx_pair',
    'ck_cb_actors', 'ck_cle_effects', 'ck_cle_claim_article', 'ck_cle_decision',
    'ck_cle_cancel_tree', 'chk_nav_door',
);

/* ── الاستخراجُ بالسطرِ من نسخةِ ما قبلَ إعادةِ البناء ────────────────────── */
$dump = $ROOT . '/database/baseline/auto_pre_up_20260803_084927_equipation_manage.sql';
if (!is_file($dump)) { exit("نسخةُ 2026-08-03 غيرُ موجودة\n"); }
$defs = array();
$curTable = '';
$fh = fopen($dump, 'r');
while (($line = fgets($fh)) !== false) {
    if (preg_match('/^CREATE TABLE `([^`]+)`/i', $line, $m)) { $curTable = $m[1]; continue; }
    if (!preg_match('/^\s*CONSTRAINT `([^`]+)` CHECK \((.*)\),?\s*$/i', $line, $m)) { continue; }
    if (!in_array($m[1], $WANT, true)) { continue; }
    $clause = rtrim(trim($m[2]), ',');
    /* القوسُ الخارجيُّ يعود إلينا عند البناء — نحفظ الشرطَ كما كتبه المُخرِج */
    $defs[$m[1]] = array('table' => $curTable, 'clause' => $clause);
}
fclose($fh);

/* ── الحيُّ الآن ─────────────────────────────────────────────────────────── */
$live = array();
$r = $db->query("SELECT CONSTRAINT_NAME n FROM information_schema.CHECK_CONSTRAINTS
                  WHERE CONSTRAINT_SCHEMA=DATABASE()");
while ($r && ($x = $r->fetch_assoc())) { $live[$x['n']] = true; }

$L = array();
$L[] = '**القياس:** ' . date('Y-m-d H:i') . ' · المصدر: `' . basename($dump) . '`';
$L[] = '';
$L[] = '| # | القيد | الجدول | صفوفُ الجدول | **مخالفون** | الحال |';
$L[] = '|---|---|---|---|---|---|';

$clean = array(); $dirty = array(); $noTable = array();
$i = 0;
foreach ($WANT as $name) {
    $i++;
    if (isset($live[$name])) { $L[] = '| ' . $i . ' | `' . $name . '` | — | — | — | قائمٌ سلفًا |'; continue; }
    if (!isset($defs[$name])) { $L[] = '| ' . $i . ' | `' . $name . '` | — | — | — | **لا نصَّ في النسخة** |'; continue; }
    $t = $defs[$name]['table'];
    $c = $defs[$name]['clause'];

    /* أموجودٌ الجدولُ أصلًا؟ */
    $ex = $db->query("SELECT COUNT(*) FROM information_schema.TABLES
                       WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $db->real_escape_string($t) . "'");
    if (!$ex || (int) $ex->fetch_row()[0] === 0) {
        $noTable[$name] = $t;
        $L[] = '| ' . $i . ' | `' . $name . '` | `' . $t . '` | **الجدولُ غيرُ موجود** | — | يُعلَن |';
        continue;
    }
    $rows = (int) $db->query("SELECT COUNT(*) FROM `{$t}`")->fetch_row()[0];
    $q = $db->query("SELECT COUNT(*) FROM `{$t}` WHERE NOT ({$c})");
    if (!$q) {
        $L[] = '| ' . $i . ' | `' . $name . '` | `' . $t . '` | ' . $rows
             . ' | **تعذّر القياس** | ' . mb_substr($db->error, 0, 40) . ' |';
        continue;
    }
    $bad = (int) $q->fetch_row()[0];
    if ($bad === 0) { $clean[$name] = $defs[$name]; }
    else            { $dirty[$name] = $defs[$name] + array('bad' => $bad, 'rows' => $rows); }
    $L[] = '| ' . $i . ' | `' . $name . '` | `' . $t . '` | ' . $rows . ' | '
         . ($bad === 0 ? '**0** ✔' : '**' . $bad . '** ⚠') . ' | '
         . ($bad === 0 ? 'يُرمَّم الآن' : 'يُعلَن — لا تُمَسُّ بيانةٌ مالية') . ' |';
}

$L[] = '';
$L[] = '**نظيفٌ يُرمَّم فورًا: ' . count($clean) . '** · بمخالفينَ يُعلَن: ' . count($dirty)
     . ' · جدولٌ غيرُ موجود: ' . count($noTable);
$L[] = '';
if ($dirty) {
    $L[] = '## قيودٌ لها مخالفونَ أحياء — قرارُ مالك';
    $L[] = '';
    $L[] = 'إضافتُها تفشل، وإرضاؤها يعني **تعديلَ بياناتٍ ماليةٍ قائمة**. فتُعرَض:';
    $L[] = '';
    foreach ($dirty as $n => $h) {
        $L[] = '- **`' . $n . '`** · `' . $h['table'] . '` · **' . $h['bad'] . ' مخالفًا** من ' . $h['rows'];
        $L[] = '  - الشرط: `' . mb_substr($h['clause'], 0, 200) . '`';
    }
}

/* مخرَجٌ آليٌّ للهجرة — نصوصٌ حرفيةٌ لا صياغةٌ من عندي */
$json = array();
foreach ($clean as $n => $h) { $json[$n] = array('table' => $h['table'], 'clause' => $h['clause']); }
@file_put_contents($ROOT . '/storage/lost_constraints_clean.json',
    json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$out = implode("\n", $L);
echo $out . "\n\nنصوصُ النظيفِ في: storage/lost_constraints_clean.json\n";
if ($mdOut) { @file_put_contents($mdOut, "# القيودُ المفقودةُ — خطةُ الترميم\n\n" . $out . "\n"); }
