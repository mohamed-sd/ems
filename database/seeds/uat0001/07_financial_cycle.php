<?php
/**
 * UAT-0001 · بذرة ⑦ — الدورةُ المالية: مستخلصٌ ← فاتورةٌ ← ذمةٌ ← تحصيل،
 * وتسوياتُ الموردين ومسيَّراتُ الأجور، وكلُّ ذلك بقيدٍ متوازنٍ في الدفتر.
 *
 * قاعدتان:
 *   · **المستخلصُ يُولَّد من الوحدات المعتمدة** — لا كميةَ تُدخل يدويًّا.
 *   · **الفاتورةُ لا تنشأ إلا من مستخلصٍ معتمد**، وبرقمٍ تسلسليٍّ بلا فجوة.
 *
 * الأسعار: مأخوذةٌ من أسعار الموردين الحقيقية في م03 (سعر الساعة/الطن/المتر)
 * ثم تُضرب في هامشٍ **معلَنٍ** لتكون سعرَ العميل — لأن ب09 (أحكام العقد) قالبٌ
 * فارغٌ في المصدر، فالاشتقاقُ أصدقُ من الاختراع.
 */
require __DIR__ . '/_lib.php';
set_time_limit(0);

$db    = uat_db();
$actor = uat_actor();
$CO    = UAT_COMPANY;
$MARGIN = 1.35;          // هامشُ سعر العميل فوق تكلفة المورد — معلَنٌ في ملاحظة المستخلص

$acc = [];
foreach ($db->query("SELECT code,id FROM fin_chart_of_accounts WHERE company_id=$CO") as $a) $acc[$a['code']] = (int) $a['id'];
$A = fn($c) => $acc[$c] ?? null;

$REV = ['hour' => '4101', 'ton' => '4102', 'meter' => '4103', 'trip' => '4200'];

// ── قيدٌ متوازنٌ بسطرين ──────────────────────────────────────────────────────
$jseq = (int) preg_replace('/\D/', '', (string) ($db->query("SELECT COALESCE(MAX(entry_no),'JV-000000') m FROM fin_journal_entries WHERE company_id=$CO")->fetch_assoc()['m']));
$journal = function ($date, $currency, $amount, $debitAcc, $creditAcc, $memo, $projectId = null) use ($db, $CO, $actor, &$jseq) {
    if ($amount <= 0 || !$debitAcc || !$creditAcc) return null;
    $jseq++;
    $no = sprintf('JV-%06d', $jseq);
    $eid = uat_insert('fin_journal_entries', [
        'company_id' => $CO, 'entry_no' => $no, 'posting_date' => $date, 'txn_date' => $date,
        'currency' => $currency, 'fx_rate' => 1, 'base_amount' => $amount,
        'total_debit' => $amount, 'total_credit' => $amount,
        'memo' => mb_substr($memo, 0, 255), 'state' => 'posted',
        'posted_by' => $actor, 'posted_at' => $date . ' 12:00:00',
        'is_deleted' => 0, 'created_by' => $actor,
        'created_at' => $date . ' 12:00:00', 'updated_at' => $date . ' 12:00:00',
    ]);
    foreach ([[$debitAcc, $amount, 0], [$creditAcc, 0, $amount]] as [$a, $d, $c]) {
        uat_insert('fin_journal_lines', [
            'company_id' => $CO, 'entry_id' => $eid, 'account_id' => $a,
            'debit' => $d, 'credit' => $c, 'project_id' => $projectId,
            'memo' => mb_substr($memo, 0, 255), 'created_at' => $date . ' 12:00:00',
        ]);
    }
    uat_log('fin_journal_entries', 'قيد');
    return $eid;
};

// ── ① أسعارُ الوحدة من م03 ───────────────────────────────────────────────────
$px = ['hour' => [], 'ton' => [], 'meter' => []];
foreach (uat_json('م__م03_سجل_استحقاقات_ومدفوعات_المو') as $r) {
    if (trim($r['نوع المعاملة'] ?? '') !== 'استحقاق') continue;
    foreach ([['hour', 'سعر الساعة $'], ['ton', 'سعر الطن ويست'], ['ton', 'سعر الطن خام'], ['meter', 'سعر متر التفجير']] as [$k, $col]) {
        $v = uat_num($r[$col] ?? '', 0);
        if ($v > 0) $px[$k][] = $v;
    }
}
$avg = fn($a, $d) => $a ? round(array_sum($a) / count($a), 2) : $d;
$COST = ['hour' => $avg($px['hour'], 10), 'ton' => $avg($px['ton'], 3.5), 'meter' => $avg($px['meter'], 12), 'trip' => 45];
$PRICE = array_map(fn($v) => round($v * $MARGIN, 2), $COST);
printf("   أسعارُ التكلفة من م03: ساعة %s · طن %s · متر %s → سعرُ العميل (×%s): ساعة %s · طن %s · متر %s\n",
    $COST['hour'], $COST['ton'], $COST['meter'], $MARGIN, $PRICE['hour'], $PRICE['ton'], $PRICE['meter']);

