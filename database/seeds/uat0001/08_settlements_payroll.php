<?php
/**
 * UAT-0001 · بذرة ⑧ — الاستحقاقاتُ المقابلة: تسوياتُ الموردين ومسيَّراتُ الأجور.
 *
 * «الواقعةُ التشغيلية الواحدة تُنتج ثلاثةَ آثارٍ مستقلة»: ما تستحقه الشركةُ من
 * العميل (البذرة ⑦) · وما يستحقه المورد (م03) · وما يستحقه المشغّل (ش03).
 * وهنا يُبنى الأثران الثاني والثالث بأرقامهما الحقيقية لا بنسخِ الأول.
 */
require __DIR__ . '/_lib.php';
set_time_limit(0);

$db    = uat_db();
$actor = uat_actor();
$CO    = UAT_COMPANY;

$mapSup  = json_decode(file_get_contents(UAT_IMPORT_DIR . '/_map_suppliers.json'), true);
$mapOper = json_decode(file_get_contents(UAT_IMPORT_DIR . '/_map_operators.json'), true);

$acc = [];
foreach ($db->query("SELECT code,id FROM fin_chart_of_accounts WHERE company_id=$CO") as $a) $acc[$a['code']] = (int) $a['id'];
$A = fn($c) => $acc[$c] ?? null;

$jseq = (int) preg_replace('/\D/', '', (string) ($db->query("SELECT COALESCE(MAX(entry_no),'JV-000000') m FROM fin_journal_entries WHERE company_id=$CO")->fetch_assoc()['m']));
$journal = function ($date, $amount, $dr, $cr, $memo) use ($db, $CO, $actor, &$jseq) {
    if ($amount <= 0 || !$dr || !$cr) return null;
    $jseq++;
    $eid = uat_insert('fin_journal_entries', [
        'company_id' => $CO, 'entry_no' => sprintf('JV-%06d', $jseq), 'posting_date' => $date, 'txn_date' => $date,
        'currency' => 'USD', 'fx_rate' => 1, 'base_amount' => $amount, 'total_debit' => $amount, 'total_credit' => $amount,
        'memo' => mb_substr($memo, 0, 255), 'state' => 'posted', 'posted_by' => $actor, 'posted_at' => $date . ' 12:00:00',
        'is_deleted' => 0, 'created_by' => $actor, 'created_at' => $date . ' 12:00:00', 'updated_at' => $date . ' 12:00:00',
    ]);
    foreach ([[$dr, $amount, 0], [$cr, 0, $amount]] as [$a, $d, $c]) {
        uat_insert('fin_journal_lines', ['company_id' => $CO, 'entry_id' => $eid, 'account_id' => $a,
            'debit' => $d, 'credit' => $c, 'memo' => mb_substr($memo, 0, 255), 'created_at' => $date . ' 12:00:00']);
    }
    uat_log('fin_journal_entries', 'قيد');
    return $eid;
};

if (in_array('--reset', $argv, true)) {
    $db->query("DELETE sl FROM settlement_lines sl JOIN settlements s ON s.id=sl.settlement_id WHERE s.company_id=$CO AND s.settlement_no LIKE 'STL-U%'");
    $db->query("DELETE FROM settlements WHERE company_id=$CO AND settlement_no LIKE 'STL-U%'");
    $db->query("DELETE FROM fin_payments WHERE company_id=$CO AND payment_no LIKE 'PAY-U%'");
    $db->query("DELETE FROM payroll_lines WHERE company_id=$CO");
    $db->query("DELETE FROM payroll_runs WHERE company_id=$CO");
    $db->query("DELETE l FROM fin_journal_lines l JOIN fin_journal_entries e ON e.id=l.entry_id WHERE e.company_id=$CO AND (e.memo LIKE 'تسويةُ المورد%' OR e.memo LIKE 'سدادٌ للمورد%' OR e.memo LIKE 'مسيَّرُ أجور%')");
    $db->query("DELETE FROM fin_journal_entries WHERE company_id=$CO AND (memo LIKE 'تسويةُ المورد%' OR memo LIKE 'سدادٌ للمورد%' OR memo LIKE 'مسيَّرُ أجور%')");
    echo "   ↺ كُنِست التسوياتُ والمسيَّرات.\n";
}

