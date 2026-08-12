<?php
/**
 * tests/period_guard_bank_diff_test.php — حارسُ فروقِ المطابقةِ البنكيةِ يشتعل
 * ═══════════════════════════════════════════════════════════════════════════
 * **ما كُشف وقيس**: الفرعُ ③ في `includes/period_guard.php` («فروقُ مطابقةٍ
 * بنكيةٍ مفتوحة») كان **ميتًا ولم يشتعل قطُّ** — خمسةُ أسماءٍ خاطئةٍ في استعلامٍ
 * واحد (`m.status` · `'Difference'` · `m.line_id` · `l.line_id` · `l.value_date`)،
 * و`config.php` يضبط mysqli على عدمِ الرمي فيعود `false` **صامتًا** ويصير العدُّ
 * صفرًا. أي أنَّ فترةً ماليةً كان يمكن إقفالُها وفيها فروقٌ بنكيةٌ مفتوحة —
 * **حارسٌ قائمٌ نصًّا غائبٌ فعلًا**، وهي ثالثةُ حالةٍ من هذا النمطِ في هذا النظام.
 *
 * ── ولا يُقبل خضارٌ خاوٍ ────────────────────────────────────────────────────
 * صفرُ فرقٍ اليومَ (`bank_recon_matches` فارغٌ) — فقولُ «الحارسُ سليمٌ» بلا زرعٍ
 * شهادةٌ على الفراغِ لا على الحارس. فيُزرع فرقٌ **حقيقيٌّ** بمسارِ الجدولِ نفسِه
 * ويُشترط أن يظهر حاجبًا، ثم يُرفع الزرعُ ويُشترط أن يزول.
 *
 * ◆ والوسمُ عائليٌّ (`PGB`) والكنسُ **بعائلةِ الوسم** — فجولةٌ ماتت تترك ثغرةً
 *   حيةً تُعمي التالية.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/includes/period_guard.php';

$conn = $GLOBALS['conn'];
$CO = 4;
$FAMILY = 'PGB';
$MARK = $FAMILY . getmypid();
/* ◆ **الدالةُ تأخذ `$periodId` لا تواريخَ** — وأوّلُ صياغةٍ لي مرَّرت تاريخين
     فرجعت `$p` نُلًّا فعادت مصفوفةٌ فارغةٌ، **فقرأتُ «الحارسُ لا يشتعل» وهو لم
     يُسأل أصلًا**. فتُقرأ فترةٌ ماليةٌ حقيقيةٌ ويُزرع السطرُ داخلَ مداها. */
