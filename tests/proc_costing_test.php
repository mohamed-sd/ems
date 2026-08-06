<?php
/**
 * tests/proc_costing_test.php — حارسُ طبقة التكاليف (إضافات الدور 16 · 2026-08-06)
 * ═══════════════════════════════════════════════════════════════════════════
 * «التكلفةُ تُشتق من دفتر الحركات لا تُراكم في متغير»:
 *   ① تسعيرُ الاستلام من سطر الأمر × fx (معادل الدفاتر)
 *   ② الترسملُ الوصولي: البوليصةُ ترفع تكلفةَ الوحدة بنصيبها — والأرشفةُ تعيدها
 *   ③ المتوسطُ المرجح من الاستلامات المُكلَّفة حصرًا (التاريخُ غيرُ المسعَّر خارجه)
 *   ④ إعادةُ الاحتساب idempotent — تكرارُها لا يغيّر شيئًا
 *
 * يبذر ويكنس — لا يمسّ صفًّا حقيقيًّا. التشغيل: php tests/proc_costing_test.php
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$_SESSION['user'] = array('id' => 1, 'role' => '16', 'company_id' => 4, 'name' => 'costing test');

require_once dirname(__DIR__) . '/Procurement/proc_helpers.php';
require_once dirname(__DIR__) . '/app/Services/Procurement/ProcCostingService.php';

use App\Services\Procurement\ProcCostingService as Cost;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$CO = 4;
$gate = proc_gate(false);
$ids = array('item' => 0, 'order' => 0, 'custody' => 0, 'landed' => array());

$cleanup = function () use ($conn, &$ids) {
    if ($ids['custody']) {
        $conn->query("DELETE FROM proc_stock_move WHERE ref_type='proc_receipt_custody' AND ref_id={$ids['custody']} AND company_id=4");
        $conn->query("DELETE FROM proc_receipt_line WHERE custody_id={$ids['custody']}");
        $conn->query("DELETE FROM proc_receipt_custody WHERE id={$ids['custody']}");
    }
    foreach ($ids['landed'] as $lid) { $conn->query("DELETE FROM proc_landed_cost WHERE id=$lid"); }
    if ($ids['order']) {
        $conn->query("DELETE FROM proc_order_line WHERE order_id={$ids['order']}");
        $conn->query("DELETE FROM proc_order WHERE id={$ids['order']}");
    }
    if ($ids['item']) { $conn->query("DELETE FROM proc_item WHERE id={$ids['item']}"); }
};
register_shutdown_function($cleanup);

// ═══ البذر: صنفٌ + أمرٌ (10 × 50 · fx=2 ⇒ معادل السطر 100/وحدة) + استلامٌ كامل ═══
head('البذر');
$conn->query("INSERT INTO proc_item (company_id, code, name, material_nature, status, is_deleted, created_at)
              VALUES (4, 'TST-COST', 'صنف حزام التكاليف', 'قابل للتخزين', 1, 0, NOW())");
$ids['item'] = intval($conn->insert_id);
$conn->query("INSERT INTO proc_order (company_id, code, supplier_id, state, currency, fx_rate, total_amount, base_amount, fin_approval_ref, created_at)
              VALUES (4, 'TST-PO-COST', 3, 'استلام نهائي', 'USD', 2, 500, 1000, 'TEST', NOW())");
$ids['order'] = intval($conn->insert_id);
$conn->query("INSERT INTO proc_order_line (company_id, order_id, item_id, item_name, qty, unit_price, subtotal)
              VALUES (4, {$ids['order']}, {$ids['item']}, 'صنف حزام التكاليف', 10, 50, 500)");
$conn->query("INSERT INTO proc_receipt_custody (company_id, code, holder_name, receipt_date, order_id, warehouse_id, expected_destination, state, is_deleted, created_at)
              VALUES (4, 'TST-RC-COST', 'حزام', CURDATE(), {$ids['order']}, 2, 'مخزن', 'مستلَمة', 0, NOW())");
$ids['custody'] = intval($conn->insert_id);
$conn->query("INSERT INTO proc_receipt_line (company_id, custody_id, item_id, item_name, qty)
              VALUES (4, {$ids['custody']}, {$ids['item']}, 'صنف حزام التكاليف', 10)");
check($ids['item'] > 0 && $ids['order'] > 0 && $ids['custody'] > 0, 'بُذرت السلسلة (صنف · أمر · عهدة)');

// حركةُ الاستلام كما يكتبها كاتبُ الشاشة
proc_receipt_stock_rewrite($gate, $ids['custody'],
    array(array('item_id' => $ids['item'], 'item_name' => 'صنف حزام التكاليف', 'qty' => 10.0)),
    'مخزن', 2, date('Y-m-d'), 1);

// ═══ ① تسعيرُ الاستلام من الأمر ═══════════════════════════════════════════
head('① التسعير من سطر الأمر × fx');
$n = Cost::repriceOrderReceipts($gate, $ids['order']);
check($n === 1, 'أعيد تسعيرُ صنفٍ واحد');
$uc = function () use ($conn, $ids) {
    $r = $conn->query("SELECT unit_cost FROM proc_stock_move WHERE ref_type='proc_receipt_custody' AND ref_id={$ids['custody']}");
    $x = $r ? $r->fetch_assoc() : null; return $x ? (float) $x['unit_cost'] : null;
};
check(abs($uc() - 100.0) < 0.001, 'تكلفةُ الوحدة = السعر × fx (50 × 2 = 100)');
check(abs((float) Cost::avgCostOf($gate, $ids['item']) - 100.0) < 0.001, 'والمتوسطُ المرجح 100');

// ═══ ② الترسملُ الوصولي ═══════════════════════════════════════════════════
head('② التكلفة الوصولية تُرسمَل وتُسترد');
$conn->query("INSERT INTO proc_landed_cost (company_id, order_id, doc_no, cost_type, amount, currency, fx_rate, base_amount, is_deleted, created_at)
              VALUES (4, {$ids['order']}, 'TST-BL', 'شحن', 300, 'SDG', 1, 300, 0, NOW())");
$ids['landed'][] = $lid = intval($conn->insert_id);
Cost::repriceOrderReceipts($gate, $ids['order']);
check(abs($uc() - 130.0) < 0.001, 'البوليصة 300/10 وحدات → التكلفة 130');
check(abs((float) Cost::avgCostOf($gate, $ids['item']) - 130.0) < 0.001, 'والمتوسط تبعها 130');

$conn->query("UPDATE proc_landed_cost SET is_deleted=1 WHERE id=$lid");
Cost::repriceOrderReceipts($gate, $ids['order']);
check(abs($uc() - 100.0) < 0.001, 'أرشفةُ البوليصة تُخرج نصيبَها — عادت 100');

// ═══ ③ الاستلامُ غيرُ المسعَّر خارج المتوسط ═══════════════════════════════
head('③ التاريخُ غيرُ المسعَّر لا يسمم المتوسط');
$conn->query("INSERT INTO proc_stock_move (company_id, item_id, warehouse_id, move_type, qty, unit_cost, ref_type, ref_id, moved_at)
              VALUES (4, {$ids['item']}, 2, 'استلام', 999, NULL, 'proc_receipt_custody', {$ids['custody']}, NOW())");
Cost::recomputeItemAvg($gate, $ids['item']);
check(abs((float) Cost::avgCostOf($gate, $ids['item']) - 100.0) < 0.001,
    '999 وحدةً بلا تكلفةٍ لم تُحرّك المتوسط (استلاماتٌ مُكلَّفةٌ حصرًا)');

// ═══ ④ العطالة ═══════════════════════════════════════════════════════════
head('④ إعادةُ الاحتساب idempotent');
$a1 = Cost::avgCostOf($gate, $ids['item']);
Cost::recomputeItemAvg($gate, $ids['item']);
Cost::recomputeItemAvg($gate, $ids['item']);
check(abs((float) Cost::avgCostOf($gate, $ids['item']) - (float) $a1) < 0.0001, 'التكرارُ لا يغيّر شيئًا');

$cleanup();
$ids = array('item' => 0, 'order' => 0, 'custody' => 0, 'landed' => array());

fwrite(STDOUT, "\n" . str_repeat('═', 46) . "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
