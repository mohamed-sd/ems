<?php
/**
 * tests/credit_debit_note_test.php — M-02
 * ═══════════════════════════════════════════════════════════════════════════
 * الإشعارُ الدائن/المدين (ENT-03 §3-⑥ · الدستور §3.1).
 *
 * ما يُثبته:
 *   ① **الأصلُ لا يُمسّ**: `claims` و`claim_lines` بايت-مطابقةٌ قبل الإشعار وبعده.
 *   ② الإشعارُ يشير إلى المستخلص **وسطرِه** — ومرجعُ سطرٍ من مستخلصٍ آخر يُرفض.
 *   ③ **الذمّةُ تتحرك بمقدار الإشعار وباتجاهه** — الدائنُ يُنقص والمدينُ يزيد.
 *   ④ **ولا إيرادَ مزدوج**: صفرُ قيدٍ في الدفتر · وحقيقةٌ واحدةٌ في الجذر
 *      تحمل `reverses_event_id` إلى فاتورتها.
 *   ⑤ **سببٌ ومستندٌ إلزامان** · والمبلغُ موجب · والاتجاهُ من قائمته.
 *   ⑥ **مَن أنشأ لا يعتمد** (403 بنيويًّا فوق المنح).
 *   ⑦ **العطالة**: مفتاحٌ مكرَّرٌ يُرجع القائمَ ولا ينشئ ثانيًا · وإجازةٌ مكررةٌ
 *      لا تحرّك الذمّةَ مرتين.
 *   ⑧ **لا رصيدَ دائنٌ من عدم**: دائنٌ يتجاوز المتبقي يُرفض.
 *   ⑨ لا إشعارَ على مستخلصٍ لم تصدر فاتورتُه · والمعتمَدُ لا يُلغى.
 *   ⑩ الصافي المشتقُّ = الفاتورة − الدائن + المدين.
 *
 * يبذر مستخلصَه وفاتورتَه وذمّتَه ويكنس الكلَّ — لا يمسّ مستخلصًا قائمًا.
 * التشغيل: php tests/credit_debit_note_test.php — رمز الخروج 0/1.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => 4, 'name' => 'cdnote test');
require_once dirname(__DIR__) . '/Contracts/note_helpers.php';

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
function info($m) { fwrite(STDOUT, "     · {$m}\n"); }

$conn = $GLOBALS['conn'];
$gate = ems_tenant_db();
$CO   = 4;
$MARK = 'CDN' . getmypid();
$PREP = 921;   // مُعِدٌّ وهمي
$APPR = 922;   // مُجيزٌ وهمي

// ── الكنس: الإسقاطُ قبل الجذر (FK على root_event_id) ──────────────────────
$cleanup = function () use ($conn, $MARK) {
    $conn->query("DELETE FROM credit_debit_notes WHERE claim_id IN
                    (SELECT id FROM (SELECT id FROM claims WHERE claim_no LIKE '{$MARK}%') x)");
    $conn->query("DELETE FROM claim_lines WHERE claim_id IN
                    (SELECT id FROM (SELECT id FROM claims WHERE claim_no LIKE '{$MARK}%') x)");
    $conn->query("DELETE FROM fin_receivables WHERE doc_ref LIKE 'INV-{$MARK}%'");
    $conn->query("DELETE FROM claims WHERE claim_no LIKE '{$MARK}%'");
    $orphan = "SELECT id FROM (SELECT id, source_ref FROM ems_business_events) be
                WHERE (be.source_ref LIKE '{$MARK}%' OR be.source_ref LIKE 'CDN-%')
                  AND NOT EXISTS (SELECT 1 FROM (SELECT note_no FROM credit_debit_notes) n
                                   WHERE n.note_no = be.source_ref)
                  AND NOT EXISTS (SELECT 1 FROM (SELECT claim_no FROM claims) c
                                   WHERE c.claim_no = be.source_ref)";
    $conn->query("DELETE FROM fin_financial_events WHERE root_event_id IN ({$orphan})");
    $conn->query("DELETE FROM ems_business_events WHERE id IN ({$orphan})");
};
$cleanup();
register_shutdown_function($cleanup);

fwrite(STDOUT, "\n══ M-02 — الإشعارُ الدائن/المدين ══\n");

// ── البذر: مستخلصٌ **مفوترٌ** بذمّته ────────────────────────────────────────
$cno = $MARK . '-C1';
$inv = 'INV-' . $MARK . '-C1';
$conn->query("INSERT INTO claims (company_id, claim_no, contract_id, client_id, project_id,
                period_from, period_to, currency, gross_amount, retention_amount, net_amount,
                tax_amount, invoice_no, invoice_date, state, submitted_by, version)
              VALUES ({$CO}, '{$cno}', 5, 1, 1, '2094-01-01','2094-01-31','USD',
                      1000.00, 0, 1000.00, 0, '{$inv}', '2094-02-01', 'invoiced', {$PREP}, 2)");
$CLAIM = (int) $conn->insert_id;
$conn->query("INSERT INTO claim_lines (company_id, claim_id, source_kind, source_ref,
                work_date, qty, unit_price, amount, dispute_flag)
              VALUES ({$CO}, {$CLAIM}, 'timesheet', 999001, '2094-01-05', 10, 100, 1000.00, 0)");
$LINE = (int) $conn->insert_id;
/* INJ-0036: الذمّةُ تشير إلى فاتورةٍ صادرةٍ حقيقية — والبذرةُ تشبه الواقع */
require_once __DIR__ . '/_source_doc_seed.php';
$TI = seed_source_invoice($conn, $CO, $inv, 1, 1000.00, 'USD', $CLAIM);
$conn->query("INSERT INTO fin_receivables (company_id, customer_entity_id, doc_type, doc_ref,
                source_doc_id, project_id, amount, collected, state)
              VALUES ({$CO}, 1, 'invoice', '{$inv}', {$TI}, 1, 1000.00, 0, 'open')");
