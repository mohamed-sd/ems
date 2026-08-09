<?php
/**
 * tools/u13_contract_fields_seed.php — حقولُ العقدِ الحاكمةُ الـ28: بذرٌ وحسم
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المصبُّ الواحد**: العناوينُ والأحكامُ تُقرأ من `gov_doc_registry` التي
 *   يملؤها المُستخرِجُ من الوثيقةِ نفسِها — فلا عنوانَ يُنسخ بيدٍ إلى SQL.
 *   وما يُضاف هنا هو **الموضعُ في النظامِ الحيِّ** وحدَه (قرارُ ربطٍ لا نصُّ
 *   وثيقة)، ثم يُحسم آليًّا: أموجودٌ ذلك العمودُ فعلًا أم فجوة؟
 *
 * ◆ والحسمُ من `information_schema` لا من الإعلان: لو حُذف عمودٌ غدًا صار
 *   الحقلُ فجوةً في التقريرِ التالي بلا أن يمسَّ أحدٌ هذا الملف.
 *
 * التشغيل: php tools/u13_contract_fields_seed.php [--apply]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$ROOT  = dirname(__DIR__);
$APPLY = in_array('--apply', $argv, true);
require_once $ROOT . '/includes/env.php';
$db = new mysqli(ems_env('DB_HOST', '127.0.0.1'), ems_env('DB_USER', 'root'),
                 ems_env('DB_PASS', ''), ems_env('DB_NAME', 'ems'), (int) ems_env('DB_PORT', '3306'));
$db->set_charset('utf8mb4');

/* ═══ الموضعُ والإلزام — قرارُ ربطٍ لكلِّ رمز ══════════════════════════════
   المفتاح: رمزُ الحقلِ · القيمة: [الإلزام, الجدول, العمود, ما يلزم المالكَ]
   ◆ الإلزامُ مأخوذٌ من نصِّ الوثيقةِ حرفًا: «ولا يُقبل … بلا» و«ولا استحقاقَ
     بلا» و«إلزاميٌّ لكل قيد» ⇦ always · و«عند الانطباق/وجودها/إن وُجد»
     ⇦ conditional · وما لا حكمَ فيه ⇦ optional.
   ◆ والموضعُ الفارغُ **إعلانُ فجوةٍ** لا سهو — ومعه ما يلزم لسدِّها. */
$MAP = array(
'CFIELD-01' => array('always',      'contracts', 'first_party',              ''),
'CFIELD-02' => array('always',      'contracts', 'second_party',             ''),
'CFIELD-03' => array('conditional', 'contracts', 'signing_authority_ref',    ''),
'CFIELD-04' => array('always',      '',          '',                         'سجلٌّ تجاريٌّ برقمِه وتاريخِ سريانِه على بطاقةِ الطرف — ويُفحص انتهاؤه'),
'CFIELD-05' => array('conditional', '',          '',                         'رقمٌ ضريبيٌّ/زكويٌّ على بطاقةِ الطرفِ عند الانطباق'),
'CFIELD-06' => array('optional',    '',          '',                         'حقلُ القانونِ الحاكمِ في رأسِ العقد'),
'CFIELD-07' => array('optional',    '',          '',                         'جهةُ فضِّ النزاعِ ومقرُّها في رأسِ العقد'),
'CFIELD-08' => array('optional',    '',          '',                         'حدُّ المسؤوليةِ وسقفُ التعويض — والغيابُ يعني مسؤوليةً مفتوحةً فيُعلَن'),
'CFIELD-09' => array('optional',    '',          '',                         'شرطُ القوةِ القاهرةِ ونطاقُه'),
'CFIELD-10' => array('always',      'contracts', 'grace_period_days',        ''),
'CFIELD-11' => array('conditional', 'contracts', 'guarantees',               ''),
'CFIELD-12' => array('conditional', '',          '',                         'التأمينُ المطلوبُ وحدودُه عند الانطباق'),
'CFIELD-13' => array('conditional', 'contract_amendments', 'amendment_code', ''),
'CFIELD-14' => array('always',      'contracts', 'contract_signing_date',    ''),
'CFIELD-15' => array('always',      'contracts', 'price_currency_contract',  ''),
'CFIELD-16' => array('conditional', 'fin_fx_rates', 'rate_to_base',          ''),
'CFIELD-17' => array('always',      'contracts', 'payment_time',             ''),
'CFIELD-18' => array('conditional', 'contract_penalty_rules', 'rule_kind',   ''),
'CFIELD-19' => array('always',      'contracts', 'project_id',               ''),
'CFIELD-20' => array('always',      'scr_business_models', 'code_model',     ''),
'CFIELD-21' => array('always',      'contracts', 'total_contract_units',     ''),
'CFIELD-22' => array('always',      'contracts', 'hours_monthly_target',     ''),
'CFIELD-23' => array('always',      'contracts', 'total_contract_permonth',  ''),
'CFIELD-24' => array('conditional', 'contract_penalty_rules', 'min_readiness_pct', ''),
'CFIELD-25' => array('conditional', 'contracts', 'equip_shifts_contract',    ''),
'CFIELD-26' => array('conditional', '',          '',                         'حدُّ ائتمانٍ للعميلِ لا يُعدَّل إلا بقرارٍ ماليٍّ معتمد'),
'CFIELD-27' => array('conditional', '',          '',                         'المستعملُ من الحدِّ والمتاحُ — يُشتقّان من الحدِّ ومن المستخلصات'),
'CFIELD-28' => array('conditional', 'clients',   'entity_type',              ''),
);

