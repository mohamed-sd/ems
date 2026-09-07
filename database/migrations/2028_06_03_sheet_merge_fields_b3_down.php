<?php
/**
 * 2028_06_03_sheet_merge_fields_b3_down.php — نزعُ أعمدةِ الدمجِ وحدَها
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ينزع ما أنشأته الهجرةُ لا غير**: الأعمدةُ الخمسةَ عشرَ التي أُعيد استعمالُها
 *   (كانت قائمةً قبلَ الجولة) **ليست في هذه القائمة** — فحذفُها حذفُ عمودِ عملٍ.
 * ⚠ **وبعدَ العكسِ تعود الشاشاتُ ناقصةَ الأعمدةِ حتى تُعكَس شيفرتُها كذلك**
 *   (‏`git revert` لالتزامِ الجولة) — فالعمودُ يُقرأ من التصيير.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI \u{641}\u{642}\u{637}\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$t0 = microtime(true);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS'); if ($p === null || $p === '') { $p = ems_env('DB_PASS'); }
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_error) { exit('\u{62A}\u{639}\u{630}\u{631} \u{627}\u{644}\u{627}\u{62A}\u{635}\u{627}\u{644}: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');

function ems_sm_cols(mysqli $c, $t) {
    $out = array();
    $r = $c->query("SHOW COLUMNS FROM `" . $c->real_escape_string($t) . "`");
    if ($r) { while ($x = $r->fetch_assoc()) { $out[strtolower($x['Field'])] = true; } }
    return $out;
}

$SPEC = array(
    'achievement_records' => array('line_uid', 'account', 'range_label', 'achievement_indicator', 'value', 'unit', 'in_role_language', 'vs_previous_range', 'last_update'),
    'exec_approvals' => array('item_uid', 'source', 'action_type', 'delay_days', 'priority_level', 'state'),
    'fin_budget_lines' => array('indicator_uid', 'kpi_catalog_indicator', 'value', 'unit', 'currency', 'last_update'),
    'fin_chart_of_accounts' => array('line_uid', 'list_name', 'value', 'comparison_period', 'disclosure_note'),
    'proc_request' => array('indicator_uid', 'kpi_catalog_indicator', 'value', 'unit', 'last_update'),
    'proc_rfq' => array('invitation_uid', 'rfq_uid', 'supplier_no', 'invitation_date', 'receipt_confirmation', 'response', 'incoming_offer_ref', 'invitation_state', 'creator_name', 'created_date', 'data_state', 'source_ref'),
    'scr_attendance' => array('record_uid', 'employee_no', 'overtime_hours', 'mission_ref', 'leave_ref', 'registration_source', 'note', 'record_state', 'creator_name', 'data_state', 'source_ref'),
    'scr_monthly_close' => array('closing_uid', 'closing_month', 'month_days', 'fully_approved_days', 'incomplete_days', 'pending_records', 'open_stop_decisions', 'approved_units_total', 'actual_hours_total', 'downtime_total', 'plan_achievement_rate', 'carried_exceptions_list', 'closing_state', 'creator_name', 'reviewer', 'approver', 'data_state', 'source_ref'),
    'scr_project_contracts' => array('line_uid', 'contract_uid', 'employee_no', 'project_link_uid', 'reference_hiring_need', 'supplier_contract_as_trigger', 'agreed_end_trigger', 'actual_end_trigger', 'occurrence_date', 'notice_period', 'settlement_ref', 'creator_name', 'created_date', 'data_state', 'source_ref'),
    'scr_site_gate_equip' => array('entity_type', 'entity_ref', 'movement_direction', 'movement_time', 'site_code', 'active_allocation_ref', 'allocation_match', 'escort_or_driver', 'purpose', 'pass_source', 'event_without_pass', 'pass_state', 'creator_name', 'created_date', 'data_state', 'source_ref'),
    'scr_transfer_fleet' => array('carrier_code', 'rental_supplier_ref', 'weight_capacity_tons', 'allowed_length_and_width', 'axles_count', 'road_licence_and_expiry', 'cargo_insurance_and_expiry', 'valid_route_permits', 'cargo_match_check', 'carrier_state', 'creator_name', 'data_state', 'source_ref'),
    'scr_workshop' => array('capability_code', 'type', 'name', 'location', 'specialities', 'technician_level', 'certificates_and_validity', 'daily_capacity', 'available_now', 'reporting_line', 'external_contract_ref', 'capability_state', 'creator_name', 'data_state', 'source_ref'),
    'tre_cash_move' => array('indicator_uid', 'kpi_catalog_indicator', 'value', 'state', 'last_update'),
    'user_capacities' => array('capacity_uid', 'her_source', 'range_to', 'active_now', 'delegation_ref', 'capacity_state'),
    'work_items' => array('task_uid', 'task_type', 'task_source', 'origin_screen', 'reference', 'task_deadline', 'task_state', 'postponement_reason', 'component_uid', 'account', 'role_label', 'component', 'live_content', 'its_source', 'last_update'),
    'worker_qualification' => array('business_event_uid', 'event_time', 'event_type', 'its_source', 'project_label', 'location', 'equipment', 'event_summary', 'origin_record_ref', 'importance_degree'),
);

$gone = 0; $miss = 0; $fail = 0;
foreach ($SPEC as $tbl => $cols) {
    $have = ems_sm_cols($conn, $tbl);
    if (!$have) { echo "  \u{26D4} \u{62C}\u{62F}\u{648}\u{644} \u{63A}\u{627}\u{626}\u{628}: {$tbl}\n"; continue; }
    $parts = array();
    foreach ($cols as $c) {
        if (!isset($have[strtolower($c)])) { $miss++; continue; }
        $parts[] = "DROP COLUMN `" . $c . "`";
    }
    if (!$parts) { continue; }
    if ($conn->query("ALTER TABLE `{$tbl}` " . implode(', ', $parts))) {
        $gone += count($parts);
        printf("  \u{2714} %-26s -%d\n", $tbl, count($parts));
    } else {
        $fail += count($parts);
        printf("  \u{26D4} %-26s %s\n", $tbl, $conn->error);
    }
}
printf("\n\u{646}\u{064F}\u{632}\u{639}\u{062A} %d \u{00B7} \u{63A}\u{627}\u{626}\u{628}\u{629} %d \u{00B7} \u{641}\u{627}\u{634}\u{644}\u{629} %d\n", $gone, $miss, $fail);
if ($fail > 0) { exit("\n\u{26D4} \u{633}\u{642}\u{637} \u{646}\u{632}\u{639}.\n"); }
$conn->query("DELETE FROM `schema_migrations` WHERE `filename` = '2028_06_03_sheet_merge_fields_b3.php'");
echo "\u{2714} \u{62A}\u{645}\u{651}.\n";