$RECV = (int) $conn->insert_id;
$conn->query("UPDATE claims SET receivable_id={$RECV} WHERE id={$CLAIM}");
// حقيقةُ فوترةٍ للأصل — ليُعلَّق بها نسبُ العكس
require_once dirname(__DIR__) . '/app/Core/EventPublisher.php';
$INVEVR = \App\Core\EventPublisher::publishFact($conn, array(
    'event_key' => 'billing.claim.invoiced', 'category' => 'financial', 'source_module' => 'sales',
    'company_id' => $CO, 'entity_type' => 'claim', 'entity_id' => $CLAIM,
    'occurred_at' => gmdate('Y-m-d H:i:s'), 'created_by' => 1,
    'idempotency_key' => 'claim:' . $CLAIM, 'amount' => 1000.00, 'currency' => 'USD',
    'source_ref' => $cno, 'payload' => array('seed' => true),
));
// `publishFact` تُرجع مصفوفةً {id,…} لا رقمًا — گوتشا كلّفت تأكيدًا كاذبًا
$INVEV = (is_array($INVEVR) && isset($INVEVR['id'])) ? intval($INVEVR['id']) : 0;
check($CLAIM > 0 && $RECV > 0, "بُذر مستخلصٌ مفوتر #{$CLAIM} بفاتورة {$inv} وذمّةٍ 1000.00");

$claimBefore = $conn->query("SELECT * FROM claims WHERE id={$CLAIM}")->fetch_assoc();
$lineBefore  = $conn->query("SELECT * FROM claim_lines WHERE id={$LINE}")->fetch_assoc();
$ledgerBefore = (int) $conn->query("SELECT COUNT(*) c FROM fin_financial_events")->fetch_assoc()['c'];

// ═══ ⑤ المدخلاتُ الإلزامية ═══
head('⑤ سببٌ ومستندٌ إلزامان · والمبلغُ موجب');
$r = cdnote_create($gate, $CLAIM, 'credit', 100, '', 'DOC-1', null, null, $PREP);
check(empty($r['ok']) && $r['code'] === 422 && mb_strpos($r['reason'], 'سببُ الإشعار إلزامي') !== false,
    'بلا سبب: 422 — ' . $r['reason']);
$r = cdnote_create($gate, $CLAIM, 'credit', 100, 'مبالغةٌ في الفوترة', '', null, null, $PREP);
check(empty($r['ok']) && $r['code'] === 422 && mb_strpos($r['reason'], 'المستند') !== false,
    'وبلا مستند: 422 — ' . $r['reason']);
