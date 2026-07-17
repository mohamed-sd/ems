<?php
/**
 * اختبارات بوابة التحويل المالي — D02 §5/§6
 * القواعد: الطابور استنتاجٌ صادق · التسعير المسبق = ما سيُولَّد · التحويل يولّد
 * المروحة · العطالة · الأهلية الخادمية · خروج المحوَّل من الطابور · حارس الازدواج.
 * التشغيل: php tests/unit_convert_gate_test.php — رمز الخروج 0/1.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);

require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/EffectFanout.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\EffectFanout;

$PASS = 0; $FAIL = 0;
function ok($label, $cond) {
    global $PASS, $FAIL;
    if ($cond) { $PASS++; echo "  ✔ {$label}\n"; }
    else { $FAIL++; echo "  ✘ FAIL: {$label}\n"; }
}

$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_APP_USER'), ems_env('DB_APP_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "FATAL: db connect\n"); exit(1); }
$conn->set_charset('utf8mb4');
$root = new mysqli(ems_env('DB_HOST'), 'root', '', ems_env('DB_NAME'));
if ($root->connect_errno) { fwrite(STDERR, "FATAL: root connect\n"); exit(1); }
$root->set_charset('utf8mb4');

$CO = 4;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, 0, '', true));
$TAG = 'CVGATE_' . getmypid();

function counts($conn) {
    return array(
        'events' => intval($conn->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0]),
        'dues'   => intval($conn->query("SELECT COUNT(*) FROM fin_dues")->fetch_row()[0]),
        'costs'  => intval($conn->query("SELECT COUNT(*) FROM fin_cost_records")->fetch_row()[0]),
        'links'  => intval($conn->query("SELECT COUNT(*) FROM fin_event_links")->fetch_row()[0]),
        'roots'  => intval($conn->query("SELECT COUNT(*) FROM ems_business_events")->fetch_row()[0]),
    );
}
$c0 = counts($conn);
$seed = array('ts' => array(), 'appr' => array(), 'ops' => array(), 'contracts' => array(),
              'sup_contracts' => array(), 'ce' => array(), 'sce' => array(),
              'equip' => array(), 'supplier' => array(), 'project' => array());

/** إدراجٌ محروس — لا معرّفَ يُسجَّل للحذف إلا بنجاحٍ مؤكَّد (گوتشا insert_id). */
function seed_insert($root, $sql, &$bucket) {
    if (!$root->query($sql)) { throw new \RuntimeException('فشل بذر: ' . $root->error . ' ← ' . substr($sql, 0, 120)); }
    $id = intval($root->insert_id);
    if ($id <= 0) { throw new \RuntimeException('بذرٌ بلا معرّفٍ وليد'); }
    $bucket[] = $id;
    return $id;
}

/** استعلام الطابور نفسه المستعمَل في الشاشة (بلا طبقة البوابة — قراءة تحقّق). */
function queue_ids($root, $CO, $onlyId = null) {
    $extra = $onlyId ? (' AND t.id = ' . intval($onlyId)) : '';
    $sql = "SELECT t.id FROM timesheet t
              JOIN timesheet_approvals ta ON ta.timesheet_id = t.id AND ta.approval_level = 4 AND ta.status = 1
              LEFT JOIN operations t_op ON t_op.id = t.operator
             WHERE t.company_id = $CO AND COALESCE(t.status, 1) = 1
               AND NOT EXISTS (SELECT 1 FROM fin_event_links l
                                WHERE l.parent_kind = 'timesheet' AND l.parent_ref = t.id)
                   $extra
             ORDER BY t.`date` ASC LIMIT 200";
    $out = array();
    $rs = $root->query($sql);
    if ($rs) { while ($r = $rs->fetch_assoc()) { $out[] = intval($r['id']); } }
    return $out;
}

$WORK_DATE = '2026-06-20';

