<?php
/**
 * tests/workflow_chain_links_test.php — المستندُ يولّد تاليَه بمرجعٍ ظاهر
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0142 · INJ-0091 · INJ-0335 · INJ-0292
 *
 * · INJ-0142: «قبولُ عرضٍ يولّد عقدًا مسودةً يحمل `quotation_id`، وشاشةُ العقد
 *   تعرض رابطًا للعرض الأب، وبنودُ العقد تطابق بنودَ العرض».
 * · INJ-0091: «اعتمادُ طلبِ شراءٍ يُظهره في شاشة طلب العروض؛ والترسيةُ تُنشئ
 *   مسودةَ أمرِ شراءٍ تحمل `rfq_id` و`award_id` ويظهر المرجعُ الأبُ في كلا الشاشتين».
 * · INJ-0335: «لا يمكن حفظُ أمرِ شراءٍ بلا طلبٍ مرتبط، ولا بطلبٍ غيرِ معتمد».
 * · INJ-0292: «الدور ٢٧ يقترح خصمًا بمستندٍ مؤيدٍ فيظهر في صندوق الاعتماد
 *   بحالة pending … ودورٌ بلا منحةٍ يُرفض ٤٠٣».
 *
 * ◆ **والحقلُ الرابطُ محروسٌ في القاعدةِ لا في الواجهة**: قادحُ
 *   `trg_po_request_required` يردُّ أمرًا بلا طلبٍ **ولو كُتب من شاشةٍ أخرى**.
 * ◆ والوسمُ عائليٌّ ثابتٌ والكنسُ به · ويُفحص مُرجَعُ كلِّ حذف.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
ob_start(); require_once $ROOT . '/config.php'; ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/app/Services/Workflow/ChainLinkService.php';

use App\Services\Workflow\ChainLinkService as CHN;

$conn = $GLOBALS['conn'];
$CO   = 4;
$TAG  = 'CHN-TEST-FAMILY';
$PASS = 0; $FAIL = 0; $NOTES = array();
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ المستندُ يولّد تاليَه بمرجعٍ ظاهر');

$_SESSION['user'] = array('id' => 1, 'role' => '1', 'company_id' => $CO, 'name' => 'شاهد');
$gate = ems_tenant_db();

$sweep = function () use ($conn, $TAG) {
    /* الأبناءُ قبل الآباء — والمُرجَعُ يُفحص لأنَّ FK يردُّ صامتًا */
    $conn->query("DELETE FROM proc_order WHERE code LIKE '%{$TAG}%'");
    $conn->query("DELETE FROM supplier_rfqs WHERE rfq_no LIKE '%{$TAG}%' OR title LIKE '%{$TAG}%'");
    $conn->query("DELETE FROM contracts WHERE second_party LIKE '%{$TAG}%'");
    $conn->query("DELETE FROM quotations WHERE quotation_code LIKE '%{$TAG}%'");
    $conn->query("DELETE FROM proc_request WHERE code LIKE '%{$TAG}%'");
    $conn->query("DELETE FROM fin_dues WHERE period_ref LIKE '%{$TAG}%'");
    $left = 0;
    foreach (array('quotations' => 'quotation_code', 'proc_request' => 'code') as $t => $c) {
        $r = $conn->query("SELECT COUNT(*) FROM `{$t}` WHERE `{$c}` LIKE '%{$TAG}%'");
        if ($r && ($x = $r->fetch_row())) { $left += (int) $x[0]; }
    }
    return $left;
};
$ok($sweep() === 0, 'الكنسُ القبليُّ نظيفٌ بالعائلة');

/* ── ① البنيةُ: الحقولُ الرابطةُ قائمةٌ في القاعدة ─────────────────────────── */
$hasCol = function ($t, $c) use ($conn) {
    $r = $conn->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
    return (bool) ($r && $r->fetch_row());
};
$ok($hasCol('contracts', 'quotation_id'), '`contracts.quotation_id` قائمٌ — العقدُ يعرف عرضَه');
$ok($hasCol('supplier_rfqs', 'request_id'), '`supplier_rfqs.request_id` قائمٌ — العروضُ تعرف طلبَها');
$ok($hasCol('proc_order', 'rfq_id') && $hasCol('proc_order', 'award_id'),
    '`proc_order.rfq_id/award_id` قائمان — الأمرُ يعرف ترسيتَه');