$r = cdnote_create($gate, $CLAIM, 'credit', -5, 'س', 'DOC-1', null, null, $PREP);
check(empty($r['ok']) && $r['code'] === 422, 'ومبلغٌ سالب: 422 — المبلغُ موجبٌ والاتجاهُ يحمل الإشارة');
$r = cdnote_create($gate, $CLAIM, 'refund', 100, 'س', 'DOC-1', null, null, $PREP);
check(empty($r['ok']) && $r['code'] === 422, 'واتجاهٌ خارج القائمة: 422');

// ═══ ② المرجعُ إلى الأصل وسطرِه ═══
head('② المرجعُ إلى المستخلص وسطرِه');
$r = cdnote_create($gate, $CLAIM, 'credit', 250, 'مبالغةٌ في كمية السطر', 'DOC-CR-1',
                   999999, null, $PREP);
check(empty($r['ok']) && $r['code'] === 422 && mb_strpos($r['reason'], 'ليس من هذا المستخلص') !== false,
    'سطرٌ من غير هذا المستخلص: 422 — ' . $r['reason']);

$cr = cdnote_create($gate, $CLAIM, 'credit', 250, 'مبالغةٌ في كمية السطر', 'DOC-CR-1',
                    $LINE, 'idem-cr-1', $PREP);
check(!empty($cr['ok']) && $cr['code'] === 201, 'وبالسطر الصحيح: أُنشئ ' . ($cr['note_no'] ?? '—'));
$NOTE = (int) $cr['note_id'];
$nrow = $conn->query("SELECT * FROM credit_debit_notes WHERE id={$NOTE}")->fetch_assoc();
check((int) $nrow['claim_id'] === $CLAIM && (int) $nrow['claim_line_id'] === $LINE,
    'ويحمل مرجعَي المستخلص والسطر');
check((string) $nrow['invoice_no'] === $inv, 'ورقمَ الفاتورة الأصلية: ' . $nrow['invoice_no']);

// ═══ ⑦ العطالة عند الإنشاء ═══
head('⑦ العطالة');
$dup = cdnote_create($gate, $CLAIM, 'credit', 250, 'مبالغةٌ في كمية السطر', 'DOC-CR-1',
                     $LINE, 'idem-cr-1', $PREP);
check(!empty($dup['ok']) && !empty($dup['existing']) && (int) $dup['note_id'] === $NOTE,
    'مفتاحٌ مكرَّرٌ يُرجع القائمَ ولا ينشئ ثانيًا: ' . ($dup['note_no'] ?? '—'));
$cnt = (int) $conn->query("SELECT COUNT(*) c FROM credit_debit_notes WHERE claim_id={$CLAIM}")
                  ->fetch_assoc()['c'];
check($cnt === 1, "وصفٌّ واحدٌ في القاعدة: {$cnt}");

// ═══ ⑥ فصلُ اليدين ═══
head('⑥ مَن أنشأ لا يعتمد');
$r = cdnote_approve($conn, $gate, $NOTE, $APPR);
check(empty($r['ok']) && $r['code'] === 422, 'لا يُجاز قبل الرفع للمالية: ' . $r['reason']);
$s = cdnote_submit($gate, $NOTE, $PREP);
check(!empty($s['ok']), 'المُعِدُّ يرفعه للمالية');
/* ◆ حارسان يحرسان الحكمَ نفسَه من بابين: `ems_no_self_approval` على
     `created_by` (وهو الأعلى)، وفصلُ اليدين على `submitted_by/prepared_by`.
     و`$PREP` هنا هو المُنشئُ **والمُعِدّ** معًا، فيسبقه الأعلى برسالتِه.
     فاشتراطُ نصٍّ بعينه يقيس **أيَّ بابٍ أُغلق** لا **أأُغلق أم لا** — والحكمُ
     المطلوبُ إنما هو الثاني. (كان هذا أحدَ الاختباراتِ الحمر.) */
