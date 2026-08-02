<?php
/**
 * UAT-0001 · بذرة ⑪ — الجداولُ التي أبت المولِّدَ العامّ.
 *
 * كلُّ واحدٍ منها له قيدُ `CHECK` يحمل **قاعدةَ عملٍ** لا مجردَ نوع: خطةُ الموارد
 * تشترط ورديةً بين 1 و4، وخطةُ الدفع تشترط تاريخَ استحقاقٍ أو شرطَه، والضمانُ
 * النقديُّ وحده يُخصم من المستخلص، والتبديلُ يشترط اختلافَ الداخل عن الخارج.
 * فتُملأ يدويًّا بدلالتها الصحيحة — والقيدُ هو الذي علّمنا القاعدة.
 */
require __DIR__ . '/_lib.php';
mysqli_report(MYSQLI_REPORT_ERROR);

$db    = uat_db();
$CO    = UAT_COMPANY;
$actor = uat_actor();
$other = (int) ($db->query("SELECT id FROM users WHERE company_id=$CO AND id<>$actor ORDER BY id LIMIT 1")->fetch_assoc()['id'] ?? $actor + 1);

$try = function (callable $fn, $table) {
    try { $fn(); uat_log($table, 'صف'); } catch (Throwable $e) { uat_log($table, 'رُفض'); }
};

$contracts = [];
foreach ($db->query("SELECT id, project_id, actual_start, actual_end FROM contracts WHERE company_id=$CO AND is_deleted=0 ORDER BY id DESC LIMIT 30") as $r) $contracts[] = $r;
$lineIds = [];
foreach ($db->query("SELECT id FROM client_contract_lines LIMIT 40") as $r) $lineIds[] = (int) $r['id'];
$eqIds = [];
foreach ($db->query("SELECT id FROM equipments WHERE company_id=$CO ORDER BY id DESC LIMIT 40") as $r) $eqIds[] = (int) $r['id'];
$typeIds = [];
foreach ($db->query("SELECT id FROM equipments_types LIMIT 20") as $r) $typeIds[] = (int) $r['id'];

