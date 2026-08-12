<?php
/**
 * tests/bank_lines_integrity_test.php — سلامةُ أسطرِ الكشفِ البنكيِّ ومصدرُ المحرَّم
 * ═══════════════════════════════════════════════════════════════════════════
 * ثلاثةُ أعطالٍ كُشفت بقياسِ بابِ ٤-٩ من وثيقةِ OWF-01، وهذا شاهدُها الحيّ:
 *
 * ① **سندٌ كان يُطابَق مرتين** — السندُ 10 يطالبه السطرُ الحقيقيُّ 7 والملفَّقُ 18،
 *    والسندُ 11 يطالبه 8 و19. و`tests/bank_reconciliation_test.php` يشترط نصًّا
 *    «لا سندٌ يُطابَق مرتين» — **فالقاعدةُ كانت تخالف فاحصَها**. أُزيل الملفَّقُ
 *    وأُضيف `uq_fin_bsl_payment`.
 * ② **و12 مرجعَ سندٍ يتيمٌ** بلا FK يمنعه — أُضيف `fk_fin_bsl_payment` بـSET NULL
 *    (فسطرُ البنكِ واقعةٌ قائمةٌ بذاتها؛ فقدانُ نظيرِه يجعله غيرَ مطابَقٍ لا معدومًا).
 * ③ **وعملةٌ مغروزةٌ `'SDG'`** في `BankReconService` في موضعين، والأساسُ **USD**
 *    و19 من 20 حسابًا بالدولار — فالحرفُ كان يُخطئ في الغالبِ لا في النادر.
 *
 * وعطلٌ رابعٌ في **مصدرِ المحرَّم**: القسمُ ج في المانفست كان يُدرج `bank_statements`
 * و`bank_statement_lines` **ويُغفل نظائرَها بالبادئةِ `fin_`** — فبُذرت خامًا. وكان
 * البذّارُ يُلصق استثناءَين بيدِه، وهو **سجلٌّ ثانٍ للمحرَّم** يتفرَّق عن المانفست.
 * فأُدرجت الأربعةُ في المانفست، ورُفع الاستثناءُ، ومحلَّه حارسٌ يفضح الانحراف.
 *
 * ◆ ولا يُقبل خضارٌ خاوٍ: كلُّ قيدٍ يُجَسُّ بالموجبِ **والسالبِ** — فقيدٌ يردُّ
 *   كلَّ شيءٍ منعٌ لا تمييز.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
$CO = 4;
$T = 'fin_bank_statement_lines';
$MARK = 'BLI' . getmypid();
$FAMILY = 'BLI';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $extra = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($extra !== '' ? "  ⟵ {$extra}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
/** عددٌ بفحصِ المُرجَعِ — الفشلُ يعود null لا صفرًا (وقعتُ في هذا الفخِّ ثلاثًا اليومَ) */
$one = function ($sql) use ($conn) {
    $r = $conn->query($sql);
    if ($r === false) { return null; }
    $x = $r->fetch_row();
    return $x === null ? null : $x[0];
};
$conn->query("DELETE FROM `{$T}` WHERE description LIKE '{$FAMILY}%'");
$say('══ سلامةُ أسطرِ الكشفِ البنكيِّ  (كُنس ' . max(0, $conn->affected_rows) . ' من عائلةِ ' . $FAMILY . ')');

/* ══ ① الحالةُ الراهنةُ نظيفةٌ: لا مكرَّرَ ولا يتيم ════════════════════════════ */
$dup = $one("SELECT COUNT(*) FROM (SELECT matched_payment_id FROM `{$T}`
              WHERE matched_payment_id IS NOT NULL
              GROUP BY matched_payment_id HAVING COUNT(*) > 1) x");
