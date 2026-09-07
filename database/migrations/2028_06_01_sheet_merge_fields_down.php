<?php
/**
 * 2028_06_01_sheet_merge_fields_down.php — نزعُ أعمدةِ الدمجِ وحدَها
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
    'contracts' => array('client_name_search', 'project_no', 'system_contract_no', 'company_sequence', 'document_signature', 'contractual_start', 'contractual_end', 'execution_start', 'execution_end', 'commitment_cycles_count', 'current_contracted_units', 'current_monthly_capacity', 'evidence_source', 'currency', 'pricing_basis_as_stated', 'unit_price_as_stated', 'tax', 'payment_and_billing', 'deposit_or_advance', 'maintenance_and_spares', 'transport_and_packing', 'commercial_officer', 'notes', 'provided_service_type', 'contracting_unit_basis', 'contract_signing_place', 'historically_likely_place', 'likely_place_basis', 'likely_place_evidence_level', 'source_state_as_stated', 'state_change_basis', 'billing_unit', 'min_quantity', 'min_level_frequency', 'guaranteed_quantity', 'billing_threshold', 'shortage_bearer', 'shortage_rule', 'price_structure', 'price_versions_count', 'pricing_ref'),
    'employee_contracts' => array('contract_uid', 'employee_no', 'wage_model', 'basic_wage', 'allowances', 'date_from', 'date_to', 'project_ref', 'signed_contract_attachment', 'creator_name', 'reviewer', 'approver', 'data_state', 'source_ref'),
    'employees' => array('employee_no', 'national_id', 'type', 'marital_status', 'commencement_date', 'is_operational_operator', 'workforce_operator_code', 'contact_details', 'emergency_contact', 'creator_name', 'reviewer', 'approver', 'data_state', 'source_ref'),
    'exec_approvals' => array('request_uid', 'source_department', 'request_type', 'deputy_role', 'approval_scope', 'previous_approval', 'recommendation', 'documents', 'risk', 'deputy_decision', 'conditions', 'creator_name', 'created_date', 'source_ref'),
    'fin_currencies' => array('line_uid', 'currency', 'counterparty', 'effective_date', 'source_documentation', 'line_state', 'creator_name', 'data_state', 'source_ref'),
    'fin_dues' => array('due_uid', 'supplier_invoice_no', 'supplier_no', 'due_source', 'source_ref', 'gate_check', 'tax', 'due_state', 'creator_name', 'reviewer', 'approver', 'data_state'),
    'fin_financial_periods' => array('period_uid', 'month', 'period_entries', 'pending_entries', 'warehouse_match', 'treasury_match', 'incoming_ops_closures', 'processing_variances', 'closing_decision', 'reopen_decision', 'period_state', 'creator_name', 'reviewer', 'approver', 'data_state', 'source_ref'),
    'fin_requests' => array('request_details', 'attachment', 'decision_owner_party', 'approval_route', 'request_state', 'party_decision', 'decision_date', 'creator_name', 'data_state'),
    'housing_unit' => array('occupied_count', 'vacant_count', 'supervisor', 'maintenance_state', 'unit_state', 'creator_name', 'data_state', 'source_ref'),
    'mnt_breakdown' => array('intake_uid', 'report_no', 'report_date', 'amount', 'equipment_code', 'location', 'fault_description', 'initial_tree_node', 'severity_degree', 'equipment_stopped', 'stoppage_impact', 'intake_decision', 'branched_inspection_req_no', 'intake_state', 'fault_severity', 'downtime_duration', 'operational_impact', 'preventability', 'recurrence', 'response_performance', 'delay_reason', 'chain_of_responsibility', 'equipment_stop_time', 'operator_report_time', 'maintenance_receipt_time', 'diagnosis_start_time', 'diagnosis_end_time', 'part_request_time', 'part_available_time', 'site_arrival_time', 'technician_arrival_time', 'repair_start_time', 'repair_end_time', 'test_time', 'certification_time', 'equipment_back_in_service_time', 'downtime_total', 'actual_repair_time', 'creator_name', 'created_date', 'source_ref'),
    'mnt_daily_care' => array('line_uid', 'equipment_code', 'care_checklist_for_type', 'executor', 'creator_name', 'created_date', 'data_state', 'source_ref'),
    'mnt_external_repair' => array('line_uid', 'order_no', 'external_party_supplier', 'contract_or_guarantee_ref', 'creator_name', 'created_date', 'data_state', 'source_ref'),
    'mnt_kpi_period' => array('line_uid', 'equipment_or_type', 'executed_preventive_orders', 'maintenance_cost_per_hour'),
    'mnt_part_request' => array('order_no', 'requested_items', 'custody_receiver', 'issue_note_no', 'creator_name', 'created_date', 'data_state', 'source_ref'),
    'mnt_repeat_repair' => array('event_uid', 'equipment_code', 'original_order_no', 'within_certificate_validity', 'root_cause_analysis', 'new_order_no', 'decision', 'event_state', 'creator_name', 'created_date', 'data_state', 'source_ref'),
    'payroll_runs' => array('payroll_uid', 'payroll_scope', 'employees_count', 'basic_total', 'allowances_total', 'production_incentives', 'operator_workforce_basis', 'payroll_net', 'finance_referral', 'treasury_referral', 'payroll_state', 'creator_name', 'reviewer', 'approver', 'data_state', 'source_ref'),
    'proc_award' => array('rfq_no', 'compared_offers', 'comparison_table', 'awarded_offer', 'award_value', 'selection_justification', 'justification_detail', 'committee_members', 'award_state', 'creator_name', 'created_date', 'reviewer', 'approver', 'approval_date', 'data_state', 'source_ref'),
    'proc_delivery_event' => array('line_uid', 'order_no', 'covered_quantity', 'grn_no', 'inspection_result', 'supplier_notification', 'line_state', 'creator_name', 'created_date', 'data_state', 'source_ref'),
    'proc_hazmat_control' => array('line_uid', 'item_code', 'severity_category', 'statutory_permit', 'isolation_location', 'authorized_custodian', 'batch_tracking_required', 'disbursement_authority', 'dual_control', 'validity_constraint', 'disposal_route', 'controls_state', 'creator_name', 'created_date', 'data_state', 'source_ref'),
    'proc_invoice_match' => array('match_no', 'supplier_no', 'order_no', 'grn_notes', 'received_value', 'variance_classification', 'variance_value', 'variance_explanation', 'finance_referral', 'match_state', 'creator_name', 'reviewer', 'approver', 'data_state', 'source_ref'),
    'proc_issue_request' => array('item_uid', 'request_no', 'item_code', 'requested_quantity', 'approved_quantity', 'cumulative_spend', 'remaining', 'item_state', 'creator_name', 'created_date', 'data_state', 'source_ref', 'arrival_date', 'issue_type', 'justifying_reference', 'reference_check', 'requested_items', 'balance_check', 'warehouse_decision', 'request_state'),
    'proc_offer' => array('offer_uid', 'rfq_no', 'supplier_no', 'supplier_name', 'receipt_date', 'offer_value', 'offered_payment_terms', 'offer_validity', 'technical_evaluation', 'inspection_notes', 'financial_arrangement', 'offer_state', 'creator_name', 'created_date', 'data_state', 'source_ref', 'line_uid', 'request_item_ref', 'item_code', 'offered_unit_price', 'quantity', 'item_lead_time', 'alternative_proposed', 'technical_note', 'item_financial_ranking'),
    'proc_order' => array('order_date', 'minutes_no', 'supplier_no', 'framework_contract_ref', 'items_count_ref_sh07_2', 'total_value', 'delivery_place', 'delay_penalty', 'order_state', 'creator_name', 'reviewer', 'approver', 'data_state', 'source_ref'),
    'proc_request' => array('requesting_party', 'charged_project', 'items_count_ref_sh02_2', 'required_date', 'initial_estimate', 'request_state', 'creator_name', 'reviewer', 'approver', 'data_state'),
    'proc_supplier_eval' => array('line_uid', 'supplier_no', 'its_value', 'schedule_adherence', 'average_delay', 'inspection_rejection_rate', 'match_variances', 'composite_index', 'resulting_classification'),
    'proc_transfer' => array('order_date', 'items_count_ref_kh09_2', 'transfer_justification', 'transport_mode', 'gate_pass', 'receipt_note', 'receipt_match', 'order_state', 'creator_name', 'data_state', 'source_ref'),
    'proc_warehouse' => array('warehouse_code', 'warehouse_name', 'warehouse_type', 'custodian_on_duty', 'custody_method', 'special_licence', 'storage_capacity', 'safety_controls', 'warehouse_state', 'creator_name', 'created_date', 'reviewer', 'approver', 'approval_date', 'data_state', 'source_ref'),
    'proc_wh_close' => array('closing_uid', 'month', 'month_grn_notes', 'month_issue_notes', 'month_transfers', 'settled_count_variances', 'open_custodies_carried', 'closing_stock_value', 'finance_match', 'closing_state', 'creator_name', 'created_date', 'reviewer', 'approver', 'approval_date', 'data_state', 'source_ref'),
    'project' => array('project_no', 'client_no', 'client_name_search', 'description', 'execution_scope_location', 'sector', 'project_state', 'start_date', 'commercial_officer', 'estimated_value_usd', 'estimated_value_sdg', 'contracts_count', 'notes', 'client_side_project_code', 'region_state', 'client_project_sequence', 'service_type', 'work_model', 'naming_and_bounds_basis', 'grouping_rule', 'evidence_level'),
    'risk_register' => array('risk_uid', 'risk_title', 'classification_node', 'family', 'identification_source', 'source_event_key', 'affected_entity', 'entity_ref', 'affected_operating_unit', 'risk_description', 'risk_owner', 'identification_date', 'last_assessment', 'current_residual_level', 'risk_state', 'creator_name', 'reviewer', 'approver', 'approval_date', 'data_state', 'source_ref'),
    'risk_treatments' => array('action_uid', 'risk_uid', 'processing_route', 'action_description', 'owner_name', 'executing_department', 'target_level_after_action', 'delay_days', 'reassess_after', 'action_state', 'creator_name', 'created_date', 'data_state', 'source_ref'),
    'sal_client_needs' => array('request_no', 'client_no', 'client_name_search', 'project_no', 'opportunity_no', 'request_type', 'request_scope', 'requested_service', 'required_work_model', 'unit', 'requested_qty_or_volume', 'required_machine_types', 'machines_count', 'expected_start', 'expected_end', 'expected_start_basis', 'expected_end_basis', 'dates_data_state', 'core_commercial_requirements', 'receipt_date', 'offer_response_due', 'resulting_contract_ref', 'source_commitment_cycle_key', 'evidence_level', 'residual_value_basis'),
    'sal_quotation_lines' => array('item_no', 'offer_no', 'contract_ref', 'item_type', 'service_type', 'equipment_or_item_type', 'work_model', 'equipment_count', 'monthly_unit_basis', 'duration_months', 'quantity_or_target', 'measure_unit', 'value', 'price_version_effective_from', 'price_basis', 'tax_as_stated', 'price_text_as_stated', 'data_state', 'commercial_notes', 'source_commitment_cycle_key', 'evidence_level', 'residual_value_basis'),
    'scr_op_monthly' => array('plan_line_uid', 'plan_month', 'project_no', 'project_name', 'client_contract_code', 'equipment_code', 'equipment_type', 'work_model', 'contractual_target', 'plan_target', 'measure_unit', 'planned_work_days', 'day_shifts', 'available_hours_per_shift', 'season_factor', 'season_adjusted_target', 'contractual_variance_reason', 'line_state', 'creator_name', 'created_date', 'reviewer', 'approver', 'approval_date', 'data_state', 'source_ref'),
    'ticket_sla_policies' => array('line_uid', 'report_type', 'responsible_department', 'response_sla', 'resolution_sla', 'escalation_ladder', 'matrix_effective_from', 'line_state', 'creator_name', 'data_state', 'source_ref'),
    'timesheet' => array('record_uid', 'operating_day', 'equipment_code', 'equipment_type', 'submitting_supplier', 'project_contract_unit', 'annual_container_key', 'monthly_container_no', 'slot_code_and_occupancy', 'outside_contract_window_flag', 'current_location', 'work_zone', 'operator_code', 'work_model', 'default_change_reason', 'available_hours', 'executed_quantity', 'measure_unit', 'meter_start_of_shift', 'meter_end_of_shift', 'meter_hours', 'actual_total', 'standby_total', 'downtime_total', 'capacity_exceeded', 'override_reason', 'field_reference', 'sync_state', 'record_state', 'creator_name', 'reviewer', 'approver', 'data_state', 'source_ref'),
    'timesheet_approvals' => array('batch_uid', 'approval_day', 'batch_scope', 'records_count', 'records_over_capacity', 'records_missing_reason', 'approval_stage', 'site_approver', 'parties_approver', 'contracts_approver', 'match_result', 'excluded_records', 'exception_reason', 'batch_decision', 'creator_name', 'reviewer', 'approver', 'data_state', 'source_ref'),
    'transfer_permits' => array('permit_uid', 'order_no', 'permit_scope', 'permit_attachment', 'permit_state', 'creator_name', 'data_state', 'source_ref'),
    'transfer_requests' => array('requesting_party', 'resource_move_order_ref', 'cargo_type', 'equipment_or_item_code', 'weight_or_dimensions', 'from_location', 'to_location', 'loading_notes', 'request_state', 'creator_name', 'data_state', 'source_ref'),
    'tre_pay_batches' => array('order_no', 'request_no', 'approval_completeness_check', 'beneficiary', 'value', 'disbursing_vessel', 'vessel_balance_check', 'first_location', 'second_location', 'delegation_validity_check', 'bank_execution_ref', 'execution_date', 'receivables_effect', 'order_state', 'creator_name', 'created_date', 'reviewer', 'approver', 'approval_date', 'data_state', 'source_ref'),
    'trp_closure' => array('closing_uid', 'order_no', 'receipt_minutes_check', 'closing_note', 'creator_name', 'created_date', 'reviewer', 'approver', 'approval_date', 'data_state', 'source_ref'),
    'trp_damage_claim' => array('order_no', 'likely_cause_party', 'estimated_claim_value', 'evidence_documents', 'settlement_decision', 'creator_name', 'created_date', 'reviewer', 'approver', 'approval_date', 'data_state', 'source_ref'),
    'trp_origin_handover' => array('item_uid', 'order_no', 'executor', 'pre_transfer_photos', 'route_risk_assessment', 'creator_name', 'created_date', 'data_state', 'source_ref'),
    'trp_trip_leg' => array('stage_uid', 'order_no', 'stage_sequence', 'assigned_carrier', 'driver', 'estimated_distance', 'stage_handover_to_next', 'creator_name', 'created_date', 'data_state', 'source_ref'),
    'worker_leave_absence' => array('employee_no', 'supporting_attachment', 'rotation_cycle_ref', 'request_state', 'creator_name', 'reviewer', 'approver', 'data_state', 'source_ref'),
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
$conn->query("DELETE FROM `schema_migrations` WHERE `filename` = '2028_06_01_sheet_merge_fields.php'");
echo "\u{2714} \u{62A}\u{645}\u{651}.\n";
