<?php
/**
 * E-06 · ملءُ عقود الأفعال الحاكمة العشرة — ACT-01 §9-② (update0006-b)
 * ─────────────────────────────────────────────────────────────────────
 * «ملءُ العقود للأفعال الحاكمة العشرة أولًا» — الحقولُ ⑥⑦⑧⑨ من مصادرَ
 * حيةٍ مقيسة: fin_effect_map (المروحة) · capacity_outbox · أسماءُ الأحداث
 * من الخدمات نفسِها — **لا اختراع**. idempotent.
 * التشغيل: php tools/act_seed_contracts.php
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

function w($conn, $code, $table, $op) {
    $c = mysqli_real_escape_string($conn, $code); $t = mysqli_real_escape_string($conn, $table);
    mysqli_query($conn, "INSERT IGNORE INTO action_writes (action_code, table_name, operation) VALUES ('$c','$t','$op')");
}
function ev($conn, $code, $event, $cond = null) {
    $c = mysqli_real_escape_string($conn, $code); $e = mysqli_real_escape_string($conn, $event);
    $isC = $cond ? 1 : 0; $cx = $cond ? "'" . mysqli_real_escape_string($conn, $cond) . "'" : 'NULL';
    mysqli_query($conn, "INSERT IGNORE INTO action_events (action_code, event_name, is_conditional, condition_expr) VALUES ('$c','$e',$isC,$cx)");
}
function cons($conn, $event, $class, $method, $produces) {
    $e = mysqli_real_escape_string($conn, $event); $cl = mysqli_real_escape_string($conn, $class);
    mysqli_query($conn, "INSERT IGNORE INTO event_consumers (event_name, consumer_class, consumer_method, produces, active)
                         VALUES ('$e','$cl','" . mysqli_real_escape_string($conn, $method) . "','$produces',1)");
}
function imp($conn, $code, $type, $ref, $effect, $lat = 'async') {
    $c = mysqli_real_escape_string($conn, $code);
    mysqli_query($conn, "INSERT IGNORE INTO action_impacts (action_code, impacted_type, impacted_ref, effect, latency)
                         VALUES ('$c','$type','" . mysqli_real_escape_string($conn, $ref) . "','$effect','$lat')");
}
// INSERT IGNORE يحتاج مفتاحًا فريدًا على action_impacts — يُضاف إن غاب
mysqli_query($conn, "ALTER TABLE action_impacts ADD UNIQUE KEY uq_ai (action_code, impacted_type, impacted_ref, effect)");
mysqli_query($conn, "ALTER TABLE action_writes ADD UNIQUE KEY uq_aw2 (action_code, table_name, operation)");

/* ═══ ① unit.chain.approve — أغنى الأفعال أثرًا (خريطة ACT-01 §3 نفسها) ═══ */
$A = 'unit.chain.approve';
foreach (array(array('unit_approvals','insert'), array('unit_entries','update'),
               array('capacity_consumption_ledger','insert'), array('capacity_outbox','insert'),
               array('ems_business_events','insert'), array('approval_signatures','insert')) as $t) w($conn, $A, $t[0], $t[1]);
ev($conn, $A, 'unit.link.approved');
ev($conn, $A, 'unit.chain.completed', 'آخرُ حلقةٍ في السلسلة');
ev($conn, $A, 'capacity.consumed', 'اكتمالُ السلسلة');
ev($conn, $A, 'supplier.share.consumed', 'اكتمالُ السلسلة ولحصةِ موردٍ أثر');
// المستهلكون — المروحةُ الحيةُ من fin_effect_map + دفترُ القدرات
cons($conn, 'unit.chain.completed', 'App\\Core\\EffectFanout', 'fanout', 'write');                       // → fin_financial_events · fin_dues · fin_cost_records · unit_party_awards
cons($conn, 'capacity.consumed', 'App\\Services\\Capacity\\BalanceCalculator', 'rebuild', 'dashboard_refresh');
cons($conn, 'supplier.share.consumed', 'App\\Services\\Capacity\\SupplierPerformanceAggregator', 'rebuild', 'write');
cons($conn, 'unit.link.approved', 'App\\Services\\Policy\\UnitJourneyService', 'notifyNextLink', 'notify');
// المتأثرون — الجهاتُ الستُّ من مثال ACT-01 §2
imp($conn, $A, 'org_unit', '8',  'notify');        // الحركةُ والتشغيل — الحلقةُ التالية
imp($conn, $A, 'org_unit', '1',  'counter');       // مديرُ التشغيل — فجوتُه ولوحتُه
imp($conn, $A, 'org_unit', '3',  'notify');        // المالية — أثرٌ مقترحٌ ينتظر البوابة
imp($conn, $A, 'party', 'supplier', 'data_change'); // الموردُ — استهلاكُ حصته
imp($conn, $A, 'party', 'operator', 'data_change'); // المشغّلُ — إنتاجُه واستحقاقُه
imp($conn, $A, 'screen', 'Approvals/hours_approval.php', 'counter', 'sync');
imp($conn, $A, 'screen', 'main/my_workspace.php', 'counter');