// ── ① خطةُ موارد العقد ──────────────────────────────────────────────────────
$i = 0;
foreach ($contracts as $c) {
    foreach ([0, 1] as $k) {
        $i++;
        if (uat_count('contract_resource_plan', "company_id=$CO") >= 20) break 2;
        $try(function () use ($db, $CO, $actor, $c, $lineIds, $typeIds, $i) {
            uat_insert('contract_resource_plan', [
                'company_id' => $CO, 'contract_id' => $c['id'],
                'line_id' => $lineIds[$i % max(1, count($lineIds))] ?? 1,
                'equipment_type_id' => $typeIds[$i % max(1, count($typeIds))] ?? 1,
                'count_basic' => 2 + ($i % 5), 'count_backup' => $i % 2,
                'shifts_per_day' => 2, 'hours_per_shift' => 10.00,
                'operators_count' => 4 + ($i % 4), 'supervisors_count' => 1,
                'technicians_count' => 1, 'assistants_count' => $i % 2,
                'capacity_share_percent' => 100.000, 'share_kind' => ($i % 4 === 0) ? 'backup_only' : 'productive',
                'valid_from' => $c['actual_start'], 'valid_to' => $c['actual_end'],
                'state' => 'active', 'note' => 'خطةُ مواردَ مفصولةٌ عن بنود المبيعات',
                'created_by' => $actor, 'is_deleted' => 0,
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }, 'contract_resource_plan');
    }
}

// ── ② خطةُ الدفع — مقدمٌ ثم مستخلصاتٌ شهريةٌ ثم ردُّ المحتجز ─────────────────
$i = 0;
foreach ($contracts as $c) {
    if (uat_count('contract_payment_schedule', "company_id=$CO") >= 20) break;
    $plan = [
        ['advance_installments', 'advance', 'percent', 20.0, 'مقدمٌ مستردٌّ يُستهلك على الدفعات'],
        ['monthly_claim', 'monthly_settlement', 'percent', 75.0, 'مستخلصٌ شهريٌّ بعد الاعتماد'],
        ['retention_release', 'retention_release', 'percent', 5.0, 'ردُّ المحتجز بعد الاستلام النهائي'],
    ];
    foreach ($plan as $seq => [$pattern, $kind, $basis, $pct, $note]) {
        $i++;
        $try(function () use ($db, $CO, $actor, $c, $pattern, $kind, $basis, $pct, $note, $seq, $i) {
            uat_insert('contract_payment_schedule', [
                'company_id' => $CO, 'contract_id' => $c['id'], 'version' => 1,
                'effective_from' => $c['actual_start'], 'seq' => $seq + 1,
                'pattern' => $pattern, 'payment_kind' => $kind,
                'advance_type' => $kind === 'advance' ? 'recoverable' : null,
                'treatment' => $kind === 'advance' ? 'liability' : 'revenue',
                'amount_basis' => $basis, 'percent_value' => $pct,
                'amount_expected' => round(12000 + $i * 1350, 2), 'currency' => 'USD',
                'due_date' => date('Y-m-d', strtotime($c['actual_start'] . ' +' . (30 * ($seq + 1)) . ' day')),
                'due_condition' => $note, 'received_amount' => 0,
                'state' => 'not_due', 'source' => 'generated', 'note' => $note,
                'created_by' => $actor, 'is_deleted' => 0,
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }, 'contract_payment_schedule');
    }
}

// ── ③ ضماناتُ العقد — النقديُّ وحده يُخصم من المستخلص ───────────────────────
$i = 0;
foreach ($contracts as $c) {
    if (uat_count('contract_guarantees', "company_id=$CO") >= 20) break;
    $i++;
    $cash = ($i % 2 === 1);
    $try(function () use ($CO, $actor, $c, $cash, $i) {
        uat_insert('contract_guarantees', [
            'company_id' => $CO, 'contract_id' => $c['id'],
            'kind' => $cash ? 'cash_retention' : 'performance_bond',
            'amount' => round(5000 + $i * 750, 2), 'currency' => 'USD',
            'deductible_from_claim' => $cash ? 1 : 0,
            'issue_date' => $c['actual_start'],
            'expiry_date' => $cash ? null : date('Y-m-d', strtotime(($c['actual_end'] ?: date('Y-m-d')) . ' +90 day')),
            'due_release_date' => $cash ? date('Y-m-d', strtotime(($c['actual_end'] ?: date('Y-m-d')) . ' +180 day')) : null,
            'release_condition' => $cash ? 'بعد الاستلام النهائي وانتهاء فترة الضمان' : null,
            'state' => 'active', 'note' => $cash ? 'محتجزٌ نقديٌّ 5٪' : 'خطابُ ضمانِ حسن تنفيذ',
            'created_by' => $actor, 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }, 'contract_guarantees');
}

// ── ④ تعاقبُ المعدات على المقعد — الداخلُ غيرُ الخارج ───────────────────────
$seats = [];
foreach ($db->query("SELECT id, contract_id, equipment_id FROM op_containers WHERE company_id=$CO AND level='معدة' AND equipment_id IS NOT NULL ORDER BY id DESC LIMIT 30") as $r) $seats[] = $r;
$i = 0;
foreach ($seats as $s) {
    if (uat_count('container_swaps', "company_id=$CO") >= 20) break;
    $i++;
    $in = $eqIds[$i % max(1, count($eqIds))] ?? null;
    if (!$in || (int) $in === (int) $s['equipment_id']) $in = $eqIds[($i + 1) % max(1, count($eqIds))] ?? null;
    if (!$in || (int) $in === (int) $s['equipment_id']) continue;
    $try(function () use ($CO, $actor, $s, $in, $i) {
        uat_insert('container_swaps', [
            'company_id' => $CO, 'container_id' => $s['id'],
            'out_ref' => $s['equipment_id'], 'in_ref' => $in,
            'swap_kind' => 'equipment', 'swap_date' => date('Y-m-d', strtotime('-' . (30 + $i * 9) . ' day')),
            'reason' => ['عطلٌ يستوجب الإحلال', 'انتهاءُ عقد المورد', 'ترقيةُ الطاقة الإنتاجية', 'صيانةٌ رأسمالية'][$i % 4],
            'created_by' => $actor, 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }, 'container_swaps');
}

// ── ⑤ أحداثُ دورة حياة العقد — التمديدُ بمجموعة آثارٍ محدَّدة ───────────────
$i = 0;
foreach ($contracts as $c) {
    if (uat_count('contract_lifecycle_events', "company_id=$CO") >= 20) break;
    $i++;
    $try(function () use ($CO, $actor, $c, $i) {
        uat_insert('contract_lifecycle_events', [
            'company_id' => $CO, 'contract_id' => $c['id'], 'state' => 'extension',
            'advance_effect' => 'continue', 'retention_effect' => 'hold',
            'unbilled_effect' => 'bill_cycle', 'penalty_effect' => 'continue',
            'container_effect' => 'extend',
            'event_date' => date('Y-m-d', strtotime('-' . (20 + $i * 11) . ' day')),
            'note' => 'تمديدُ العقد بالشروط نفسِها لمدة ثلاثة أشهر',
            'created_by' => $actor, 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }, 'contract_lifecycle_events');
}

// ── ⑥ تقييمُ الموردين — النسبةُ بين صفرٍ وواحد ──────────────────────────────
$sups = [];
foreach ($db->query("SELECT id FROM suppliers WHERE company_id=$CO ORDER BY id DESC LIMIT 25") as $r) $sups[] = (int) $r['id'];
$crit = ['readiness', 'quality', 'response_time', 'incidents', 'compliance', 'cost'];
$i = 0;
foreach ($db->query("SELECT id FROM supplier_evaluations WHERE company_id=$CO ORDER BY id DESC LIMIT 25") as $e) {
    foreach ($crit as $j => $cr) {
        if (uat_count('supplier_evaluation_lines', "company_id=$CO") >= 20) break 2;
        $i++;
        $try(function () use ($CO, $e, $cr, $i) {
            uat_insert('supplier_evaluation_lines', [
                'company_id' => $CO, 'evaluation_id' => $e['id'], 'criterion' => $cr,
                'ratio' => round(0.55 + ($i % 9) * 0.05, 2), 'score' => 55 + ($i % 40),
                'note' => 'مقيَّمٌ من واقع الأداء الشهري',
            ]);
        }, 'supplier_evaluation_lines');
    }
}

// ── ⑦ تسوياتُ نهاية الخدمة — يدُ الإعداد غيرُ يد الاعتماد ───────────────────
$emps = [];
foreach ($db->query("SELECT id FROM employees WHERE company_id=$CO ORDER BY id DESC LIMIT 25") as $r) $emps[] = (int) $r['id'];
foreach ($emps as $i => $emp) {
    if (uat_count('employee_final_settlements', "company_id=$CO") >= 20) break;
    $try(function () use ($CO, $actor, $other, $emp, $i) {
        uat_insert('employee_final_settlements', [
            'company_id' => $CO, 'employee_id' => $emp,
            'end_date' => date('Y-m-d', strtotime('-' . (40 + $i * 13) . ' day')),
            'reason' => ['استقالة', 'انتهاءُ عقد', 'إنهاءُ خدمة'][$i % 3],
            'service_years' => round(1 + ($i % 7) + 0.5, 2),
            'gross_amount' => round(1200 + $i * 210, 2), 'deductions_amount' => round(100 + $i * 15, 2),
            'net_amount' => round(1100 + $i * 195, 2), 'currency' => 'USD',
            'state' => 'approved',
            'prepared_by' => $actor, 'approved_by' => $other,     // ck_fs_hands: فصلُ اليدين
            'note' => 'تصفيةٌ نهائيةٌ مطابقةٌ لعقد العامل',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }, 'employee_final_settlements');
}

// ── ⑧ مطابقةُ كشوف البنك — القرارُ باسمٍ لا مجهول ──────────────────────────
$bsl = [];
foreach ($db->query("SELECT id FROM bank_statement_lines ORDER BY id DESC LIMIT 25") as $r) $bsl[] = (int) $r['id'];
foreach ($bsl as $i => $l) {
    if (uat_count('bank_recon_matches') >= 20) break;
    $try(function () use ($CO, $actor, $l, $i) {
        uat_insert('bank_recon_matches', [
            'company_id' => $CO, 'statement_line_id' => $l,
            'match_kind' => 'payment', 'matched_ref' => 1000 + $i,
            'amount' => round(500 + $i * 320, 2),
            'state' => ($i % 3 === 0) ? 'resolved' : 'suggested',
            'decided_by' => ($i % 3 === 0) ? $actor : null,
            'decided_at' => ($i % 3 === 0) ? date('Y-m-d H:i:s') : null,
            'note' => 'مطابقةٌ آليةٌ بمبلغٍ وتاريخٍ متطابقين',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }, 'bank_recon_matches');
}

// ── ⑨ أنواعُ الغياب — رموزٌ فريدة ───────────────────────────────────────────
$abs = ['ANN' => 'إجازةٌ سنوية', 'SICK' => 'إجازةٌ مرضية', 'UNPAID' => 'إجازةٌ بلا أجر', 'FIELD' => 'إجازةٌ ميدانية',
        'MISSION' => 'مأمورية', 'EMERG' => 'حالةٌ طارئة', 'ABSENT' => 'غيابٌ بلا إذن', 'TRAVEL_IN' => 'في الطريق إلى المشروع',
        'TRAVEL_OUT' => 'في الطريق إلى الإجازة', 'ARRIVE' => 'يومُ الوصول', 'DEPART' => 'يومُ المغادرة',
        'WAIT' => 'انتظارُ أمر عمل', 'TRAIN' => 'تدريب', 'SUSP' => 'إيقافٌ تأديبي', 'HAJJ' => 'إجازةُ حج',
        'PAT' => 'إجازةُ أبوّة', 'MAT' => 'إجازةُ أمومة', 'COMP' => 'تعويضُ راحة', 'OFF' => 'راحةٌ أسبوعية', 'HOL' => 'عطلةٌ رسمية'];
foreach ($abs as $code => $name) {
    $try(function () use ($CO, $code, $name) {
        if (uat_one("SELECT id FROM payroll_absence_types WHERE company_id=? AND code=?", [$CO, $code])) return;
        uat_insert('payroll_absence_types', [
            'company_id' => $CO, 'code' => $code, 'name_ar' => $name,
            'paid' => in_array($code, ['UNPAID', 'ABSENT', 'SUSP'], true) ? 0 : 1,
            'affects_entitlement' => 1, 'active' => 1,
        ]);
    }, 'payroll_absence_types');
}

uat_print_report('البذرة ⑪ · الجداول ذات القيود السلوكية');

$still = [];
foreach (['contract_resource_plan', 'contract_payment_schedule', 'contract_guarantees', 'container_swaps',
          'contract_lifecycle_events', 'supplier_evaluation_lines', 'supplier_evaluation_weights',
          'employee_final_settlements', 'employee_final_settlement_lines', 'bank_recon_matches',
          'payroll_absence_types', 'payroll_settings', 'rfq_quotes', 'rfq_awards'] as $t) {
    $n = uat_count($t);
    if ($n < 20) $still[] = "$t=$n";
}
echo "   ما زال دون العشرين: " . ($still ? implode(' · ', $still) : 'لا شيء ✔') . "\n";
