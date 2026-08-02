<?php
/**
 * UAT-0001 · بذرة ⑫ — إتمامُ الجداول ذات القيود السلوكية بأعمدتها الصحيحة.
 * (تكملةُ ⑪ بعد قراءة البنية الفعلية لكل جدول: الإلزامُ وقيمُ الـENUM.)
 */
require __DIR__ . '/_lib.php';
mysqli_report(MYSQLI_REPORT_ERROR);

$db    = uat_db();
$CO    = UAT_COMPANY;
$actor = uat_actor();
$other = (int) ($db->query("SELECT id FROM users WHERE company_id=$CO AND id<>$actor ORDER BY id LIMIT 1")->fetch_assoc()['id'] ?? $actor + 1);
$now   = date('Y-m-d H:i:s');

$try = function (callable $fn, $t) { try { $fn(); uat_log($t, 'صف'); } catch (Throwable $e) { uat_log($t, 'رُفض: ' . substr($e->getMessage(), 0, 40)); } };
$fill = fn($t, $where = '1') => uat_count($t, $where) < 20;

$contracts = [];
foreach ($db->query("SELECT id, actual_start, actual_end FROM contracts WHERE company_id=$CO AND is_deleted=0 ORDER BY id DESC LIMIT 40") as $r) $contracts[] = $r;

// ── ضماناتُ العقد ───────────────────────────────────────────────────────────
foreach ($contracts as $i => $c) {
    if (!$fill('contract_guarantees')) break;
    $cash = ($i % 2 === 0);
    $try(fn() => uat_insert('contract_guarantees', [
        'company_id' => $CO, 'contract_id' => $c['id'],
        'kind' => $cash ? 'cash_retention' : 'bank_guarantee',
        'nature' => $cash ? 'asset' : 'off_balance',
        'deductible_from_claim' => $cash ? 1 : 0,
        'amount' => round(5000 + $i * 820, 2), 'currency' => 'USD',
        'state' => 'active', 'needs_review' => 0, 'is_deleted' => 0,
        'due_release_date' => $cash ? date('Y-m-d', strtotime(($c['actual_end'] ?: date('Y-m-d')) . ' +180 day')) : null,
        'release_condition' => $cash ? 'بعد الاستلام النهائي وانقضاء فترة الضمان' : null,
        'expiry_date' => $cash ? null : date('Y-m-d', strtotime(($c['actual_end'] ?: date('Y-m-d')) . ' +90 day')),
        'created_at' => $now,
    ]), 'contract_guarantees');
}

// ── تعاقبُ المعدات على المقاعد ──────────────────────────────────────────────
$seats = [];
foreach ($db->query("SELECT id, equipment_id FROM op_containers WHERE company_id=$CO AND level='معدة' AND equipment_id IS NOT NULL ORDER BY id DESC LIMIT 40") as $r) $seats[] = $r;
$eq = [];
foreach ($db->query("SELECT id FROM equipments WHERE company_id=$CO ORDER BY id DESC LIMIT 40") as $r) $eq[] = (int) $r['id'];
foreach ($seats as $i => $s) {
    if (!$fill('container_swaps')) break;
    $in = $eq[($i * 3 + 1) % max(1, count($eq))] ?? null;
    if (!$in || (int) $in === (int) $s['equipment_id']) continue;
    $try(fn() => uat_insert('container_swaps', [
        'company_id' => $CO, 'container_id' => $s['id'], 'swap_kind' => 'معدة',
        'out_ref' => $s['equipment_id'], 'in_ref' => $in,
        'effective_from' => date('Y-m-d', strtotime('-' . (25 + $i * 8) . ' day')),
        'reason' => ['عطلٌ يستوجب الإحلال خلال المهلة', 'انتهاءُ عقد المورد', 'ترقيةُ الطاقة الإنتاجية', 'صيانةٌ رأسمالية'][$i % 4],
        'created_by' => $actor, 'created_at' => $now,
    ]), 'container_swaps');
}