$orph = $one("SELECT COUNT(*) FROM `{$T}` l
               WHERE l.matched_payment_id IS NOT NULL
                 AND NOT EXISTS (SELECT 1 FROM fin_payments p WHERE p.id = l.matched_payment_id)");
$ok($dup !== null && (int) $dup === 0, 'صفرُ سندٍ مطابَقٍ مرتين (' . var_export($dup, true) . ')',
    'و`bank_reconciliation_test` يشترط ذلك نصًّا — فمخالفتُه تناقضٌ في النظام');
$ok($orph !== null && (int) $orph === 0, 'وصفرُ مرجعِ سندٍ يتيمٍ (' . var_export($orph, true) . ')');
$seq = $one("SELECT COUNT(*) FROM `{$T}`
              WHERE (amount - 524.50) >= 0
                AND ROUND((amount - 524.50)/137, 6) = ROUND((amount - 524.50)/137, 0)");
$ok($seq !== null && (int) $seq === 0,
    'وصفرُ صفٍّ في المتسلسلِ الحسابيِّ الملفَّقِ (خطوةُ 137) — البصمةُ زالت');

/* ══ ② القيدان قائمان ═══════════════════════════════════════════════════════ */
$cons = array();
$r = $conn->query("SELECT CONSTRAINT_NAME c FROM information_schema.TABLE_CONSTRAINTS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$T}'");
while ($r && ($x = $r->fetch_assoc())) { $cons[$x['c']] = true; }
$ok(isset($cons['uq_fin_bsl_payment']), 'قيدُ uq_fin_bsl_payment قائم');
$ok(isset($cons['fk_fin_bsl_payment']), 'وقيدُ fk_fin_bsl_payment قائم');

/* ══ ③ **الجسُّ في الاتجاهين** — موجبٌ وسالبان ═══════════════════════════════ */
$acct = $one("SELECT id FROM fin_bank_accounts WHERE company_id = {$CO} LIMIT 1");
/* سندٌ **غيرُ مطابَقٍ** لا أوّلُ سندٍ: أوّلُ جسٍّ لي أخذ LIMIT 1 فوقع على مطابَقٍ
   سلفًا فردَّه الفريدُ، فسقط الفرعُ الموجبُ — وقيدٌ يردُّ كلَّ شيءٍ كان يمرُّ. */
$pay = $one("SELECT p.id FROM fin_payments p
              WHERE NOT EXISTS (SELECT 1 FROM `{$T}` l WHERE l.matched_payment_id = p.id)
              LIMIT 1");
$ok($acct !== null, 'وُجد حسابٌ بنكيٌّ حقيقيٌّ (' . var_export($acct, true) . ')');
$ok($pay !== null, 'ووُجد سندٌ حقيقيٌّ **غيرُ مطابَقٍ** (' . var_export($pay, true) . ')',
    'بغيرِه لا يُقاس الفرعُ الموجبُ فيصير القيدُ منعًا لا تمييزًا');

if ($acct !== null && $pay !== null) {
    $ins = function ($pid, $tag) use ($conn, $T, $CO, $acct, $MARK) {
        $v = $pid === null ? 'NULL' : (int) $pid;
        $conn->query("INSERT INTO `{$T}`
            (company_id, bank_account_id, txn_date, description, direction, amount,
             matched_payment_id, reconciled, created_at)
            VALUES ({$CO}, " . (int) $acct . ", '2029-01-01',
                    '" . $conn->real_escape_string($MARK . '-' . $tag) . "', 'deposit', 1.00,
                    {$v}, 0, NOW())");
        return $conn->errno;
    };
    $e1 = $ins((int) $pay, 'POS');
    $ok($e1 === 0, 'الموجبُ: سندٌ حقيقيٌّ غيرُ مطابَقٍ **يمرُّ** (خطأ ' . $e1 . ')',
        'لو رُدَّ لكان القيدُ يمنع الصحيحَ كما يمنع الخطأ');
    $e2 = $ins((int) $pay, 'DUP');
    $ok($e2 === 1062 || $e2 === 1586,
        'والسالبُ ①: مطابقتُه مرةً ثانيةً **تُردُّ** (خطأ ' . $e2 . ')');
    $ghost = (int) $one('SELECT COALESCE(MAX(id),0) + 99999 FROM fin_payments');
    $e3 = $ins($ghost, 'ORPH');
    $ok($e3 === 1452 || $e3 === 1216,
        'والسالبُ ②: سندٌ غيرُ موجودٍ **يُردُّ** (خطأ ' . $e3 . ')');
    $conn->query("DELETE FROM `{$T}` WHERE description LIKE '{$FAMILY}%'");
    $swept = max(0, $conn->affected_rows);
    $left = $one("SELECT COUNT(*) FROM `{$T}` WHERE description LIKE '{$FAMILY}%'");
    $ok((int) $left === 0, 'ورُفع الزرعُ (' . $swept . ' صفًّا · باقٍ ' . var_export($left, true) . ')');
}

/* ══ ④ لا عملةَ مغروزةً في المطابقةِ البنكية ═════════════════════════════════ */
$svc = (string) file_get_contents($ROOT . '/app/Services/Finance/BankReconService.php');
/* ◆ يُقرأ **الكودُ** لا التعليقُ — وقعتُ في هذا الفخِّ مرتين اليومَ */
$code = '';
foreach (token_get_all($svc) as $tk) {
    if (is_array($tk)) {
        if ($tk[0] === T_COMMENT || $tk[0] === T_DOC_COMMENT) { continue; }
        $code .= $tk[1];
    } else { $code .= $tk; }
}
$ok(strpos($code, "'SDG'") === false,
    "صفرُ 'SDG' مغروزةٍ في كودِ BankReconService المنفَّذ",
    'ذكرُها في تعليقٍ مقصودٌ — يشرح العيبَ');
$ok(strpos($code, 'accountCurrency') !== false, 'والعملةُ تُشتَقُّ من الحسابِ (accountCurrency)');
$ok(strpos($code, 'matchCurrency') !== false, 'وعملةُ الفرقِ من حسابِ كشفِه (matchCurrency)');
$ok(strpos($code, 'ems_fx_base_currency') !== false,
    'والأساسُ من مصدرِه الواحدِ في includes/fx.php — لا منطقَ عملةٍ مستنسخًا');
/* والأساسُ فعلًا USD — فلو كان SDG لكان الحرفُ صحيحًا ولا معنى للإصلاح */
$base = $one("SELECT code FROM fin_currencies WHERE company_id = {$CO} AND is_base = 1 LIMIT 1");
$ok($base !== null && (string) $base !== 'SDG',
    'والعملةُ الأساسُ ليست SDG (هي ' . var_export($base, true) . ') — فالحرفُ كان يُخطئ',
    'لو كانت SDG لما كان في الإصلاحِ أثرٌ عمليّ');

/* ══ ⑤ المانفستُ مصدرٌ واحدٌ للمحرَّمِ — والبذّارُ لا يُلصق استثناءً ═══════════ */
$MPATH = $ROOT . '/docs/uat/UAT_DATA_POPULATION_MANIFEST_ar.md';
$ok(is_file($MPATH), 'المانفستُ موجودٌ');
$sec = '';
if (is_file($MPATH)) {
    $mdoc = (string) file_get_contents($MPATH);
    $from = strpos($mdoc, '## ج ·');
    if ($from !== false) {
        $to = strpos($mdoc, '## ', $from + 4);
        $sec = substr($mdoc, $from, ($to === false ? strlen($mdoc) : $to) - $from);
    }
}
$tables = array();
if ($sec !== '' && preg_match_all('~`([a-z0-9_]+)`~', $sec, $mm)) {
    $tables = array_values(array_unique($mm[1]));
}
$ok(count($tables) >= 50, 'وقسمُ ج يُقرأ (' . count($tables) . ' جدولًا)');
foreach (array('supplier_contracts', 'supplier_contract_lines',
               'fin_bank_accounts', 'fin_bank_statement_lines') as $t) {
    $ok(in_array($t, $tables, true), "و«{$t}» مُدرَجٌ في القسم ج",
        'غيابُه يعني أنَّ المِلءَ العامَّ سيبذره خامًا فيتجاوز حارسَه');
}
/* والترويسةُ تُطابق العدَّ — عدَّادٌ وقائمةٌ في موضعين يتفرَّقان دائمًا */
$declared = 0;
if ($sec !== '' && preg_match('~—\s*(\d+)\s*جدولًا~u', $sec, $dm)) { $declared = (int) $dm[1]; }
$ok($declared === count($tables),
    'وترويسةُ القسمِ تُطابق عدَّه (' . $declared . ' = ' . count($tables) . ')',
    'كانت 78 والفعليُّ 84 — عدَّادٌ وقائمةٌ يتفرَّقان');
/* والبذّارُ يقرأ المانفستَ ولا يُلصق قائمةً ثانية */
$seed = (string) file_get_contents($ROOT . '/database/seeds/uat0001/10_autofill.php');
$seedCode = '';
foreach (token_get_all($seed) as $tk) {
    if (is_array($tk)) {
        if ($tk[0] === T_COMMENT || $tk[0] === T_DOC_COMMENT) { continue; }
        $seedCode .= $tk[1];
    } else { $seedCode .= $tk; }
}
$ok(strpos($seedCode, "array_merge(\$SKIP, \$cycleOnly, array(") === false,
    'والبذّارُ لا يُلصق استثناءً بيدِه — سجلّان للمحرَّمِ يتفرَّقان',
    'الاستثناءُ المُلصَقُ كان سببَ أنَّ fin_bank_* بُذرت خامًا بلا سطرٍ يحرسها');
$ok(strpos($seedCode, 'MUST_BE_IN_MANIFEST') !== false,
    'ومحلَّه حارسٌ يفضح انحرافَ المانفستِ ويُوقف المِلء');

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
