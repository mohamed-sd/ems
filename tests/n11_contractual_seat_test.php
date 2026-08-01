<?php
/**
 * tests/n11_contractual_seat_test.php — N-11 المقعد التعاقدي (البوابة ①)
 * ═══════════════════════════════════════════════════════════════════════════
 * الثوابت المختبرة (PLAN-04 §2.1 · PLAN-05 §11):
 *   ① المقعد توسعة لمستوى «معدة» — تعريفه على مستوى آخر 422.
 *   ② seat_no فريد داخل العقد — التكرار 409 بنيويًّا.
 *   ③ seat_kind يُشتق من بند البيع لا يُختار: hour→contractual_seat ·
 *      ton→operational_resource_slot · حصة مورد→supplier_allocation.
 *   ④ مقعد تعاقبت عليه معدتان: التاريخ سليم ولا تداخل — والتداخل 409.
 *   ⑤ سبب الاستبدال إلزامي لغير الإسناد الأول — 422.
 *   ⑥ عقد بمقاعد ملئ بعضها: seat_gap صحيح وكل فراغ بنوعه ودلالته.
 *   ⑦ مستوى «نوع» قائم في ENUM — حاوية نوع بين المورد والمعدة تُنشأ ويُحترم Σ.
 * البذر معزول (علامة N11T) ويُكنس كاملًا. التشغيل: php tests/n11_contractual_seat_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => 4, 'name' => 'N11 test');
require_once dirname(__DIR__) . '/app/Services/ContractSeatService.php';

use App\Services\ContractSeatService as CSS;

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
mysqli_report(MYSQLI_REPORT_OFF);
$gate = ems_tenant_db();
$CO = 4;
$MARK = 'N11T';

$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE a FROM seat_assignments a JOIN op_containers c ON c.id = a.container_id WHERE c.container_no LIKE '{$MARK}%'");
    for ($i = 0; $i < 5; $i++) { $conn->query("DELETE FROM op_containers WHERE container_no LIKE '{$MARK}%' AND id NOT IN (SELECT * FROM (SELECT parent_id FROM op_containers WHERE container_no LIKE '{$MARK}%' AND parent_id IS NOT NULL) x)"); }
    $conn->query("DELETE FROM op_containers WHERE container_no LIKE '{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

// ── البذرة: شجرة رئيسية ← مورد ← نوع ← معدة×3 على العقد 7 ──
$CID = 7;
// contract_item_id = NULL: للعقد 7 رئيسيةٌ حيةٌ على بنده (uq_main_per_item) — وبذرة الاختبار معزولة عنها
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, contract_item_id, unit_type, cap_qty, project_id)
              VALUES ({$CO}, '{$MARK}-M', 'رئيسية', NULL, {$CID}, NULL, 'hour', 900.00, 10)");
$MAIN = (int) $conn->insert_id;
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, unit_type, cap_qty, supplier_id)
              VALUES ({$CO}, '{$MARK}-S', 'مورد', {$MAIN}, {$CID}, 'hour', 900.00, 1)");
$SUP = (int) $conn->insert_id;
$conn->query("UPDATE op_containers SET allocated_qty=900.00 WHERE id={$MAIN}");

head('⑦ مستوى «نوع» — حاوية نوع بين المورد والمعدة');
$e = $conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, unit_type, cap_qty)
                   VALUES ({$CO}, '{$MARK}-T', 'نوع', {$SUP}, {$CID}, 'hour', 900.00)");
check($e === true, 'حاوية نوع (حفار) أُنشئت تحت المورد — المستوى الجديد الوحيد');
$TYPE = (int) $conn->insert_id;
$conn->query("UPDATE op_containers SET allocated_qty=900.00 WHERE id={$SUP}");

$seats = array();
for ($i = 1; $i <= 3; $i++) {
    $conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, unit_type, cap_qty, role_kind)
                  VALUES ({$CO}, '{$MARK}-E{$i}', 'معدة', {$TYPE}, {$CID}, 'hour', 300.00, '" . ($i === 3 ? 'احتياطية' : 'أساسية') . "')");
    $seats[$i] = (int) $conn->insert_id;
}
$conn->query("UPDATE op_containers SET allocated_qty=900.00 WHERE id={$TYPE}");
check($seats[3] > 0, 'ثلاث حاويات معدة تحت حاوية النوع — Σ 900 = سقف النوع');

head('①②③ تعريف المقاعد — الفرادة والاشتقاق');
$r = CSS::defineSeat($gate, $CO, $SUP, array('seat_no' => 1), 1);
check(!$r['ok'] && $r['code'] === 422, 'تعريف مقعد على مستوى «مورد» ⇒ 422: ' . $r['reason']);

$r = CSS::defineSeat($gate, $CO, $seats[1], array('seat_no' => 1, 'pricing_model' => 'hour', 'contract_hours_monthly' => 300, 'unit_price' => 10, 'currency' => 'USD'), 1);
check($r['ok'] && strpos($r['reason'], 'contractual_seat') !== false, 'مقعد 1 من بند ساعة ⇒ contractual_seat (اشتقاق لا اختيار)');
$r = CSS::defineSeat($gate, $CO, $seats[2], array('seat_no' => 2, 'pricing_model' => 'ton'), 1);
check($r['ok'] && strpos($r['reason'], 'operational_resource_slot') !== false, 'مقعد 2 من بند طن ⇒ operational_resource_slot');
$r = CSS::defineSeat($gate, $CO, $seats[3], array('seat_no' => 3, 'pricing_model' => 'hour', 'is_supplier_allocation' => true), 1);
check($r['ok'] && strpos($r['reason'], 'supplier_allocation') !== false, 'مقعد 3 حصة مورد ⇒ supplier_allocation');

$r = CSS::defineSeat($gate, $CO, $seats[2], array('seat_no' => 1, 'pricing_model' => 'ton'), 1);
check(!$r['ok'] && $r['code'] === 409, 'seat_no مكرر داخل العقد ⇒ 409 بنيويًّا (uq_seat_no)');

head('④⑤ التعاقب — لا تداخل وسبب إلزامي');
$r = CSS::assignEquipment($gate, $CO, $seats[1], 12, '2026-07-01', '2026-07-15', array(), 1);
check($r['ok'], 'المعدة 12 جلست في المقعد 1 (1-15 يوليو)');
$r = CSS::assignEquipment($gate, $CO, $seats[1], 13, '2026-07-10', '2026-07-20', array('replace_reason' => 'عطل'), 1);
check(!$r['ok'] && $r['code'] === 409, 'معدة ثانية بفترة متداخلة ⇒ 409: ' . mb_substr($r['reason'], 0, 60));
$r = CSS::assignEquipment($gate, $CO, $seats[1], 13, '2026-07-16', null, array(), 1);
check(!$r['ok'] && $r['code'] === 422, 'تعاقب بلا سبب استبدال ⇒ 422');
$r = CSS::assignEquipment($gate, $CO, $seats[1], 13, '2026-07-16', null, array('replace_reason' => 'عطل المعدة 12 — محضر عطل MNT-101'), 1);
check($r['ok'], 'التعاقب بسبب مكتوب يقع — المعدة 13 من 16 يوليو مفتوحًا');
$succ = CSS::successionOf($gate, $CO, $seats[1]);
check(count($succ) === 2 && $succ[0]['equipment_id'] == 12 && $succ[1]['equipment_id'] == 13, 'تاريخ التعاقب سليم لكلٍّ ولا تداخل');
$r = CSS::assignEquipment($gate, $CO, $seats[1], 14, '2026-08-01', null, array('replace_reason' => 'س'), 1);
check(!$r['ok'] && $r['code'] === 409, 'فترة مفتوحة تمنع أي إسناد لاحق حتى تُنهى ⇒ 409');

head('⑥ فجوة المقاعد — محسوبة وكل فراغ بنوعه');
$gap = CSS::seatGap($gate, $CO, $CID, '2026-07-18');
check($gap['seats_contracted'] === 3 && $gap['seats_filled'] === 1 && $gap['seat_gap'] === 2,
      "عقد بثلاثة مقاعد ملئ واحد: gap=2 (الفعلي: {$gap['seat_gap']})");
$kinds = array();
foreach ($gap['empty_seats'] as $e2) { $kinds[$e2['seat_kind']] = $e2['implication']; }
check(isset($kinds['operational_resource_slot']) && strpos($kinds['operational_resource_slot'], 'مؤشر تغطية') !== false,
      'الفراغ التشغيلي مؤشر تغطية داخلي — لا حق للعميل');
check(!isset($kinds['contractual_seat']) || strpos($kinds['contractual_seat'], 'بنص العقد') !== false,
      'الفراغ التعاقدي بند مطالبة بنص العقد لا تلقائيًّا');

echo PHP_EOL . "══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══" . PHP_EOL;
exit($FAIL === 0 ? 0 : 1);
