<?php
/**
 * tools/nav09_seed_map.php — بذرُ قاموس المواءمة الثلاثي (NAV-09 ⓪-3)
 * ───────────────────────────────────────────────────────────────────────────
 * لكل ملفٍّ قانونيٍّ في الورقة 98 قرارٌ من ثلاثة:
 *   live   — موجودٌ باسمه على القرص (مطابقةٌ آلية)
 *   mapped — موجودٌ باسمٍ آخرَ (مرشّحاتٌ يدويةٌ يُتحقق من وجودها ملفًّا)
 *   soon   — لم يُبنَ بعدُ — رابطُه صفحةُ «قريبًا»
 * آمنُ الإعادة: يحدّث الحالات ولا يمسّ ما قرّره المالكُ يدويًّا (note='manual').
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/nav09_read.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);

/* المواءماتُ اليدوية: قانوني → مرشّحاتٌ بالأولوية (أولُ موجودٍ يفوز) */
$MAP = array(
    // مساحة عملي والمشترك
    'messages.php'        => array('chats/index.php'),
    'reports_index.php'   => array('emsreports/index.php'),
    'assistants.php'      => array('main/all_assistants.php'),
    // البلاغات
    'tickets_dept.php'    => array('Tickets/dept_inbox.php'),
    'ticket_open.php'     => array('Tickets/ticket_contextual_open.php'),
    'tickets.php'         => array('Tickets/tickets_list.php'),
    'ticket_route.php'    => array('Tickets/ticket_types_config.php'),
    'ticket_escalate.php' => array('Tickets/ticket_escalation_config.php'),
    'ticket_split.php'    => array('Tickets/ticket_workstreams_board.php'),
    'ticket_close.php'    => array('Tickets/admin_close.php'),
    'ticket_recur.php'    => array('Tickets/ticket_recurrence.php'),
    'ticket_kpi.php'      => array('Tickets/ticket_dashboard.php'),
    'ticket_config.php'   => array('Tickets/ticket_types_config.php'),
    'ticket_periodic.php' => array('Tickets/ticket_recurrence.php'),
    // الموقع والتشغيل
    'distribution.php'    => array('Operations/distribution_space.php'),
    'stops.php'           => array('Operations/stops_unattributed.php'),
    'ops_room.php'        => array('Operations/operations_room.php'),
    'units.php'           => array('Operations/containers.php'),
    'unit_assign.php'     => array('Operations/containers.php'),
    'op_assign.php'       => array('Operations/distribution_space.php'),
    'coverage.php'        => array('Contracts/contract_coverage.php'),
    // الصيانة والأسطول
    'readiness.php'       => array('Fleet/readiness_board.php'),
    'preventive.php'      => array('Maintenance/preventive_plans.php'),
    'failures.php'        => array('Equipments/manage_failure_codes.php'),
    'equip_models.php'    => array('Equipments/fleet_models.php'),
    'equip_docs.php'      => array('Equipments/equipment_documents.php'),
    'equip_meters.php'    => array('Equipments/meter_readings.php'),
    'equip_types.php'     => array('Equipments/equipments_types.php'),
    'dep_profiles.php'    => array('Equipments/fleet_depreciation_profiles.php'),
    'depreciation.php'    => array('Finance/assets_fin.php'),
    'fleet_owners.php'    => array('Financing/owners_registry.php'),
    // النقل
    'transfer_req.php'    => array('Transport/transfer_requests.php'),
    'transfer_ord.php'    => array('Transport/transfer_orders_list.php'),
    'transfer_track.php'  => array('Transport/transfer_in_transit.php'),
    'transfer_cost.php'   => array('Transport/transfer_close_cost.php'),
    'transfer_tariff.php' => array('Transport/transfer_tariffs.php'),
    // سلسلة الإمداد
    'pr.php'              => array('Procurement/requests_proc.php'),
    'rfq.php'             => array('Procurement/rfq_compare_award.php'),
    'po.php'              => array('Procurement/orders_proc.php'),
    'items.php'           => array('Procurement/items_proc.php'),
    'stock.php'           => array('Procurement/stock_proc.php'),
    'issue.php'           => array('Procurement/issue_proc.php'),
    'custody.php'         => array('Procurement/receipt_custody_proc.php'),
    'warehouses.php'      => array('Procurement/master_data_proc.php'),
    // المبيعات والعقود
    'contract_terms.php'  => array('Contracts/contract_card.php'),
    'price_adjust.php'    => array('Contracts/price_terms.php'),
    'invoices.php'        => array('Contracts/tax_invoices.php'),
    'receivables.php'     => array('Contracts/collections.php'),
    // الموردون
    'supplier_contracts.php' => array('Suppliers/supplierscontracts.php'),
    'supplier_quota.php'  => array('Suppliers/shares_coverage.php'),
    'supplier_equip.php'  => array('Suppliers/equipment_plan.php'),
    'supplier_settle.php' => array('Suppliers/settlements.php'),
    'supplier_stmt.php'   => array('Finance/supplier_statement_fin.php'),
    'supplier_perf.php'   => array('Suppliers/supplier_capacity.php'),
    // القوى والموارد
    'operators.php'       => array('Employees/equipment_operators.php'),
    'op_deduct.php'       => array('Workforce/proposed_deductions.php'),
    'emp_contracts.php'   => array('Employees/employee_contracts.php'),
    'recruitment.php'     => array('Workforce/recruitment_pipeline.php'),
    'leaves.php'          => array('Workforce/worker_leave_absence.php'),
    'advances.php'        => array('Workforce/employee_advances.php'),
    'payroll.php'         => array('Workforce/payroll_runs.php'),
    // المالية
    'entitlement.php'     => array('Finance/entitlement_gate.php'),
    'payables.php'        => array('Finance/dues_fin.php'),
    'payments.php'        => array('Finance/payments_fin.php'),
    'treasury.php'        => array('Finance/accounts_fin.php'),
    'bank_recon.php'      => array('Finance/bank_reconciliation_fin.php'),
    'journal.php'         => array('Finance/journal_form_fin.php'),
    'coa.php'             => array('Finance/management_accounting_fin.php'),
    'fx_rates.php'        => array('Finance/currencies_fin.php'),
    'period_close.php'    => array('Finance/periods_fin.php'),
    'variance.php'        => array('Finance/variance_monitor_fin.php'),
    'cash_forecast.php'   => array('Finance/cash_forecast_fin.php'),
    'fin_statements.php'  => array('Finance/financial_statements_fin.php'),
    'cost_report.php'     => array('Finance/cost_report_fin.php'),
    'maint_provision.php' => array('Finance/maintenance_provision_fin.php'),
    'cycle_time.php'      => array('FinRequests/cycle_time_board.php'),
    // التمويل والملكية
    'financiers.php'      => array('Financing/financiers_registry.php'),
    'fin_ops.php'         => array('Financing/financing_board.php'),
    'shares.php'          => array('Financing/owners_registry.php'),
    'fin_exit.php'        => array('Financing/deviations.php'),
    'fin_variance.php'    => array('Financing/deviations.php'),
    'fin_idle.php'        => array('Financing/deviations.php'),
    'fin_cost.php'        => array('Financing/cost_allocation.php'),
    // الجديد في v4
    'fin_deviations.php'  => array('Financing/deviations.php'),
    'supplier_plan.php'   => array('Suppliers/equipment_plan.php'),
    'quota_ledger.php'    => array('Suppliers/shares_coverage.php'),
    // الحوكمة
    'visibility.php'      => array('Portal/visibility_keys.php'),
    'audit.php'           => array('ActivityLogs/activity_logs.php'),
    'guard_denials.php'   => array('admin/audit_log.php'),
);

