<?php
/**
 * tests/procurement_three_way_match_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 * حارس المطابقة الثلاثية (UX-09 §8.2 · FES §4.1 · §7).
 * «لا استحقاقَ بلا مطابقة»: أمرُ الشراء × سندُ الاستلام × فاتورةُ المورد.
 *
 * الحرّاسُ الستة:
 *   ① البوابة: لا مطابقةَ قبل الاستلام النهائي
 *   ② المطابقة النظيفة → دَينُ المورد يُفتح بحدثه
 *   ③ فرقُ السعر فوق السماح → var_pending **بلا دَين**
 *   ④ فرقُ الكمية → var_pending (لا سماحَ في الكميات — قطعةٌ ناقصةٌ نقص)
 *   ⑤ العطالة: فاتورةٌ تُسجَّل مرتين لا تُقيَّد مرتين
 *   ⑥ هويةُ الطرف: `proc_supplier` لا `supplier` — سجلّان بمعرّفاتٍ متصادمة
 *
 * يبذر أوامرَه ويكنسها — لا يمسّ أمرًا حقيقيًّا.
 * التشغيل: php tests/procurement_three_way_match_test.php — رمز الخروج 0/1.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';

while (ob_get_level() > 0) { ob_end_clean(); }

// سياقُ المستأجر قبل أول استعمالٍ للبوابة (scopedQuery ترفض بلا جلسة — گوتشا مقيسة)
$_SESSION['user'] = array('id' => 1, 'role' => '16', 'company_id' => 4, 'name' => '3way test');

require_once dirname(__DIR__) . '/Procurement/proc_helpers.php';

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$CO = 4; $ACTOR = 1; $SUP = 3;      // مورد مشترياتٍ حقيقي: «الوطنية للفلاتر»
$MARK = 'M3W' . getmypid();
$seeded = array();

$scalar = function ($sql) use ($conn) {
    $r = $conn->query($sql); $v = $r ? $r->fetch_row() : null; return $v ? $v[0] : 0;
};

/** أمرٌ ببندٍ واحدٍ واستلامٍ كامل (أو ناقصٍ بتمرير $recvQty). */
$mkOrder = function ($amount, $qty, $recvQty, $state = 'استلام نهائي') use ($conn, $CO, $ACTOR, $SUP, $MARK, &$seeded) {
    static $i = 0; $i++;
    $code = 'TST-' . $MARK . '-' . $i;
    $st = $conn->prepare(
        "INSERT INTO proc_order (company_id, code, supplier_id, project_id, currency, fx_rate,
             total_amount, base_amount, state, received_pct, created_by, created_at)
         VALUES (?, ?, ?, 2, 'SDG', 1, ?, ?, ?, 100, ?, NOW())");
    $st->bind_param('isiddsi', $CO, $code, $SUP, $amount, $amount, $state, $ACTOR);
    $st->execute(); $oid = $conn->insert_id; $st->close();
    $conn->query("INSERT INTO proc_order_line (company_id, order_id, item_name, qty, unit_price, subtotal, created_at)
                  VALUES ({$CO}, {$oid}, 'صنفٌ اختباري', {$qty}, " . ($amount / max($qty, 1)) . ", {$amount}, NOW())");
    if ($recvQty > 0) {
        $conn->query("INSERT INTO proc_receipt_custody (company_id, code, holder_name, receipt_date, supplier_id,
                      order_id, state, created_by, created_at)
                      VALUES ({$CO}, 'TST-RC-{$MARK}-{$i}', 'مستلِم', CURDATE(), {$SUP}, {$oid}, 'مستلَمة', {$ACTOR}, NOW())");
        $rc = $conn->insert_id;
        $conn->query("INSERT INTO proc_receipt_line (company_id, custody_id, item_name, qty, created_at)
                      VALUES ({$CO}, {$rc}, 'صنفٌ اختباري', {$recvQty}, NOW())");
    }
    $seeded[] = $oid;
    return array($oid, $code);
};

$cleanup = function () use ($conn, &$seeded, $MARK) {
    foreach ($seeded as $oid) {
        $conn->query("DELETE FROM fin_dues WHERE period_ref = 'PO-" . intval($oid) . "'");
        $conn->query("DELETE FROM fin_financial_events WHERE entity_type IN ('proc_order','proc_order_invoice') AND entity_id=" . intval($oid));
        $conn->query("DELETE FROM ems_business_events WHERE entity_type IN ('proc_order','proc_order_invoice') AND entity_id=" . intval($oid));
        $conn->query("DELETE rl FROM proc_receipt_line rl JOIN proc_receipt_custody rc ON rc.id=rl.custody_id WHERE rc.order_id=" . intval($oid));
        $conn->query("DELETE FROM proc_receipt_custody WHERE order_id=" . intval($oid));
        $conn->query("DELETE FROM proc_order_line WHERE order_id=" . intval($oid));
        $conn->query("DELETE FROM proc_order WHERE id=" . intval($oid));
    }
};
register_shutdown_function($cleanup);

$dues0   = (int) $scalar("SELECT COUNT(*) FROM fin_dues");
$ledger0 = (int) $scalar("SELECT COUNT(*) FROM fin_financial_events");

// ═══ حدُّ السماح ═══════════════════════════════════════════════════════════
head('حدُّ السماح: ±2٪ أو 100 أيُّهما أصغر');

check(abs(proc_match_tolerance(1000) - 20.0) < 0.01, 'أمرٌ بـ1000 → السماح 20 (النسبةُ أصغر)');
check(abs(proc_match_tolerance(1000000) - 100.0) < 0.01, 'أمرٌ بمليون → السماح 100 لا 20,000 (المبلغُ أصغر)');
check(abs(proc_match_tolerance(5000) - 100.0) < 0.01, 'نقطةُ التعادل عند 5000 → 100');

// ═══ ① البوابة ═════════════════════════════════════════════════════════════
head('① لا مطابقةَ قبل الاستلام النهائي');

list($earlyId, ) = $mkOrder(4000.00, 10, 10, 'مؤكَّد');
$r = proc_match_invoice($conn, $earlyId, 'INV-EARLY', date('Y-m-d'), 4000.00, $ACTOR);
check($r['status'] === 'skipped', 'أمرٌ «مؤكَّد» → لا مطابقة (' . $r['status'] . ')');
check(strpos($r['reason'], 'الاستلام') !== false, 'والسببُ معلَنٌ للمستخدم: لا مطابقةَ قبل الاستلام');
check((int) $scalar("SELECT COUNT(*) FROM fin_dues") === $dues0, 'صفر ذمّةٍ جديدة');

// ═══ ② المطابقة النظيفة ═══════════════════════════════════════════════════
head('② مطابقةٌ نظيفة → دَينُ المورد');

list($okId, $okCode) = $mkOrder(4000.00, 10, 10);
$r = proc_match_invoice($conn, $okId, 'INV-' . $MARK . '-OK', date('Y-m-d'), 4000.00, $ACTOR);
check($r['status'] === 'matched', 'فاتورةٌ مطابقةٌ تمامًا → matched');
check(abs($r['qty_var']) < 0.0001 && abs($r['price_var']) < 0.01, 'صفرُ فرقٍ في الكمية والسعر');
check(!empty($r['due_id']), '★ فُتح دَينُ المورد في الذمّة');

$due = $conn->query("SELECT * FROM fin_dues WHERE id=" . intval($r['due_id']))->fetch_assoc();
if ($due) {
    check((string)$due['party_type'] === 'proc_supplier',
        '★ نوعُ الطرف `proc_supplier` — لا يُخلط بمورد الآليات');
    check(intval($due['party_ref']) === $SUP, 'والمرجعُ مورد المشتريات نفسُه');
    check((string)$due['due_type'] === 'purchase' && (string)$due['direction'] === 'credit',
        'نوعُ الذمّة «شراء» واتجاهُها دائن (دَينٌ علينا)');
    check(abs((float)$due['amount'] - 4000.00) < 0.01, 'بقيمة الفاتورة');
    check((string)$due['settlement_state'] === 'pending', 'وحالتُها «بانتظار السداد»');
}
$po = $conn->query("SELECT match_state, invoice_no, matched_at FROM proc_order WHERE id=" . intval($okId))->fetch_assoc();
check($po && $po['match_state'] === 'matched' && !empty($po['matched_at']),
    'وحالةُ المطابقة على الأمر صارت matched بوقتها');

$ev = $conn->query("SELECT * FROM fin_financial_events WHERE entity_type='proc_order_invoice' AND entity_id=" . intval($okId))->fetch_assoc();
check($ev !== null, 'وحدثُ الاستحقاق منشورٌ في الدفتر');
if ($ev) {
    check(intval($ev['project_id']) === 2, 'بمشروعه');
    check(strpos((string)$ev['source_ref'], 'INV-') === 0, 'ومرجعُه رقمُ الفاتورة لا رقمُ الأمر');
}

// ═══ ③ فرقُ السعر ═════════════════════════════════════════════════════════
head('③ فرقُ سعرٍ فوق السماح → وقفٌ بلا دَين');

$duesBefore = (int) $scalar("SELECT COUNT(*) FROM fin_dues");
list($varId, ) = $mkOrder(4000.00, 10, 10);
$r = proc_match_invoice($conn, $varId, 'INV-' . $MARK . '-VAR', date('Y-m-d'), 4500.00, $ACTOR);
check($r['status'] === 'var_pending', 'فاتورةٌ بـ4500 مقابل أمرٍ بـ4000 → var_pending');
check(abs($r['price_var'] - 500.00) < 0.01, 'وفرقُ السعر 500 معلَن');
check(abs($r['tolerance'] - 80.0) < 0.01, 'والسماحُ 80 (2٪ من 4000) معلَنٌ معه');
check($r['due_id'] === null, '★ ولا دَينَ — الفرقُ ينتظر قرارًا موثَّقًا');
check((int) $scalar("SELECT COUNT(*) FROM fin_dues") === $duesBefore, 'صفر ذمّةٍ جديدة');
$po = $conn->query("SELECT match_state, invoice_amount FROM proc_order WHERE id=" . intval($varId))->fetch_assoc();
check($po && $po['match_state'] === 'var_pending', 'وحالةُ الأمر var_pending');
check($po && abs((float)$po['invoice_amount'] - 4500.00) < 0.01,
    'والفاتورةُ محفوظةٌ بقيمتها رغم الوقف (لا تضيع)');

// فرقٌ صغيرٌ ضمن السماح يمرّ
list($smallId, ) = $mkOrder(4000.00, 10, 10);
$r = proc_match_invoice($conn, $smallId, 'INV-' . $MARK . '-SM', date('Y-m-d'), 4050.00, $ACTOR);
check($r['status'] === 'matched', 'فرقُ 50 ضمن سماح 80 → يمرّ مطابَقًا');
check(!empty($r['due_id']), 'ويُفتح دَينُه');

// ═══ ④ فرقُ الكمية ════════════════════════════════════════════════════════
head('④ فرقُ الكمية — لا سماحَ فيه');

$duesBefore = (int) $scalar("SELECT COUNT(*) FROM fin_dues");
list($qtyId, ) = $mkOrder(4000.00, 10, 8);        // وصل 8 من 10
$r = proc_match_invoice($conn, $qtyId, 'INV-' . $MARK . '-QTY', date('Y-m-d'), 4000.00, $ACTOR);
check($r['status'] === 'var_pending', 'وصل 8 من 10 والفاتورةُ بالكامل → var_pending');
check(abs($r['qty_var'] + 2.0) < 0.0001, 'وفرقُ الكمية −2 معلَن');
check($r['due_id'] === null, '★ ولا دَينَ — لا تُدفع قطعةٌ لم تصل');
check((int) $scalar("SELECT COUNT(*) FROM fin_dues") === $duesBefore, 'صفر ذمّةٍ جديدة');

// ═══ ⑤ العطالة ════════════════════════════════════════════════════════════
head('⑤ فاتورةٌ تُسجَّل مرتين لا تُقيَّد مرتين');

$duesBefore  = (int) $scalar("SELECT COUNT(*) FROM fin_dues");
$ledgerBefore = (int) $scalar("SELECT COUNT(*) FROM fin_financial_events");
$r2 = proc_match_invoice($conn, $okId, 'INV-' . $MARK . '-OK', date('Y-m-d'), 4000.00, $ACTOR);
check($r2['status'] === 'matched', 'إعادةُ المطابقة تعيد matched');
check((int) $scalar("SELECT COUNT(*) FROM fin_dues") === $duesBefore, '★ صفر ذمّةٍ جديدة (المفتاح PO-{id})');
check((int) $scalar("SELECT COUNT(*) FROM fin_financial_events") === $ledgerBefore, 'وصفر حدثٍ جديد');
check((int) $scalar("SELECT COUNT(*) FROM fin_dues WHERE period_ref='PO-" . intval($okId) . "'") === 1,
    'صفُّ ذمّةٍ واحدٌ للأمر بعد مطابقتين');

// ═══ ⑥ المدخلاتُ الناقصة ══════════════════════════════════════════════════
head('⑥ حراسةُ المدخلات');

$r = proc_match_invoice($conn, $okId, '', date('Y-m-d'), 4000.00, $ACTOR);
check($r['status'] === 'skipped', 'فاتورةٌ بلا رقم → ترفض');
$r = proc_match_invoice($conn, $okId, 'INV-X', date('Y-m-d'), 0, $ACTOR);
check($r['status'] === 'skipped', 'فاتورةٌ بقيمة صفر → ترفض');
$r = proc_match_invoice($conn, 99999999, 'INV-X', date('Y-m-d'), 100, $ACTOR);
check($r['status'] === 'skipped', 'أمرٌ غيرُ موجودٍ → skipped بلا رمي');

// ═══ نموذج القيدين ════════════════════════════════════════════════════════
head('نموذجُ القيدين: مصروفٌ عند الاستلام · دَينٌ عند الفاتورة');

$expenseRow = $conn->query("SELECT COUNT(*) FROM fin_financial_events WHERE entity_type='proc_order' AND entity_id=" . intval($okId))->fetch_row()[0];
$payableRow = $conn->query("SELECT COUNT(*) FROM fin_financial_events WHERE entity_type='proc_order_invoice' AND entity_id=" . intval($okId))->fetch_row()[0];
check((int)$payableRow === 1, 'حدثُ الاستحقاق واحدٌ (من الفاتورة)');
check((int)$expenseRow <= 1, 'وحدثُ المصروف واحدٌ على الأكثر (من الاستلام) — لا مصروفَ مضاعف');

$cleanup();
check((int) $scalar("SELECT COUNT(*) FROM fin_dues") === $dues0, 'بعد الكنس: الذمّةُ كما كانت');
check((int) $scalar("SELECT COUNT(*) FROM fin_financial_events") === $ledger0, 'والدفترُ كما كان');

fwrite(STDOUT, "\n" . str_repeat('═', 46) . "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
