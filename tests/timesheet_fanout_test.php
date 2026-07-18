<?php
/**
 * اختبارات مروحة الدوام — EffectFanout::forTimesheetId (D02 م1-①)
 * القواعد: المصدر الواحد · التسعير من العقدين بعملتيهما · سلّم حسم عقد المورد ·
 * مطابقة وحدة الفوترة · العطالة · الذرّية · حارس المراجعة · عدم التلفيق.
 * التشغيل: php tests/timesheet_fanout_test.php — رمز الخروج 0/1.
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
$TAG = 'TSFAN_' . getmypid();

function counts($conn) {
    return array(
        'events' => intval($conn->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0]),
        'dues'   => intval($conn->query("SELECT COUNT(*) FROM fin_dues")->fetch_row()[0]),
        'costs'  => intval($conn->query("SELECT COUNT(*) FROM fin_cost_records")->fetch_row()[0]),
        'links'  => intval($conn->query("SELECT COUNT(*) FROM fin_event_links")->fetch_row()[0]),
        'roots'  => intval($conn->query("SELECT COUNT(*) FROM ems_business_events")->fetch_row()[0]),
        'awards' => intval($conn->query("SELECT COUNT(*) FROM unit_party_awards")->fetch_row()[0]),
    );
}
$c0 = counts($conn);

// ── بذر عالمٍ مستقلٍّ كامل: مورد/معدة/مشروع/عقدان/تشغيل ──
// ⚠️ قاعدة السلامة: لا يُسجَّل معرّفٌ للحذف إلا إذا **نجح إدراجه فعلًا**.
// mysqli->insert_id يحتفظ بقيمة آخر إدراجٍ ناجح بعد فشل إدراج — فتسجيلُه
// بلا تحقّقٍ يعني حذفَ صفٍّ حقيقيٍّ لم ننشئه في التنظيف. (وقع فعلًا أثناء
// تطوير هذه الحزمة: حُذفت معدة #14 واسترُجعت من نسخة 2026-06-25.)
$seed = array('ts' => array(), 'ops' => array(), 'contracts' => array(), 'sup_contracts' => array(),
              'ce' => array(), 'sce' => array(), 'equip' => array(), 'supplier' => array(), 'project' => array());

/** إدراجٌ محروس: يعيد المعرّف الوليد أو يُسقط الحزمة بصوتٍ عالٍ — لا يخمّن. */
function seed_insert($root, $sql, &$bucket) {
    if (!$root->query($sql)) {
        throw new \RuntimeException("فشل بذر: " . $root->error . " ← " . substr($sql, 0, 120));
    }
    $id = intval($root->insert_id);
    if ($id <= 0) { throw new \RuntimeException("بذرٌ بلا معرّفٍ وليد ← " . substr($sql, 0, 120)); }
    $bucket[] = $id;
    return $id;
}