$r = cdnote_approve($conn, $gate, $NOTE, $PREP);
check(empty($r['ok']) && $r['code'] === 403
      && (mb_strpos($r['reason'], 'من أعدّه') !== false
          || mb_strpos($r['reason'], 'من أنشأ') !== false),
    'والمُعِدُّ نفسُه لا يُجيزه: 403 — ' . $r['reason']);

// ═══ ③④ الذمّةُ تتحرك · ولا إيرادَ مزدوج ═══
head('③ الذمّةُ تتحرك بمقدار الإشعار — ④ ولا إيرادَ مزدوج');
$ap = cdnote_approve($conn, $gate, $NOTE, $APPR);
check(!empty($ap['ok']), 'ويدٌ ثانيةٌ تُجيزه');
$rc = $conn->query("SELECT amount, outstanding, state FROM fin_receivables WHERE id={$RECV}")->fetch_assoc();
check((float) $rc['amount'] == 750.00,
    'الذمّةُ نقصت 250 بالدائن: 1000 ← ' . $rc['amount']);
check((float) $rc['outstanding'] == 750.00, 'والمتبقي محسوبٌ من القاعدة: ' . $rc['outstanding']);

$ledgerAfter = (int) $conn->query("SELECT COUNT(*) c FROM fin_financial_events")->fetch_assoc()['c'];
check($ledgerAfter === $ledgerBefore,
    "و**صفرُ قيدٍ في الدفتر**: {$ledgerBefore} ← {$ledgerAfter} — الإشعارُ يفوتر عكسًا ولا يعترف");
