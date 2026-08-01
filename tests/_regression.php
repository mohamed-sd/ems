<?php
/**
 * tests/_regression.php — مشغّلُ حزمة الانحدار (عدّةٌ لا اختبار).
 * التشغيل: php tests/_regression.php [suite]   —  suite: core (افتراضي) · all
 * يطبع سطرًا لكل حزمة برمز خروجها وعدّادَي النجاح/الفشل المستخرَجَين من مخرجها.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$php = 'C:/wamp64/bin/php/php8.2.30/php.exe';
$dir = __DIR__;
$suite = isset($argv[1]) ? $argv[1] : 'core';

$core = array(
    'tenant_leak_test', 'audit_trail_test', 'event_publisher_test', 'fes_event_contract_test',
    'period_lock_test', 'journal_head_columns_test', 'base_equivalent_test', 'fx_currency_test',
    'idempotency_resend_test', 'contract_state_machine_test', 'contract_snapshot_test',
    'employee_contract_registry_test', 'employee_contract_amendment_test', 'pay_components_test',
    'incentive_rules_test', 'cost_bearers_test', 'payroll_snapshot_gate_test',
    'payroll_time_path_test', 'payroll_production_path_test', 'payroll_offset_test',
    'payroll_run_cycle_test', 'final_settlement_test', 'employee_settlement_test',
    'pay_policy_state_test', 'operator_due_policy_test', 'timesheet_fanout_test',
    'attribution_test', 'effect_fanout_test',
    'transfer_tariff_test', 'depreciation_event_test', 'periodic_events_test', 'bank_reconciliation_test', 'rfq_cycle_test', 'contract_sites_test', 'contract_lines_test', 'contract_monthly_plan_test', 'contract_resource_plan_test', 'contract_payment_schedule_test', 'contract_guarantees_test', 'allocation_targets_test', 'three_currencies_test', 'daily_plan_test', 'dues_source_doc_test', 'settlement_test', 'settlement_invoice_close_test',
    'supplier_advances_test', 'supplier_statement_test', 'supplier_rules_test',
    'supplier_capacity_test', 'supplier_evaluation_test', 'supplier_closure_test',
    'supplier_documents_test', 'tax_invoice_test', 'client_statement_test',
    'collection_control_test', 'claim_dispute_test', 'claims_test', 'unified_nav_test',
);

$files = $core;
if ($suite === 'all') {
    $files = array();
    foreach (glob($dir . '/*_test.php') as $f) { $files[] = basename($f, '.php'); }
    sort($files);
}

$green = 0; $red = 0; $lines = array();
foreach ($files as $t) {
    $path = $dir . '/' . $t . '.php';
    if (!is_file($path)) { $lines[] = sprintf('  %-42s  MISSING', $t); $red++; continue; }
    $out = array(); $code = 0;
    exec('"' . $php . '" "' . $path . '" 2>&1', $out, $code);
    $txt = implode("\n", $out);
    $p = 0; $f = 0;
    // صيغتان مستعملتان في الحزم: «N نجاح · M فشل» و«N ناجح · M فاشل»
    if (preg_match('~النتيجة:\s*(\d+)\s*(?:نجاح|ناجح)\s*·\s*(\d+)\s*(?:فشل|فاشل)~u', $txt, $m)) {
        $p = (int) $m[1]; $f = (int) $m[2];
    } elseif (preg_match('~(\d+)\s*(?:نجاح|ناجح|passed)\D{0,12}?(\d+)\s*(?:فشل|فاشل|failed)~u', $txt, $m)) {
        $p = (int) $m[1]; $f = (int) $m[2];
    }
    $mark = ($code === 0) ? '✔' : '✘';
    if ($code === 0) { $green++; } else { $red++; }
    $lines[] = sprintf('  %s %-42s %3d/%-3d exit=%d', $mark, $t, $p, $f, $code);
    if ($code !== 0) {
        foreach ($out as $l) { if (strpos($l, 'FAIL') !== false || stripos($l, 'error') !== false) { $lines[] = '      ' . $l; } }
    }
}
echo implode("\n", $lines) . "\n";
echo str_repeat('─', 70) . "\n";
echo 'خضراء: ' . $green . ' · حمراء: ' . $red . ' · المجموع: ' . count($files) . "\n";
exit($red > 0 ? 1 : 0);