/* ═══ ② entitlement.gate.approve — بوابةُ الاستحقاق ═══ */
$A = 'entitlement.gate.approve';
foreach (array(array('fin_dues','update'), array('ems_business_events','insert'), array('approval_signatures','insert')) as $t) w($conn, $A, $t[0], $t[1]);
ev($conn, $A, 'policy.entitlement.posted');       // الاسمُ الحيُّ من UnitJourneyService:219
cons($conn, 'policy.entitlement.posted', 'App\\Core\\EffectFanout', 'fanout', 'write');
imp($conn, $A, 'org_unit', '3', 'data_change');
imp($conn, $A, 'party', 'supplier', 'notify');
imp($conn, $A, 'party', 'operator', 'notify');

/* ═══ ③ invoice.issue / invoice.cancel ═══ */
w($conn, 'invoice.issue', 'tax_invoices', 'insert'); w($conn, 'invoice.issue', 'ems_business_events', 'insert');
ev($conn, 'invoice.issue', 'revenue.invoice.issued');
cons($conn, 'revenue.invoice.issued', 'App\\Core\\EffectFanout', 'fanout', 'write');
imp($conn, 'invoice.issue', 'party', 'client', 'notify');
imp($conn, 'invoice.issue', 'org_unit', '3', 'data_change');
w($conn, 'invoice.cancel', 'tax_invoices', 'update'); ev($conn, 'invoice.cancel', 'revenue.invoice.cancelled');
cons($conn, 'revenue.invoice.cancelled', 'App\\Core\\EffectFanout', 'fanout', 'write');
imp($conn, 'invoice.cancel', 'org_unit', '3', 'data_change');

/* ═══ ④ collection.record / reverse ═══ */
w($conn, 'collection.record', 'fin_collections', 'insert'); w($conn, 'collection.record', 'ems_business_events', 'insert');
ev($conn, 'collection.record', 'revenue.collection.recorded');
cons($conn, 'revenue.collection.recorded', 'App\\Core\\EffectFanout', 'fanout', 'write');
imp($conn, 'collection.record', 'org_unit', '3', 'data_change');
imp($conn, 'collection.record', 'org_unit', '2', 'counter');   // المبيعاتُ — ذمّتُها تتحدث
w($conn, 'collection.reverse', 'fin_collections', 'update');
ev($conn, 'collection.reverse', 'revenue.collection.allocated');
cons($conn, 'revenue.collection.allocated', 'App\\Services\\Revenue\\CollectionService', 'reflectClaimState', 'write');

/* ═══ ⑤ payroll.run / reverse ═══ */
w($conn, 'payroll.run', 'payroll_runs', 'insert'); w($conn, 'payroll.run', 'ems_business_events', 'insert');
ev($conn, 'payroll.run', 'payroll.run.opened');
cons($conn, 'payroll.run.opened', 'App\\Services\\Payroll\\PayrollRunService', 'bindSnapshots', 'write');
imp($conn, 'payroll.run', 'org_unit', '14', 'data_change');
imp($conn, 'payroll.run', 'org_unit', '3', 'notify');
w($conn, 'payroll.reverse', 'payroll_runs', 'update');
ev($conn, 'payroll.reverse', 'payroll.deductions.reversed');
cons($conn, 'payroll.deductions.reversed', 'App\\Services\\Payroll\\OffsetService', 'reverseRunDeductions', 'write');

/* ═══ ⑥ supplier.settlement.approve / reverse ═══ */
w($conn, 'supplier.settlement.approve', 'coverage_settlement_lines', 'insert');
w($conn, 'supplier.settlement.approve', 'ems_business_events', 'insert');
ev($conn, 'supplier.settlement.approve', 'coverage.settled');
cons($conn, 'coverage.settled', 'App\\Core\\EffectFanout', 'fanout', 'write');
imp($conn, 'supplier.settlement.approve', 'party', 'supplier', 'notify');
imp($conn, 'supplier.settlement.approve', 'org_unit', '3', 'data_change');
w($conn, 'supplier.settlement.reverse', 'supplier_shares', 'update');
ev($conn, 'supplier.settlement.reverse', 'supplier.share.modified');
cons($conn, 'supplier.share.modified', 'App\\Services\\Capacity\\SupplierPerformanceAggregator', 'rebuild', 'write');