// ── أحداثُ دورة حياة العقد — كلُّ حالةٍ بمجموعة آثارها المسموحة ─────────────
$LIFE = [
    ['extension',   'continue',      'hold',                'bill_cycle',       'continue',                   'extend'],
    ['renewal',     'settle_and_new', 'release_after_grace', 'final_claim_old',  'close_old_start_new',        'new_tree'],
    ['suspension',  'pause_recovery', 'hold',                'bill_before_pause', 'pause_time_not_performance', 'suspend'],
    ['natural_end', 'consume_then_refund', 'release_after_grace', 'final_claim',  'accrue_to_effect_date',      'close_readonly'],
];
$i = 0;
foreach ($contracts as $c) {
    foreach ($LIFE as $L) {
        if (!$fill('contract_lifecycle_events')) break 2;
        $i++;
        $try(fn() => uat_insert('contract_lifecycle_events', [
            'company_id' => $CO, 'contract_id' => $c['id'], 'state' => $L[0],
            'advance_effect' => $L[1], 'retention_effect' => $L[2], 'unbilled_effect' => $L[3],
            'penalty_effect' => $L[4], 'container_effect' => $L[5],
            'effect_date' => date('Y-m-d', strtotime('-' . (15 + $i * 7) . ' day')),
            'is_deleted' => 0, 'created_at' => $now,
        ]), 'contract_lifecycle_events');
    }
}

// ── أوزانُ ومؤشراتُ تقييم الموردين ─────────────────────────────────────────
$IND = ['readiness' => 30, 'coverage' => 25, 'attributed_stops' => 20, 'operator_quality' => 15, 'incidents' => 10];
foreach ($IND as $ind => $w) {
    $try(function () use ($CO, $ind, $w, $now) {
        if (uat_one("SELECT id FROM supplier_evaluation_weights WHERE company_id=? AND indicator=?", [$CO, $ind])) return;
        uat_insert('supplier_evaluation_weights', ['company_id' => $CO, 'indicator' => $ind, 'weight' => $w, 'is_deleted' => 0, 'created_at' => $now]);
    }, 'supplier_evaluation_weights');
}
$i = 0;
foreach ($db->query("SELECT id FROM supplier_evaluations WHERE company_id=$CO ORDER BY id DESC LIMIT 30") as $e) {
    foreach ($IND as $ind => $w) {
        if (!$fill('supplier_evaluation_lines')) break 2;
        $i++;
        $try(fn() => uat_insert('supplier_evaluation_lines', [
            'company_id' => $CO, 'evaluation_id' => $e['id'], 'indicator' => $ind,
            'measurable' => 1, 'weight' => $w, 'earned' => round($w * (0.6 + ($i % 5) * 0.08), 2),
            'ratio' => round(0.6 + ($i % 5) * 0.08, 2), 'created_at' => $now,
        ]), 'supplier_evaluation_lines');
    }
}

// ── تصفياتُ نهاية الخدمة وأسطرُها ──────────────────────────────────────────
$ec = [];
foreach ($db->query("SELECT id, employee_id FROM employee_contracts WHERE company_id=$CO ORDER BY id DESC LIMIT 40") as $r) $ec[] = $r;
foreach ($ec as $i => $c) {
    if (!$fill('employee_final_settlements')) break;
    $dues = round(600 + $i * 55, 2); $leave = round(300 + $i * 25, 2); $eos = round(900 + $i * 90, 2);
    $off  = round(120 + $i * 10, 2); $net = round($dues + $leave + $eos - $off, 2);
    $try(fn() => uat_insert('employee_final_settlements', [
        'company_id' => $CO, 'contract_id' => $c['id'], 'employee_id' => $c['employee_id'],
        'effective_date' => date('Y-m-d', strtotime('-' . (35 + $i * 11) . ' day')),
        'currency' => 'USD', 'service_years' => round(1 + ($i % 7) + 0.5, 2),
        'dues_amount' => $dues, 'leave_amount' => $leave, 'eos_amount' => $eos,
        'advances_offset' => $off, 'advances_remaining' => 0,
        'net_amount' => $net, 'recognized_amount' => $net,
        'state' => 'approved', 'prepared_by' => $actor, 'approved_by' => $other,
        'is_deleted' => 0, 'created_at' => $now,
    ]), 'employee_final_settlements');
}
$i = 0;
foreach ($db->query("SELECT id FROM employee_final_settlements WHERE company_id=$CO ORDER BY id DESC LIMIT 30") as $s) {
    foreach ([['dues', 'مستحقاتٌ غيرُ مدفوعة'], ['leave', 'رصيدُ إجازاتٍ نقديّ'], ['eos', 'مكافأةُ نهاية الخدمة'], ['advance_offset', 'خصمُ سلفةٍ قائمة']] as $j => [$k, $d]) {
        if (!$fill('employee_final_settlement_lines')) break 2;
        $i++;
        $try(fn() => uat_insert('employee_final_settlement_lines', [
            'company_id' => $CO, 'settlement_id' => $s['id'], 'line_type' => $k,
            'description' => $d, 'amount' => round(($k === 'advance_offset' ? -1 : 1) * (200 + $i * 35), 2),
            'computable' => 1, 'created_at' => $now,
        ]), 'employee_final_settlement_lines');
    }
}

