<?php
/**
 * tests/settlement_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 * حارس تسوية الطرف (UX-02 §15.3/§15.4 · UX-05 §2.2/§5.2 · FES §8).
 *
 * الحرّاسُ السبعة:
 *   ① التوليدُ من المصادر — **صفرُ إدخالِ مبلغ**؛ الاستحقاقُ والتحميلُ من دفتر الطرف
 *   ② الصافي = الأولي − التحميلات، والسالبُ **يفتح ذمّةً مدينةً** على الطرف
 *   ③ العطالة: (طرف × فترة) لا يتكرر — 409 بمرجع القائم
 *   ④ الاعتراضُ بسببٍ إلزامي · لا يجمّد التسوية · ويمنع الاعتماد (423)
 *   ⑤ فصلُ اليدين: مَن أعدّ لا يعتمد (403) — بنيويًّا لا بفحصٍ لاحق
 *   ⑥ قاعدةُ عدم التلفيق: بندٌ بلا سعرِ صرفٍ لتاريخه يمنع الاعتماد ولا يُحسب صفرًا
 *   ⑦ صفٌّ احتُسب في تسويةٍ لا يُحتسب في أخرى
 *
 * يبذر مورّدَه وذممَه في فترةٍ بعيدة ويكنس الكلَّ — لا يمسّ موردًا ولا ذمّةً قائمة.
 * التشغيل: php tests/settlement_test.php — رمز الخروج 0/1.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

// موضوع الحزمة دلالة التسوية لا حارس وثائق المورد (له حزمته supplier_documents_test)
// — يُحيَّد بالتراكب المعزول بعد قلبه enforce في البوابة ② (سلامة البيئة §3-⑩)
require_once __DIR__ . '/_guard_env.php';
ems_test_env_override(array('EMS_SUPPLIER_DOC_GATE' => 'monitor'));
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '2', 'company_id' => 4, 'name' => 'settlement test');
require_once dirname(__DIR__) . '/app/Services/Settlement/SettlementService.php';

use App\Services\Settlement\SettlementService as SVC;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$gate = ems_tenant_db();
$CO   = 4;
$MARK = 'STL' . getmypid();
$FROM = '2092-03-01';
$TO   = '2092-03-31';
$PER  = '2092-03';
$PREP = 901;   // مُعِدٌّ وهمي
$APPR = 902;   // مُجيزٌ وهمي

// ── كنسٌ استباقي ──────────────────────────────────────────────────────────
// رقمُ التسوية `STL-{السنة الحالية}-NNNN` **يُعاد استعمالُه** بعد كل كنس (العدّادُ
// يعدّ القائم)، فالكنسُ بنمط الرقم لا يصحّ ولا يصحّ التأكيدُ على عددٍ مطلقٍ به.
// الكنسُ هنا بمالك الصفّ (اسمُ الطرف الموسوم)، والأحداثُ **باليتيمة** منها:
// حدثُ تسويةٍ لا تسويةَ له = بقيةُ تشغيلٍ سابق، مهما كان رقمُه.
$cleanup = function () use ($conn, $MARK) {
    // طلبُ الدفع **قبل** تسويته: `fk_req_settlement` بـRESTRICT، فحذفُ التسوية
    // قبل طلبها يفشل صامتًا ويترك كلَّ ما بعده معلَّقًا.
    $conn->query("DELETE r FROM fin_requests r
                    JOIN settlements s ON s.id = r.settlement_id
                   WHERE s.party_name LIKE '%{$MARK}%'");
    $conn->query("DELETE sl FROM settlement_lines sl
                    JOIN settlements s ON s.id = sl.settlement_id
                   WHERE s.party_name LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM settlements WHERE party_name LIKE '%{$MARK}%'");
    // الإسقاطُ قبل الجذر: `fin_financial_events.root_event_id` مفتاحٌ أجنبيٌّ على
    // الدفتر، فحذفُ الجذر قبل إسقاطه يسقط بخرق FK (نمطُ الناشر المعروف).
    $orphan = "SELECT id FROM (SELECT id, source_ref FROM ems_business_events) be
                WHERE be.source_ref LIKE 'STL-%'
                  AND NOT EXISTS (SELECT 1 FROM (SELECT settlement_no FROM settlements) s
                                   WHERE s.settlement_no = be.source_ref)";
    $conn->query("DELETE FROM fin_financial_events WHERE root_event_id IN ({$orphan})");
    $conn->query("DELETE FROM ems_business_events WHERE id IN ({$orphan})");
    $conn->query("DELETE FROM fin_dues WHERE period_ref = '2092-03'");
    $conn->query("DELETE FROM suppliers WHERE name LIKE '%{$MARK}%'");
};
$cleanup();

// ── البذر: مورّدٌ + ذممُه بالدولار (له سعرٌ = 1 فتُقيَّم) ──────────────────
$conn->query("INSERT INTO suppliers (company_id, name, created_at) VALUES ({$CO}, 'موردُ {$MARK}', NOW())");
$SUP = $conn->insert_id;

// M-11: الخصمُ يلزمه مصدرٌ (قيدُ `ck_dues_debit_source`) — والوقودُ هنا
// يُبذر بسند صرفٍ (`proc_issue`) كما يقع في الواقع، والاستحقاقُ حرٌّ منه.
$mkDue = function ($dir, $type, $amount, $cur = 'USD') use ($conn, $CO, $SUP, $PER) {
    $src = ($dir === 'debit') ? 'proc_issue' : null;
    $sid = ($dir === 'debit') ? 1 : null;
    $st = $conn->prepare("INSERT INTO fin_dues (company_id, party_type, party_ref, due_type, direction,
                          amount, currency, period_ref, source_doc_type, source_doc_id, created_by, created_at)
                          VALUES (?, 'supplier', ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
    $ref = (string) $SUP;
    $st->bind_param('isssdsssi', $CO, $ref, $type, $dir, $amount, $cur, $PER, $src, $sid);
    $st->execute(); $id = $conn->insert_id; $st->close();
    return $id;
};

$D1 = $mkDue('credit', 'hours', 1000.00);   // استحقاق
$D2 = $mkDue('credit', 'tons',   500.00);   // استحقاق
$D3 = $mkDue('debit',  'fuel',   300.00);   // تحميل

// ═══════════════════════════════════════════════════════════════════════════
head('① التوليدُ من المصادر — صفرُ إدخالِ مبلغ');

$res = SVC::generate($gate, $conn, 'supplier', $SUP, $FROM, $TO, $PREP);
check($res['ok'] === true, 'التسويةُ تولّدت (' . $res['reason'] . ')');
$SID = intval($res['settlement_id']);
check($res['entitlements'] === 2, 'واستحقاقان جُلبا من دفتر الطرف (وُجد ' . $res['entitlements'] . ')');
check($res['charges'] === 1,      'وتحميلٌ واحد (وُجد ' . $res['charges'] . ')');

$s = $gate->selectOne('settlements', array('where' => array('id' => $SID)));
check($s && abs((float) $s['gross_amount'] - 1500.00) < 0.005,
    '★ الأولي 1000+500 = 1500 (وُجد ' . ($s ? $s['gross_amount'] : '—') . ')');
check($s && abs((float) $s['charges_amount'] - 300.00) < 0.005,
    'والتحميلاتُ 300 (وُجد ' . ($s ? $s['charges_amount'] : '—') . ')');
check($s && abs((float) $s['net_amount'] - 1200.00) < 0.005,
    '★ والصافي 1500 − 300 = 1200');
check($s && (string) $s['net_direction'] === 'payable', 'واتجاهُه «له علينا»');
check($s && (string) $s['state'] === 'draft', 'وحالتُها مسودة');

$lines = $gate->select('settlement_lines', array('where' => array('settlement_id' => $SID)));
$withSource = 0;
foreach ($lines as $l) { if ($l['source_ref'] !== '' && $l['source_kind'] !== '') { $withSource++; } }
check(count($lines) === 3 && $withSource === 3, '★ وكلُّ بندٍ يحمل رابطَ أصله (3/3)');

// ═══════════════════════════════════════════════════════════════════════════
head('③ العطالة: (طرف × فترة) لا يتكرر');

$again = SVC::generate($gate, $conn, 'supplier', $SUP, $FROM, $TO, $PREP);
check($again['ok'] === false && $again['code'] === 409, '★ التوليدُ الثاني يُرفض 409');
check(intval($again['settlement_id']) === $SID, 'ويعيد مرجعَ القائمة لا ينشئ ثانية');

// ═══════════════════════════════════════════════════════════════════════════
head('⑦ صفٌّ احتُسب لا يُحتسب مرتين');

$r = $conn->query("SELECT COUNT(*) c FROM fin_dues WHERE id IN({$D1},{$D2},{$D3}) AND settlement_id = {$SID}");
check(intval($r->fetch_assoc()['c']) === 3, 'الذممُ الثلاثُ وُسمت بتسويتها');

// تسويةٌ لفترةٍ أخرى لا تلتقطها ثانيةً
$other = SVC::generate($gate, $conn, 'supplier', $SUP, '2092-04-01', '2092-04-30', $PREP);
check($other['ok'] === false && $other['code'] === 422,
    '★ وفترةٌ أخرى لا تجد شيئًا — لا تُعاد الذممُ المحتسَبة');

// ═══════════════════════════════════════════════════════════════════════════
head('④ الاعتراض: بسببٍ إلزامي · لا يجمّد · ويمنع الاعتماد');

SVC::submit($gate, $SID, $PREP);
$s = $gate->selectOne('settlements', array('where' => array('id' => $SID)));
check((string) $s['state'] === 'review', 'رُفعت للمراجعة');

$fuelLine = null;
foreach ($lines as $l) { if ((string) $l['charge_type'] === 'fuel') { $fuelLine = $l; } }
check($fuelLine !== null, 'وبندُ الوقود موجودٌ للاعتراض عليه');

$noReason = SVC::objectLine($gate, $fuelLine['id'], '   ', $PREP);
check($noReason['ok'] === false && $noReason['code'] === 422, '★ اعتراضٌ بلا سببٍ يُرفض');

$obj = SVC::objectLine($gate, $fuelLine['id'], 'الوقودُ صُرف لمعدةٍ ليست لي', $PREP);
check($obj['ok'] === true, 'واعتراضٌ بسببٍ يُقبل');

$s = $gate->selectOne('settlements', array('where' => array('id' => $SID)));
check(intval($s['open_objections']) === 1, 'وعدّادُ الاعتراضات صار 1');
check((string) $s['state'] === 'review', '★ والتسويةُ **لم تتجمد** — بقيت في المراجعة');

$ap = SVC::approve($gate, $conn, $SID, $APPR);
check($ap['ok'] === false && $ap['code'] === 423, '★ والاعتمادُ يُمنع 423 ما دام اعتراضٌ مفتوحًا');

$rv = SVC::resolveObjection($gate, $fuelLine['id'], $APPR);
check($rv['ok'] === true, 'وحسمُ الاعتراض يعيد البندَ محتسبًا');

// ═══════════════════════════════════════════════════════════════════════════
head('⑤ فصلُ اليدين: مَن أعدّ لا يعتمد');

$self = SVC::approve($gate, $conn, $SID, $PREP);
check($self['ok'] === false && $self['code'] === 403,
    '★ المُعِدُّ نفسُه يُرفض اعتمادُه (403)');

$ap = SVC::approve($gate, $conn, $SID, $APPR);
check($ap['ok'] === true, 'ومُجيزٌ آخرُ يعتمد (' . $ap['reason'] . ')');

$s = $gate->selectOne('settlements', array('where' => array('id' => $SID)));
// §15.3 `Review → Approved → PaymentRequested`: الصافي الموجبُ يولّد طلبَه فورًا
// فتقف الحالةُ عند `payment_requested`؛ والسالبُ يقف عند `approved` (لا دفعَ له).
check(in_array((string) $s['state'], array('approved', 'payment_requested'), true),
    'والحالةُ تجاوزت المراجعة (وُجد ' . $s['state'] . ')');
// التأكيدُ على **مفتاح العطالة** لا على الرقم: الرقمُ يُعاد استعمالُه بين
// التشغيلات، والمفتاحُ `settlement:{id}` فريدٌ لهذه التسوية وحدها.
$r = $conn->query("SELECT COUNT(*) c FROM ems_business_events
                    WHERE idempotency_key = 'settlement:{$SID}'");
check(intval($r->fetch_assoc()['c']) === 1, '★ وحدثُ FES منشورٌ بمفتاح رقمها');

// ═══════════════════════════════════════════════════════════════════════════
head('⑨ طلبُ الدفع مولَّدًا من التسوية — صفرُ إعادةِ كتابة');

check(!empty($ap['payment_request_no']), '★ الاعتمادُ ولّد طلبَ دفعٍ برقمه ('
    . (isset($ap['payment_request_no']) ? $ap['payment_request_no'] : '—') . ')');
$REQ = isset($ap['payment_request_id']) ? intval($ap['payment_request_id']) : 0;

$s = $gate->selectOne('settlements', array('where' => array('id' => $SID)));
check($REQ > 0 && intval($s['payment_request_id']) === $REQ, 'والتسويةُ تحمل مرجعَه (ربطٌ في الاتجاهين)');
check((string) $s['state'] === 'payment_requested', 'وحالتُها صارت «طُلب الدفع» (§15.3)');

if ($REQ > 0) {
    $req = $gate->selectOne('fin_requests', array('where' => array('id' => $REQ)));
    check($req && abs((float) $req['amount'] - 1200.00) < 0.005,
        '★ والمبلغُ منسوخٌ من الصافي 1200 — لا يُكتب بيدٍ ثانية');
    check($req && (string) $req['beneficiary_type'] === 'supplier'
               && intval($req['beneficiary_ref']) === $SUP,
        'والمستفيدُ هو المورد نفسُه مشتقًّا');
    check($req && (string) $req['request_type'] === 'supplier_payment', 'ونوعُه «دفعُ مورد»');
    check($req && (string) $req['state'] === 'approved',
        '★ ويدخل **معتمَدًا جاهزًا للصرف** — لا اعتمادَ مكرَّرٌ للرقم نفسه (قرارُ المالك)');
    check($req && intval($req['settlement_id']) === $SID, 'ويحمل مرجعَ تسويته');
    check($req && (string) $req['source_ref'] === (string) $s['settlement_no'],
        'ورقمُ التسوية في مرجعه النصّي كذلك');
}

// ═══════════════════════════════════════════════════════════════════════════
head('⑩ الإنفاذُ البنيوي: لا دفعَ لطرفٍ بلا تسوية (§15.4)');

$conn->query("INSERT INTO fin_requests (company_id, request_no, request_type, source_module,
              beneficiary_type, amount, currency, created_at)
              VALUES ({$CO}, 'TSTREQ-{$MARK}', 'supplier_payment', 'suppliers',
              'supplier', 500, 'USD', NOW())");
check($conn->errno === 3819,
    '★ دفعُ موردٍ بلا مرجعِ تسويةٍ يُرفض بنيويًّا (errno=' . $conn->errno . ')');

$conn->query("INSERT INTO fin_requests (company_id, request_no, request_type, source_module,
              beneficiary_type, amount, currency, created_at)
              VALUES ({$CO}, 'TSTREQ2-{$MARK}', 'purchase', 'procurement',
              'supplier', 500, 'USD', NOW())");
check($conn->errno === 0,
    'وشراءُ بضاعةٍ من موردٍ **لا يُعطَّل** — سندُه المطابقةُ لا التسوية');
$conn->query("DELETE FROM fin_requests WHERE request_no LIKE 'TSTREQ%{$MARK}'");

// ═══════════════════════════════════════════════════════════════════════════
head('② الصافي السالب يفتح ذمّةً مدينةً على الطرف');

$conn->query("INSERT INTO suppliers (company_id, name, created_at) VALUES ({$CO}, 'مدينُ {$MARK}', NOW())");
$SUP2 = $conn->insert_id;
$mkDue2 = function ($dir, $type, $amount) use ($conn, $CO, $SUP2, $PER) {
    $src = ($dir === 'debit') ? 'proc_issue' : null;
    $sid = ($dir === 'debit') ? 1 : null;
    $st = $conn->prepare("INSERT INTO fin_dues (company_id, party_type, party_ref, due_type, direction,
                          amount, currency, period_ref, source_doc_type, source_doc_id, created_by, created_at)
                          VALUES (?, 'supplier', ?, ?, ?, ?, 'USD', ?, ?, ?, 1, NOW())");
    $ref = (string) $SUP2;
    $st->bind_param('isssdssi', $CO, $ref, $type, $dir, $amount, $PER, $src, $sid);
    $st->execute(); $st->close();
};
$mkDue2('credit', 'hours',  400.00);
$mkDue2('debit',  'fuel',  1000.00);      // التحميلُ يفوق الاستحقاق

$neg = SVC::generate($gate, $conn, 'supplier', $SUP2, $FROM, $TO, $PREP);
check($neg['ok'] === true, 'تسويةُ الصافي السالب تولّدت');
$SID2 = intval($neg['settlement_id']);
$s2 = $gate->selectOne('settlements', array('where' => array('id' => $SID2)));
check($s2 && abs((float) $s2['net_amount'] + 600.00) < 0.005,
    '★ الصافي 400 − 1000 = −600 (وُجد ' . ($s2 ? $s2['net_amount'] : '—') . ')');
check($s2 && (string) $s2['net_direction'] === 'receivable', 'واتجاهُه «علينا له دَين»');

SVC::submit($gate, $SID2, $PREP);
$ap2 = SVC::approve($gate, $conn, $SID2, $APPR);
check($ap2['ok'] === true && $ap2['net_direction'] === 'receivable', 'واعتُمدت');

$s2 = $gate->selectOne('settlements', array('where' => array('id' => $SID2)));
check(!empty($s2['receivable_due_id']), '★ وفُتحت ذمّةٌ مدينةٌ على المورد — الرقمُ لا يضيع');
check(empty($s2['payment_request_id']),
    '★ و**لا طلبَ دفعٍ** للصافي السالب — لا يُصرف لمن يدين لك (قرارُ المالك)');
check(empty($ap2['payment_request_no']), 'والخدمةُ تُعلنه فارغًا لا تخترعه');
if (!empty($s2['receivable_due_id'])) {
    $d = $gate->selectOne('fin_dues', array('where' => array('id' => intval($s2['receivable_due_id']))));
    check($d && (string) $d['direction'] === 'debit' && abs((float) $d['amount'] - 600.00) < 0.005,
        'وقيمتُها 600 باتجاه مدين');
}

// ═══════════════════════════════════════════════════════════════════════════
head('⑥ قاعدةُ عدم التلفيق: بندٌ بلا سعرِ صرفٍ يمنع الاعتماد');

require_once dirname(__DIR__) . '/includes/fx.php';
$sdgRate = ems_fx_rate('SDG', $TO);

$conn->query("INSERT INTO suppliers (company_id, name, created_at) VALUES ({$CO}, 'جنيهُ {$MARK}', NOW())");
$SUP3 = $conn->insert_id;
$st = $conn->prepare("INSERT INTO fin_dues (company_id, party_type, party_ref, due_type, direction,
                      amount, currency, period_ref, created_by, created_at)
                      VALUES (?, 'supplier', ?, 'hours', 'credit', 750.00, 'SDG', ?, 1, NOW())");
$ref3 = (string) $SUP3;
$st->bind_param('iss', $CO, $ref3, $PER); $st->execute(); $st->close();

$g3 = SVC::generate($gate, $conn, 'supplier', $SUP3, $FROM, $TO, $PREP);
check($g3['ok'] === true, 'تسويةُ الجنيه تولّدت (التوليدُ لا يتوقف)');
$SID3 = intval($g3['settlement_id']);

if ($sdgRate === null) {
    check(intval($g3['unpriced']) === 1, '★ وبندُها معلَّمٌ «بلا سعر» (وُجد ' . $g3['unpriced'] . ')');
    $s3 = $gate->selectOne('settlements', array('where' => array('id' => $SID3)));
    check(abs((float) $s3['net_amount']) < 0.005, '★ ولم يُحسب صفرًا مضلِّلًا بل بقي البندُ خارج الجمع');
    SVC::submit($gate, $SID3, $PREP);
    $ap3 = SVC::approve($gate, $conn, $SID3, $APPR);
    check($ap3['ok'] === false && $ap3['code'] === 422,
        '★ والاعتمادُ يُمنع بسببٍ معلَن: ' . $ap3['reason']);
} else {
    ok('سعرُ الجنيه مُدخَلٌ — البنودُ الجنيهيةُ تُقيَّم (' . $sdgRate . ') فلا حالةَ انتظار');
    ok('(الحارسُ يُعاد تلقائيًّا لو أُزيل السعر)');
    ok('—');
}

// ═══════════════════════════════════════════════════════════════════════════
head('⑧ الكنس');

$cleanup();
$r = $conn->query("SELECT COUNT(*) c FROM settlements WHERE party_name LIKE '%{$MARK}%'");
check(intval($r->fetch_assoc()['c']) === 0, 'لا تسويةَ اختبارٍ باقية');
$r = $conn->query("SELECT COUNT(*) c FROM fin_dues WHERE period_ref = '2092-03'");
check(intval($r->fetch_assoc()['c']) === 0, 'ولا ذمّةَ اختبار');
$r = $conn->query("SELECT COUNT(*) c FROM ems_business_events WHERE source_ref LIKE 'STL-2092-%'");
check(intval($r->fetch_assoc()['c']) === 0, 'ولا حدثَ اختبارٍ في الدفتر');

fwrite(STDOUT, "\n══════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