// ── ① تسوياتُ الموردين · م03 (1,156 استحقاقًا + 857 دفعية) ───────────────────
$acc_rows = []; $pay_rows = [];
foreach (uat_json('م__م03_سجل_استحقاقات_ومدفوعات_المو') as $r) {
    $sup  = trim($r['اسم المورد'] ?? '');
    $date = uat_date($r['التاريخ'] ?? '');
    if ($sup === '' || !$date) continue;
    $kind = trim($r['نوع المعاملة'] ?? '');
    $mo   = substr($date, 0, 7);
    if ($kind === 'استحقاق') $acc_rows[$sup][$mo][] = $r + ['_d' => $date];
    elseif ($kind === 'دفعية') $pay_rows[$sup][] = $r + ['_d' => $date];
}

$sseq = 0; $pseq = 0;
if (uat_count('settlements', "company_id=$CO AND settlement_no LIKE 'STL-U%'") === 0) {
    $db->begin_transaction(); $k = 0;
    foreach ($acc_rows as $sup => $months) {
        $sid = $mapSup['byName'][$sup] ?? null;
        if (!$sid) continue;
        foreach ($months as $mo => $rows) {
            $gross = 0.0; $charges = 0.0; $d1 = null; $d2 = null;
            foreach ($rows as $r) {
                $gross   += uat_num($r['إجمالي الاستحقاق $'] ?? '', 0);
                $charges += uat_num($r['الخصومات'] ?? '', 0);
                $d1 = $d1 === null ? $r['_d'] : min($d1, $r['_d']);
                $d2 = $d2 === null ? $r['_d'] : max($d2, $r['_d']);
            }
            if ($gross <= 0) continue;
            $net = round($gross - $charges, 2);
            $sseq++;
            $no  = sprintf('STL-U%05d', $sseq);
            $stId = uat_insert('settlements', [
                'company_id' => $CO, 'settlement_no' => $no, 'party_type' => 'supplier',
                'party_ref' => $sid, 'party_name' => mb_substr($sup, 0, 190),
                'period_from' => $d1, 'period_to' => $d2, 'currency' => 'USD', 'fx_rate' => 1,
                'base_amount' => $net, 'gross_amount' => round($gross, 2), 'charges_amount' => round($charges, 2),
                'net_amount' => $net, 'net_direction' => $net >= 0 ? 'payable' : 'receivable',
                'state' => 'approved', 'open_objections' => 0,
                'prepared_by' => $actor, 'prepared_at' => $d2 . ' 10:00:00',
                'approved_by' => $actor, 'approved_at' => $d2 . ' 14:00:00',
                'notes' => mb_substr('من دفتر م03 · ' . count($rows) . ' حركةَ استحقاق', 0, 255),
                'is_deleted' => 0, 'created_by' => $actor,
                'created_at' => $d2 . ' 10:00:00', 'updated_at' => $d2 . ' 14:00:00',
            ]);
            // مرجعُ السطر فريدٌ بقيدٍ في القاعدة (uq_line_source) — والدفترُ الحقيقيُّ
            // يحمل أكثرَ من حركةٍ للمعدة نفسِها في اليوم نفسِه، فيُميَّز بترتيبه.
            $ln = 0;
            foreach ($rows as $r) {
                $ln++;
                $amt = uat_num($r['إجمالي الاستحقاق $'] ?? '', 0);
                if ($amt > 0) {
                    uat_insert('settlement_lines', [
                        'company_id' => $CO, 'settlement_id' => $stId, 'line_kind' => 'entitlement',
                        'source_kind' => 'supplier_ledger', 'source_ref' => mb_substr(($r['رمز الآلية'] ?? '') . '@' . $r['_d'] . '#' . $ln, 0, 190),
                        'description' => mb_substr(trim($r['البيان'] ?? '') ?: ('استحقاقُ ' . ($r['نوع الآلية'] ?? 'معدة')), 0, 255),
                        'work_date' => $r['_d'], 'amount' => $amt, 'currency' => 'USD', 'fx_rate' => 1,
                        'base_amount' => $amt, 'objected' => 0, 'created_at' => $r['_d'] . ' 10:00:00',
                    ]);
                }
                $ch = uat_num($r['الخصومات'] ?? '', 0);
                if ($ch > 0) {
                    uat_insert('settlement_lines', [
                        'company_id' => $CO, 'settlement_id' => $stId, 'line_kind' => 'charge',
                        'charge_type' => 'خصمُ ساعاتٍ أو جزاء',
                        'source_kind' => 'supplier_ledger', 'source_ref' => mb_substr(($r['رمز الآلية'] ?? '') . '@' . $r['_d'] . '#chg' . $ln, 0, 190),
                        'description' => 'خصمٌ على استحقاق المورد', 'work_date' => $r['_d'],
                        'amount' => $ch, 'currency' => 'USD', 'fx_rate' => 1, 'base_amount' => $ch,
                        'objected' => 0, 'created_at' => $r['_d'] . ' 10:00:00',
                    ]);
                }
            }
            uat_log('settlements', 'تسوية');
            $journal($d2, $net, $A('5101'), $A('2101'), 'تسويةُ المورد ' . $no . ' · ' . mb_substr($sup, 0, 60));
            if (++$k % 100 === 0) { $db->commit(); $db->begin_transaction(); }
        }
    }
    $db->commit();

    // ── الدفعيات: سدادٌ فعليٌّ للموردين ──────────────────────────────────────
    $db->begin_transaction(); $k = 0;
    foreach ($pay_rows as $sup => $rows) {
        $sid = $mapSup['byName'][$sup] ?? null;
        if (!$sid) continue;
        foreach ($rows as $r) {
            $amt = uat_num($r['المستلم $'] ?? '', 0);
            if ($amt <= 0) continue;
            $pseq++;
            uat_insert('fin_payments', [
                'company_id' => $CO, 'payment_no' => sprintf('PAY-U%05d', $pseq), 'direction' => 'disbursement',
                'party_type' => 'supplier', 'party_ref' => $sid, 'method' => 'transfer',
                'bank_ref' => sprintf('OUT-%s-%05d', substr($r['_d'], 0, 4), $pseq),
                'received_on' => $r['_d'], 'amount' => $amt, 'allocated_amount' => $amt,
                'currency' => 'USD', 'fx_rate' => 1, 'base_amount' => $amt,
                'memo' => mb_substr(trim($r['البيان'] ?? '') ?: 'سدادُ مستحقات المورد', 0, 255),
                'paid_at' => $r['_d'] . ' 11:00:00', 'state' => 'executed', 'executed_by' => $actor,
                'is_deleted' => 0, 'created_by' => $actor,
                'created_at' => $r['_d'] . ' 11:00:00', 'updated_at' => $r['_d'] . ' 11:00:00',
            ]);
            $journal($r['_d'], $amt, $A('2101'), $A('1102'), 'سدادٌ للمورد ' . mb_substr($sup, 0, 60));
            uat_log('fin_payments', 'سداد مورد');
            if (++$k % 150 === 0) { $db->commit(); $db->begin_transaction(); }
        }
    }
    $db->commit();
} else { echo "   ⚠ التسويات مبذورةٌ سلفًا — تُتخطى.\n"; }