// ── مطابقةُ كشوف البنك ─────────────────────────────────────────────────────
$i = 0;
foreach ($db->query("SELECT id FROM bank_statement_lines ORDER BY id DESC LIMIT 30") as $l) {
    if (!$fill('bank_recon_matches')) break;
    $i++;
    $amt = round(500 + $i * 275, 2);
    $res = ($i % 3 === 0);
    $try(fn() => uat_insert('bank_recon_matches', [
        'company_id' => $CO, 'statement_line_id' => $l['id'],
        'match_kind' => $res ? 'manual' : 'auto',
        'bank_amount' => $amt, 'system_amount' => $res ? $amt : round($amt - 12.5, 2),
        'state' => $res ? 'resolved' : 'open_difference',
        'decided_by' => $res ? $actor : null, 'decided_at' => $res ? $now : null,
        'created_at' => $now,
    ]), 'bank_recon_matches');
}

// ── أنواعُ الغياب والحضور ──────────────────────────────────────────────────
$ABS = [
    ['يوم عمل', 'full', 'site', 'yes', 'yes', 0],
    ['يوم الوصول إلى الموقع', 'full', 'transit', 'no', 'per_contract', 0],
    ['يوم مغادرة الموقع', 'full', 'transit', 'no', 'per_contract', 0],
    ['إجازة ميدانية', 'per_contract', 'off', 'no', 'no', 0],
    ['مأمورية', 'full', 'mission', 'by_attribution', 'by_attribution', 0],
    ['إجازة مرضية', 'per_policy', 'off', 'no', 'no', 0],
    ['غياب بلا إذن', 'none', 'off', 'no', 'no', 1],
    ['حالة طارئة', 'per_hr', 'off', 'no', 'no', 0],
    ['إجازة بدون أجر', 'none', 'off', 'no', 'no', 0],
    ['انتظار أمر عمل', 'full', 'site', 'by_attribution', 'by_attribution', 0],
    ['في الطريق إلى المشروع', 'full', 'transit', 'no', 'per_contract', 0],
    ['في الطريق إلى الإجازة', 'full', 'transit', 'no', 'no', 0],
    ['إجازة سنوية', 'full', 'off', 'no', 'no', 0],
    ['راحة أسبوعية', 'full', 'off', 'no', 'no', 0],
    ['عطلة رسمية', 'full', 'off', 'no', 'per_contract', 0],
    ['تدريب', 'full', 'mission', 'no', 'no', 0],
    ['إيقاف تأديبي', 'none', 'off', 'no', 'no', 1],
    ['إجازة حج', 'per_policy', 'off', 'no', 'no', 0],
    ['إجازة أبوّة', 'full', 'off', 'no', 'no', 0],
    ['تعويض راحة', 'full', 'off', 'no', 'no', 0],
];
foreach ($ABS as $i => [$type, $pay, $pres, $bill, $sup, $viol]) {
    $try(function () use ($CO, $type, $pay, $pres, $bill, $sup, $viol, $now) {
        if (uat_one("SELECT id FROM payroll_absence_types WHERE company_id=? AND event_type=?", [$CO, $type])) return;
        uat_insert('payroll_absence_types', [
            'company_id' => $CO, 'event_type' => $type, 'pay_effect' => $pay, 'presence' => $pres,
            'billable' => $bill, 'supplier_due' => $sup, 'deducts' => $pay === 'none' ? 1 : 0,
            'deduct_percent' => $pay === 'none' ? 100 : 0, 'incentive_base' => $pres === 'site' ? 1 : 0,
            'conduct_violation' => $viol, 'active' => 1, 'created_at' => $now,
        ]);
    }, 'payroll_absence_types');
}

uat_print_report('البذرة ⑫ · إتمام الجداول السلوكية');
$still = [];
foreach (['contract_guarantees', 'container_swaps', 'contract_lifecycle_events', 'supplier_evaluation_lines',
          'supplier_evaluation_weights', 'employee_final_settlements', 'employee_final_settlement_lines',
          'bank_recon_matches', 'payroll_absence_types', 'contract_resource_plan', 'contract_payment_schedule'] as $t) {
    $n = uat_count($t);
    $still[] = "$t=$n" . ($n >= 20 ? '✔' : '');
}
echo '   ' . implode(' · ', $still) . "\n";