$ev = $conn->query("SELECT id, event_key, reverses_event_id, amount FROM ems_business_events
                     WHERE entity_type='credit_debit_note' AND entity_id={$NOTE}")->fetch_all(MYSQLI_ASSOC);
check(count($ev) === 1, 'وحقيقةٌ واحدةٌ في الجذر: ' . count($ev));
check(count($ev) === 1 && $ev[0]['event_key'] === 'billing.note.credit_issued',
    'بمفتاحها الصحيح: ' . ($ev[0]['event_key'] ?? '—'));
check(count($ev) === 1 && (int) $ev[0]['reverses_event_id'] === (int) $INVEV,
    'وتحمل نسبَها إلى فاتورتها (`reverses_event_id`): #' . ($ev[0]['reverses_event_id'] ?? '—'));

// ═══ ① الأصلُ لا يُمسّ ═══
head('① الأصلُ لا يُمسّ');
$claimAfter = $conn->query("SELECT * FROM claims WHERE id={$CLAIM}")->fetch_assoc();
$lineAfter  = $conn->query("SELECT * FROM claim_lines WHERE id={$LINE}")->fetch_assoc();
$diff = array();
foreach ($claimBefore as $k => $v) { if ((string) $claimAfter[$k] !== (string) $v) { $diff[] = $k; } }
check(empty($diff), 'صفُّ المستخلص كما كان حرفًا بحرف' . ($diff ? ' (تغيّر: ' . implode(',', $diff) . ')' : ''));
$dl = array();
foreach ($lineBefore as $k => $v) { if ((string) $lineAfter[$k] !== (string) $v) { $dl[] = $k; } }
check(empty($dl), 'وسطرُه كذلك' . ($dl ? ' (تغيّر: ' . implode(',', $dl) . ')' : ''));

// ═══ ⑦ العطالة عند الإجازة ═══
head('⑦ إجازةٌ مكررةٌ لا تحرّك الذمّةَ مرتين');
$again = cdnote_approve($conn, $gate, $NOTE, $APPR);
check(!empty($again['ok']) && !empty($again['existing']), 'تُرجَع كما هي: ' . $again['reason']);
$rc2 = $conn->query("SELECT amount FROM fin_receivables WHERE id={$RECV}")->fetch_assoc();
check((float) $rc2['amount'] == 750.00, 'والذمّةُ لم تتحرك ثانيةً: ' . $rc2['amount']);
$ev2 = (int) $conn->query("SELECT COUNT(*) c FROM ems_business_events
                            WHERE entity_type='credit_debit_note' AND entity_id={$NOTE}")
                  ->fetch_assoc()['c'];
check($ev2 === 1, "ولا حقيقةَ ثانية: {$ev2}");

// ═══ ⑧ لا رصيدَ دائنٌ من عدم ═══
head('⑧ لا رصيدَ دائنٌ من عدم');
$over = cdnote_create($gate, $CLAIM, 'credit', 800, 'محاولةُ تجاوز', 'DOC-X', null, 'idem-over', $PREP);
check(empty($over['ok']) && $over['code'] === 422,
    'دائنٌ 800 والمتبقي 750: مرفوضٌ — ' . $over['reason']);
$fit = cdnote_create($gate, $CLAIM, 'credit', 750, 'ردُّ العمل كاملًا', 'DOC-Y', null, 'idem-fit', $PREP);
check(!empty($fit['ok']), 'ودائنٌ 750 بالضبط: مقبول');
cdnote_cancel($gate, (int) $fit['note_id'], $PREP);

// ═══ المدينُ يزيد ═══
head('③ب المدينُ يزيد الذمّة');
$db = cdnote_create($gate, $CLAIM, 'debit', 120, 'نقصٌ في احتساب بندٍ', 'DOC-DB-1', null, 'idem-db', $PREP);
check(!empty($db['ok']), 'أُنشئ المدين: ' . ($db['note_no'] ?? '—'));
cdnote_submit($gate, (int) $db['note_id'], $PREP);
$apd = cdnote_approve($conn, $gate, (int) $db['note_id'], $APPR);
check(!empty($apd['ok']), 'وأُجيز');
$rc3 = $conn->query("SELECT amount FROM fin_receivables WHERE id={$RECV}")->fetch_assoc();
check((float) $rc3['amount'] == 870.00, 'والذمّةُ زادت 120: 750 ← ' . $rc3['amount']);
$evd = $conn->query("SELECT event_key FROM ems_business_events
                      WHERE entity_type='credit_debit_note' AND entity_id=" . (int) $db['note_id'])
            ->fetch_assoc();
check($evd && $evd['event_key'] === 'billing.note.debit_issued', 'وبمفتاحٍ مختلفٍ عن الدائن');

// ═══ ⑩ الصافي المشتق ═══
head('⑩ الصافي المشتقُّ — لا مخزَّن');
$c2 = $conn->query("SELECT * FROM claims WHERE id={$CLAIM}")->fetch_assoc();
$net = cdnote_claim_net($gate, $c2);
check((float) $net['invoiced'] == 1000.00 && (float) $net['credit'] == 250.00
      && (float) $net['debit'] == 120.00 && (float) $net['net'] == 870.00,
    'الفاتورة 1000 − دائن 250 + مدين 120 = ' . $net['net']);
check((float) $net['net'] == (float) $rc3['amount'],
    'ويطابق الذمّةَ بالضبط: ' . $net['net'] . ' = ' . $rc3['amount']);

// ═══ ⑨ حدودٌ أخرى ═══
head('⑨ حدودٌ أخرى');
$r = cdnote_cancel($gate, $NOTE, $APPR);
check(empty($r['ok']) && mb_strpos($r['reason'], 'لا يُلغى') !== false,
    'المعتمَدُ لا يُلغى — يُقابَل بإشعارٍ عكسي: ' . $r['reason']);

$draftNo = $MARK . '-C2';
$conn->query("INSERT INTO claims (company_id, claim_no, contract_id, client_id, period_from,
                period_to, currency, gross_amount, net_amount, tax_amount, state, version)
              VALUES ({$CO}, '{$draftNo}', 5, 1, '2094-02-01','2094-02-28','USD', 500, 500, 0, 'draft', 1)");
$C2 = (int) $conn->insert_id;
$r = cdnote_create($gate, $C2, 'credit', 50, 'س', 'DOC-Z', null, null, $PREP);
check(empty($r['ok']) && $r['code'] === 422 && mb_strpos($r['reason'], 'صدرت فاتورتُه') !== false,
    'ولا إشعارَ على مستخلصٍ لم تصدر فاتورتُه: ' . $r['reason']);
$r = cdnote_create($gate, 99999999, 'credit', 50, 'س', 'DOC-Z', null, null, $PREP);
check(empty($r['ok']) && $r['code'] === 404, 'ومستخلصٌ لا وجودَ له: 404');

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
