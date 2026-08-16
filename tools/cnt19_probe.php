<?php
/**
 * مسبارُ الدراسةِ العكسية — حزمةُ الحاويات/القيد اليومي/المبيعات/الموردين
 * الوثائق: SUP-CNT-01 · SAL-CNT-01 · TS-01 · M-08 · M-09
 * التشغيل: php tools/cnt19_probe.php
 * يقيس: الجداولَ والأعمدةَ (بالخريطة الاسمية الحية) · الأفعالَ في nav09_action_map ·
 *        القوادحَ والمناظرَ · الشاشاتِ بالملف · مسارات nav_items.
 * التقرير المرافق: docs/CNT19_TRACKER_ar.md
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

/** @var mysqli $conn */
echo "DB=" . $conn->query("SELECT DATABASE()")->fetch_row()[0] . "\n\n";

function tbl(mysqli $c, string $t): bool {
    $r = $c->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $c->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
}
function colset(mysqli $c, string $t): array {
    $out = [];
    $r = $c->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $c->real_escape_string($t) . "'");
    while ($r && $row = $r->fetch_row()) $out[$row[0]] = 1;
    return $out;
}

// ── ① الجداول: اسمُ الوثيقة => النظيرُ الحي ──
$tableMap = [
    'shift_entries'        => ['unit_entries', 'unit_time_log'],
    'sup_handover_events'  => ['container_swaps'],
    'الهرم (annual/type/slots)' => ['op_containers'],
    'coverage'             => ['contract_commitments'],
    'quota_ledger'         => ['container_consumption'],
    'supplier_settle'      => ['settlements', 'settlement_lines'],
    'contract_amendments'  => ['contract_amendments'],
];
echo "== TABLES (doc => live) ==\n";
foreach ($tableMap as $doc => $lives) {
    $st = [];
    foreach ($lives as $lv) $st[] = "$lv:" . (tbl($conn, $lv) ? 'EXISTS' : 'MISSING');
    echo "$doc => " . implode(' · ', $st) . "\n";
}

// ── ② أعمدةُ TS-01 على النظائرِ الحية ──
$wanted = [
    'unit_entries'         => ['container_key','client_id','meter_before','meter_after','fuel_received_qty','fuel_issued_qty','seed_tag','entity_layer','shift_slot_key','created_by_role'],
    'contract_amendments'  => ['container_key','capacity_units','work_model','unit_of_measure','actual_start','actual_end','capacity_source'],
    'contract_commitments' => ['container_key','slot_monthly_basis','renewal_months','type_capacity'],
    'container_consumption'=> ['layer','share_key','gap_units'],
    'settlements'          => ['adj_work_added','adj_breakdown_added','adj_standby_added','adj_deducted','supplier_executed_hours','borne_by_treasury','adj_doc_ref'],
];
echo "\n== COLUMNS ==\n";
foreach ($wanted as $t => $list) {
    if (!tbl($conn, $t)) { echo "$t: TABLE MISSING\n"; continue; }
    $have = colset($conn, $t);
    $miss = array_values(array_diff($list, array_keys($have)));
    echo "$t: " . (count($list) - count($miss)) . "/" . count($list) . " موجودة" . ($miss ? " · ناقص: " . implode(',', $miss) : '') . "\n";
}

// ── ③ الأفعال (nav09_action_map: canonical_code/live_code · state) ──
$actGroups = [
    'TS-01 (8)'  => ['shift.entry.record','shift.entry.void','cnt.annual.open','cnt.types.define','cnt.slots.open','cnt.slot.allocate','sup.handover.record','sup.settle.apply'],
    'M-08 (32)'  => ['contract.activate','unbilled.claim','client.create','opp.qualify','quote.send','quote.accept','cov.define','unit.define','terms.set','price.trigger','claim.issue','claim.client.approve','penalty.compute','risk.log','project.create','amend.activate','evt.log','svc.define','price.approve','uom.define','sales.model.define','review.block','risk.sal.view','risk.sal.raise','risk.sal.evidence','gov.sal.view','gov.sal.attest','unit.st.03','unit.st.04','unit.st.08','unit.st.09','unit.stmt.client'],
    'M-09 (28)'  => ['supplier.activate','supplier.evaluate','quota.allocate','quota.consume','settle.approve','sc.activate','se.register','stmt.issue','bank.verify','perf.penalty','supp.eval','supp.close','cap.measure','rule.define','sadv.grant','quota.post','plan.commit','eq.quota.allocate','eq.quota.shift','risk.sup.view','risk.sup.raise','risk.sup.evidence','gov.sup.view','gov.sup.attest','ap.shares.allocate','ap.oblig.generate','unit.st.05','unit.stmt.supplier'],
];
echo "\n== ACTIONS ==\n";
foreach ($actGroups as $g => $acts) {
    $in = "'" . implode("','", array_map([$conn, 'real_escape_string'], $acts)) . "'";
    $found = [];
    $r = $conn->query("SELECT canonical_code, state FROM nav09_action_map WHERE canonical_code IN ($in) OR live_code IN ($in)");
    while ($r && $row = $r->fetch_assoc()) $found[$row['canonical_code']] = $row['state'];
    $live = $unbuilt = $absent = 0;
    $absentList = [];
    foreach ($acts as $a) {
        $s = $found[$a] ?? null;
        if ($s === 'bound_page' || $s === 'alias') $live++;
        elseif ($s !== null) $unbuilt++;
        else { $absent++; $absentList[] = $a; }
    }
    echo "$g: حي=$live · معلَن غير مبني=$unbuilt · غائب=$absent" . ($absentList ? "\n   غائب: " . implode(' · ', $absentList) : '') . "\n";
}

