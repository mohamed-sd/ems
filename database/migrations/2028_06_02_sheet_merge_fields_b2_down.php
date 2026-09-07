<?php
/**
 * 2028_06_02_sheet_merge_fields_b2_down.php — نزعُ أعمدةِ الدمجِ وحدَها
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
    'claims' => array('commitment_cycle_key', 'contract_code', 'client_no', 'client_name_search', 'range_to', 'reference_done_quantity', 'unit', 'computed_due_usd', 'computed_due_sdg', 'claimed_value_usd', 'claimed_value_sdg', 'measure_or_claim_ref', 'client_approval_state', 'finance_handover_date', 'invoice_ref', 'followup_state', 'invoiced_to_client_usd', 'unclaimed_due_usd', 'collection_state', 'collection_state_basis', 'measure_or_settlement_evidence', 'evidence_level'),
    'clients' => array('client_no', 'short_name', 'classification_basis', 'client_state', 'sector', 'country', 'city_or_region', 'registration_no', 'account_owner', 'recognition_source', 'priority_degree', 'credit_rating', 'credit_limit_usd', 'credit_limit_sdg', 'default_payment_terms', 'first_deal_date', 'contracts_count', 'active_contracts', 'last_exec_activity', 'dealing_models', 'notes', 'service_types', 'dealt_currencies', 'source_billing_frequency', 'client_data_evidence_level'),
    'exec_decisions' => array('action_uid', 'deputy_role', 'decision_source', 'subject', 'department', 'responsible', 'due_date', 'priority', 'delay_days', 'evidence', 'closure'),
    'fin_bank_statement_lines' => array('match_uid', 'bank_account', 'bank_statement_balance', 'book_balance', 'variance_items', 'variance_reason', 'variance_treatment', 'remaining_variance', 'statement_attachment', 'match_state', 'creator_name', 'created_date', 'data_state', 'source_ref'),
    'fin_event_links' => array('line_uid', 'original_event', 'publishing_event', 'integration_matrix_effect_rule', 'generated_entry', 'entry_date', 'effect_value', 'event_to_entry_lag', 'consistency_state', 'processing_ref'),
    'fin_journal_entries' => array('event_ref', 'entry_lines_count_ref_m06_2', 'reversal_of', 'entry_state', 'creator_name', 'reviewer', 'approver', 'data_state', 'source_ref'),
    'fin_payments' => array('line_uid', 'client_no', 'project_no', 'total_receivable', 'bracket_value', 'last_collection', 'claim_action'),
    'fin_tax_transactions' => array('declaration_uid', 'tax_type', 'output_tax', 'input_tax', 'net_due', 'statutory_due_date', 'governance_submission_ref', 'treasury_settlement_ref', 'declaration_state', 'creator_name', 'data_state'),
    'fleet_equipment_history' => array('event_sequence', 'location', 'contract_unit', 'document'),
    'mnt_plan' => array('line_uid', 'equipment_code', 'equipment_type', 'cutoff_source', 'preventive_cycle', 'allocated_asset_cutoff', 'cycle_hours', 'last_preventive_meter', 'current_meter_reading', 'remaining_to_due', 'expected_due_date', 'standard_cycle_items', 'due_state', 'generated_order_no', 'creator_name', 'data_state', 'source_ref'),
    'org_units' => array('offer_status', 'deputy_role', 'out_of_scope_read_only', 'kpi', 'pending_requests', 'overdue_actions', 'critical_risks', 'budget_status', 'daily_report_status', 'monthly_close_status', 'compliance_status', 'download_link'),
    'proc_count_session' => array('session_uid', 'count_committee', 'counted_items_count', 'variance_items_ref_kh10_2', 'investigation_ref', 'session_state', 'creator_name', 'reviewer', 'approver', 'data_state', 'source_ref'),
    'proc_package' => array('package_uid', 'grouping_period', 'package_scope', 'bundled_requests', 'items_count', 'total_estimate', 'single_pass_justification', 'purchase_channel', 'package_state', 'creator_name', 'created_date', 'data_state', 'source_ref', 'membership_uid', 'purchase_request_no', 'covered_request_items', 'bundling_date', 'membership_state'),
    'proc_po_amendment' => array('line_uid', 'type', 'order_or_request_no', 'justification', 'active_aam_rule', 'approval_path', 'approval_decision', 'financial_impact', 'affected_items', 'line_state', 'creator_name', 'created_date', 'reviewer', 'approver', 'approval_date', 'data_state', 'source_ref'),
    'proc_stock_state' => array('line_uid', 'item_code', 'balance_state', 'quantity', 'value', 'below_minimum'),
    'quotations' => array('internal_offer_no', 'opportunity_no', 'request_no', 'client_no', 'client_name_search', 'project_no', 'official_offer_no', 'date_basis', 'work_model', 'validity_period', 'payment_or_billing_terms', 'offer_state', 'client_response', 'decision_state', 'decision_date', 'offer_value_usd', 'offer_value_sdg', 'resulting_contract_ref', 'source_commitment_cycle_key', 'evidence_level', 'residual_value_basis', 'event_no', 'record_type', 'offer_no', 'contract_ref', 'new_commitment_cycle', 'previous_commitment_cycle', 'comparison_scope', 'date_value', 'change_type', 'commercial_impact', 'reference_document', 'reason_or_evidence', 'requesting_party'),
    'rec_applications' => array('vacancy_no', 'opening_date', 'job_title', 'required_headcount', 'vacancy_requirements', 'candidates', 'accepted_candidate', 'practical_test_result', 'submitted_offer', 'vacancy_state', 'creator_name', 'reviewer', 'approver', 'data_state', 'source_ref'),
    'risk_export_log' => array('line_uid', 'frequency', 'period', 'family', 'item', 'value', 'direction', 'needs_decision', 'risk_ref'),
    'tickets' => array('report_no', 'registration_time', 'registration_channel', 'reporter_uid', 'reporter_name', 'reporter_department', 'reporter_entity', 'subject_type', 'subject_uid', 'subject_name', 'subject_owning_department', 'category', 'nature', 'priority_level', 'confidentiality_level', 'report_description', 'attachments', 'ticket_owner', 'assigned_department', 'resolution_owner', 'processing_deadline', 'report_state', 'creator_name', 'data_state', 'source_ref', 'line_uid', 'offer_scope', 'registration_date', 'report_subject', 'entity_created_in_our_dept', 'sla_deadline', 'remaining_or_delay', 'awaiting_verification'),
    'transfer_orders' => array('order_date', 'request_no', 'cargo_type', 'equipment_code', 'from_location', 'to_location', 'planned_distance', 'transport_mode', 'carrier', 'carrier_contract', 'driver', 'driver_licence_valid', 'planned_route', 'planned_departure_date', 'planned_arrival_date', 'required_permits', 'order_state', 'creator_name', 'reviewer', 'approver', 'data_state', 'source_ref', 'business_event_uid', 'event_type', 'event_time', 'geo_location', 'carrier_meter_reading', 'event_note', 'attachment_or_photo', 'recorded_offline', 'line_state'),
    'tre_beneficiaries' => array('beneficiary_uid', 'beneficiary_name', 'beneficiary_type', 'account_or_iban', 'verification_document', 'verification_date', 'independent_verifier', 'pending_account_change', 'verification_state', 'created_date', 'data_state', 'source_ref'),
    'trp_kpi_period' => array('line_uid'),
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
$conn->query("DELETE FROM `schema_migrations` WHERE `filename` = '2028_06_02_sheet_merge_fields_b2.php'");
echo "\u{2714} \u{62A}\u{645}\u{651}.\n";