// ── ٠ إعادةُ التوليد بالوسم عند التوقف في المنتصف ────────────────────────────
if (in_array('--reset', $argv, true)) {
    $db->query("DELETE a FROM fin_collection_allocations a JOIN fin_payments p ON p.id=a.payment_id WHERE p.company_id=$CO AND p.payment_no LIKE 'RCV-%'");
    $db->query("DELETE FROM fin_payments WHERE company_id=$CO AND payment_no LIKE 'RCV-%'");
    $db->query("DELETE l FROM fin_journal_lines l JOIN fin_journal_entries e ON e.id=l.entry_id WHERE e.company_id=$CO AND (e.memo LIKE 'إيرادُ المستخلص%' OR e.memo LIKE 'تحصيلٌ على الفاتورة%')");
    $db->query("DELETE FROM fin_journal_entries WHERE company_id=$CO AND (memo LIKE 'إيرادُ المستخلص%' OR memo LIKE 'تحصيلٌ على الفاتورة%')");
    $db->query("DELETE FROM fin_receivables WHERE company_id=$CO AND doc_ref LIKE 'INV-%'");
    $db->query("DELETE i FROM tax_invoices i JOIN claims c ON c.id=i.claim_id WHERE c.company_id=$CO AND c.notes LIKE 'مولَّدٌ من الوحدات%'");
    $db->query("DELETE cl FROM claim_lines cl JOIN claims c ON c.id=cl.claim_id WHERE c.company_id=$CO AND c.notes LIKE 'مولَّدٌ من الوحدات%'");
    $db->query("DELETE FROM claims WHERE company_id=$CO AND notes LIKE 'مولَّدٌ من الوحدات%'");
    echo "   ↺ كُنِست الدورةُ المالية المولَّدة سابقًا.\n";
}