/* ◆ **والقادحُ يُقاس بفعلِه لا بجدولِ `information_schema`**: قراءةُ
     `TRIGGERS` تحتاج امتيازًا قد لا يملكه حسابُ التطبيق، فترجع صفرًا على
     قادحٍ قائم. **فالجسُّ وظيفيٌّ**: يُحاول الإدراجُ ويُقاس الردّ (فقرة ④). */

/* ── ② عرضٌ مقبولٌ ⇒ عقدٌ مسودةٌ بمرجعِه ──────────────────────────────────── */
$qid = 0;
$st = $conn->prepare("INSERT INTO quotations (company_id, quotation_code, client_id, currency,
                                              amount_total, state, created_by, created_at)
                      VALUES (?, ?, NULL, 'SDG', 1000, 'مسودة', 1, NOW())");
if ($st) {
    $code = $TAG . '-Q1';
    $st->bind_param('is', $CO, $code);
    if ($st->execute()) { $qid = (int) $conn->insert_id; }
    $st->close();
}
$ok($qid > 0, "عرضُ سعرٍ للقياس (#{$qid}) بحالة «مسودة»");

$r1 = CHN::contractFromQuotation($conn, $gate, $CO, $qid, 1);
$ok(empty($r1['ok']) && (int) $r1['code'] === 422,
    '**وعرضٌ مسودةٌ لا يولّد عقدًا** — ولا التزامَ بلا قبول (' . (int) $r1['code'] . ')');

$conn->query("UPDATE quotations SET state = 'مقبول' WHERE id = {$qid}");
$r2 = CHN::contractFromQuotation($conn, $gate, $CO, $qid, 1);
$ok(!empty($r2['ok']), 'وقبولُ العرضِ يولّد عقدًا (' . (int) $r2['code'] . ')', $r2['reason']);
$cid = (int) ($r2['contract_id'] ?? 0);
if ($cid > 0) {
    $conn->query("UPDATE contracts SET second_party = '" . $conn->real_escape_string('عميلُ شاهدٍ ' . $TAG)
                . "' WHERE id = {$cid}");
    $rr = $conn->query("SELECT quotation_id, contract_status FROM contracts WHERE id = {$cid}");
    $row = $rr ? $rr->fetch_assoc() : null;
    $ok($row && (int) $row['quotation_id'] === $qid,
        '**والعقدُ يحمل `quotation_id` أباه** — فالسؤالُ «من أين جاء؟» له جواب');
    $ok($row && (string) $row['contract_status'] === 'مسودة',
        'ويُولد **مسودةً** لا نافذًا — فالتوليدُ لا يتخطى دورةَ الحياة');
}
$r3 = CHN::contractFromQuotation($conn, $gate, $CO, $qid, 1);
$ok(!empty($r3['ok']) && !empty($r3['existing']) && (int) $r3['contract_id'] === $cid,
    '**وتوليدٌ ثانٍ لا يُنشئ عقدًا ثانيًا** — العطالةُ بالمرجعِ لا بالوقت');

