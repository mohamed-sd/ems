<?php
/**
 * tools/fix_lost_constraints_classify.php — فصلُ المفقودِ عن وهمِ الباحث
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ `fix_missing_checks.php` أعلن 38 اسمًا مفقودًا. والقياسُ الأدقُّ يكشف أن
 *   بعضَها **وهمُ باحثٍ لا فقدٌ**: الفاحصُ يذكر `uq_stage_once` والقيدُ الحيُّ
 *   اسمُه `uq_stage_once_per_round` — بادئةٌ لا غياب. وإعلانُ هذا فقدًا يقود
 *   إلى «ترميمِ» قيدٍ قائمٍ باسمٍ ثانٍ، فيصير في القاعدةِ حارسان لحكمٍ واحد.
 *
 * ◆ فتُفصَل ثلاثةُ أصناف:
 *     `LOST`        — له تعريفٌ حرفيٌّ في نسخةِ ما قبلَ إعادةِ البناءِ (2026-08-03)
 *                     وغائبٌ الآن ⇒ **يُرمَّم بنصِّه**.
 *     `PREFIX`      — بادئةُ اسمٍ حيٍّ أطول ⇒ **وهمُ باحث**، ويُصلَح الباحثُ لا القاعدة.
 *     `NEVER_BUILT` — لا تعريفَ له في أيِّ نسخةٍ ⇒ **مواصفةٌ لم تُبنَ**، تُعلَن
 *                     ديْنًا ولا تُخترع.
 *
 * ◆ ويُميَّز `CONSTRAINT x CHECK (…)` من مجرَّدِ **ذكرٍ في تعليقِ عمود** — فالثاني
 *   ليس تعريفًا، وقد خدعني في `ck_container_parent`.
 *
 * التشغيل: php tools/fix_lost_constraints_classify.php [--md=<path>]
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/fix_lib.php';
$db = fix_db();
$mdOut = null;
foreach ($argv as $a) { if (strpos($a, '--md=') === 0) { $mdOut = substr($a, 5); } }

/* ── الحيُّ: أسماءٌ كاملةٌ من الحرّاسِ الثلاثة ─────────────────────────────── */
$live = array();
$r = $db->query("SELECT CONSTRAINT_NAME n, TABLE_NAME t FROM information_schema.CHECK_CONSTRAINTS
                  WHERE CONSTRAINT_SCHEMA=DATABASE()");
while ($r && ($x = $r->fetch_assoc())) { $live[$x['n']] = $x['t']; }
$r = $db->query("SELECT DISTINCT INDEX_NAME n, TABLE_NAME t FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA=DATABASE() AND NON_UNIQUE=0");
while ($r && ($x = $r->fetch_assoc())) { $live[$x['n']] = $x['t']; }
$r = $db->query("SELECT DISTINCT CONSTRAINT_NAME n, TABLE_NAME t FROM information_schema.KEY_COLUMN_USAGE
                  WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL");
while ($r && ($x = $r->fetch_assoc())) { $live[$x['n']] = $x['t']; }

/* ── نسخةُ ما قبلَ إعادةِ البناء + أحدثُ نسخة ───────────────────────────── */
$dumps = glob($ROOT . '/database/baseline/auto_pre_up_*.sql');
usort($dumps, static function ($a, $b) { return strcmp(basename($b), basename($a)); });
$preRebuild = array();
foreach ($dumps as $f) {
    if (strpos(basename($f), 'auto_pre_up_20260803') === 0) { $preRebuild[] = $f; }
}
if (!$preRebuild) { $preRebuild = array_slice($dumps, 0, 3); }

$names = array_slice($argv, 1);
$names = array_values(array_filter($names, static function ($v) { return strpos($v, '--') !== 0; }));
if (!$names) {
    $names = array('ck_container_alloc', 'ck_container_parent', 'ck_container_consumed',
        'ck_swap_differs', 'ck_rotation_cycle', 'ck_sa_standby_zero', 'ck_chp_superseded',
        'ck_sadv_inst', 'uq_stage_once', 'ck_je_balanced', 'ck_je_fx_pair',
        'ck_settlement_invoice_diff', 'ck_led_qty_positive', 'uq_price_term',
        'ck_sup_line_standby', 'uq_sup_', 'uq_policy_rule', 'ck_alloc_target',
        'ck_cg_nature', 'ck_cg_deduct', 'ck_cg_dates', 'ck_cg_state_reason',
        'ck_cps_treatment', 'ck_cps_advance_link', 'ck_pay_fx_pair', 'ck_cb_actors',
        'ck_cle_effects', 'ck_cle_claim_article', 'ck_cle_decision', 'ck_cle_cancel_tree',
        'chk_nav_door', 'uq_aw2', 'FK_VIOLATION');
}

$cls = array('LOST' => array(), 'PREFIX' => array(), 'NEVER_BUILT' => array(), 'LIVE' => array());
foreach ($names as $name) {
    if (isset($live[$name])) { $cls['LIVE'][$name] = $live[$name]; continue; }

    /* بادئةُ اسمٍ حيٍّ أطول؟ */
    $pfx = null;
    foreach ($live as $ln => $lt) {
        if ($ln !== $name && strpos($ln, $name) === 0) { $pfx = $ln . ' [' . $lt . ']'; break; }
    }
    if ($pfx !== null) { $cls['PREFIX'][$name] = $pfx; continue; }

    /* تعريفٌ حرفيٌّ في نسخةِ ما قبلَ إعادةِ البناء؟ */
    $def = null; $tbl = '?'; $src = '';
    foreach ($preRebuild as $f) {
        $sql = (string) @file_get_contents($f);
        /* **تعريفٌ** لا ذكرٌ: CONSTRAINT `x` CHECK (…)  أو  UNIQUE KEY `x` (…) */
        $re = '/(?:CONSTRAINT|UNIQUE\s+KEY|KEY)\s+`' . preg_quote($name, '/') . '`\s*(CHECK\s*\(.*?\)|\([^)]*\))/is';
        if (!preg_match($re, $sql, $m, PREG_OFFSET_CAPTURE)) { continue; }
        $def = preg_replace('/\s+/', ' ', trim($m[0][0]));
        $head = substr($sql, 0, $m[0][1]);
        if (preg_match_all('/CREATE TABLE `([^`]+)`/i', $head, $tm)) { $tbl = end($tm[1]); }
        $src = basename($f);
        break;
    }
    if ($def !== null) { $cls['LOST'][$name] = array('table' => $tbl, 'def' => $def, 'src' => $src); }
    else               { $cls['NEVER_BUILT'][] = $name; }
}

$L = array();
$L[] = '**القياس:** ' . date('Y-m-d H:i') . ' · نسخةُ المقارنة: '
     . ($preRebuild ? basename($preRebuild[0]) : '—');
$L[] = '';
$L[] = '| الصنف | العدد | المعنى |';
$L[] = '|---|---|---|';
$L[] = '| **LOST** | ' . count($cls['LOST']) . ' | له تعريفٌ حرفيٌّ قبلَ 2026-08-03 وغائبٌ الآن ⇒ **يُرمَّم بنصِّه** |';
$L[] = '| **PREFIX** | ' . count($cls['PREFIX']) . ' | بادئةُ اسمٍ حيٍّ أطول ⇒ **وهمُ باحثٍ** لا فقد |';
$L[] = '| **NEVER_BUILT** | ' . count($cls['NEVER_BUILT']) . ' | لا تعريفَ في أيِّ نسخةٍ ⇒ مواصفةٌ لم تُبنَ · **لا تُخترع** |';
$L[] = '| LIVE | ' . count($cls['LIVE']) . ' | قائمٌ فعلًا |';
$L[] = '';
$L[] = '## ① LOST — تُرمَّم بنصِّها الأصلي';
$L[] = '';
foreach ($cls['LOST'] as $n => $h) {
    $L[] = '**`' . $n . '`** · جدول `' . $h['table'] . '` · من `' . $h['src'] . '`';
    $L[] = '```sql';
    $L[] = $h['def'];
    $L[] = '```';
}
$L[] = '';
$L[] = '## ② PREFIX — وهمُ الباحثِ (يُصلَح الباحثُ لا القاعدة)';
$L[] = '';
foreach ($cls['PREFIX'] as $n => $full) { $L[] = '- `' . $n . '` ← الحيُّ `' . $full . '`'; }
$L[] = '';
$L[] = '## ③ NEVER_BUILT — ديْنٌ مُعلَنٌ لا اختراع';
$L[] = '';
foreach ($cls['NEVER_BUILT'] as $n) { $L[] = '- `' . $n . '`'; }

$out = implode("\n", $L);
echo $out . "\n";
if ($mdOut) { @file_put_contents($mdOut, "# القيودُ المفقودةُ — تصنيفٌ مقيس\n\n" . $out . "\n"); echo "\nتقرير: {$mdOut}\n"; }