// ── ② عقودُ المشغّلين ثم مسيَّراتُ الأجور · ش03 ──────────────────────────────
$wcOf = [];
foreach (uat_json('ش__ش01_مشغلو_إكوبيشن') as $r) {
    $no = trim($r['رقم المشغل'] ?? '');
    if (!isset($mapOper[$no])) continue;
    $emp = $mapOper[$no];
    $f = uat_date($r['أول عمل'] ?? '');
    $t = uat_date($r['آخر عمل'] ?? '');
    $id = uat_upsert('worker_contract',
        ['company_id' => $CO, 'employee_id' => $emp, 'code' => 'WC-' . $no],
        [
            'contract_type' => 'مشروع', 'wage' => 450, 'wage_method' => 'شهري',
            'date_start' => $f, 'date_end' => $t,
            'state' => ($t && $t < date('Y-m-d', strtotime('-6 months'))) ? 'منتهٍ' : 'نافذ',
            'rotation_pattern' => 'شهران+شهر', 'work_days' => 60, 'leave_days' => 30,
            'monthly_hours_base' => 260, 'fixed_wage_ratio' => 70.00,
            'billable_downtime' => 'استعداد العميل',
            'allow_housing' => 50, 'allow_food' => 60, 'allow_site' => 40, 'allow_transport' => 30,
            'created_by' => $actor,
        ]);
    $wcOf[$emp] = $id;
    uat_log('worker_contract', 'عقد مشغّل');
}