$SUP = seed_insert($root, "INSERT INTO suppliers (company_id, name, phone, status)
    VALUES ($CO, '$TAG-مورد', '000', 1)", $seed['supplier']);
$PRJ = seed_insert($root, "INSERT INTO project (company_id, name, client, location, total, status)
    VALUES ($CO, '$TAG-مشروع', '$TAG-عميل', 'اختبار', '0', 1)", $seed['project']);
$EQ  = seed_insert($root, "INSERT INTO equipments (company_id, name, code, type, suppliers, status)
    VALUES ($CO, '$TAG-معدة', '$TAG-code', '77', '$SUP', 1)", $seed['equip']);

$ETYPE = 77; // نوع معدةٍ لا يصطدم ببيانات الشركة الحقيقية

/** عقد عميلٍ بسعرٍ ووحدةٍ وعملة. */
function mk_client_contract($root, $CO, $PRJ, $ETYPE, $cur, $price, $unitLabel, &$seed) {
    $cid = seed_insert($root, "INSERT INTO contracts (company_id, project_id, contract_signing_date, price_currency_contract, status)
        VALUES ($CO, $PRJ, '2026-01-01', '$cur', 1)", $seed['contracts']);
    seed_insert($root, "INSERT INTO contractequipments (company_id, contract_id, equip_type, equip_unit, equip_price)
        VALUES ($CO, $cid, '$ETYPE', '$unitLabel', $price)", $seed['ce']);
    return $cid;
}
/** عقد موردٍ بنافذة سريانٍ وسعرٍ ووحدةٍ وعملة. */
function mk_supplier_contract($root, $CO, $SUP, $PRJ, $ETYPE, $cur, $price, $unitLabel, $start, $end, &$seed) {
    $s = $start === null ? 'NULL' : "'$start'";
    $e = $end === null ? 'NULL' : "'$end'";
    $cid = seed_insert($root, "INSERT INTO supplierscontracts
        (company_id, supplier_id, project_id, contract_signing_date, price_currency_contract, actual_start, actual_end, status)
        VALUES ($CO, $SUP, $PRJ, '2026-01-01', '$cur', $s, $e, 1)", $seed['sup_contracts']);
    seed_insert($root, "INSERT INTO suppliercontractequipments (company_id, contract_id, equip_type, equip_unit, equip_price)
        VALUES ($CO, $cid, '$ETYPE', '$unitLabel', $price)", $seed['sce']);
    return $cid;
}
/** تشغيلٌ يربط المعدة بالمشروع والعقد. */
function mk_op($root, $CO, $PRJ, $EQ, $ETYPE, $contractId, $SUP, &$seed) {
    return seed_insert($root, "INSERT INTO operations
        (company_id, project_id, equipment, equipment_type, equipment_category, contract_id, supplier_id,
         `start`, `end`, reason, days, status)
        VALUES ($CO, '$PRJ', '$EQ', '$ETYPE', 'اختبار', '$contractId', '$SUP',
                '2026-06-01', '2026-12-31', 'بذر اختبار', '30', 1)", $seed['ops']);
}
/** صفُّ دوامٍ بكميةٍ من نوعٍ معيّن. */
function mk_ts($root, $CO, $opId, $date, $hours, $meters = 0, $tons = 0, &$seed = null) {
    $st = $root->prepare("INSERT INTO timesheet (company_id, operator, employee_id, shift, `date`, `type`, time_notes,
                          executed_hours, total_work_hours, shift_hours, meters_count, tons_count, operator_hours, status)
                          VALUES (?, ?, '', 'ص', ?, 'عادي', '', ?, ?, ?, ?, ?, 0, 1)");
    $opStr = strval($opId);
    $st->bind_param('issddddd', $CO, $opStr, $date, $hours, $hours, $hours, $meters, $tons);
    if (!$st->execute()) { $e = $st->error; $st->close(); throw new \RuntimeException('فشل بذر دوام: ' . $e); }
    $id = intval($root->insert_id);
    $st->close();
    if ($id <= 0) { throw new \RuntimeException('بذر دوامٍ بلا معرّفٍ وليد'); }
    if ($seed !== null) { $seed['ts'][] = $id; }
    return $id;
}

$WORK_DATE = '2026-06-15';

try {

echo "── 1) المروحة الكاملة من يوم دوامٍ معتمد (ساعة · جنيه) ──\n";
$c_sdg = mk_client_contract($root, $CO, $PRJ, $ETYPE, 'جنيه', 3500, 'ساعة', $seed);
mk_supplier_contract($root, $CO, $SUP, $PRJ, $ETYPE, 'جنيه', 2200, 'ساعة', '2026-06-01', '2026-12-31', $seed);
$op1 = mk_op($root, $CO, $PRJ, $EQ, $ETYPE, $c_sdg, $SUP, $seed);
$ts1 = mk_ts($root, $CO, $op1, $WORK_DATE, 10, 0, 0, $seed);

$r1 = null;
$gate->runInTransaction(function ($g) use (&$r1, $conn, $ts1) {
    $r1 = EffectFanout::forTimesheetId($conn, $g, $ts1, 72);
});
// D02 §2.6: أثرٌ رابعٌ منذ أحكام الأطراف — الحكمُ التعاقديُّ يسبق المال
ok('توليد 4 آثارٍ حقيقية (إيراد + مستحق مورد + تكلفة + أحكام الأطراف)', count($r1['effects']) === 4);
ok('إعلان أثرين غير متاحين (مشغّل + صيانة) — لا تلفيق', count($r1['skipped']) === 2);

$rev = $root->query("SELECT amount, currency, quantity, unit, entity_type, entity_id, payload
                     FROM fin_financial_events WHERE idempotency_key='fanout:ts:$ts1:revenue'")->fetch_assoc();
ok('الإيراد = 10 × 3500 = 35000 بالجنيه', $rev && floatval($rev['amount']) === 35000.00 && $rev['currency'] === 'SDG');
ok('الأثر يشير لصف الدوام مصدرًا (entity_type=timesheet)', $rev && $rev['entity_type'] === 'timesheet' && intval($rev['entity_id']) === $ts1);
ok('الكمية والوحدة من المسجَّل (10 ساعات)', $rev && floatval($rev['quantity']) === 10.0 && $rev['unit'] === 'hour');
$pl = $rev ? json_decode($rev['payload'], true) : array();
ok('لقطة التسعير محفوظةٌ في الحمولة (سعر + عملة + عقد)',
    isset($pl['unit_price']) && floatval($pl['unit_price']) === 3500.0 && ($pl['currency'] ?? '') === 'SDG' && !empty($pl['client_contract_id']));

$due = $root->query("SELECT d.amount, d.currency, d.due_type, d.party_type, d.party_ref FROM fin_dues d
                     JOIN fin_event_links l ON l.target_id=d.id AND l.target_table='fin_dues'
                     WHERE l.parent_kind='timesheet' AND l.parent_ref=$ts1 AND l.effect_type='supplier_due'")->fetch_assoc();
ok('مستحق المورد = 10 × 2200 = 22000 بالجنيه لمورد المعدة',
    $due && floatval($due['amount']) === 22000.00 && $due['currency'] === 'SDG'
    && $due['due_type'] === 'hours' && $due['party_type'] === 'supplier' && intval($due['party_ref']) === $SUP);

$cost = $root->query("SELECT c.total_cost, c.revenue, c.profit, c.currency, c.unit, c.qty, c.equipment_id, c.project_id
                      FROM fin_cost_records c
                      JOIN fin_event_links l ON l.target_id=c.id AND l.target_table='fin_cost_records'
                      WHERE l.parent_kind='timesheet' AND l.parent_ref=$ts1 AND l.effect_type='cost_record'")->fetch_assoc();
ok('التكلفة 22000 · الإيراد 35000 · الربح 13000 (عملةٌ واحدة)',
    $cost && floatval($cost['total_cost']) === 22000.00 && floatval($cost['revenue']) === 35000.00
    && floatval($cost['profit']) === 13000.00 && $cost['currency'] === 'SDG');
ok('التكلفة محمَّلةٌ على المعدة والمشروع الصحيحين',
    $cost && intval($cost['equipment_id']) === $EQ && intval($cost['project_id']) === $PRJ);

echo "── 2) العطالة: إعادة التشغيل لا تُكرّر أثرًا ──\n";
$before2 = counts($conn);
$r2 = null;
$gate->runInTransaction(function ($g) use (&$r2, $conn, $ts1) {
    $r2 = EffectFanout::forTimesheetId($conn, $g, $ts1, 72);
});
$after2 = counts($conn);
ok('إعادة التشغيل: صفر أثرٍ جديد', count($r2['effects']) === 0);
ok('العدّادات ثابتة (لا ازدواج مال)',
    $after2['events'] === $before2['events'] && $after2['dues'] === $before2['dues']
    && $after2['costs'] === $before2['costs'] && $after2['links'] === $before2['links']);

echo "── 3) حارس المراجعة: تغيّرُ الكمية بعد التوليد لا يُولّد أثرًا ثانيًا ──\n";
$root->query("UPDATE timesheet SET executed_hours=6, total_work_hours=6, shift_hours=6 WHERE id=$ts1");
$before3 = counts($conn);
$r3 = null;
$gate->runInTransaction(function ($g) use (&$r3, $conn, $ts1) {
    $r3 = EffectFanout::forTimesheetId($conn, $g, $ts1, 72);
});
$after3 = counts($conn);
ok('يُعلن مراجعةً معلّقة (لا كتابة)', !empty($r3['revision_pending']) && count($r3['effects']) === 0);
ok('صفر صفٍّ جديدٍ في كل الجداول', $after3 === $before3);
$root->query("UPDATE timesheet SET executed_hours=10, total_work_hours=10, shift_hours=10 WHERE id=$ts1");

echo "── 4) عملتان مختلفتان: لا يُجمع إيرادٌ بعملةٍ فوق تكلفةٍ بأخرى ──\n";
$c_usd = mk_client_contract($root, $CO, $PRJ, $ETYPE, 'دولار', 100, 'ساعة', $seed);
$op2 = mk_op($root, $CO, $PRJ, $EQ, $ETYPE, $c_usd, $SUP, $seed);
$ts2 = mk_ts($root, $CO, $op2, $WORK_DATE, 8, 0, 0, $seed);
$r4 = null;
$gate->runInTransaction(function ($g) use (&$r4, $conn, $ts2) {
    $r4 = EffectFanout::forTimesheetId($conn, $g, $ts2, 72);
});
$rev4 = $root->query("SELECT amount, currency FROM fin_financial_events WHERE idempotency_key='fanout:ts:$ts2:revenue'")->fetch_assoc();
ok('الإيراد بالدولار كما في عقده (8 × 100 = 800 USD)',
    $rev4 && floatval($rev4['amount']) === 800.00 && $rev4['currency'] === 'USD');
$cost4 = $root->query("SELECT c.total_cost, c.revenue, c.currency FROM fin_cost_records c
                       JOIN fin_event_links l ON l.target_id=c.id AND l.target_table='fin_cost_records'
                       WHERE l.parent_kind='timesheet' AND l.parent_ref=$ts2")->fetch_assoc();
ok('التكلفة بالجنيه (عقد المورد) والإيراد يُحجب من صف التكلفة — لا خلط عملتين',
    $cost4 && $cost4['currency'] === 'SDG' && $cost4['revenue'] === null);

echo "── 5) سلّم حسم عقد المورد: الساري بتاريخ العمل يفوز ──\n";
// عقدٌ ثانٍ للمورد نفسه خارج نافذة تاريخ العمل بسعرٍ مختلف تمامًا
mk_supplier_contract($root, $CO, $SUP, $PRJ, $ETYPE, 'جنيه', 9999, 'ساعة', '2020-01-01', '2020-12-31', $seed);
$ctx5 = EffectFanout::resolveTimesheet($conn, $ts1);
ok('اختار العقد الساري بالتاريخ (2200 لا 9999)',
    $ctx5['supplier']['ok'] && floatval($ctx5['supplier']['price']) === 2200.0 && $ctx5['supplier']['resolved_by'] === 'in_force_at_date');

echo "── 6) الغموض لا يُخمَّن: عقدان ساريان بسعرين ⇒ تعذّرٌ معلن ──\n";
mk_supplier_contract($root, $CO, $SUP, $PRJ, $ETYPE, 'جنيه', 3100, 'ساعة', '2026-06-10', '2026-12-31', $seed);
$ctx6 = EffectFanout::resolveTimesheet($conn, $ts1);
ok('يرفض التسعير ويعلن السبب (أكثر من عقدٍ سارٍ)',
    !$ctx6['supplier']['ok'] && strpos($ctx6['supplier']['reason'], 'أكثر من عقد') !== false);
$ts6 = mk_ts($root, $CO, $op1, $WORK_DATE, 5, 0, 0, $seed);
$r6 = null;
$gate->runInTransaction(function ($g) use (&$r6, $conn, $ts6) {
    $r6 = EffectFanout::forTimesheetId($conn, $g, $ts6, 72);
});
$hasSupplierEffect = false;
foreach ($r6['effects'] as $e) { if ($e['effect'] === 'supplier_due' || $e['effect'] === 'cost_record') { $hasSupplierEffect = true; } }
// الإيراد + حكم الأطراف (العميل مستحقٌّ والمورد معلَنُ التعذّر) — ولا مستحقَ ولا تكلفة
ok('صفر مستحقٍ وصفر تكلفةٍ عند الغموض (الإيراد وحكمُه وحدهما)', !$hasSupplierEffect && count($r6['effects']) === 2);

echo "── 7) مطابقة وحدة الفوترة: عقدٌ بالمتر وصفٌّ سجّل ساعاتٍ ⇒ لا تسعير ملفَّق ──\n";
$c_meter = mk_client_contract($root, $CO, $PRJ, $ETYPE, 'جنيه', 40, 'متر طولي', $seed);
$op7 = mk_op($root, $CO, $PRJ, $EQ, $ETYPE, $c_meter, $SUP, $seed);
$ts7 = mk_ts($root, $CO, $op7, $WORK_DATE, 7, 0, 0, $seed); // ساعاتٌ فقط
$ctx7 = EffectFanout::resolveTimesheet($conn, $ts7);
ok('يرفض ضربَ ساعاتٍ في سعر المتر ويعلن السبب',
    !$ctx7['client']['ok'] && strpos($ctx7['client']['reason'], 'يفوتر') !== false);
$ts7b = mk_ts($root, $CO, $op7, $WORK_DATE, 0, 120, 0, $seed); // أمتارٌ فعلية
$ctx7b = EffectFanout::resolveTimesheet($conn, $ts7b);
ok('ويقبل حين يُسجَّل المتر فعلًا (120 مترًا × 40)',
    $ctx7b['client']['ok'] && $ctx7b['unit'] === 'meter' && floatval($ctx7b['qty']) === 120.0);

echo "── 8) الذرّية: فشلٌ وسط المروحة ⇒ لا أثرٍ يتيم ──\n";
$ts8 = mk_ts($root, $CO, $op1, $WORK_DATE, 4, 0, 0, $seed);
$before8 = counts($conn);
$threw = false;
try {
    $gate->runInTransaction(function ($g) use ($conn, $ts8) {
        EffectFanout::forTimesheetId($conn, $g, $ts8, 72);
        throw new \RuntimeException('حقن فشلٍ متعمّد لاختبار الذرّية');
    });
} catch (\Throwable $e) { $threw = true; }
$after8 = counts($conn);
ok('المعاملة تراجعت (استثناءٌ حُقن)', $threw);
ok('صفر أثرٍ يتيم بعد التراجع', $after8 === $before8);

echo "── 9) المصدر الواحد: صفٌّ بلا كميةٍ لا يولّد شيئًا ──\n";
$ts9 = mk_ts($root, $CO, $op1, $WORK_DATE, 0, 0, 0, $seed);
$r9 = null;
$gate->runInTransaction(function ($g) use (&$r9, $conn, $ts9) {
    $r9 = EffectFanout::forTimesheetId($conn, $g, $ts9, 72);
});
// ستةُ آثارٍ في الخريطة الآن — وكلُّها متعذّرةٌ معلَنةُ السبب، ولا صفَّ حكمٍ فارغ
ok('صفر أثرٍ وكلُّ متعذّرٍ معلَنٌ بسببه', count($r9['effects']) === 0 && count($r9['skipped']) === 6);

} catch (\Throwable $t) {
    $FAIL++;
    echo "  ✘ استثناء غير متوقع: " . $t->getMessage() . "\n" . $t->getTraceAsString() . "\n";
}

// ── تنظيف كامل (الأثر ثم مصادره ثم عالَم البذر) ──
foreach ($seed['ts'] as $id) {
    $root->query("DELETE FROM fin_cost_records WHERE id IN (SELECT target_id FROM fin_event_links WHERE parent_kind='timesheet' AND parent_ref=$id AND target_table='fin_cost_records')");
    $root->query("DELETE FROM fin_dues WHERE id IN (SELECT target_id FROM fin_event_links WHERE parent_kind='timesheet' AND parent_ref=$id AND target_table='fin_dues')");
    $root->query("DELETE FROM fin_financial_events WHERE id IN (SELECT target_id FROM fin_event_links WHERE parent_kind='timesheet' AND parent_ref=$id AND target_table='fin_financial_events')");
    // أحكام الأطراف: صفٌّ لكل طرفٍ ورابطٌ واحدٌ للأثر — التنظيف بمفتاح المصدر
    $root->query("DELETE FROM unit_party_awards WHERE source_kind='timesheet' AND source_ref=$id");
    $root->query("DELETE FROM fin_event_links WHERE parent_kind='timesheet' AND parent_ref=$id");
    $root->query("DELETE FROM ems_business_events WHERE entity_type='timesheet' AND entity_id=$id");
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