/* ═══ ⑦ unit.state.change / revert ═══ */
w($conn, 'unit.state.change', 'unit_state_changes', 'insert'); w($conn, 'unit.state.change', 'change_approvals', 'insert');
ev($conn, 'unit.state.change', 'workflow.state_changed');      // الاسمُ الحيُّ (83 حدثًا)
cons($conn, 'workflow.state_changed', 'App\\Core\\EffectFanout', 'fanout', 'write');
imp($conn, 'unit.state.change', 'org_unit', '8', 'notify');
imp($conn, 'unit.state.change', 'org_unit', '1', 'notify');
w($conn, 'unit.state.revert', 'unit_state_changes', 'insert');
ev($conn, 'unit.state.revert', 'workflow.state_changed');

/* ═══ ⑧ coverage.substitute.activate / end ═══ */
w($conn, 'coverage.substitute.activate', 'substitute_coverages', 'insert');
ev($conn, 'coverage.substitute.activate', 'coverage.substitute.approved');
cons($conn, 'coverage.substitute.approved', 'App\\Services\\Capacity\\GapMonitor', 'recompute', 'dashboard_refresh');
imp($conn, 'coverage.substitute.activate', 'org_unit', '8', 'notify');
imp($conn, 'coverage.substitute.activate', 'party', 'supplier', 'notify');
w($conn, 'coverage.substitute.end', 'substitute_coverages', 'update');
w($conn, 'coverage.substitute.end', 'coverage_settlement_lines', 'insert');
ev($conn, 'coverage.substitute.end', 'coverage.settled');

/* ═══ ⑨ capacity.consume / عكسُه في ① ═══ */
w($conn, 'capacity.consume', 'capacity_consumption_ledger', 'insert');
w($conn, 'capacity.consume', 'capacity_outbox', 'insert');
ev($conn, 'capacity.consume', 'capacity.consumed');
w($conn, 'unit.chain.reverse', 'capacity_consumption_ledger', 'insert'); // سطرٌ عاكسٌ لا حذف
ev($conn, 'unit.chain.reverse', 'capacity.consumption.reversed');
cons($conn, 'capacity.consumption.reversed', 'App\\Services\\Capacity\\BalanceCalculator', 'rebuild', 'write');

/* ═══ ⑩ period.provisions.run / reverse ═══ */
w($conn, 'period.provisions.run', 'fin_financial_events', 'insert');
w($conn, 'period.provisions.run', 'ems_business_events', 'insert');
ev($conn, 'period.provisions.run', 'expense.depreciation.recorded');   // الاسمُ الحيُّ (449 حدثًا)
ev($conn, 'period.provisions.run', 'payable.finance_installment.accrued'); // الحيُّ (46)
cons($conn, 'expense.depreciation.recorded', 'App\\Core\\EffectFanout', 'fanout', 'write');
cons($conn, 'payable.finance_installment.accrued', 'App\\Core\\EffectFanout', 'fanout', 'write');
imp($conn, 'period.provisions.run', 'org_unit', '3', 'data_change');
imp($conn, 'period.provisions.run', 'org_unit', '4', 'data_change');   // التمويلُ — أقساطُه
w($conn, 'period.provisions.reverse', 'fin_financial_events', 'insert');
ev($conn, 'period.provisions.reverse', 'finance.recon_adjustment.posted'); // الحيُّ (46)
cons($conn, 'finance.recon_adjustment.posted', 'App\\Core\\EffectFanout', 'fanout', 'write');

/* ═══ عكوسٌ متبقيةٌ بأحداثها ═══ */
w($conn, 'entitlement.gate.reverse', 'unit_effects', 'insert');
ev($conn, 'entitlement.gate.reverse', 'policy.line.objected');
cons($conn, 'policy.line.objected', 'App\\Services\\Policy\\UnitJourneyService', 'objectLine', 'write');

/* ═══ التقرير ═══ */
foreach (array('action_writes', 'action_events', 'event_consumers', 'action_impacts') as $t) {
    $r = mysqli_query($conn, "SELECT COUNT(*) c FROM $t");
    echo "$t: " . mysqli_fetch_assoc($r)['c'] . "\n";
}
