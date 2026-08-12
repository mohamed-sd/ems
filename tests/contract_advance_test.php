<?php
/**
 * tests/contract_advance_test.php — M-01
 * ═══════════════════════════════════════════════════════════════════════════
 * دفترُ الدفعة المقدَّمة (ق-19 · الدستور §3.1).
 *
 * ما يُثبته:
 *   ① **النزيفُ توقّف فورًا**: عقدٌ بلا قبضٍ مسجَّل ⇒ استقطاعُه **صفر** —
 *      لا استردادَ لدَينٍ لم يُقرَض، بلا انتظار قرار.
 *   ② **السقفُ يعمل**: الاستقطاعُ = الأقلُّ من (النسبة · الرصيد)، ويتوقف عند
 *      التصفير — فلا خصمَ إلى الأبد.
 *   ③ **القبضُ حقيقةٌ بلا قيدٍ في الدفتر**: `publishFact` لا `postLedger` —
 *      «سلفةٌ تُستردّ لا إيرادٌ يُعترف به».
 *   ④ **المبلغُ لا يُشتق ولا يُقدَّر** · والمستندُ إلزام · والعطالةُ بالسند.
 *   ⑤ **الرصيدُ من مصدرَين قائمَين**: المقبوضُ − المستهلَك، والملغى خارجَ العدّ.
 *   ⑥ **تقريرُ المطابقة** يُعلن العقدَ 5 (12.50 استردادًا بلا قبض) ولا يمسّه.
 *   ⑦ لا يُلغى قبضٌ استُهلك منه (409) — وإلا صار الرصيدُ سالبًا.
 *   ⑧ جدولُ الاستهلاك مقروءٌ من المصدر القائم لا من جدولٍ موازٍ.
 *
 * يبذر عقدَه ودفعاتِه ويكنس الكلَّ — ولا يمسّ عقدًا قائمًا ولا بندَ مستخلص.
 * التشغيل: php tests/contract_advance_test.php — رمز الخروج 0/1.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => 4, 'name' => 'advance test');
require_once dirname(__DIR__) . '/Contracts/advance_helpers.php';
require_once dirname(__DIR__) . '/Contracts/claim_helpers.php';

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
function info($m) { fwrite(STDOUT, "     · {$m}\n"); }

$conn = $GLOBALS['conn'];
$gate = ems_tenant_db();
$CO   = 4;
$MARK = 'ADVT' . getmypid();

/* بعائلةِ الوسمِ `ADVT` لا بوسمِ الشوطِ وحدَه: الوسمُ بـ`getmypid()` يجعل كلَّ شوطٍ
   أعمى عمّا تركه سابقٌ أخفق كنسُه، فتتراكم مستخلصاتٌ ومقدَّماتٌ تُربك عقدَ القياس. */
$FAMILY = 'ADVT';
$cleanup = function () use ($conn, $MARK, $FAMILY) {
    if ($FAMILY === '') { return; }   // وسمٌ فارغٌ ⇒ لا كنسَ (صونًا للبيانات)
    $conn->query("DELETE FROM contract_advances WHERE doc_ref LIKE '{$FAMILY}%'");
    $conn->query("DELETE FROM claim_lines WHERE claim_id IN
                    (SELECT id FROM (SELECT id FROM claims WHERE claim_no LIKE '{$FAMILY}%') x)");
    $conn->query("DELETE FROM claims WHERE claim_no LIKE '{$FAMILY}%'");
    $orphan = "SELECT id FROM (SELECT id, entity_type, entity_id FROM ems_business_events) be
                WHERE be.entity_type='contract_advance'
                  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM contract_advances) a
                                   WHERE a.id = be.entity_id)";
    $conn->query("DELETE FROM fin_financial_events WHERE root_event_id IN ({$orphan})");
    $conn->query("DELETE FROM ems_business_events WHERE id IN ({$orphan})");
};
$cleanup();
register_shutdown_function($cleanup);

fwrite(STDOUT, "\n══ M-01 — دفترُ الدفعة المقدَّمة ══\n");