// ── ② المستخلصاتُ من الوحدات المعتمدة ────────────────────────────────────────
$cseq = (int) preg_replace('/\D/', '', (string) ($db->query("SELECT COALESCE(MAX(claim_no),'CLM-000000') m FROM claims WHERE company_id=$CO")->fetch_assoc()['m']));
$done = uat_count('claims', "company_id=$CO AND notes LIKE 'مولَّدٌ من الوحدات%'");
if ($done > 0) {
    echo "   ⚠ المستخلصات مبذورةٌ سلفًا ($done) — تُتخطى.\n";
} else {
    $rows = $db->query("SELECT ue.contract_id, DATE_FORMAT(ue.entry_date,'%Y-%m') mo, ue.unit_type,
                               SUM(ue.qty) qty, MIN(ue.entry_date) d1, MAX(ue.entry_date) d2,
                               c.project_id, p.client_id, MAX(ue.state) st
                        FROM unit_entries ue
                        JOIN contracts c ON c.id = ue.contract_id
                        JOIN project  p ON p.id = c.project_id
                        WHERE ue.company_id=$CO AND ue.source_ref='UAT-B07'
                          AND ue.state IN ('converted','sales_approved')
                        GROUP BY ue.contract_id, mo, ue.unit_type, c.project_id, p.client_id
                        ORDER BY mo");
    $db->begin_transaction(); $k = 0;
    foreach ($rows as $g) {
        $unit  = $g['unit_type'];
        $qty   = round((float) $g['qty'], 2);
        if ($qty <= 0) continue;
        $price = $PRICE[$unit] ?? $PRICE['hour'];
        $gross = round($qty * $price, 2);
        $ret   = round($gross * 0.05, 2);              // المحتجز 5٪ كما في رأس العقد
        $tax   = 0.0;                                   // معالجةُ الضريبة «لا تُطبق» في القوائم المرجعية
        $net   = round($gross - $ret + $tax, 2);
        $cseq++;
        $cno = sprintf('CLM-%06d', $cseq);
        $st  = $g['st'] === 'converted' ? 'approved' : 'submitted';

        $cid = uat_insert('claims', [
            'company_id' => $CO, 'claim_no' => $cno, 'contract_id' => $g['contract_id'],
            'client_id' => $g['client_id'], 'project_id' => $g['project_id'],
            'period_from' => $g['d1'], 'period_to' => $g['d2'], 'currency' => 'USD',
            'gross_amount' => $gross, 'retention_amount' => $ret,
            'retention_note' => 'محتجزٌ 5٪ من قيمة المستخلص — يُرد بعد الاستلام النهائي',
            'net_amount' => $net, 'tax_amount' => $tax,
            'state' => $st, 'version' => 1,
            'submitted_by' => $actor, 'submitted_at' => $g['d2'] . ' 12:00:00',
            'approved_by' => $st === 'approved' ? $actor : null,
            'approved_at' => $st === 'approved' ? $g['d2'] . ' 15:00:00' : null,
            'notes' => mb_substr("مولَّدٌ من الوحدات المعتمدة · سعرُ الوحدة $price مشتقٌّ من متوسط تكلفة المورد ×$MARGIN", 0, 255),
            'is_deleted' => 0, 'created_by' => $actor,
            'created_at' => $g['d2'] . ' 12:00:00', 'updated_at' => $g['d2'] . ' 12:00:00',
        ]);
        uat_insert('claim_lines', [
            'company_id' => $CO, 'claim_id' => $cid, 'source_kind' => 'unit_entries',
            'source_ref' => (int) $g['contract_id'], 'work_date' => $g['d2'],
            'unit_type' => $unit, 'qty' => $qty, 'unit_price' => $price, 'amount' => $gross,
            'dispute_flag' => 0, 'dispute_state' => 'none', 'created_at' => $g['d2'] . ' 12:00:00',
        ]);
        uat_log('claims', 'مستخلص');
        if (++$k % 200 === 0) { $db->commit(); $db->begin_transaction(); }
    }
    $db->commit();
}

// ── ③ الفواتيرُ والذممُ والتحصيل — من المستخلصات المعتمدة حصرًا ──────────────
if (uat_count('tax_invoices', "company_id=$CO") === 0) {
    $serialByYear = [];
    $pseq = (int) preg_replace('/\D/', '', (string) ($db->query("SELECT COALESCE(MAX(payment_no),'RCV-000000') m FROM fin_payments WHERE company_id=$CO")->fetch_assoc()['m']));
    $db->begin_transaction(); $k = 0;
    $res = $db->query("SELECT * FROM claims WHERE company_id=$CO AND state='approved' AND is_deleted=0 ORDER BY period_to");
    foreach ($res as $c) {
        $yr = (int) date('Y', strtotime($c['period_to']));
        $serialByYear[$yr] = ($serialByYear[$yr] ?? 0) + 1;
        $seq = $serialByYear[$yr];
        $inv = uat_insert('tax_invoices', [
            'company_id' => $CO, 'claim_id' => $c['id'], 'client_id' => $c['client_id'],
            'serial_no' => sprintf('INV-%d-%05d', $yr, $seq), 'serial_year' => $yr, 'serial_seq' => $seq,
            'currency' => 'USD', 'net_amount' => $c['net_amount'], 'tax_rate' => 0, 'tax_amount' => 0,
            'total_amount' => $c['net_amount'], 'state' => 'issued',
            'issued_at' => $c['period_to'] . ' 16:00:00', 'issued_by' => $actor,
            'created_at' => $c['period_to'] . ' 16:00:00', 'updated_at' => $c['period_to'] . ' 16:00:00',
        ]);
        $db->query("UPDATE claims SET invoice_no='" . sprintf('INV-%d-%05d', $yr, $seq) . "', invoice_date='{$c['period_to']}', state='invoiced' WHERE id={$c['id']}");
        uat_log('tax_invoices', 'فاتورة');

        $due = date('Y-m-d', strtotime($c['period_to'] . ' +30 day'));
        $rid = uat_insert('fin_receivables', [
            'company_id' => $CO, 'customer_entity_id' => $c['client_id'], 'doc_type' => 'invoice',
            'doc_ref' => sprintf('INV-%d-%05d', $yr, $seq), 'project_id' => $c['project_id'],
            'amount' => $c['net_amount'], 'currency' => 'USD', 'fx_rate_recognized' => 1,
            'base_amount' => $c['net_amount'], 'collected' => 0, 'due_date' => $due,
            'state' => 'open', 'is_deleted' => 0, 'created_by' => $actor,
            'created_at' => $c['period_to'] . ' 16:00:00', 'updated_at' => $c['period_to'] . ' 16:00:00',
        ]);
        uat_log('fin_receivables', 'ذمة');

        // القيدُ ①: إيرادٌ معترفٌ به مقابل ذمةٍ مدينة
        $revAcc = $A($REV[$db->query("SELECT unit_type FROM claim_lines WHERE claim_id={$c['id']} LIMIT 1")->fetch_assoc()['unit_type'] ?? 'hour'] ?? '4101');
        $journal($c['period_to'], 'USD', (float) $c['net_amount'], $A('1201'), $revAcc,
            'إيرادُ المستخلص ' . $c['claim_no'], $c['project_id']);

        // التحصيل: ثلاثةُ أرباعِ الفواتير المستحقة تُحصَّل كاملةً أو جزئيًّا
        if ($due < date('Y-m-d') && ($c['id'] % 4) !== 0) {
            $ratio = (($c['id'] % 3) === 0) ? 0.6 : 1.0;            // تحصيلٌ جزئيٌّ في الثلث
            $amt   = round($c['net_amount'] * $ratio, 2);
            if ($amt > 0) {
                $pseq++;
                $pdate = date('Y-m-d', strtotime($due . ' +' . (5 + $c['id'] % 20) . ' day'));
                if ($pdate > date('Y-m-d')) $pdate = date('Y-m-d');
                $pid = uat_insert('fin_payments', [
                    'company_id' => $CO, 'payment_no' => sprintf('RCV-%06d', $pseq), 'direction' => 'collection',
                    'party_type' => 'customer', 'party_ref' => $c['client_id'], 'method' => 'bank',
                    // ck_collection_bank_ref: تحصيلٌ بنكيٌّ بلا مرجعِ حوالةٍ مرفوض
                    'bank_ref' => sprintf('TRF-%d-%06d', $yr, $pseq),
                    'received_on' => $pdate, 'amount' => $amt, 'allocated_amount' => $amt,
                    'currency' => 'USD', 'fx_rate' => 1, 'base_amount' => $amt,
                    'receivable_id' => $rid, 'memo' => 'تحصيلُ الفاتورة ' . sprintf('INV-%d-%05d', $yr, $seq),
                    'paid_at' => $pdate . ' 11:00:00', 'state' => 'executed', 'executed_by' => $actor,
                    'is_deleted' => 0, 'created_by' => $actor,
                    'created_at' => $pdate . ' 11:00:00', 'updated_at' => $pdate . ' 11:00:00',
                ]);
                uat_insert('fin_collection_allocations', [
                    'company_id' => $CO, 'payment_id' => $pid, 'receivable_id' => $rid,
                    // ck_alloc_target: التخصيصُ على فاتورةٍ يجب أن يشير إلى الذمة نفسِها
                    'target_kind' => 'invoice', 'target_ref' => $rid, 'amount' => $amt,
                    'pay_currency' => 'USD', 'target_currency' => 'USD', 'amount_target' => $amt,
                    'fx_rate_pay' => 1, 'fx_rate_target' => 1, 'base_amount' => $amt, 'fx_diff_base' => 0,
                    'basis' => 'explicit', 'note' => 'تخصيصٌ بمرجعٍ صريحٍ من العميل',
                    'created_by' => $actor, 'created_at' => $pdate . ' 11:00:00',
                ]);
                $db->query("UPDATE fin_receivables SET collected=$amt, state='" . ($ratio >= 1 ? 'collected' : 'partial') . "' WHERE id=$rid");
                $journal($pdate, 'USD', $amt, $A('1102'), $A('1201'), 'تحصيلٌ على الفاتورة ' . sprintf('INV-%d-%05d', $yr, $seq), $c['project_id']);
                uat_log('fin_payments', 'تحصيل');
            }
        }
        if (++$k % 150 === 0) { $db->commit(); $db->begin_transaction(); }
    }
    $db->commit();
} else { echo "   ⚠ الفواتير مبذورةٌ سلفًا — تُتخطى.\n"; }

uat_print_report('البذرة ⑦ · الدورة المالية (العميل)');
printf("   المستخلصات: %d · الفواتير: %d · الذمم: %d · التحصيلات: %d · القيود: %d · الأسطر: %d\n",
    uat_count('claims', "company_id=$CO"), uat_count('tax_invoices', "company_id=$CO"),
    uat_count('fin_receivables', "company_id=$CO"), uat_count("fin_payments", "company_id=$CO AND direction='collection'"),
    uat_count('fin_journal_entries', "company_id=$CO"), uat_count('fin_journal_lines', "company_id=$CO"));
$bal = $db->query("SELECT ROUND(SUM(debit)-SUM(credit),2) d FROM fin_journal_lines WHERE company_id=$CO")->fetch_assoc()['d'];
printf("   فرقُ الميزان (مدين − دائن): %s %s\n", $bal, ((float) $bal === 0.0) ? '✔' : '✘');
