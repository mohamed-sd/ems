# مسحةُ التلوثِ — جردُ قراءةٍ فقط

· 2026-08-18 17:48 · أمرُ الإنتاج: `php tools/uxui_pollution_scan.php --md=<الملف>`
· **لا كتابةَ في بياناتٍ في هذه الجولة** — بنصِّ قرارِ المالك (ثامنًا ②).

| الجدول | العمود | رتبةُ العمود | العلامة | إصابات | عيّنة |
|---|---|---|---|---|---|
| `visibility_audit_log` | `element_code` | خانةُ رمز | UAT | 57 | field.evaluation |
| `template_permissions` | `permission_code` | خانةُ رمز | UAT | 28 | Workforce/worker_evaluation.php:update |
| `approval_workflow_rules` | `entity_type` | خانةُ رمز | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026-001 |
| `capacity_outbox` | `entity_type` | خانةُ رمز | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_approval_chain` | `source_kind` | خانةُ رمز | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026-001 |
| `fin_backflow_log` | `source_kind` | خانةُ رمز | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_cycle_time_metrics` | `request_type` | خانةُ رمز | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_obl_avoidance` | `contract_kind` | خانةُ رمز | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026-001 |
| `fin_obl_register` | `contract_kind` | خانةُ رمز | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `processed_operations` | `doc_type` | خانةُ رمز | UAT | 20 | storage/uat/processed_operations-9.pdf |
| `processed_operations` | `effect_kind` | خانةُ رمز | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026-001 |
| `risk_incidents` | `entity_type` | خانةُ رمز | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fleet_equipment_compliance` | `doc_type` | خانةُ رمز | UAT | 19 | storage/uat/fleet_equipment_compliance-9 |
| `fleet_equipment_protection` | `protection_type` | خانةُ رمز | UAT | 19 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `worker_leave_absence` | `event_type` | خانةُ رمز | UAT | 19 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `workforce_requirement` | `worker_category` | خانةُ رمز | UAT | 19 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `incentive_rules` | `incentive_type` | خانةُ رمز | UAT | 18 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `ems_state_transitions` | `from_state` | خانةُ رمز | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `ems_state_transitions` | `to_state` | خانةُ رمز | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fleet_model` | `operating_category` | خانةُ رمز | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fleet_model` | `fuel_type` | خانةُ رمز | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `iaf_access_log` | `scope_kind` | خانةُ رمز | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `mnt_lookup` | `type` | خانةُ رمز | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `signing_authorities` | `scope_type` | خانةُ رمز | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `worker_movement` | `origin_state` | خانةُ رمز | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `worker_movement` | `destination_state` | خانةُ رمز | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `gov_doc_registry` | `item_code` | خانةُ رمز | TEST | 15 | STDTEST-15 |
| `mnt_order_part` | `category` | خانةُ رمز | UAT | 15 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_business_models` | `status` | خانةُ رمز | UAT | 15 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `audit_logs` | `action_type` | خانةُ رمز | UAT | 13 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `mnt_inspection_template` | `inspection_type` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `proc_lookup` | `type` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_asset_recon` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_attendance` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_break_glass` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_canonical_names` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_code_bridge` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_consumption_rate` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_contract_review` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_deductions` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_doc_types` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_equipment_quota` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_equipment_sourcing` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_exceptions` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_fin_assets` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_fin_changes` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_fin_models` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_founding_mode` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_guards` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_monthly_close` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_op_codes` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_op_monthly` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_op_qual` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_ownership_links` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_perm_explain` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_portal_users` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_production` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_project_contracts` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_rotation` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_shift_log` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_site_gate_equip` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_site_gate_person` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_site_shift_plan` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_site_work_calendar` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_state_machines` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_transfer_fleet` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_transfer_permits` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_unbilled` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_unit_perf` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `scr_workshop` | `status` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `suppliercontractequipments` | `equip_type` | خانةُ رمز | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_obl_recognition` | `contract_kind` | خانةُ رمز | UAT | 9 | يخضع لمراجعة الربع القادم · UAT-2026-001 |
| `scr_access_review` | `status` | خانةُ رمز | UAT | 9 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `contractequipments` | `equip_type` | خانةُ رمز | UAT | 8 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `deduction_types` | `ded_kind` | خانةُ رمز | UAT | 8 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `proc_item` | `category` | خانةُ رمز | UAT | 8 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `proc_item` | `served_category` | خانةُ رمز | UAT | 8 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `opportunities` | `source` | خانةُ رمز | UAT | 6 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `opportunities` | `sector_category` | خانةُ رمز | UAT | 6 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `nav09_action_map` | `canonical_code` | خانةُ رمز | TEST | 5 | gov.sup.attest |
| `nav09_action_map` | `live_code` | خانةُ رمز | TEST | 5 | Governance/gov_m14_actions.php::gov_atte |
| `proc_landed_cost` | `cost_type` | خانةُ رمز | UAT | 5 | مطابقٌ للمستند المرفق · UAT-20 |
| `approval_requests` | `entity_type` | خانةُ رمز | UAT | 4 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `mnt_breakdown` | `state` | خانةُ رمز | UAT | 4 | مطابقٌ للمستند المرفق · UAT-20 |
| `nav_items` | `permission_code` | خانةُ رمز | UAT | 4 | Workforce/worker_evaluation.php |
| `mnt_plan` | `state` | خانةُ رمز | UAT | 3 | مطابقٌ للمستند المرفق · UAT-20 |
| `modules` | `code` | خانةُ رمز | UAT | 3 | Workforce/worker_evaluation.php |
| `approval_signatures` | `document_type` | خانةُ رمز | UAT | 2 | storage/uat/approval_signatures-20.pdf |
| `fin_cost_centers` | `code` | خانةُ رمز | UAT | 2 | MAUATC |
| `fin_routing_log` | `source_kind` | خانةُ رمز | UAT | 2 | مراجَعٌ من المالية ومطابق · UAT-2026-002 |
| `nav09_action_map` | `live_code` | خانةُ رمز | UAT | 2 | page:Workforce/worker_evaluation.php |
| `proc_order` | `state` | خانةُ رمز | UAT | 2 | مطابقٌ للمستند المرفق · UAT-20 |
| `proc_receipt_custody` | `state` | خانةُ رمز | UAT | 2 | مطابقٌ للمستند المرفق · UAT-20 |
| `proc_request` | `need_source` | خانةُ رمز | UAT | 2 | مطابقٌ للمستند المرفق · UAT-20 |
| `proc_request` | `state` | خانةُ رمز | UAT | 2 | مطابقٌ للمستند المرفق · UAT-20 |
| `proc_warehouse` | `type` | خانةُ رمز | UAT | 2 | مطابقٌ للمستند المرفق · UAT-20 |
| `contract_commitments` | `commitment_code` | خانةُ رمز | SEED | 1 | SEED-CMT-01 |
| `ems_event_outbox` | `event_code` | خانةُ رمز | TEST | 1 | risk.access_review.attested |
| `ems_event_subscriptions` | `event_code` | خانةُ رمز | TEST | 1 | risk.access_review.attested |
| `nav09_action_map` | `canonical_code` | خانةُ رمز | UAT | 1 | supplier.evaluate |
| `payroll_absence_types` | `event_type` | خانةُ رمز | UAT | 1 | أُثبت من واقع التشغيل الميداني · UAT-202 |
| `portal_elements` | `element_code` | خانةُ رمز | UAT | 1 | field.evaluation |
| `ticket_types` | `category` | خانةُ رمز | UAT | 1 | مراجَعٌ من المالية ومطابق · UAT-2026 |
| `timesheet_failure_hours` | `sub_category` | خانةُ رمز | UAT | 1 | مراجَعٌ من المالية ومطابق · UAT-2026 |
| `gov_doc_registry` | `coverage_kind` | تعداد | UAT | 100 | uat |
| `fin_fx_differences` | `source_kind` | تعداد | UAT | 10 | revaluation |
| `transfer_cost_rules` | `movement_type` | تعداد | DEMO | 4 | demob |
| `transfer_orders` | `direction` | تعداد | DEMO | 4 | demob |
| `rec_applications` | `stage` | تعداد | TEST | 3 | practical_test |
| `risk_register` | `state` | تعداد | UAT | 2 | controls_evaluated |
| `founding_mode` | `mode` | تعداد | TEST | 1 | permission_test |
| `activity_logs` | `screen_name` | اسمٌ ظاهر | UAT | 4587 | worker_evaluation |
| `activity_logs` | `field_name` | اسمٌ ظاهر | UAT | 875 | evaluation_id,indicator,measurable,weigh |
| `gov_doc_variance` | `subject` | اسمٌ ظاهر | UAT | 7 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `activities` | `subject` | اسمٌ ظاهر | UAT | 6 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `activity_logs` | `field_name` | اسمٌ ظاهر | SEED | 4 | entry_no,entry_date,project_id,contract_ |
| `gov_screen_cycle` | `screen_title` | اسمٌ ظاهر | UAT | 3 | تقييمي (my_evaluation.php) |
| `nav09_action_map` | `event_name` | اسمٌ ظاهر | UAT | 3 | WorkerEvaluated |
| `nav09_action_map` | `event_name` | اسمٌ ظاهر | TEST | 3 | AccessReviewAttested |
| `activity_logs` | `screen_name` | اسمٌ ظاهر | SEED | 2 | seeds/e07_stop_resp_backfill |
| `event_consumers` | `event_name` | اسمٌ ظاهر | TEST | 1 | risk.access_review.attested |
| `gov_screen_cycle` | `screen_title` | اسمٌ ظاهر | TEST | 1 | اختبار قابلية التجنب للعقود (fin_avoid_t |
| `gov_screen_cycle` | `screen_title` | اسمٌ ظاهر | SAMPLE | 1 | العينات ونتائج الاختبارات (iaf_samples.p |
| `personal_notifications` | `title` | اسمٌ ظاهر | TEST | 1 | تجاوزُ سقفٍ رفع آليًّا للاعتماد الأعلى:  |
| `unit_entries` | `seed_tag` | نصٌّ عام | SEED | 9880 | legacy-seed-20260812 |
| `activity_logs` | `user_agent` | نصٌّ عام | TEST | 7889 | Mozilla/5.0 test |
| `activity_logs` | `new_value` | نصٌّ عام | TEST | 3359 | {"type_code":"LT","name":"LEAKTEST_A_978 |
| `activity_logs` | `old_value` | نصٌّ عام | UAT | 875 | {"evaluation_id":null,"indicator":null," |
| `activity_logs` | `new_value` | نصٌّ عام | UAT | 875 | {"evaluation_id":709,"indicator":"readin |
| `activity_logs` | `request_payload` | نصٌّ عام | TEST | 667 | {"username":"tickets_mgr_test","password |
| `op_containers` | `container_no` | نصٌّ عام | UAT | 663 | CNT-UAT-00663 |
| `v_slot_total_margin` | `container_no` | نصٌّ عام | UAT | 396 | CNT-UAT-00663 |
| `equipment_ownership_registry` | `migrated_from` | نصٌّ عام | UAT | 193 | UAT-2026 |
| `v_supplier_share_units` | `container_no` | نصٌّ عام | UAT | 153 | CNT-UAT-00661 |
| `contract_commitments` | `container_key` | نصٌّ عام | UAT | 134 | CNT-UAT-00661 |
| `v_container_elapsed_target` | `container_no` | نصٌّ عام | UAT | 114 | CNT-UAT-00660 |
| `fin_budget_lines` | `cause` | نصٌّ عام | UAT | 76 | يخضع لمراجعة الربع القادم · UAT-2106 |
| `fin_budget_lines` | `corrective_action` | نصٌّ عام | UAT | 76 | يخضع لمراجعة الربع القادم · UAT-2106 |
| `uat_evidence` | `criterion` | نصٌّ عام | TEST | 65 | W0_restore_tested |
| `uat_runs` | `tag` | نصٌّ عام | UAT | 65 | UAT-2026 |
| `activity_logs` | `session_id` | نصٌّ عام | UAT | 56 | ZtVAanOuQP60UatJFkNhpb1ngM27Uyw4lfs9SgMx |
| `guard_denials` | `attempted_ref` | نصٌّ عام | TEST | 41 | fb10_test#11 |
| `ems_business_events` | `payload` | نصٌّ عام | TEST | 34 | {"sg_code":null,"source":"manual","title |
| `ems_event_outbox` | `payload` | نصٌّ عام | TEST | 34 | {"sg_code":null,"source":"manual","title |
| `screen_view_rows` | `route` | نصٌّ عام | UAT | 29 | Workforce/worker_evaluation.php |
| `screen_view_rows` | `canonical_file` | نصٌّ عام | UAT | 26 | worker_evaluation.php |
| `activity_logs` | `user_agent` | نصٌّ عام | UAT | 24 | EMS-UAT-S5 |
| `gov_profile_items` | `item_ref` | نصٌّ عام | UAT | 23 | Workforce/worker_evaluation.php |
| `approval_workflow_rules` | `action` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026-001 |
| `approval_workflow_rules` | `role_required` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `capacity_outbox` | `event_key` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `capacity_outbox` | `payload_json` | نصٌّ عام | UAT | 20 | {"source": "UAT-2026"} |
| `capacity_outbox` | `idempotency_key` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026-001 |
| `ems_event_deliveries` | `consumer` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `ems_event_deliveries` | `seed_tag` | نصٌّ عام | UAT | 20 | UAT-2026 |
| `ems_event_deliveries` | `consumer_key` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `ems_job_queue` | `payload_json` | نصٌّ عام | UAT | 20 | {"seq": 20, "note": "مولَّدٌ آليًّا", "s |
| `ems_job_queue` | `seed_tag` | نصٌّ عام | UAT | 20 | UAT-2026 |
| `ems_post_idempotency` | `idem_key` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026-002 |
| `ems_processed_events` | `consumer` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026-001 |
| `exec_audit_reports` | `overall_opinion` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `exec_matter_opinions` | `opinion_text` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_approval_chain` | `actor_capacity` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_backflow_log` | `source_stage` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_budget_change_requests` | `dept_module` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_client_statements` | `idempotency_key` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026-001 |
| `fin_cycle_time_metrics` | `dept_module` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_cycle_time_metrics` | `idempotency_key` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026-001 |
| `fin_entitlements` | `idempotency_key` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026-001 |
| `fin_margin_analysis` | `idempotency_key` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026-001 |
| `fin_obl_avoidance` | `special_standard` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_obl_avoidance` | `steps_json` | نصٌّ عام | UAT | 20 | {"source":"UAT-2026","seq":9,"note":"مول |
| `fin_obl_register` | `proration_basis` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_obl_register` | `cost_center` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_obl_register` | `dims_json` | نصٌّ عام | UAT | 20 | {"source":"UAT-2026","seq":9,"note":"مول |
| `fin_obl_schedule` | `proration_basis` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_obl_schedule` | `recognition_rule` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `gov_approval_decisions` | `decided_capacity` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `gov_approval_decisions` | `idempotency_key` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026-001 |
| `gov_export_log` | `actor_capacity` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `gov_export_log` | `entity_key` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `gov_inheritance_denials` | `child_entity` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `gov_inheritance_denials` | `child_field` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `gov_inheritance_denials` | `source_shown` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `iaf_findings` | `auditee_dept` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `iaf_plan` | `basis` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `iaf_quality_reviews` | `summary` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `iaf_quality_reviews` | `reviewed_by` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `iaf_universe` | `owner_dept` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `processed_operations` | `consumer` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026-001 |
| `request_responses` | `origin_link` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `risk_committee_items` | `resolution_ar` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `risk_control_evidence` | `evidence_text` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `risk_export_log` | `actor_capacity` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `risk_export_log` | `view_key` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `risk_incidents` | `root_cause` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `ticket_attachments` | `file_path` | نصٌّ عام | UAT | 20 | storage/uat/ticket_attachments-60.pdf |
| `workspace_navigation_log` | `to_layer` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `workspace_views` | `screen` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026-002 |
| `workspace_views` | `view_key` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026-002 |
| `work_delegations` | `effect_on_open` | نصٌّ عام | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `rec_stage_log` | `to_stage` | نصٌّ عام | TEST | 19 | practical_test |
| `attendance_policies` | `applies_to_json` | نصٌّ عام | UAT | 18 | {"source": "UAT-2026"} |
| `fin_entitlement_gate_log` | `idempotency_key` | نصٌّ عام | UAT | 18 | يخضع لمراجعة الربع القادم · UAT-2026-001 |
| `risk_acceptances` | `compensating_ctl` | نصٌّ عام | UAT | 18 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_cashflow` | `idempotency_key` | نصٌّ عام | UAT | 17 | يخضع لمراجعة الربع القادم · UAT-2026-001 |
| `risk_register` | `dedup_key` | نصٌّ عام | UAT | 17 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `risk_treatments` | `plan_ar` | نصٌّ عام | UAT | 17 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `activity_logs` | `request_payload` | نصٌّ عام | UAT | 16 | {"unit_code":"uatx","unit_name":"وحدة اخ |
| `ems_event_deliveries` | `last_error` | نصٌّ عام | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `ems_state_transitions` | `workflow` | نصٌّ عام | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `ems_state_transitions` | `entity_table` | نصٌّ عام | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `ems_state_transitions` | `action` | نصٌّ عام | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_project_pl` | `allocation_basis` | نصٌّ عام | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_project_pl` | `idempotency_key` | نصٌّ عام | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026-001 |
| `fleet_depreciation_profile_audit` | `old_data` | نصٌّ عام | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fleet_depreciation_profile_audit` | `new_data` | نصٌّ عام | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fleet_equipment_compliance` | `attachment_path` | نصٌّ عام | UAT | 16 | storage/uat/fleet_equipment_compliance-9 |
| `fleet_equipment_protection` | `attachment_path` | نصٌّ عام | UAT | 16 | storage/uat/fleet_equipment_protection-9 |
| `fleet_model` | `manufacturer` | نصٌّ عام | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fleet_model` | `std_capacity_uom` | نصٌّ عام | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `iaf_access_log` | `purpose` | نصٌّ عام | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `mnt_plan_task` | `component` | نصٌّ عام | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `opportunities` | `requirements_json` | نصٌّ عام | UAT | 16 | {"source":"UAT-2026","seq":20,"note":"مو |
| `rec_stage_log` | `from_stage` | نصٌّ عام | TEST | 16 | practical_test |
| `worker_leave_absence` | `rotation_pattern` | نصٌّ عام | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `worker_movement` | `origin` | نصٌّ عام | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `worker_movement` | `origin_city` | نصٌّ عام | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `worker_movement` | `destination_city` | نصٌّ عام | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `worker_movement` | `site_zone` | نصٌّ عام | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `workspace_navigation_log` | `from_layer` | نصٌّ عام | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `achievement_snapshots` | `metrics_json` | نصٌّ عام | UAT | 15 | {"source":"UAT-2026"} |
| `attendance_policies` | `missing_punch_rule` | نصٌّ عام | UAT | 15 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `attendance_policies` | `late_rule` | نصٌّ عام | UAT | 15 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `exec_assignments` | `conflict_detail` | نصٌّ عام | UAT | 15 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fleet_model_service_spec` | `uom` | نصٌّ عام | UAT | 15 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fleet_model_service_spec` | `photo_path` | نصٌّ عام | UAT | 15 | storage/uat/fleet_model_service_spec-9.p |
| `gov_doc_registry` | `family` | نصٌّ عام | TEST | 15 | STDTEST |
| `gov_doc_registry` | `covered_by` | نصٌّ عام | TEST | 15 | u13_stdtest_harness |
| `housing_unit` | `location` | نصٌّ عام | UAT | 15 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `incentive_rules` | `condition_text` | نصٌّ عام | UAT | 15 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `mnt_order_labor` | `role` | نصٌّ عام | UAT | 15 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `clients` | `email` | نصٌّ عام | TEST | 14 | client07@example.test |
| `tickets` | `complaint` | نصٌّ عام | UAT | 14 | محطة UATF6724 |
| `tickets` | `operational_summary` | نصٌّ عام | UAT | 14 | محطة UATF6724 |
| `action_execution_log` | `denied_by_guard` | نصٌّ عام | UAT | 13 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `action_execution_log` | `ip` | نصٌّ عام | UAT | 13 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `employee_final_settlements` | `clearance_doc` | نصٌّ عام | UAT | 13 | storage/uat/clearance-9.pdf |
| `ems_business_events` | `source_ref` | نصٌّ عام | TEST | 13 | INV-TEST-2470 |
| `mnt_lookup` | `extra` | نصٌّ عام | UAT | 13 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `request_routes` | `trigger_key` | نصٌّ عام | UAT | 13 | يخضع لمراجعة الربع القادم · UAT-2026-001 |
| `request_routes` | `rule_text` | نصٌّ عام | UAT | 13 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `request_routes` | `receiver_dept` | نصٌّ عام | UAT | 13 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `request_routes` | `receiver_role` | نصٌّ عام | UAT | 13 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `request_routes` | `fallback_role` | نصٌّ عام | UAT | 13 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `risk_controls` | `evidence_spec` | نصٌّ عام | UAT | 13 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `timesheet_approval_notes` | `note_text` | نصٌّ عام | UAT | 13 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `trs_notifications` | `dedupe_key` | نصٌّ عام | UAT | 13 | يخضع لمراجعة الربع القادم · UAT-2026-001 |
| `fin_entitlements` | `client_ruling` | نصٌّ عام | UAT | 12 | يخضع لمراجعة الربع القادم · UAT- |
| `fin_entitlements` | `supplier_ruling` | نصٌّ عام | UAT | 12 | يخضع لمراجعة الربع القادم · UAT- |
| `fin_entitlements` | `operator_ruling` | نصٌّ عام | UAT | 12 | يخضع لمراجعة الربع القادم · UAT- |
| `fin_entitlements` | `ruleset_version` | نصٌّ عام | UAT | 12 | يخضع لمراجعة الربع القادم · UAT- |
| `fin_internal_allocations` | `basis` | نصٌّ عام | UAT | 12 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `mnt_breakdown` | `reporter_dept` | نصٌّ عام | UAT | 12 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `mnt_breakdown` | `attachment` | نصٌّ عام | UAT | 12 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `risk_appetite` | `domain` | نصٌّ عام | UAT | 12 | يخضع لمراجعة الربع القادم · UAT-2026-001 |
| `risk_appetite` | `appetite_ar` | نصٌّ عام | UAT | 12 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `risk_appetite` | `tolerance_ar` | نصٌّ عام | UAT | 12 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `risk_appetite` | `authority_ar` | نصٌّ عام | UAT | 12 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `risk_appetite` | `changeable_ar` | نصٌّ عام | UAT | 12 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `risk_appetite` | `prev_appetite_ar` | نصٌّ عام | UAT | 12 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `risk_committee` | `cycle_ar` | نصٌّ عام | UAT | 12 | يخضع لمراجعة الربع القادم · UAT- |
| `schema_migrations` | `filename` | نصٌّ عام | SEED | 12 | 2027_06_21_template_union_reseed.php |
| `transfer_attachments` | `file_path` | نصٌّ عام | UAT | 12 | storage/uat/transfer_attachments-43.pdf |
| `audit_logs` | `ip_address` | نصٌّ عام | UAT | 11 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `audit_logs` | `user_agent` | نصٌّ عام | UAT | 11 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `contract_amendments` | `old_value` | نصٌّ عام | UAT | 11 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `contract_amendments` | `new_value` | نصٌّ عام | UAT | 11 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `contract_amendments` | `effect_summary` | نصٌّ عام | UAT | 11 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_entitlement_gate_log` | `client_ruling` | نصٌّ عام | UAT | 11 | يخضع لمراجعة الربع القادم · UAT- |
| `fin_entitlement_gate_log` | `supplier_ruling` | نصٌّ عام | UAT | 11 | يخضع لمراجعة الربع القادم · UAT- |
| `fin_entitlement_gate_log` | `operator_ruling` | نصٌّ عام | UAT | 11 | يخضع لمراجعة الربع القادم · UAT- |
| `mnt_plan` | `scope` | نصٌّ عام | UAT | 11 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `task_templates` | `deliverable` | نصٌّ عام | UAT | 11 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `task_templates` | `evidence_required` | نصٌّ عام | UAT | 11 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `trs_notifications` | `body` | نصٌّ عام | UAT | 11 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `trs_notifications` | `link_url` | نصٌّ عام | UAT | 11 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `activity_logs` | `old_value` | نصٌّ عام | SEED | 10 | {"entry_no":null,"entry_date":null,"proj |
| `activity_logs` | `new_value` | نصٌّ عام | SEED | 10 | {"entry_no":"TMP-6a81bc03a09d91.44687459 |
| `capacity_outbox` | `last_error` | نصٌّ عام | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_accountants` | `specialization` | نصٌّ عام | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_requests` | `statement` | نصٌّ عام | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_requests` | `cost_center` | نصٌّ عام | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `suppliercontractequipments` | `equip_unit` | نصٌّ عام | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `ticket_categories` | `applies_to` | نصٌّ عام | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 · 2 |
| `fin_depreciation` | `basis_json` | نصٌّ عام | UAT | 9 | {"source":"UAT-2026"} |
| `fin_obl_recognition` | `standard` | نصٌّ عام | UAT | 9 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_obl_recognition` | `trigger_text` | نصٌّ عام | UAT | 9 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_obl_recognition` | `layers_text` | نصٌّ عام | UAT | 9 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_obl_recognition` | `guard_text` | نصٌّ عام | UAT | 9 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_role_migration` | `rule_text` | نصٌّ عام | UAT | 9 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `client_contracts` | `second_party` | نصٌّ عام | UAT | 8 | UAT-M00-SIGN-103939 |
| `commercial_risks` | `mitigation` | نصٌّ عام | UAT | 8 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `contracts` | `second_party` | نصٌّ عام | UAT | 8 | UAT-M00-SIGN-103939 |
| `ems_business_events` | `payload` | نصٌّ عام | UAT | 8 | {"contract_id":2316,"first_party":"Equip |
| `ems_event_outbox` | `payload` | نصٌّ عام | UAT | 8 | {"contract_id":2316,"first_party":"Equip |
| `evaluations` | `self_scores_json` | نصٌّ عام | UAT | 8 | {"source":"UAT-2026"} |
| `evaluations` | `mgr_scores_json` | نصٌّ عام | UAT | 8 | {"source":"UAT-2026"} |
| `fin_approval_conflicts` | `rule_text` | نصٌّ عام | UAT | 8 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_quality_kpis` | `threshold` | نصٌّ عام | UAT | 8 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_quality_kpis` | `owner_role` | نصٌّ عام | UAT | 8 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_quality_kpis` | `cadence` | نصٌّ عام | UAT | 8 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_quality_kpis` | `source_sql` | نصٌّ عام | UAT | 8 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `iaf_authorities` | `accept_test` | نصٌّ عام | UAT | 8 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `iaf_charter` | `admin_line` | نصٌّ عام | UAT | 8 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `iaf_charter` | `purpose` | نصٌّ عام | UAT | 8 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `iaf_charter` | `authority` | نصٌّ عام | UAT | 8 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `iaf_charter` | `independence` | نصٌّ عام | UAT | 8 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `iaf_charter` | `not_following` | نصٌّ عام | UAT | 8 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `proc_lookup` | `extra` | نصٌّ عام | UAT | 8 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `proc_receipt_custody` | `receipt_location` | نصٌّ عام | UAT | 8 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `proc_supplier` | `email` | نصٌّ عام | UAT | 8 | uat19@equipation.sd |
| `proc_warehouse` | `location` | نصٌّ عام | UAT | 8 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `quotations` | `payment_terms` | نصٌّ عام | UAT | 8 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `risk_assessments` | `technique` | نصٌّ عام | UAT | 8 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `risk_escalations` | `reason_ar` | نصٌّ عام | UAT | 8 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `transfer_orders` | `route` | نصٌّ عام | UAT | 8 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `transfer_orders` | `analytic_cost_center` | نصٌّ عام | UAT | 8 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `contract_guarantees` | `issuer` | نصٌّ عام | UAT | 7 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `contract_guarantees` | `release_condition` | نصٌّ عام | UAT | 7 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `contract_guarantees` | `source_text` | نصٌّ عام | UAT | 7 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `deduction_types` | `formula_json` | نصٌّ عام | UAT | 7 | {"source": "UAT-2026"} |
| `ems_job_queue` | `batch_failures` | نصٌّ عام | UAT | 7 | {"seq": 20, "note": "مولَّدٌ آليًّا", "s |
| `ems_job_queue` | `last_error` | نصٌّ عام | UAT | 7 | مطابقٌ للمستند المرفق · UAT-2026 |
| `equipment_ownership_registry` | `migrated_from` | نصٌّ عام | SEED | 7 | seed_demo |
| `equipment_ownership_registry` | `migrated_from` | نصٌّ عام | DEMO | 7 | seed_demo |
| `fin_routing_event_map` | `event_key` | نصٌّ عام | UAT | 7 | يخضع لمراجعة الربع القادم · UAT-2026-001 |
| `fin_routing_event_map` | `source_module` | نصٌّ عام | UAT | 7 | يخضع لمراجعة الربع القادم · UAT-2026-001 |
| `gov_doc_variance` | `declared_where` | نصٌّ عام | UAT | 7 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `gov_doc_variance` | `declared_value` | نصٌّ عام | UAT | 7 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `gov_doc_variance` | `registered_where` | نصٌّ عام | UAT | 7 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `gov_doc_variance` | `registered_value` | نصٌّ عام | UAT | 7 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `gov_doc_variance` | `resolved_value` | نصٌّ عام | UAT | 7 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `gov_doc_variance` | `basis` | نصٌّ عام | UAT | 7 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `gov_doc_variance` | `impact` | نصٌّ عام | UAT | 7 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `gov_doc_variance` | `decided_by` | نصٌّ عام | UAT | 7 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `gov_doc_variance` | `owner_action` | نصٌّ عام | UAT | 7 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `mnt_order` | `pm_cycle_key` | نصٌّ عام | UAT | 7 | يخضع لمراجعة الربع القادم · UAT-2026-001 |
| `proc_request` | `requesting_dept` | نصٌّ عام | UAT | 7 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `transfer_permits` | `authority` | نصٌّ عام | UAT | 7 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `transfer_permits` | `document_path` | نصٌّ عام | UAT | 7 | storage/uat/transfer_permits-20.pdf |
| `activities` | `outcome` | نصٌّ عام | UAT | 6 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_financial_events` | `source_ref` | نصٌّ عام | TEST | 6 | INV-TEST-2470 |
| `gov_field_class` | `field_key` | نصٌّ عام | TEST | 6 | accept_test |
| `mnt_order` | `workshop` | نصٌّ عام | UAT | 6 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `mnt_order` | `diagnosis` | نصٌّ عام | UAT | 6 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `mnt_order` | `actions_taken` | نصٌّ عام | UAT | 6 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `opportunities` | `state_region` | نصٌّ عام | UAT | 6 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `opportunities` | `capacity_summary` | نصٌّ عام | UAT | 6 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `persons` | `contact_json` | نصٌّ عام | UAT | 6 | {"seq": 21, "note": "مولَّدٌ آليًّا", "s |
| `persons` | `docs_json` | نصٌّ عام | UAT | 6 | {"seq": 21, "note": "مولَّدٌ آليًّا", "s |
| `schema_migrations` | `filename` | نصٌّ عام | DEMO | 6 | 2026_07_18_tickets_sla_backfill_demo.sql |
| `fin_financial_events` | `payload` | نصٌّ عام | TEST | 5 | {"qty": 8, "unit": "hour", "record_no":  |
| `approval_requests` | `action` | نصٌّ عام | UAT | 4 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `approval_requests` | `payload` | نصٌّ عام | UAT | 4 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `approval_steps` | `role_required` | نصٌّ عام | UAT | 4 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `contract_payment_schedule` | `treatment_basis` | نصٌّ عام | UAT | 4 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `contract_payment_schedule` | `due_condition` | نصٌّ عام | UAT | 4 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `fin_signal_rules` | `rule_expr` | نصٌّ عام | UAT | 4 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `fin_signal_rules` | `destination_ar` | نصٌّ عام | UAT | 4 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `job_titles` | `duties_json` | نصٌّ عام | UAT | 4 | {"source": "UAT-2026"} |
| `job_titles` | `allowed_scopes_json` | نصٌّ عام | UAT | 4 | {"source": "UAT-2026"} |
| `job_titles` | `prohibitions_json` | نصٌّ عام | UAT | 4 | {"source": "UAT-2026"} |
| `job_titles` | `qualifications_json` | نصٌّ عام | UAT | 4 | {"source": "UAT-2026"} |
| `nav_items` | `route` | نصٌّ عام | UAT | 4 | Workforce/worker_evaluation.php |
| `org_units` | `owner_doc` | نصٌّ عام | UAT | 4 | storage/uat/org_units-19.pdf |
| `readiness_lines` | `required` | نصٌّ عام | UAT | 4 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `readiness_lines` | `available` | نصٌّ عام | UAT | 4 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `uat_evidence` | `evidence_ref` | نصٌّ عام | UAT | 4 | UAT-DEF-003 · db_tools.php:83 |
| `users` | `email` | نصٌّ عام | TEST | 4 | test.chief.acc@equipation.sd |
| `gov_screen_cycle` | `screen_file` | نصٌّ عام | UAT | 3 | worker_evaluation.php |
| `mnt_breakdown` | `severity` | نصٌّ عام | UAT | 3 | مطابقٌ للمستند المرفق · UAT-20 |
| `nav09_action_map` | `canonical_file` | نصٌّ عام | UAT | 3 | worker_evaluation.php |
| `nav09_action_map` | `guard_evidence` | نصٌّ عام | UAT | 3 | حارسُ صلاحيةٍ محلولٌ في Workforce/worker |
| `nav09_action_map` | `idempotency_evidence` | نصٌّ عام | UAT | 3 | لا مفتاحَ عطالةٍ ولا نشرَ حقائقَ ولا فري |
| `nav09_file_map` | `canonical_file` | نصٌّ عام | UAT | 3 | worker_evaluation.php |
| `nav09_file_map` | `real_path` | نصٌّ عام | UAT | 3 | Workforce/worker_evaluation.php |
| `nav_canonical_current` | `route` | نصٌّ عام | UAT | 3 | workforce/worker_evaluation.php |
| `org_structure_versions` | `snapshot_json` | نصٌّ عام | UAT | 3 | {"source":"UAT-2026","seq":20,"note":"مو |
| `schema_migrations` | `filename` | نصٌّ عام | UAT | 3 | 2027_07_05_uat_note_pollution.php |
| `screen_about` | `screen_path` | نصٌّ عام | UAT | 3 | Workforce/worker_evaluation.php |
| `activity_logs` | `session_id` | نصٌّ عام | TEST | 2 | W4T,Kto1ex35LHD8b6,0RmkMdc1Rjv66sSTestS, |
| `activity_logs` | `session_id` | نصٌّ عام | SEED | 2 | jJFb4ZIOp-I7GPPqO7JS6zY7W6QMXyFbD9SEeDCV |
| `activity_logs` | `session_id` | نصٌّ عام | DEMO | 2 | qyj88pwCTDeMOuUyK5U8U3fZ1si1fHRGrZgh4zem |
| `approval_signatures` | `step` | نصٌّ عام | UAT | 2 | مراجَعٌ من المالية ومطابق · UAT-2026-002 |
| `approval_signatures` | `ip` | نصٌّ عام | UAT | 2 | مراجَعٌ من المالية ومطابق · UAT-2026 |
| `employees` | `health_issues` | نصٌّ عام | UAT | 2 | Velit consequatur V |
| `equipment_documents` | `issuer` | نصٌّ عام | UAT | 2 | Vitae consequat Lab |
| `fin_contract_types` | `accounts_csv` | نصٌّ عام | UAT | 2 | مراجَعٌ من المالية ومطابق · UAT-2026 |
| `fin_contract_types` | `cost_nature` | نصٌّ عام | UAT | 2 | مراجَعٌ من المالية ومطابق · UAT-2026 |
| `fin_contract_types` | `accounting_rule` | نصٌّ عام | UAT | 2 | مراجَعٌ من المالية ومطابق · UAT-2026 |
| `fin_routing_log` | `trigger_key` | نصٌّ عام | UAT | 2 | مراجَعٌ من المالية ومطابق · UAT-2026 |
| `fin_routing_log` | `source_dept` | نصٌّ عام | UAT | 2 | مراجَعٌ من المالية ومطابق · UAT-2026 |
| `nav09_action_map` | `writes_text` | نصٌّ عام | UAT | 2 | worker_evaluations |
| `nav_canonical` | `route` | نصٌّ عام | UAT | 2 | Workforce/worker_evaluation.php |
| `nav_canonical` | `canonical_en` | نصٌّ عام | UAT | 2 | Worker Evaluation |
| `proc_item` | `material_nature` | نصٌّ عام | UAT | 2 | مطابقٌ للمستند المرفق · UAT-20 |
| `proc_order` | `fin_approval_ref` | نصٌّ عام | TEST | 2 | FIN-APR-TEST-02 |
| `proc_receipt_custody` | `expected_destination` | نصٌّ عام | UAT | 2 | مطابقٌ للمستند المرفق · UAT-20 |
| `products` | `default_uom` | نصٌّ عام | UAT | 2 | مطابقٌ للمستند المرفق · UAT-20 |
| `transfer_events` | `body` | نصٌّ عام | UAT | 2 | مراجَعٌ من المالية ومطابق · UAT-2026 |
| `transfer_events` | `body` | نصٌّ عام | DEMO | 2 | إنشاء أمر ترحيل (EX24-DEMOB-2026-07-0006 |
| `transfer_events` | `old_value` | نصٌّ عام | UAT | 2 | مراجَعٌ من المالية ومطابق · UAT-2026 |
| `transfer_events` | `new_value` | نصٌّ عام | UAT | 2 | مراجَعٌ من المالية ومطابق · UAT-2026 |
| `transfer_orders` | `order_no` | نصٌّ عام | DEMO | 2 | EX24-DEMOB-2026-07-0006 |
| `uat_evidence` | `actual` | نصٌّ عام | UAT | 2 | فشل — ems_backup_20260802_124859.sql ينق |
| `activity_logs` | `url` | نصٌّ عام | UAT | 1 | http://localhost/ems/Workforce/worker_ev |
| `employees` | `license_issuer` | نصٌّ عام | UAT | 1 | Vitae consequat Lab |
| `employees` | `certificates` | نصٌّ عام | UAT | 1 | Cumque consequatur |
| `employees` | `owner_supervisor` | نصٌّ عام | UAT | 1 | Consequatur elit n |
| `employees` | `address` | نصٌّ عام | UAT | 1 | Dolor et consequatur |
| `employees` | `previous_employer` | نصٌّ عام | UAT | 1 | Ipsum consequatur |
| `ems_business_events` | `event_key` | نصٌّ عام | TEST | 1 | risk.access_review.attested |
| `ems_business_events` | `idempotency_key` | نصٌّ عام | TEST | 1 | rsk:risk.access_review.attested:897:2026 |
| `ems_event_deliveries` | `result_ref` | نصٌّ عام | TEST | 1 | watch:clear/3/risk.access_review.atteste |
| `equipments` | `license_authority` | نصٌّ عام | UAT | 1 | Consequatur non recu |
| `equipment_operators` | `license_issuer` | نصٌّ عام | UAT | 1 | Vitae consequat Lab |
| `fin_approval_types` | `question` | نصٌّ عام | UAT | 1 | أُثبت من واقع التشغيل الميداني · UAT-202 |
| `fin_approval_types` | `rule_text` | نصٌّ عام | UAT | 1 | أُثبت من واقع التشغيل الميداني · UAT-202 |
| `fin_approval_types` | `allowed_roles` | نصٌّ عام | UAT | 1 | أُثبت من واقع التشغيل الميداني · UAT-202 |
| `fin_backflow_notices` | `fires_when` | نصٌّ عام | UAT | 1 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_backflow_notices` | `destination` | نصٌّ عام | UAT | 1 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_backflow_notices` | `rule_text` | نصٌّ عام | UAT | 1 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_backflow_rules` | `rule_text` | نصٌّ عام | UAT | 1 | مُدرجٌ ضمن الدورة الشهرية · UAT-2026 |
| `fin_backflow_rules` | `accept_test` | نصٌّ عام | UAT | 1 | مُدرجٌ ضمن الدورة الشهرية · UAT-2026 |
| `fin_chart_of_accounts` | `name_en` | نصٌّ عام | DEMO | 1 | Transport, Mobilization & Demobilization |
| `fin_obl_alerts` | `fires_when` | نصٌّ عام | UAT | 1 | أُثبت من واقع التشغيل الميداني · UAT-202 |
| `fin_obl_alerts` | `destination` | نصٌّ عام | UAT | 1 | أُثبت من واقع التشغيل الميداني · UAT-202 |
| `fin_obl_alerts` | `risk_if_ignored` | نصٌّ عام | UAT | 1 | أُثبت من واقع التشغيل الميداني · UAT-202 |
| `fin_obl_avoidance_tests` | `question` | نصٌّ عام | UAT | 1 | بناءً على طلب الموقع · UAT-2026 |
| `fin_obl_avoidance_tests` | `outcome` | نصٌّ عام | UAT | 1 | بناءً على طلب الموقع · UAT-2026 |
| `fin_obl_layers` | `birth` | نصٌّ عام | UAT | 1 | مراجَعٌ من المالية ومطابق · UAT-2026 |
| `fin_obl_layers` | `rule_text` | نصٌّ عام | UAT | 1 | مراجَعٌ من المالية ومطابق · UAT-2026 |
| `fin_obl_layers` | `sides` | نصٌّ عام | UAT | 1 | مراجَعٌ من المالية ومطابق · UAT-2026 |
| `fin_obl_types` | `born_when` | نصٌّ عام | UAT | 1 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `fin_obl_types` | `accounts` | نصٌّ عام | UAT | 1 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `fin_obl_types` | `formula` | نصٌّ عام | UAT | 1 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `fin_obl_types` | `term_rule` | نصٌّ عام | UAT | 1 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `fin_requests` | `source_ref` | نصٌّ عام | UAT | 1 | Velit elit et exercitation quis sed ipsa |
| `gov_data_classes` | `meaning` | نصٌّ عام | UAT | 1 | أُثبت من واقع التشغيل الميداني · UAT-202 |
| `gov_data_classes` | `examples` | نصٌّ عام | UAT | 1 | أُثبت من واقع التشغيل الميداني · UAT-202 |
| `gov_data_classes` | `create_roles` | نصٌّ عام | UAT | 1 | أُثبت من واقع التشغيل الميداني · UAT-202 |
| `gov_data_classes` | `edit_roles` | نصٌّ عام | UAT | 1 | أُثبت من واقع التشغيل الميداني · UAT-202 |
| `gov_data_classes` | `read_roles` | نصٌّ عام | UAT | 1 | أُثبت من واقع التشغيل الميداني · UAT-202 |
| `gov_orphan_links` | `route` | نصٌّ عام | UAT | 1 | Suppliers/supplier_evaluation.php |
| `gov_screen_cycle` | `screen_file` | نصٌّ عام | TEST | 1 | fin_avoid_test.php |
| `gov_screen_cycle` | `screen_file` | نصٌّ عام | SAMPLE | 1 | iaf_samples.php |
| `gov_stage_outputs` | `output_doc` | نصٌّ عام | UAT | 1 | final_settlement · payables · worker_eva |
| `nav_redirects` | `old_route` | نصٌّ عام | UAT | 1 | Suppliers/supplier_evaluation.php |
| `proc_landed_cost` | `doc_no` | نصٌّ عام | TEST | 1 | BL-TEST-001 |
| `proc_order` | `invoice_no` | نصٌّ عام | TEST | 1 | INV-TEST-2470 |
| `risk_units` | `linked_depts` | نصٌّ عام | UAT | 1 | مراجَعٌ من المالية ومطابق · UAT-2026 |
| `risk_units` | `output_ar` | نصٌّ عام | UAT | 1 | مراجَعٌ من المالية ومطابق · UAT-2026 |
| `schema_migrations` | `filename` | نصٌّ عام | TEST | 1 | 2026_07_07_drop_stray_test_probe.sql |
| `timesheet_failure_hours` | `failure_detail` | نصٌّ عام | UAT | 1 | مراجَعٌ من المالية ومطابق · UAT-2026 |
| `transfer_delivery_docs` | `doc_ref` | نصٌّ عام | TEST | 1 | DOC-TRSCOST-TEST-FAMILY-NB |
| `uat_evidence` | `actual` | نصٌّ عام | TEST | 1 | استُعيدت في equipation_restore_test: 358 |
| `uat_runs` | `executor` | نصٌّ عام | UAT | 1 | Claude Code · منسّق UAT |
| `users` | `password` | نصٌّ عام | UAT | 1 | $2y$10$CiSs9LuD13yUBWcI4YkTU.ppKtVSwijG8 |
| `timesheet` | `time_notes` | حقلٌ نصيٌّ مشروع | UAT | 48379 | UAT-2026 [ساعات من الوردية — ق-15] |
| `operations` | `reason` | حقلٌ نصيٌّ مشروع | UAT | 450 | UAT-SEAT·مقاولات النقل·79·1·T9 |
| `permission_audit_events` | `reason` | حقلٌ نصيٌّ مشروع | UAT | 371 | نقل ملكية UATB9040 |
| `contract_notes` | `note` | حقلٌ نصيٌّ مشروع | UAT | 204 | UAT-KEY·مقاولات النقل·79·1 |
| `fin_budget_lines` | `note` | حقلٌ نصيٌّ مشروع | UAT | 76 | يخضع لمراجعة الربع القادم · UAT-2106 |
| `fin_approval_chain` | `note` | حقلٌ نصيٌّ مشروع | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_backflow_log` | `close_reason` | حقلٌ نصيٌّ مشروع | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_budget_change_requests` | `decided_reason` | حقلٌ نصيٌّ مشروع | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_obl_schedule` | `close_reason` | حقلٌ نصيٌّ مشروع | UAT | 20 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `client_contract_lines` | `description` | حقلٌ نصيٌّ مشروع | UAT | 19 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `meter_readings` | `reset_reason` | حقلٌ نصيٌّ مشروع | UAT | 19 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `meter_readings` | `note` | حقلٌ نصيٌّ مشروع | UAT | 19 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `container_swaps` | `reason` | حقلٌ نصيٌّ مشروع | UAT | 18 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `ticket_transfers` | `reason` | حقلٌ نصيٌّ مشروع | UAT | 18 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `commercial_risks` | `notes` | حقلٌ نصيٌّ مشروع | UAT | 17 | وفق المعتمد في محضر الإدارة · UAT-2032 |
| `supplier_contract_notes` | `note` | حقلٌ نصيٌّ مشروع | UAT | 17 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `client_contract_lines` | `note` | حقلٌ نصيٌّ مشروع | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_budgets` | `return_reason` | حقلٌ نصيٌّ مشروع | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_budgets` | `note` | حقلٌ نصيٌّ مشروع | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_fx_differences` | `note` | حقلٌ نصيٌّ مشروع | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_maint_provision_rules` | `note` | حقلٌ نصيٌّ مشروع | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_request_lines` | `note` | حقلٌ نصيٌّ مشروع | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fleet_depreciation_profile_audit` | `note` | حقلٌ نصيٌّ مشروع | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fleet_equipment_protection` | `description` | حقلٌ نصيٌّ مشروع | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `positions` | `description` | حقلٌ نصيٌّ مشروع | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `transfer_tariffs` | `note` | حقلٌ نصيٌّ مشروع | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `worker_leave_absence` | `reason` | حقلٌ نصيٌّ مشروع | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `worker_leave_absence` | `notes` | حقلٌ نصيٌّ مشروع | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `worker_movement` | `notes` | حقلٌ نصيٌّ مشروع | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `workforce_requirement` | `notes` | حقلٌ نصيٌّ مشروع | UAT | 16 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `contract_monthly_plan` | `note` | حقلٌ نصيٌّ مشروع | UAT | 15 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `contract_penalty_assessments` | `waive_reason` | حقلٌ نصيٌّ مشروع | UAT | 15 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `contract_penalty_assessments` | `note` | حقلٌ نصيٌّ مشروع | UAT | 15 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `contract_penalty_rules` | `note` | حقلٌ نصيٌّ مشروع | UAT | 15 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `exec_assignments` | `decision_reason` | حقلٌ نصيٌّ مشروع | UAT | 15 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `exec_assignments` | `revoke_reason` | حقلٌ نصيٌّ مشروع | UAT | 15 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_operator_pay` | `note` | حقلٌ نصيٌّ مشروع | UAT | 15 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_request_documents` | `note` | حقلٌ نصيٌّ مشروع | UAT | 15 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fleet_model_service_spec` | `note` | حقلٌ نصيٌّ مشروع | UAT | 15 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `housing_unit` | `notes` | حقلٌ نصيٌّ مشروع | UAT | 15 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `tenders` | `notes` | حقلٌ نصيٌّ مشروع | UAT | 15 | وفق المعتمد في محضر الإدارة · UAT-2033 |
| `ticket_transfers` | `notes` | حقلٌ نصيٌّ مشروع | UAT | 15 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `activities` | `notes` | حقلٌ نصيٌّ مشروع | UAT | 14 | يخضع لمراجعة الربع القادم · UAT-2031 |
| `products` | `description` | حقلٌ نصيٌّ مشروع | UAT | 14 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `ems_state_transitions` | `note` | حقلٌ نصيٌّ مشروع | UAT | 13 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `opportunities` | `lost_reason` | حقلٌ نصيٌّ مشروع | UAT | 13 | يخضع لمراجعة الربع القادم · UAT-2031 |
| `opportunities` | `win_reason` | حقلٌ نصيٌّ مشروع | UAT | 13 | يخضع لمراجعة الربع القادم · UAT-2031 |
| `contract_events` | `description` | حقلٌ نصيٌّ مشروع | UAT | 12 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_funding_facilities` | `note` | حقلٌ نصيٌّ مشروع | UAT | 12 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `mnt_breakdown` | `description` | حقلٌ نصيٌّ مشروع | UAT | 12 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `opportunities` | `notes` | حقلٌ نصيٌّ مشروع | UAT | 12 | يخضع لمراجعة الربع القادم · UAT-2032 |
| `audit_logs` | `description` | حقلٌ نصيٌّ مشروع | UAT | 11 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `contract_amendments` | `reason` | حقلٌ نصيٌّ مشروع | UAT | 11 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `fin_requests` | `justification` | حقلٌ نصيٌّ مشروع | UAT | 11 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `quotations` | `notes` | حقلٌ نصيٌّ مشروع | UAT | 11 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `fin_requests` | `notes` | حقلٌ نصيٌّ مشروع | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `tenders` | `result_reason` | حقلٌ نصيٌّ مشروع | UAT | 10 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `transfer_requests` | `reason` | حقلٌ نصيٌّ مشروع | UAT | 10 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `employee_roles` | `description` | حقلٌ نصيٌّ مشروع | UAT | 9 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `evaluations` | `mgr_comment` | حقلٌ نصيٌّ مشروع | UAT | 8 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `proc_custody` | `notes` | حقلٌ نصيٌّ مشروع | UAT | 8 | وفق المعتمد في محضر الإدارة · UAT-2026 [ |
| `proc_issue` | `notes` | حقلٌ نصيٌّ مشروع | UAT | 8 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `proc_item` | `notes` | حقلٌ نصيٌّ مشروع | UAT | 8 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `proc_order` | `notes` | حقلٌ نصيٌّ مشروع | UAT | 8 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `proc_receipt_custody` | `notes` | حقلٌ نصيٌّ مشروع | UAT | 8 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `proc_warehouse` | `notes` | حقلٌ نصيٌّ مشروع | UAT | 8 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `transfer_orders` | `notes` | حقلٌ نصيٌّ مشروع | UAT | 8 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `worker_evaluation` | `notes` | حقلٌ نصيٌّ مشروع | UAT | 8 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `contract_guarantees` | `state_reason` | حقلٌ نصيٌّ مشروع | UAT | 7 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `contract_guarantees` | `note` | حقلٌ نصيٌّ مشروع | UAT | 7 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `fin_routing_event_map` | `note` | حقلٌ نصيٌّ مشروع | UAT | 7 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `proc_request` | `notes` | حقلٌ نصيٌّ مشروع | UAT | 7 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `admin_audit_log` | `description` | حقلٌ نصيٌّ مشروع | TEST | 6 | حذف حساب مدير أعلى: smk_e1b@test.local |
| `pricelists` | `notes` | حقلٌ نصيٌّ مشروع | UAT | 6 | وفق المعتمد في محضر الإدارة · UAT-2034 |
| `fin_financial_events` | `notes` | حقلٌ نصيٌّ مشروع | TEST | 5 | مروحة أثر الوحدة FANOUT_TEST_528_9ad67e |
| `approval_requests` | `rejection_reason` | حقلٌ نصيٌّ مشروع | UAT | 4 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `approval_steps` | `note` | حقلٌ نصيٌّ مشروع | UAT | 4 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `contract_payment_schedule` | `note` | حقلٌ نصيٌّ مشروع | UAT | 4 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `job_titles` | `description` | حقلٌ نصيٌّ مشروع | UAT | 4 | وفق المعتمد في محضر الإدارة · UAT-2026 [ |
| `proc_request_line` | `note` | حقلٌ نصيٌّ مشروع | UAT | 4 | يخضع لمراجعة الربع القادم · UAT-2026 |
| `contract_penalty_assessments` | `note` | حقلٌ نصيٌّ مشروع | SEED | 2 | [CON02-SEED-20260728] غرامةُ عجزٍ 2.5٪ ب |
| `contract_penalty_rules` | `note` | حقلٌ نصيٌّ مشروع | SEED | 2 | [CON02-SEED-20260728] غرامةُ عجزٍ 2.5٪ ب |
| `fin_routing_log` | `manual_reason` | حقلٌ نصيٌّ مشروع | UAT | 2 | مراجَعٌ من المالية ومطابق · UAT-2026 |
| `gov_screen_cycle` | `inputs_note` | حقلٌ نصيٌّ مشروع | UAT | 2 | worker_evaluations |
| `claims` | `notes` | حقلٌ نصيٌّ مشروع | SEED | 1 |  [CON02-SEED-20260728] |
| `contract_commitments` | `note` | حقلٌ نصيٌّ مشروع | SEED | 1 | [CON02-SEED-20260728] بيانةُ تجربة — لا  |
| `equipments` | `general_notes` | حقلٌ نصيٌّ مشروع | UAT | 1 | Consequatur id prov |
| `fin_approvals` | `note` | حقلٌ نصيٌّ مشروع | TEST | 1 | اعتماد تطابق TEST-UR-1 وتوليد التوأمين |
| `gov_screen_cycle` | `inputs_note` | حقلٌ نصيٌّ مشروع | TEST | 1 | contract_avoid_tests · — |
| `payroll_settings` | `note` | حقلٌ نصيٌّ مشروع | UAT | 1 | وفق المعتمد في محضر الإدارة · UAT-2026 |
| `timesheet` | `general_notes` | حقلٌ نصيٌّ مشروع | SEED | 1 | [CON02-SEED-20260728] واقعةٌ تجريبيةٌ حي |

**الحصيلة:** 560 موضعًا في 597 جدولًا · أعمدةٌ مفحوصة: 4087

◆ التصنيفُ الثلاثيُّ (إنتاجٌ مشروع · تلوثُ اختبار · مشتبَه) **لم يُجرَ آليًّا** — والافتراضُ `UNCLASSIFIED`.