// عقدُ الاختبار: قائمٌ بعملةٍ معروفة (لا يُنشأ عقدٌ وهميٌّ — نستعمل عقدًا حقيقيًّا
// **ولا نكتب فيه شيئًا**؛ كلُّ ما نكتبه صفوفُ دفعاتٍ موسومةٌ تُكنس)
/* ◆ **عيبُ عزلٍ مقيس**: كان يأخذ **أولَ** عقدٍ بعملةٍ معروفةٍ ثم يفترض في
     الفحصِ الأولِ أنه «عقدٌ بلا قبضٍ مسجَّل ⇒ استقطاعُه صفر». والواقعُ أن
     العقدَ الأولَ عليه مقدَّمٌ حقيقيٌّ (استقطاعُه 100 لا صفر، ورصيدُه يحمل
     كسرَ 0.50)، فسقط الفحصُ الأولُ ثم تساقط كلُّ ما بُني على رصيدٍ مفترَض.
     ⇒ يُشترط **صفرُ مقدَّمٍ** على العقدِ المختار: الفاحصُ يقيس ما يدّعيه. */
/* و**عقدان** لا عقدٌ واحد: الأولُ للسقفِ والاستقطاع، والثاني لشذوذِ تقريرِ
   المطابقةِ في ⑥ — فالشذوذُ يُبذَر ويُكنَس ولا يُستعار من عطبٍ حيٍّ قد يزول. */