/* ── ③ طلبُ شراءٍ ⇒ طلبُ عروضٍ (والمعتمدُ وحدَه) ──────────────────────────── */
$prid = 0;
$st = $conn->prepare("INSERT INTO proc_request (company_id, code, state, op_classification, created_at)
                      VALUES (?, ?, 'مسودة', 'استهلاكية', NOW())");
if ($st) {
    $code = $TAG . '-PR1';
    $st->bind_param('is', $CO, $code);
    if ($st->execute()) { $prid = (int) $conn->insert_id; }
    $st->close();
}
$ok($prid > 0, "طلبُ شراءٍ للقياس (#{$prid}) بحالة «مسودة»");

$q1 = CHN::rfqFromRequest($conn, $gate, $CO, $prid, $TAG . ' عنوان', 1);
$ok(empty($q1['ok']) && (int) $q1['code'] === 422,
    '**وطلبٌ غيرُ معتمدٍ لا يُشتقُّ منه طلبُ عروض** (' . (int) $q1['code'] . ')');

$conn->query("UPDATE proc_request SET state = 'معتمد' WHERE id = {$prid}");
$q2 = CHN::rfqFromRequest($conn, $gate, $CO, $prid, $TAG . ' عنوان', 1);
$ok(!empty($q2['ok']), 'واعتمادُ الطلبِ يُشتقُّ منه طلبُ عروضٍ (' . (int) $q2['code'] . ')', $q2['reason']);
$rfqId = (int) ($q2['rfq_id'] ?? 0);
if ($rfqId > 0) {
    $rr = $conn->query("SELECT request_id FROM supplier_rfqs WHERE id = {$rfqId}");
    $row = $rr ? $rr->fetch_row() : null;
    $ok($row && (int) $row[0] === $prid, '**وطلبُ العروضِ يحمل طلبَ الشراءِ أباه**');
}

/* ── ④ ولا أمرَ شراءٍ بلا طلبٍ — القادحُ يردُّ ولو من كاتبٍ آخر ─────────────── */
$blocked = false;
$conn->query("INSERT INTO proc_order (company_id, code, state, created_at)
              VALUES ({$CO}, '{$TAG}-PO-NOREQ', 'مسودة', NOW())");
if ($conn->errno !== 0 && mb_strpos((string) $conn->error, 'PO-REQ-422') !== false) { $blocked = true; }
$ok($blocked, '**والقاعدةُ تردُّ أمرَ شراءٍ بلا طلبٍ برمزٍ** — ولو أُدرج من خارجِ الشاشة',
    'الخطأ: ' . mb_substr((string) $conn->error, 0, 60));

/* ── ⑤ اقتراحُ خصمٍ — بالمستندِ وحدَه ─────────────────────────────────────── */
$d1 = CHN::proposeDeduction($conn, $gate, $CO, array('person_id' => 1, 'amount' => 50, 'doc_ref' => ''), 1);
$ok(empty($d1['ok']) && (int) $d1['code'] === 422, '**ولا اقتراحَ خصمٍ بلا مستندٍ مؤيّد**');
$d2 = CHN::proposeDeduction($conn, $gate, $CO,
    array('person_id' => 1, 'amount' => 50, 'doc_ref' => $TAG . '-DOC'), 1);
$ok(!empty($d2['ok']), 'وبمستندٍ يمرُّ الاقتراح (' . (int) $d2['code'] . ')', $d2['reason']);
$did = (int) ($d2['deduction_id'] ?? 0);
if ($did > 0) {
    $conn->query("UPDATE fin_dues SET period_ref = '{$TAG}' WHERE id = {$did}");
    $rr = $conn->query("SELECT direction, settlement_state FROM fin_dues WHERE id = {$did}");
    $row = $rr ? $rr->fetch_assoc() : null;
    $ok($row && (string) $row['direction'] === 'debit' && (string) $row['settlement_state'] === 'pending',
        '**ويظهر معلَّقًا في صندوقِ الاعتماد** — ولا يصير نافذًا إلا بالسلّم');
}

/* ── ⑥ والشاشاتُ تنادي الخدمةَ فعلًا ─────────────────────────────────────── */
foreach (array(
    'Clients/quotations.php'            => 'contractFromQuotation',
    'Suppliers/rfq_requests.php'        => 'rfqFromRequest',
    'Procurement/rfq_compare_award.php' => 'orderFromAward',
    'Workforce/proposed_deductions.php' => 'proposeDeduction',
) as $scr => $fn) {
    $s = (string) @file_get_contents($ROOT . '/' . $scr);
    $ok($s !== '' && strpos($s, $fn) !== false, "و`{$scr}` تنادي `{$fn}`");
}
$po = (string) @file_get_contents($ROOT . '/Procurement/orders_proc.php');
$ok(strpos($po, 'PO-REQ-422') !== false && strpos($po, 'requestApproved') !== false,
    '**وشاشةُ أوامرِ الشراءِ ترفض بلا طلبٍ معتمدٍ برسالةٍ صريحة**');
$ok(strpos($po, 'proc_options_from_rows($request_option_rows') !== false
    && strpos($po, '— اختر طلبًا معتمدًا —') !== false,
    'وقائمةُ الطلبِ صارت «اختر طلبًا معتمدًا» — لا خيارَ «بلا طلب» يُختار');

/* ── ⑦ الكنسُ البعديّ ────────────────────────────────────────────────────── */
$leftAfter = $sweep();
$ok($leftAfter === 0, 'والكنسُ البعديُّ نظيفٌ — لا صفَّ شاهدٍ يبقى', "بقي {$leftAfter}");
if ($leftAfter > 0) { $NOTES[] = "لم يُكنس {$leftAfter} صفًّا — يُعلَن ولا يُخفى"; }

$say('');
foreach ($NOTES as $n) { $say('  ◆ ' . $n); }
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