// ── ④ القوادحُ والمناظر (الصيغ F-01..F-12) ──
echo "\n== TRIGGERS on containers/entries ==\n";
$r = $conn->query("SELECT TRIGGER_NAME, EVENT_OBJECT_TABLE FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE IN ('op_containers','unit_entries','unit_time_log','contract_commitments','container_consumption','settlements','contract_amendments','container_swaps')");
$n = 0;
while ($r && $row = $r->fetch_assoc()) { echo "TRG {$row['TRIGGER_NAME']} on {$row['EVENT_OBJECT_TABLE']}\n"; $n++; }
echo $n ? '' : "(لا قوادحَ — قوادحُ الاشتقاقِ F-01..F-08 غيرُ مبنية)\n";
echo "\n== VIEWS (F-09/F-10 متوقَّعة كمنظرين) ==\n";
$r = $conn->query("SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE() AND (TABLE_NAME LIKE '%median%' OR TABLE_NAME LIKE '%work_day%' OR TABLE_NAME LIKE '%shift%' OR TABLE_NAME LIKE '%supplier_perf%' OR TABLE_NAME LIKE '%performance%')");
$n = 0;
while ($r && $row = $r->fetch_row()) { echo "VIEW {$row[0]}\n"; $n++; }
echo $n ? '' : "(لا منظرَ للوسيطِ أو أيامِ العمل)\n";
// v_monthly_performance تحسب أيامَ العمل COUNT(DISTINCT entry_date) = F-10 ✓ · الوسيطُ F-09 ما يزال غائبًا

// ── ترتيبُ التصحيح ①: نسبةُ ترحيلِ الوقائعِ للدفتر (SUP-0036: الهدف >99٪) ──
echo "\n== LEDGER POSTING (fin_financial_events.fes_status) ==\n";
$r = $conn->query("SELECT fes_status, COUNT(*) FROM fin_financial_events GROUP BY 1 ORDER BY 2 DESC");
$tot = 0; $posted = 0; $lines = [];
while ($r && $row = $r->fetch_row()) { $lines[] = "{$row[0]}={$row[1]}"; $tot += $row[1]; if ($row[0] === 'Posted') $posted = $row[1]; }
echo implode(' · ', $lines) . "\n";
echo "Posted rate: $posted/$tot (" . ($tot ? round(100 * $posted / $tot, 1) : 0) . "%)\n";

// ── ⑤ الشاشاتُ بالملفِّ الحي ──
$screens = [
    'M-08' => ['Operations/unbilled.php','Clients/clients.php','Projects/projects.php','Opportunities/opportunities.php','Clients/quotations.php','Contracts/contracts.php','Contracts/contract_coverage.php','Contracts/price_terms.php','Contracts/claims.php','Contracts/penalties.php','Clients/commercial_risks.php','Clients/contract_amendments.php','Clients/contract_events.php','Clients/products.php','Clients/pricelists.php','Clients/units_of_measure.php','Portal/business_models.php','Portal/contract_review.php','Risk/risk_dept_sal.php','Governance/gov_dept_sal.php'],
    'M-09' => ['Suppliers/suppliers.php','Suppliers/supplierscontracts.php','Suppliers/settlements.php','Suppliers/supplier_bank.php','Suppliers/supplier_capacity.php','Suppliers/supplier_rules.php','Suppliers/supplier_evaluation.php','Suppliers/supplier_advances.php','Suppliers/supplier_closure.php','Suppliers/equipment_plan.php','Operations/equipment_quota.php','Suppliers/shares_coverage.php','Risk/risk_dept_sup.php','Governance/gov_dept_sup.php'],
    'CNT/SHIFT' => ['Operations/shift_entry.php','Operations/shift_log.php','Operations/containers.php'],
];
echo "\n== SCREENS ==\n";
$root = dirname(__DIR__);
foreach ($screens as $g => $files) {
    $ok = 0; $missing = [];
    foreach ($files as $f) { if (is_file("$root/$f")) $ok++; else $missing[] = $f; }
    echo "$g: $ok/" . count($files) . " قائمة" . ($missing ? " · غائب: " . implode(' · ', $missing) : '') . "\n";
}
echo "\nDONE — حدِّث docs/CNT19_TRACKER_ar.md بالنتائج.\n";