$Cs = array();
$rs = $conn->query("SELECT c.id, c.price_currency_contract, c.advance_recovery_pct
                      FROM contracts c
                     WHERE c.company_id={$CO} AND c.price_currency_contract IS NOT NULL
                       AND NOT EXISTS (SELECT 1 FROM contract_advances a
                                        WHERE a.contract_id = c.id AND COALESCE(a.is_deleted,0)=0)
                     ORDER BY c.id LIMIT 2");
while ($rs && ($x = $rs->fetch_assoc())) { $Cs[] = $x; }
if (count($Cs) < 2) {
    fwrite(STDOUT, "\n⚠ يلزم **عقدان** بعملةٍ معروفةٍ وبلا مقدَّمٍ مسجَّل: أحدُهما للسقف\n"
        . "   والآخرُ لشذوذِ تقرير المطابقة — ولا يُقاس على عقدٍ عليه مقدَّمٌ قائم.\n");
    exit(1);
}
$C = $Cs[0]; $CID = (int) $C['id'];
$C2 = $Cs[1]; $CID2 = (int) $C2['id'];
info("عقدُ القياس #{$CID} · عقدُ الشذوذ #{$CID2} · عملتُهما {$C['price_currency_contract']}");
$ledgerBefore = (int) $conn->query("SELECT COUNT(*) c FROM fin_financial_events")->fetch_assoc()['c'];

// ═══ ① النزيفُ توقّف ═══
head('① عقدٌ بلا قبضٍ مسجَّل ⇒ استقطاعُه صفر');
$d0 = advance_recovery_due($gate, $CID, 1000.0, 10.0);
check((float) $d0['due'] === 0.0, 'الاستقطاع صفر رغم نسبةِ 10٪ على أساسٍ 1000: ' . $d0['due']);
check(mb_strpos($d0['reason'], 'لا دفعةَ مقدَّمةً مقبوضةً') !== false,
    'وبسببه المعلَن: ' . $d0['reason']);
check((float) $d0['by_pct'] === 100.0,
    'ومقدارُ النسبة محفوظٌ للمقارنة (100.00) — يُعلَن الفرقُ ولا يُخفى');

// ═══ ④ المدخلاتُ الإلزامية ═══
head('④ المبلغُ لا يُقدَّر · والمستندُ إلزام');
$r = advance_record($conn, $gate, $CID, 0, '2094-01-01', 'X', '', 1);
check(empty($r['ok']) && $r['code'] === 422, 'مبلغٌ صفر: 422 — ' . $r['reason']);
$r = advance_record($conn, $gate, $CID, 500, '2094-01-01', '', '', 1);
check(empty($r['ok']) && $r['code'] === 422 && mb_strpos($r['reason'], 'سند القبض') !== false,
    'وبلا سند: 422 — ' . $r['reason']);
$r = advance_record($conn, $gate, $CID, 500, '01/01/2094', $MARK . '-D0', '', 1);
check(empty($r['ok']) && $r['code'] === 422, 'وتاريخٌ غير صالح: 422');
$r = advance_record($conn, $gate, 99999999, 500, '2094-01-01', $MARK . '-D0', '', 1);
check(empty($r['ok']) && $r['code'] === 404, 'وعقدٌ لا وجودَ له: 404');

// ═══ ③ القبضُ حقيقةٌ بلا قيد ═══
head('③ القبضُ حقيقةٌ في الجذر — بلا قيدٍ في الدفتر');
$a1 = advance_record($conn, $gate, $CID, 500.00, '2094-01-01', $MARK . '-D1', 'سلفةُ تعبئة', 1);
check(!empty($a1['ok']) && $a1['code'] === 201, 'سُجّل القبضُ 500.00: ' . ($a1['advance_no'] ?? '—'));
$AID = (int) $a1['advance_id'];
$row = $conn->query("SELECT * FROM contract_advances WHERE id={$AID}")->fetch_assoc();
check((float) $row['amount'] == 500.00 && (string) $row['state'] === 'recorded', 'بحالته ومبلغه');
check((int) $row['event_id'] > 0, 'وبحدثه في الجذر: #' . $row['event_id']);
$ledgerAfter = (int) $conn->query("SELECT COUNT(*) c FROM fin_financial_events")->fetch_assoc()['c'];
check($ledgerAfter === $ledgerBefore,
    "و**صفرُ قيدٍ في الدفتر**: {$ledgerBefore} ← {$ledgerAfter} — سلفةٌ تُستردّ لا إيرادٌ يُعترف به");
$ev = $conn->query("SELECT event_key, amount FROM ems_business_events
                     WHERE entity_type='contract_advance' AND entity_id={$AID}")->fetch_assoc();
check($ev && $ev['event_key'] === 'contract.advance.received', 'وبمفتاحه: ' . ($ev['event_key'] ?? '—'));

// ═══ ④ب العطالة ═══
head('④ب العطالة — سندٌ واحدٌ لا يُسجَّل مرتين');
$dup = advance_record($conn, $gate, $CID, 500.00, '2094-01-01', $MARK . '-D1', '', 1);
check(!empty($dup['ok']) && !empty($dup['existing']) && (int) $dup['advance_id'] === $AID,
    'السندُ نفسُه يُرجع القائمَ: ' . ($dup['advance_no'] ?? '—'));
$n = (int) $conn->query("SELECT COUNT(*) c FROM contract_advances WHERE doc_ref='{$MARK}-D1'")
                ->fetch_assoc()['c'];
check($n === 1, "وصفٌّ واحدٌ لا اثنان: {$n}");

// ═══ ② السقفُ يعمل ═══
head('② السقفُ — الأقلُّ من النسبة والرصيد، ويتوقف عند التصفير');
$b = advance_balance($gate, $CID);
check((float) $b['received'] == 500.00 && (float) $b['balance'] == 500.00,
    'الرصيدُ بعد القبض: ' . $b['balance']);
$d1 = advance_recovery_due($gate, $CID, 1000.0, 10.0);
check((float) $d1['due'] === 100.0 && empty($d1['capped']),
    'أساسٌ 1000 × 10٪ = 100 (دون الرصيد): ' . $d1['due']);
$d2 = advance_recovery_due($gate, $CID, 9000.0, 10.0);
check((float) $d2['due'] === 500.0 && !empty($d2['capped']),
    'وأساسٌ 9000 × 10٪ = 900 ⇒ مقصوصٌ عند الرصيد 500: ' . $d2['due']);
check(mb_strpos($d2['reason'], 'مقصوصٌ عند الرصيد') !== false, 'وبسببه المعلَن: ' . $d2['reason']);

// استهلاكٌ حقيقيٌّ عبر بند مستخلص
$cno = $MARK . '-C1';
$conn->query("INSERT INTO claims (company_id, claim_no, contract_id, period_from, period_to,
                currency, gross_amount, net_amount, tax_amount, state, version)
              VALUES ({$CO}, '{$cno}', {$CID}, '2094-01-01','2094-01-31','USD', 1000, 900, 0, 'draft', 1)");
$CL = (int) $conn->insert_id;
$conn->query("INSERT INTO claim_lines (company_id, claim_id, source_kind, source_ref,
                work_date, qty, unit_price, amount, dispute_flag)
              VALUES ({$CO}, {$CL}, 'advance_recovery', {$CID}, '2094-01-31', 1, -300, -300.00, 0)");
$b2 = advance_balance($gate, $CID);
check((float) $b2['recovered'] == 300.00 && (float) $b2['balance'] == 200.00,
    'وبعد استهلاكِ 300: الرصيد ' . $b2['balance']);
$d3 = advance_recovery_due($gate, $CID, 9000.0, 10.0);
check((float) $d3['due'] === 200.0, 'والاستقطاعُ التالي مقصوصٌ عند 200: ' . $d3['due']);

// التصفير — **بمستخلصٍ ثانٍ**: `uq_claim_line_src (claim_id, source_kind,
// source_ref)` يمنع بندَي استردادٍ في المستخلص الواحد، وهو الصواب — بندُ
// استقطاعٍ واحدٌ لكل فترة، والاستهلاكُ يتراكم عبر الفترات لا داخلها.
$cno2 = $MARK . '-C2';
$conn->query("INSERT INTO claims (company_id, claim_no, contract_id, period_from, period_to,
                currency, gross_amount, net_amount, tax_amount, state, version)
              VALUES ({$CO}, '{$cno2}', {$CID}, '2094-02-01','2094-02-28','USD', 1000, 800, 0, 'draft', 1)");
$CL2 = (int) $conn->insert_id;
$conn->query("INSERT INTO claim_lines (company_id, claim_id, source_kind, source_ref,
                work_date, qty, unit_price, amount, dispute_flag)
              VALUES ({$CO}, {$CL2}, 'advance_recovery', {$CID}, '2094-02-28', 1, -200, -200.00, 0)");
$b3 = advance_balance($gate, $CID);
check((float) $b3['balance'] == 0.00, 'وبتمام الاستهلاك: الرصيد ' . $b3['balance']);
$d4 = advance_recovery_due($gate, $CID, 9000.0, 10.0);
check((float) $d4['due'] === 0.0 && mb_strpos($d4['reason'], 'استُردّت بالكامل') !== false,
    '**والخصمُ يتوقف** — لا استردادَ بعد التصفية: ' . $d4['reason']);

// ═══ ⑤ الملغى خارجَ العدّ ═══
head('⑤ الرصيدُ من مصدرَين — والملغى خارجَ العدّ');
$a2 = advance_record($conn, $gate, $CID, 700.00, '2094-03-01', $MARK . '-D2', '', 1);
$AID2 = (int) $a2['advance_id'];
check((float) advance_balance($gate, $CID)['balance'] == 700.00, 'قبضٌ ثانٍ 700 ⇒ الرصيد 700');
$conn->query("UPDATE contract_advances SET state='cancelled' WHERE id={$AID2}");
check((float) advance_balance($gate, $CID)['balance'] == 0.00,
    'وبإلغائه يخرج من الرصيد فورًا: ' . advance_balance($gate, $CID)['balance']);
$conn->query("UPDATE contract_advances SET state='recorded' WHERE id={$AID2}");

// ═══ ⑦ لا يُلغى قبضٌ استُهلك منه ═══
head('⑦ لا يُلغى قبضٌ استُهلك منه');
$rc = advance_cancel($gate, $AID2, 1);
check(empty($rc['ok']) && $rc['code'] === 409 && mb_strpos($rc['reason'], 'لا تُلغى بعد الاستهلاك') !== false,
    '409 — ' . $rc['reason']);
$stillOn = $conn->query("SELECT state FROM contract_advances WHERE id={$AID2}")->fetch_assoc();
check((string) $stillOn['state'] === 'recorded', 'ولا صفَّ تغيّر — الرفضُ قبل الكتابة');

// ═══ ⑧ جدولُ الاستهلاك ═══
head('⑧ جدولُ الاستهلاك من المصدر القائم');
$sch = advance_schedule($gate, $CID);
check(count($sch) === 2, 'بندان مستهلَكان بمستخلصيهما: ' . count($sch));
$sum = 0.0;
foreach ($sch as $s) { $sum += -(float) $s['amount']; }
check(round($sum, 2) == 500.00, 'ومجموعُهما يطابق المستهلَك: ' . $sum);
$tbl = $conn->query("SHOW TABLES LIKE 'advance_consumption'")->num_rows;
check($tbl === 0, 'وصفرُ جدولِ استهلاكٍ موازٍ — المصدرُ واحد');

/* ═══ ⑥ تقريرُ المطابقة — **على شذوذٍ يبذره الفاحصُ ويكنسه** ═══════════════════
   كان مثبَّتًا على أثرِ عطبٍ حيٍّ (العقد 5 · استردادُ 12.50 بلا قبض)، وذاك الأثرُ
   زال من وجهَين مستقلَّين — وكلٌّ منهما وحدَه يكفي لإسقاطِه:
     · `claim_lines` أُفرِغ بالكامل (صفرُ صفٍّ · العدّادُ يقول إن 3147 صفًّا وُجدت
       يومًا)، فالسطرُ الذي يعدُّه الفحصُ (‎-12.50) لم يبقَ له وجود.
     · والعقدُ 5 صار عليه **قبضٌ مسجَّلٌ** فلا شذوذَ فيه أصلًا.
   والخصلةُ المُختبَرةُ ملكُ **الدالةِ** لا ملكُ صفٍّ حيّ: أن التقريرَ يُعلن
   استردادًا بلا قبضٍ، ويصنّفه `no_receipt`، **ولا يمسّ البند**. فتُبذَر بالوسمِ
   وتُكنَس به — فلا يعود الفحصُ رهينةَ بياناتٍ تتغيّر بحقٍّ. */
head('⑥ تقريرُ المطابقة — يُعلن ولا يمسّ');
check((float) advance_balance($gate, $CID2)['received'] == 0.00,
    "عقدُ الشذوذ #{$CID2} بلا قبضٍ مسجَّل — شرطُ القياس");
$cnoX = $MARK . '-C3';
$conn->query("INSERT INTO claims (company_id, claim_no, contract_id, period_from, period_to,
                currency, gross_amount, net_amount, tax_amount, state, version)
              VALUES ({$CO}, '{$cnoX}', {$CID2}, '2094-04-01','2094-04-30','USD', 100, 87.50, 0, 'draft', 1)");
$CLX = (int) $conn->insert_id;
$conn->query("INSERT INTO claim_lines (company_id, claim_id, source_kind, source_ref,
                work_date, qty, unit_price, amount, dispute_flag)
              VALUES ({$CO}, {$CLX}, 'advance_recovery', {$CID2}, '2094-04-30', 1, -12.50, -12.50, 0)");
check($CLX > 0, "بُذر شذوذٌ: استردادُ 12.50 على عقدٍ بلا قبض (مستخلص #{$CLX})");

$rep = advance_reconciliation($gate);
$anom = null;
foreach ($rep as $r) { if ((int) $r['contract_id'] === $CID2) { $anom = $r; } }
check($anom !== null, "العقدُ {$CID2} معلَنٌ في التقرير — استردادٌ بلا قبض");
check($anom !== null && (float) $anom['recovered'] == 12.50
      && (float) $anom['received'] == 0.00 && (float) $anom['gap'] == 12.50,
    'باسترداده 12.50 وصفرِ قبضٍ مسجَّل · الفرقُ ' . ($anom ? $anom['gap'] : '—'));
check($anom !== null && $anom['kind'] === 'no_receipt'
      && mb_strpos($anom['label'], 'ينتظر قرار المالك') !== false,
    'وبوسمه: ' . ($anom['label'] ?? '—'));
$untouched = $conn->query("SELECT COUNT(*) c FROM claim_lines
                            WHERE claim_id={$CLX} AND source_kind='advance_recovery'
                              AND amount=-12.50")->fetch_assoc();
check((int) $untouched['c'] === 1, 'وبندُ الـ12.50 **قائمٌ كما هو** — لا مسحَ ولا تعديل');

// وعقدُ الاختبار منضبطٌ فلا يظهر
$mine = null;
foreach ($rep as $r) { if ((int) $r['contract_id'] === $CID && $CID !== 5) { $mine = $r; } }
check($mine === null || $CID === 5, 'وعقدُ الاختبار منضبطٌ فلا يظهر في التقرير');

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