/* ═══ القراءةُ من السجلِّ — لا نسخَ عنوانٍ بيد ════════════════════════════ */
$q = $db->query("SELECT item_code, seq, title, detail, doc_ref
                   FROM gov_doc_registry WHERE family = 'CFIELD' ORDER BY seq");
if ($q === false) { exit("استعلامٌ فاشل: " . $db->error . "\n"); }
$rows = $q->fetch_all(MYSQLI_ASSOC);
if (count($rows) !== 28) { exit("السجلُّ يحمل " . count($rows) . " حقلًا لا 28 — شغّل u13_reverse_audit --sync\n"); }

/** أموجودٌ العمودُ فعلًا؟ — من `information_schema` لا من الإعلان. */
function colExists(\mysqli $db, $t, $c)
{
    if ($t === '' || $c === '') { return false; }
    $st = $db->prepare("SELECT 1 FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    if (!$st) { throw new RuntimeException($db->error); }
    $st->bind_param('ss', $t, $c);
    $st->execute();
    $ok = (bool) $st->get_result()->fetch_row();
    $st->close();
    return $ok;
}

$sql = "INSERT INTO fin_contract_fields
          (company_id, field_code, seq, title, obligation, condition_ar, rule_ar,
           home_table, home_column, resolve_state, owner_action, doc_ref, active)
        VALUES (0,?,?,?,?,?,?,?,?,?,?,?,1)
        ON DUPLICATE KEY UPDATE seq=VALUES(seq), title=VALUES(title),
          obligation=VALUES(obligation), condition_ar=VALUES(condition_ar), rule_ar=VALUES(rule_ar),
          home_table=VALUES(home_table), home_column=VALUES(home_column),
          resolve_state=VALUES(resolve_state), owner_action=VALUES(owner_action),
          doc_ref=VALUES(doc_ref)";
$st = $APPLY ? $db->prepare($sql) : null;
if ($APPLY && !$st) { exit("prepare: " . $db->error . "\n"); }

$live = 0; $gap = 0; $broken = array();
printf("%-11s %-13s %-40s %-34s %s\n", 'الرمز', 'الإلزام', 'العنوان', 'الموضعُ الحي', 'الحسم');
echo str_repeat('─', 118) . "\n";
foreach ($rows as $r) {
    $code = $r['item_code'];
    if (!isset($MAP[$code])) { $broken[] = $code . ' بلا قرارِ ربط'; continue; }
    list($ob, $tbl, $col, $act) = $MAP[$code];

    $exists = colExists($db, $tbl, $col);
    if ($tbl !== '' && !$exists) {
        /* ◆ موضعٌ مُعلَنٌ لا وجودَ له = خطأُ ربطٍ يُرفع، لا فجوةٌ تُسكت عنها. */
        $broken[] = $code . " يشير إلى {$tbl}.{$col} ولا وجودَ له";
    }
    $state = $exists ? 'live' : 'gap';
    if ($state === 'live') { $live++; } else { $gap++; }
    if ($state === 'gap' && $act === '') { $act = 'يُحدَّد موضعُ الحقلِ في القاعدةِ أو يُعلَن أنه خارجَ النظام'; }

    /* شرطُ الإلزامِ وحكمُ الوثيقةِ: كلاهما من `detail` — والتفريقُ بالصيغة. */
    $detail = (string) $r['detail'];
    $cond = ($ob === 'conditional') ? $detail : '';
    $rule = ($ob === 'conditional') ? '' : $detail;

    printf("%-11s %-13s %-40s %-34s %s\n", $code, $ob, mb_substr($r['title'], 0, 40),
           ($tbl !== '' ? $tbl . '.' . $col : '—'), $state === 'live' ? '✔ حيّ' : '◆ فجوة');

    if ($APPLY) {
        $seq = (int) $r['seq'];
        $ttl = mb_substr((string) $r['title'], 0, 300);
        $ref = (string) $r['doc_ref'];
        $st->bind_param('sisssssssss', $code, $seq, $ttl, $ob, $cond, $rule, $tbl, $col, $state, $act, $ref);
        if (!$st->execute()) { $broken[] = $code . ' — ' . $st->error; }
    }
}
if ($APPLY) { $st->close(); }

echo str_repeat('─', 118) . "\n";
printf("  الحقول 28 · بموضعٍ حيٍّ %d · فجوةٌ معلَنة %d%s\n", $live, $gap, $APPLY ? ' · كُتبت' : ' (معاينة — أضف --apply)');
$alwaysGap = 0;
foreach ($MAP as $c => $m) { if ($m[0] === 'always' && $m[1] === '') { $alwaysGap++; } }
printf("  ومن الإلزاميِّ دائمًا بلا موضع: %d — وهي ما يلزم المالكَ حسمُه\n", $alwaysGap);
if ($broken) { echo "\n  ✘ أخطاءُ ربط:\n"; foreach ($broken as $b) { echo "     $b\n"; } }
exit($broken ? 1 : 0);