$PERIOD = null;
$pq = $conn->query("SELECT id, start_date, end_date FROM fin_financial_periods
                      WHERE company_id = {$CO} ORDER BY start_date DESC LIMIT 1");
if ($pq && $pq->num_rows) { $PERIOD = $pq->fetch_assoc(); }
$FROM = $PERIOD ? (string) $PERIOD['start_date'] : '2029-01-01';
$TO   = $PERIOD ? (string) $PERIOD['end_date']   : '2029-12-31';
$PID  = $PERIOD ? (int) $PERIOD['id'] : 0;
$MIDDAY = $PERIOD ? (string) $PERIOD['start_date'] : '2029-06-15';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $extra = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($extra !== '' ? "  ⟵ {$extra}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };

/** كنسٌ بعائلةِ الوسمِ — يُرجَع العددُ المحذوفُ ويُفحَص مُرجَعُ كلِّ حذف */
$sweep = function () use ($conn, $CO, $FAMILY) {
    $n = 0;
    $q = $conn->query("SELECT id FROM bank_statement_lines
                        WHERE company_id = {$CO} AND bank_ref LIKE '{$FAMILY}%'");
    $ids = array();
    while ($q && ($x = $q->fetch_assoc())) { $ids[] = (int) $x['id']; }
    if ($ids) {
        $in = implode(',', $ids);
        $conn->query("DELETE FROM bank_recon_matches WHERE statement_line_id IN ({$in})");
        $n += max(0, $conn->affected_rows);
        $conn->query("DELETE FROM bank_statement_lines WHERE id IN ({$in})");
        $n += max(0, $conn->affected_rows);
    }
    $conn->query("DELETE FROM bank_statements WHERE company_id = {$CO} AND statement_ref LIKE '{$FAMILY}%'");
    $n += max(0, $conn->affected_rows);
    return $n;
};
$say('══ حارسُ فروقِ المطابقةِ البنكيةِ  (كُنس ' . $sweep() . ' من عائلةِ ' . $FAMILY . ')');

/** يقرأ حاجبَ الفروقِ البنكيةِ من الحارسِ المركزيِّ — من دالتِه لا بنصٍّ ثانٍ */
$bankBlocker = function () use ($conn, $CO, $PID) {
    $fn = null;
    foreach (array('ems_period_close_blockers', 'ems_period_blockers', 'period_close_blockers') as $cand) {
        if (function_exists($cand)) { $fn = $cand; break; }
    }
    if ($fn === null) { return array('fn' => null, 'hit' => null); }
    $b = $fn($conn, $CO, $PID);
    if (!is_array($b)) { return array('fn' => $fn, 'hit' => null); }
    foreach ($b as $x) {
        $lbl = isset($x['label']) ? (string) $x['label'] : '';
        if (mb_strpos($lbl, 'مطابقةٍ بنكية') !== false || mb_strpos($lbl, 'المطابقةِ البنكية') !== false) {
            return array('fn' => $fn, 'hit' => $x);
        }
    }
    return array('fn' => $fn, 'hit' => false);
};

/* ══ ① الأسماءُ الخمسةُ الخاطئةُ مرفوعةٌ من المصدر ═══════════════════════════ */
$src = (string) file_get_contents($ROOT . '/includes/period_guard.php');
/* ◆ **يُقرأ الكودُ لا التعليق.** أوّلُ صياغةٍ فحصت النصَّ الخامَ فسقطت على تعليقي
     أنا الذي يُعدّد الأسماءَ الخاطئةَ ليشرح العيبَ — فحصٌ يقيس غيرَ ما يدّعي.
     (وهي ثانيةُ مرةٍ أقع فيها في هذا بعينِه في هذه الجلسة.) */
$codeOnly = '';
foreach (token_get_all($src) as $tk) {
    if (is_array($tk)) {
        if ($tk[0] === T_COMMENT || $tk[0] === T_DOC_COMMENT) { continue; }
        $codeOnly .= $tk[1];
    } else { $codeOnly .= $tk; }
}
foreach (array("m.status" => 'm.state', "'Difference'" => "'open_difference'",
               "m.line_id" => 'm.statement_line_id', "l.value_date" => 'l.txn_date') as $bad => $good) {
    $ok(strpos($codeOnly, $bad) === false, "الاسمُ الخاطئُ «{$bad}» مرفوعٌ من الكودِ (الصحيحُ {$good})",
        'ذكرُه في تعليقٍ مقصودٌ — يشرح العيبَ');
}
$ok(strpos($src, "m.state = 'open_difference'") !== false, 'والشرطُ الصحيحُ مكتوبٌ');
$ok(strpos($src, "l.match_state = 'difference'") !== false,
    'ووسمُ السطرِ محسوبٌ كذلك — موضعان يسجّلان الفرقَ لا واحد');
$ok(strpos($src, 'NOT EXISTS (SELECT 1 FROM bank_recon_matches m2') !== false,
    'وبلا تكرارِ عدٍّ بين الموضعين');
$ok(strpos($src, 'تعذّر فحصُ فروقِ المطابقةِ البنكية') !== false,
    'وفشلُ الاستعلامِ يُعلَن حاجبًا — فلا يُقفَل دورٌ بحارسٍ أعمى');

/* ══ ② الدالةُ قائمةٌ وتُنادى ═════════════════════════════════════════════════ */
$b0 = $bankBlocker();
$ok($b0['fn'] !== null, 'دالةُ حواجبِ الإقفالِ موجودةٌ (' . var_export($b0['fn'], true) . ')');
if ($b0['fn'] === null) { $sweep(); $say(''); $say("PASS={$PASS} · FAIL={$FAIL}"); exit(1); }
$ok($b0['hit'] === false, 'ولا حاجبَ فروقٍ بنكيةٍ اليومَ (الحالةُ النظيفةُ قبل الزرع)',
    'إن وُجد حاجبٌ سلفًا فالنافذةُ ليست نظيفةً وفروعُ الزرعِ لا تُفرِّق');

/* ══ ③ **الزرعُ**: فرقٌ حقيقيٌّ بمسارِ الجدولِ — ويجب أن يشتعل الحارس ═══════════ */
$hasStmt = $conn->query("SHOW TABLES LIKE 'bank_statements'");
$hasStmt = $hasStmt && $hasStmt->num_rows > 0;
$ok($hasStmt, 'جدولُ كشوفِ البنكِ قائمٌ');

$acct = $conn->query("SELECT id FROM fin_bank_accounts WHERE company_id = {$CO} LIMIT 1");
$acct = ($acct && $acct->num_rows) ? (int) $acct->fetch_assoc()['id'] : 0;
$ok($acct > 0, 'وُجد حسابٌ بنكيٌّ حقيقيٌّ (' . $acct . ') — لا رقمٌ مخترَع');

$stmtId = 0;
if ($hasStmt && $acct > 0) {
    $cols = array();
    $r = $conn->query('SHOW COLUMNS FROM bank_statements');
    while ($r && ($x = $r->fetch_assoc())) { $cols[$x['Field']] = $x; }
    /* ◆ الأعمدةُ تُثبَت لا تُحدَس — و`bank_statements` بلا FK على الحساب (قِيس)، */
    $f = array('company_id' => $CO);
    if (isset($cols['bank_account_id'])) { $f['bank_account_id'] = $acct; }
    /* ◆ العمودُ `statement_ref` لا `statement_no` — وأوّلُ صياغةٍ لي حدست الاسمَ
         فتُخطّي الحقلُ فصار فارغًا، فردَّ المفتاحُ الفريدُ (company·account·ref)
         بـ«Duplicate entry '4-1-'». **الأسماءُ تُثبَت بـSHOW COLUMNS لا تُحدَس.** */
    if (isset($cols['statement_ref'])) { $f['statement_ref'] = $MARK . '-ST'; }
    if (isset($cols['period_from'])) { $f['period_from'] = $FROM; }
    if (isset($cols['period_to'])) { $f['period_to'] = $TO; }
    /* ومدى الحالةِ `imported·matching·reconciled·closed` — و`sql_mode` خاويةٌ
         فقيمةٌ خارجَ المدى تُبتلع '' صامتًا. */
    if (isset($cols['state'])) { $f['state'] = 'imported'; }
    if (isset($cols['created_at'])) { $f['created_at'] = date('Y-m-d H:i:s'); }
    $names = array(); $vals = array();
    foreach ($f as $k => $v) { $names[] = "`{$k}`"; $vals[] = "'" . $conn->real_escape_string((string) $v) . "'"; }
    $okIns = $conn->query('INSERT INTO bank_statements (' . implode(',', $names)
                          . ') VALUES (' . implode(',', $vals) . ')');
    $stmtId = $okIns === false ? 0 : (int) $conn->insert_id;
    $ok($stmtId > 0, 'زُرع رأسُ كشفٍ (#' . $stmtId . ')', $okIns === false ? $conn->error : '');
}

$lineId = 0;
if ($stmtId > 0) {
    $okIns = $conn->query("INSERT INTO bank_statement_lines
        (company_id, statement_id, line_no, txn_date, description, direction, amount,
         bank_ref, line_key, match_state, created_at)
        VALUES ({$CO}, {$stmtId}, 1, '{$MIDDAY}', 'سطرُ جسٍّ للحارس', 'deposit', 1000.00,
                '" . $conn->real_escape_string($MARK) . "-L1',
                '" . $conn->real_escape_string($MARK) . "-K1', 'unmatched', NOW())");
    $lineId = $okIns === false ? 0 : (int) $conn->insert_id;
    $ok($lineId > 0, 'وزُرع سطرٌ داخلَ مدى الفترة ' . $MIDDAY . ' (#' . $lineId . ')', $okIns === false ? $conn->error : '');
}

if ($lineId > 0) {
    /* ﴾أ﴾ فرقٌ بسجلِّ المطابقة — الموضعُ الأول */
    $okIns = $conn->query("INSERT INTO bank_recon_matches
        (company_id, statement_line_id, payment_id, match_kind, bank_amount, system_amount,
         difference, state, difference_reason, created_at)
        VALUES ({$CO}, {$lineId}, NULL, 'none', 1000.00, 940.00, 60.00, 'open_difference',
                'فرقُ جسٍّ للحارس — يُرفع في آخرِ الجولة', NOW())");
    /* ◆ `ck_recon_diff_reason` **حارسٌ يعمل**: فرقٌ مفتوحٌ بلا سببٍ مردودٌ.
         ردَّ أوّلَ زرعٍ لي — فأُطيعه ولا أُلويه. */
    $mid = $okIns === false ? 0 : (int) $conn->insert_id;
    $ok($mid > 0, 'وزُرع فرقٌ مفتوحٌ في سجلِّ المطابقة (#' . $mid . ')',
        $okIns === false ? $conn->error : '');
    if ($mid > 0) {
        $b1 = $bankBlocker();
        $ok(is_array($b1['hit']) && (int) $b1['hit']['count'] === 1,
            '**اشتعلَ الحارسُ**: حاجبٌ واحدٌ بفرقٍ واحدٍ',
            'المُرجَعُ: ' . var_export($b1['hit'], true));
        $conn->query("DELETE FROM bank_recon_matches WHERE id = {$mid}");
        $gone = $conn->affected_rows;
        $ok($gone === 1, 'ورُفع الزرعُ (' . $gone . ' صفًّا)');
        $b2 = $bankBlocker();
        $ok($b2['hit'] === false, 'وزال الحاجبُ — فالحارسُ يتبع الواقعَ لا يثبت');
    }

    /* ﴾ب﴾ فرقٌ **بوسمِ السطرِ وحدَه** بلا صفِّ مطابقةٍ — الموضعُ الثاني الذي
           كان مُغفَلًا تمامًا: سطرٌ موسومٌ فرقًا ولا سجلَّ مطابقةٍ له. */
    $conn->query("UPDATE bank_statement_lines SET match_state = 'difference' WHERE id = {$lineId}");
    $upd = $conn->affected_rows;
    $ok($upd === 1, 'ووُسم السطرُ فرقًا بلا صفِّ مطابقةٍ (' . $upd . ')');
    $b3 = $bankBlocker();
    $ok(is_array($b3['hit']) && (int) $b3['hit']['count'] === 1,
        '**واشتعلَ الحارسُ بالموضعِ الثاني** — وكان مُغفَلًا كلِّيًّا',
        'المُرجَعُ: ' . var_export($b3['hit'], true));
    /* ﴾ج﴾ والاثنان معًا لا يُعدَّان مرتين */
    $conn->query("INSERT INTO bank_recon_matches
        (company_id, statement_line_id, payment_id, match_kind, bank_amount, system_amount,
         difference, state, difference_reason, created_at)
        VALUES ({$CO}, {$lineId}, NULL, 'none', 1000.00, 940.00, 60.00, 'open_difference',
                'فرقُ جسٍّ ثانٍ — لفحصِ عدمِ تكرارِ العدّ', NOW())");
    $mid2 = (int) $conn->insert_id;
    $b4 = $bankBlocker();
    $ok(is_array($b4['hit']) && (int) $b4['hit']['count'] === 1,
        'وسطرٌ موسومٌ **وله** صفُّ مطابقةٍ يُعدُّ واحدًا لا اثنين',
        'المُرجَعُ: ' . var_export(is_array($b4['hit']) ? $b4['hit']['count'] : $b4['hit'], true));
    if ($mid2 > 0) { $conn->query("DELETE FROM bank_recon_matches WHERE id = {$mid2}"); }
}

/* ── كنسٌ ختاميٌّ بعائلةِ الوسم ─────────────────────────────────────────────── */
$left = $sweep();
$say('   كُنس ختامًا: ' . $left . ' صفًّا');
$chk = $conn->query("SELECT COUNT(*) n FROM bank_statement_lines
                      WHERE company_id = {$CO} AND bank_ref LIKE '{$FAMILY}%'");
$chk = $chk ? (int) $chk->fetch_assoc()['n'] : -1;
$ok($chk === 0, 'صفرُ ثغرةٍ من عائلةِ الوسمِ بعد الجولة (' . $chk . ')');
$b5 = $bankBlocker();
$ok($b5['hit'] === false, 'وعادت النافذةُ نظيفةً بلا حاجب');

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