/* live الآلية: أسماءٌ مطابقةٌ — الأولويةُ لمجلد المالك عند التعدد */
$preferred = array(
    'timesheet.php'  => 'Timesheet/timesheet.php',
    'employees.php'  => 'Employees/employees.php',
    'roles.php'      => 'Settings/roles.php',
    'modules.php'    => 'Settings/modules.php',
);

$d = Nav09Reader::load($ROOT . '/docs/files/NAV-09-current.xlsx');
$files = array();
foreach ($d['matrix'] as $m) {
    if (!isset($files[$m['file']])) { $files[$m['file']] = array('title' => $m['title'], 'owner' => $m['owner']); }
}

/* فهرسُ القرص للمطابقة الآلية */
$disk = array();
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $f) {
    if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php') { continue; }
    $p = str_replace(chr(92), '/', $f->getPathname());
    if (preg_match('#/(vendor|\.claude|storage|node_modules|\.ssdiff|tests|tools|database|app|includes|cron|docs|api)/#', $p)) { continue; }
    $disk[strtolower($f->getFilename())][] = substr($p, strlen($ROOT) + 1);
}

$counts = array('live' => 0, 'mapped' => 0, 'soon' => 0, 'kept' => 0);
foreach ($files as $cf => $info) {
    $ecf = mysqli_real_escape_string($conn, $cf);
    $r = mysqli_query($conn, "SELECT state, note FROM nav09_file_map WHERE canonical_file = '$ecf'");
    if ($r && ($x = mysqli_fetch_assoc($r)) && $x['note'] === 'manual') { $counts['kept']++; continue; }

    $state = 'soon'; $real = null;
    $k = strtolower($cf);
    if (isset($preferred[$cf]) && is_file($ROOT . '/' . $preferred[$cf])) {
        $state = 'live'; $real = $preferred[$cf];
    } elseif (isset($disk[$k])) {
        $state = 'live'; $real = $disk[$k][0];
    } elseif (isset($MAP[$cf])) {
        foreach ($MAP[$cf] as $cand) {
            if (is_file($ROOT . '/' . $cand)) { $state = 'mapped'; $real = $cand; break; }
        }
    }
    $counts[$state]++;
    $et = mysqli_real_escape_string($conn, $info['title']);
    $eo = mysqli_real_escape_string($conn, $info['owner']);
    $er = $real === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, $real) . "'";
    mysqli_query($conn, "INSERT INTO nav09_file_map (canonical_file, title_ar, owner_dept, state, real_path, note)
        VALUES ('$ecf', '$et', '$eo', '$state', $er, 'auto')
        ON DUPLICATE KEY UPDATE title_ar = '$et', owner_dept = '$eo', state = '$state', real_path = $er")
        or die('✘ ' . mysqli_error($conn) . "\n");
}
echo "القاموس: live={$counts['live']} · mapped={$counts['mapped']} · soon={$counts['soon']} · محفوظ يدويًّا={$counts['kept']}\n";
$r = mysqli_query($conn, "SELECT canonical_file, title_ar FROM nav09_file_map WHERE state = 'soon' ORDER BY owner_dept, canonical_file");
echo "── قائمة «قريبًا» ──\n";
while ($x = mysqli_fetch_assoc($r)) { echo "  {$x['canonical_file']} — {$x['title_ar']}\n"; }