// السجلُّ الموحَّد لعقود العاملين — `contract_snapshots` مرتبطٌ به بمفتاحٍ أجنبي
// (fk_cs_contract → employee_contracts)، ومسارُ المسيَّر يمرّ منه لا من worker_contract.
$payModel = (int) ($db->query("SELECT id FROM pay_models ORDER BY id LIMIT 1")->fetch_assoc()['id'] ?? 1);
$ecOf = [];
foreach ($wcOf as $emp => $wc) {
    $w = uat_one("SELECT date_start, date_end FROM worker_contract WHERE id=?", [$wc]);
    $ecOf[$emp] = uat_upsert('employee_contracts',
        ['company_id' => $CO, 'employee_id' => $emp, 'source_table' => 'worker_contract', 'source_id' => $wc],
        [
            'category' => 'project', 'relation_type' => 'worker_contract:مشروع',
            'start_date' => $w['date_start'] ?? null, 'end_date' => $w['date_end'] ?? null,
            'pay_model_id' => $payModel, 'currency' => 'USD', 'state' => 'active', 'version' => 1,
            'created_by' => $actor, 'is_deleted' => 0,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    uat_log('employee_contracts', 'عقد موحَّد');
}

// لقطةُ شروط العقد — مرجعُ سطر المسيَّر (payroll_lines.snapshot_id)
$snapOf = [];
foreach ($ecOf as $emp => $ec) {
    $row = uat_one("SELECT id FROM contract_snapshots WHERE company_id=? AND contract_id=? AND as_of_date='2020-01-01' LIMIT 1", [$CO, $ec]);
    if ($row) { $snapOf[$emp] = (int) $row['id']; continue; }
    $json = json_encode(['employee_contract' => $ec, 'employee_id' => $emp, 'wage' => 450, 'method' => 'شهري'], JSON_UNESCAPED_UNICODE);
    $snapOf[$emp] = uat_insert('contract_snapshots', [
        'company_id' => $CO, 'contract_id' => $ec, 'as_of_date' => '2020-01-01',
        'snapshot_json' => $json, 'fingerprint' => sha1($json), 'valid' => 1,
        'created_by' => $actor, 'created_at' => date('Y-m-d H:i:s'),
    ]);
    uat_log('contract_snapshots', 'لقطة');
}

if (uat_count('payroll_runs', "company_id=$CO") === 0) {
    $byMonth = [];
    foreach (uat_json('ش__ش03_الأداء_الشهري_لمشغلي_إكوبيش') as $r) {
        $mo = trim($r['الشهر'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}$/', $mo)) continue;
        $byMonth[$mo][] = $r;
    }
    ksort($byMonth);
    $db->begin_transaction(); $k = 0;
    foreach ($byMonth as $mo => $rows) {
        $from = $mo . '-01';
        $to   = date('Y-m-t', strtotime($from));
        $lines = 0; $gross = 0.0; $persons = [];
        $runId = uat_insert('payroll_runs', [
            'company_id' => $CO, 'period_from' => $from, 'period_to' => $to,
            'category_filter' => 'سائق/مشغّل', 'state' => 'Approved',
            'persons_count' => 0, 'lines_count' => 0, 'blocked_count' => 0,
            'gross_total' => 0, 'currency' => 'USD', 'version' => 1,
            'note' => 'مسيَّرٌ مولَّدٌ من الأداء الشهري ش03', 'is_deleted' => 0,
            'created_by' => $actor, 'created_at' => $to . ' 18:00:00', 'updated_at' => $to . ' 18:00:00',
        ]);
        foreach ($rows as $r) {
            $no  = trim($r['رقم السائق'] ?? '');
            $emp = $mapOper[$no] ?? null;
            if (!$emp || !isset($ecOf[$emp])) continue;
            $amt = uat_num($r['الاستحقاق'] ?? '', 0);
            $inc = uat_num($r['الإضافة'] ?? '', 0);
            // «الاستحقاق» في ش03 مسجَّلٌ في 50 صفًّا فقط من 1,391 — والباقي صفرٌ في
            // المصدر. فيُحتسب من شروط العقد: أجرٌ شهريٌّ بنسبة أيام العمل، وحافزٌ
            // على الساعات الإضافية — وهو اشتقاقٌ معلَنٌ لا رقمٌ مخترع.
            $days = uat_int($r['أيام العمل'] ?? '', 0);
            if ($amt <= 0 && $days > 0) $amt = round(450 * min(1, $days / 30), 2);
            if ($inc <= 0) $inc = round(uat_num($r['إضافي ساعات'] ?? '', 0) * 1.5, 2);
            if ($amt <= 0 && $inc <= 0) continue;
            $persons[$emp] = 1;
            foreach ([['component', 'أجرٌ أساسيٌّ شهري', $amt], ['incentive', 'حافزُ إنتاج', $inc]] as [$kind, $desc, $v]) {
                if ($v <= 0) continue;
                uat_insert('payroll_lines', [
                    'company_id' => $CO, 'run_id' => $runId, 'person_id' => $emp,
                    'contract_id' => $ecOf[$emp], 'snapshot_id' => $snapOf[$emp],
                    'path' => 'project', 'component_ref' => mb_substr($desc, 0, 60), 'line_kind' => $kind,
                    'component_type' => $desc, 'calc_method' => 'من الأداء الشهري',
                    'qty' => uat_num($r['إجمالي ساعات المشغل'] ?? '', 0),
                    'entitled_days' => uat_int($r['أيام العمل'] ?? '', 0), 'period_days' => 30,
                    'rate' => 0, 'amount' => $v, 'calc_state' => 'computed',
                    'note' => mb_substr('ش03 · ' . $mo, 0, 255), 'created_at' => $to . ' 18:00:00',
                ]);
                $lines++; $gross += $v;
            }
        }
        $db->query("UPDATE payroll_runs SET persons_count=" . count($persons) . ", lines_count=$lines, gross_total=" . round($gross, 2) . " WHERE id=$runId");
        if ($gross > 0) $journal($to, round($gross, 2), $A('5102'), $A('2201'), 'مسيَّرُ أجور ' . $mo);
        uat_log('payroll_runs', 'مسيَّر');
        if (++$k % 12 === 0) { $db->commit(); $db->begin_transaction(); }
    }
    $db->commit();
} else { echo "   ⚠ المسيَّرات مبذورةٌ سلفًا — تُتخطى.\n"; }

uat_print_report('البذرة ⑧ · التسويات والمسيَّرات');
printf("   تسوياتُ الموردين: %d · أسطرُها: %d · سداداتٌ: %d · عقودُ المشغلين: %d · مسيَّرات: %d · أسطرُ الأجور: %d\n",
    uat_count('settlements', "company_id=$CO"), uat_count('settlement_lines', "company_id=$CO"),
    uat_count('fin_payments', "company_id=$CO AND direction='disbursement'"),
    uat_count('worker_contract', "company_id=$CO"), uat_count('payroll_runs', "company_id=$CO"),
    uat_count('payroll_lines', "company_id=$CO"));
$bal = $db->query("SELECT ROUND(SUM(debit)-SUM(credit),2) d FROM fin_journal_lines WHERE company_id=$CO")->fetch_assoc()['d'];
printf("   القيود: %d · الأسطر: %d · فرقُ الميزان: %s %s\n",
    uat_count('fin_journal_entries', "company_id=$CO"), uat_count('fin_journal_lines', "company_id=$CO"),
    $bal, ((float) $bal === 0.0) ? '✔' : '✘');