try {

echo "── 0) بذر عالمٍ مستقل: عقد عميل 3500 · عقد مورد 2200 · جنيه ──\n";
$SUP = seed_insert($root, "INSERT INTO suppliers (company_id, name, phone, status) VALUES ($CO, '$TAG-مورد', '000', 1)", $seed['supplier']);
$PRJ = seed_insert($root, "INSERT INTO project (company_id, name, client, location, total, status) VALUES ($CO, '$TAG-مشروع', '$TAG-ع', 'اختبار', '0', 1)", $seed['project']);
$EQ  = seed_insert($root, "INSERT INTO equipments (company_id, name, code, type, suppliers, status) VALUES ($CO, '$TAG-معدة', '$TAG-c', '77', '$SUP', 1)", $seed['equip']);
$CC  = seed_insert($root, "INSERT INTO contracts (company_id, project_id, contract_signing_date, price_currency_contract, status) VALUES ($CO, $PRJ, '2026-01-01', 'جنيه', 1)", $seed['contracts']);
seed_insert($root, "INSERT INTO contractequipments (company_id, contract_id, equip_type, equip_unit, equip_price) VALUES ($CO, $CC, '77', 'ساعة', 3500)", $seed['ce']);
$SC  = seed_insert($root, "INSERT INTO supplierscontracts (company_id, supplier_id, project_id, contract_signing_date, price_currency_contract, actual_start, actual_end, status) VALUES ($CO, $SUP, $PRJ, '2026-01-01', 'جنيه', '2026-06-01', '2026-12-31', 1)", $seed['sup_contracts']);
seed_insert($root, "INSERT INTO suppliercontractequipments (company_id, contract_id, equip_type, equip_unit, equip_price) VALUES ($CO, $SC, '77', 'ساعة', 2200)", $seed['sce']);
$OP  = seed_insert($root, "INSERT INTO operations (company_id, project_id, equipment, equipment_type, equipment_category, contract_id, supplier_id, `start`, `end`, reason, days, status) VALUES ($CO, '$PRJ', '$EQ', '77', 'اختبار', '$CC', '$SUP', '2026-06-01', '2026-12-31', 'اختبار', '30', 1)", $seed['ops']);
$TS  = seed_insert($root, "INSERT INTO timesheet (company_id, operator, employee_id, shift, `date`, `type`, time_notes, executed_hours, total_work_hours, shift_hours, operator_hours, status) VALUES ($CO, '$OP', '8', 'ص', '$WORK_DATE', 'عادي', '$TAG', 10, 10, 10, 9, 1)", $seed['ts']);

echo "── 1) قبل اكتمال الاعتماد: اليوم ليس في الطابور ──\n";
ok('صفر ظهورٍ في الطابور قبل المستوى الرابع', !in_array($TS, queue_ids($root, $CO), true));

echo "── 2) باكتمال المستوى الرابع: يدخل الطابور ولا مالَ بعد ──\n";
$before2 = counts($conn);
seed_insert($root, "INSERT INTO timesheet_approvals (company_id, timesheet_id, approval_level, approved_by, approved_by_name, status) VALUES ($CO, $TS, 4, 7, '$TAG', 1)", $seed['appr']);
ok('اليوم ظهر في طابور التحويل', in_array($TS, queue_ids($root, $CO), true));
$after2 = counts($conn);
ok('صفر أثرٍ ماليٍّ بمجرد الاعتماد (البوابة تحجز المال)',
    $after2['events'] === $before2['events'] && $after2['dues'] === $before2['dues']
    && $after2['costs'] === $before2['costs'] && $after2['links'] === $before2['links']);

echo "── 3) التسعير المسبق = ما سيُولَّد فعلًا (لا وعدَ كاذب) ──\n";
$ctx = EffectFanout::resolveTimesheet($conn, $TS);
$preRevenue = $ctx['client']['ok'] ? round($ctx['qty'] * $ctx['client']['price'], 2) : null;
$preDue     = $ctx['supplier']['ok'] ? round($ctx['qty'] * $ctx['supplier']['price'], 2) : null;
ok('العرض المسبق: إيراد 35000 · مستحق 22000', $preRevenue === 35000.00 && $preDue === 22000.00);

echo "── 4) التحويل يولّد المروحة كاملةً ──\n";
$res = null;
$gate->runInTransaction(function ($g) use (&$res, $conn, $TS) {
    $res = EffectFanout::forTimesheetId($conn, $g, $TS, 72);
});
ok('ثلاثة آثارٍ مولَّدة', count($res['effects']) === 3);
$genRevenue = null; $genDue = null;
foreach ($res['effects'] as $e) {
    if ($e['effect'] === 'revenue_event') { $genRevenue = $e['amount']; }
    if ($e['effect'] === 'supplier_due')  { $genDue = $e['amount']; }
}
ok('المولَّد يطابق المعروض تمامًا (35000 · 22000)', $genRevenue === $preRevenue && $genDue === $preDue);

echo "── 5) المحوَّل يخرج من الطابور (لا يُعرض مرتين) ──\n";
ok('اليوم لم يعد في الطابور', !in_array($TS, queue_ids($root, $CO), true));

echo "── 6) الأهلية الخادمية: يومٌ محوَّلٌ لا يُقبل ولو أُرسل معرّفه ──\n";
ok('فحص الأهلية بمعرّفٍ بعينه يعيد فراغًا', empty(queue_ids($root, $CO, $TS)));
$before6 = counts($conn);
$res6 = null;
$gate->runInTransaction(function ($g) use (&$res6, $conn, $TS) {
    $res6 = EffectFanout::forTimesheetId($conn, $g, $TS, 72);
});
$after6 = counts($conn);
ok('إعادة التحويل: صفر أثرٍ جديد (العطالة)', count($res6['effects']) === 0);
ok('العدّادات ثابتة — لا ازدواج مال', $after6 === $before6);

echo "── 7) حارس الازدواج: اليوم المحوَّل معروفٌ بمصدره ──\n";
$dupCheck = $root->query("SELECT t.id FROM timesheet t
                            JOIN operations o ON o.id = t.operator
                           WHERE t.company_id = $CO AND t.`date` = '$WORK_DATE'
                             AND o.equipment = '$EQ' AND o.project_id = '$PRJ'
                             AND EXISTS (SELECT 1 FROM fin_event_links l
                                          WHERE l.parent_kind = 'timesheet' AND l.parent_ref = t.id)
                           LIMIT 1")->fetch_assoc();
ok('استعلام الحارس يكشف اليوم المحوَّل (يمنع الإدخال الموازي)', $dupCheck && intval($dupCheck['id']) === $TS);

echo "── 8) يومٌ متعذّرٌ تسعيره: يظهر في الطابور ولا يُحوَّل ──\n";
$CC2 = seed_insert($root, "INSERT INTO contracts (company_id, project_id, contract_signing_date, price_currency_contract, status) VALUES ($CO, $PRJ, '2026-01-01', 'جنيه', 1)", $seed['contracts']);
seed_insert($root, "INSERT INTO contractequipments (company_id, contract_id, equip_type, equip_unit, equip_price) VALUES ($CO, $CC2, '78', 'متر طولي', 40)", $seed['ce']);
$OP2 = seed_insert($root, "INSERT INTO operations (company_id, project_id, equipment, equipment_type, equipment_category, contract_id, supplier_id, `start`, `end`, reason, days, status) VALUES ($CO, '$PRJ', '$EQ', '78', 'اختبار', '$CC2', '$SUP', '2026-06-01', '2026-12-31', 'اختبار', '30', 1)", $seed['ops']);
$TS2 = seed_insert($root, "INSERT INTO timesheet (company_id, operator, employee_id, shift, `date`, `type`, time_notes, executed_hours, total_work_hours, shift_hours, operator_hours, status) VALUES ($CO, '$OP2', '8', 'ص', '$WORK_DATE', 'عادي', '$TAG', 7, 7, 7, 6, 1)", $seed['ts']);
seed_insert($root, "INSERT INTO timesheet_approvals (company_id, timesheet_id, approval_level, approved_by, approved_by_name, status) VALUES ($CO, $TS2, 4, 7, '$TAG', 1)", $seed['appr']);
ok('اليوم المتعذّر يظهر في الطابور (شفافية لا إخفاء)', in_array($TS2, queue_ids($root, $CO), true));
$ctx2 = EffectFanout::resolveTimesheet($conn, $TS2);
$ready2 = ($ctx2['client']['ok'] || $ctx2['supplier']['ok']);
ok('ويُعلَن غيرَ جاهزٍ للتحويل بسببه', !$ready2 && $ctx2['client']['reason'] !== '');
$before8 = counts($conn);
$res8 = null;
$gate->runInTransaction(function ($g) use (&$res8, $conn, $TS2) {
    $res8 = EffectFanout::forTimesheetId($conn, $g, $TS2, 72);
});
$after8 = counts($conn);
ok('محاولة تحويله: صفر أثرٍ ولا رقمَ ملفَّق', count($res8['effects']) === 0 && $after8 === $before8);

} catch (\Throwable $t) {
    $FAIL++;
    echo "  ✘ استثناء غير متوقع: " . $t->getMessage() . "\n" . $t->getTraceAsString() . "\n";
}

// ── تنظيف: الأثر ثم الإسقاط ثم الجذر ثم عالَم البذر (الترتيب مُلزَمٌ بالمفاتيح) ──
foreach ($seed['ts'] as $id) {
    $root->query("DELETE FROM fin_cost_records WHERE id IN (SELECT target_id FROM fin_event_links WHERE parent_kind='timesheet' AND parent_ref=$id AND target_table='fin_cost_records')");
    $root->query("DELETE FROM fin_dues WHERE id IN (SELECT target_id FROM fin_event_links WHERE parent_kind='timesheet' AND parent_ref=$id AND target_table='fin_dues')");
    $root->query("DELETE FROM fin_financial_events WHERE id IN (SELECT target_id FROM fin_event_links WHERE parent_kind='timesheet' AND parent_ref=$id AND target_table='fin_financial_events')");
    $root->query("DELETE FROM fin_event_links WHERE parent_kind='timesheet' AND parent_ref=$id");
    $root->query("DELETE FROM fin_financial_events WHERE source_ref='TS-$id'");
    $root->query("DELETE FROM ems_business_events WHERE entity_type='timesheet' AND entity_id=$id");
    $root->query("DELETE FROM timesheet_approvals WHERE timesheet_id=$id");
    $root->query("DELETE FROM timesheet WHERE id=$id");
}
foreach ($seed['sce'] as $id) { $root->query("DELETE FROM suppliercontractequipments WHERE id=$id"); }
foreach ($seed['ce'] as $id)  { $root->query("DELETE FROM contractequipments WHERE id=$id"); }
foreach ($seed['sup_contracts'] as $id) { $root->query("DELETE FROM supplierscontracts WHERE id=$id"); }
foreach ($seed['contracts'] as $id) { $root->query("DELETE FROM contracts WHERE id=$id"); }
foreach ($seed['ops'] as $id) { $root->query("DELETE FROM operations WHERE id=$id"); }
foreach ($seed['equip'] as $id) { $root->query("DELETE FROM equipments WHERE id=$id"); }
foreach ($seed['project'] as $id) { $root->query("DELETE FROM project WHERE id=$id"); }
foreach ($seed['supplier'] as $id) { $root->query("DELETE FROM suppliers WHERE id=$id"); }

$cf = counts($conn);
ok('teardown: العدّادات عادت لخط الأساس',
    $cf['events'] === $c0['events'] && $cf['dues'] === $c0['dues'] && $cf['costs'] === $c0['costs']
    && $cf['links'] === $c0['links'] && $cf['roots'] === $c0['roots']);

echo str_repeat('═', 50) . "\n";
echo "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n";
exit($FAIL === 0 ? 0 : 1);
