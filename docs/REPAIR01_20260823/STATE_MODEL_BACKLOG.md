# آلاتُ الحالةِ — دفترُ التأليفِ المتبقّي · `BLOCKED_OWNER`

> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/govui_state_backlog.php` @ `597bc5ac`
> **والمقيسُ من السجلِّ المثبَّت** لا من تقرير — `repair01_screen_registry.state_model_ref`.

## ⓪ الكسرُ لا النسبة

| المفردة | القيمة |
|---|---|
| أسطحُ المعاملاتِ الحقيقيّة (المقام) | **392** |
| منها مربوطٌ بآلةِ حالتِه بشاهدٍ | **271** |
| **متبقٍّ بلا آلةٍ مؤلَّفة** | **121** على **112** كيانًا |

⚠ **والمقامُ ارتفع بالبناءِ لا بالتراجع**: إغلاقُ جبهةِ الحقولِ أعطى كلَّ سطحٍ
حبّةً مقيسة، فدخل المقامَ أسطحٌ لم تكن تُقاس (‏210 ⇒ 392). **فالكسرُ يُعلَن**
ولا يُقرأ ارتفاعُ المقامِ تراجعًا.

## ① ما يلزم لكلِّ آلة — وستٌّ من ثمانٍ قرارُ مالك

| البند | من يقرّره |
|---|---|
| الحالاتُ وأسماؤها | الورقةُ الحاكمةُ إن سمّتها · وإلّا **المالك** |
| الانتقالاتُ المسموحة | **المالك** |
| الانتقالاتُ الممنوعةُ ومُسبِّباتُها | **المالك** |
| مالكُ كلِّ انتقال (فصلُ واجبات) | **المالك** |
| الشرطُ المسبقُ لكلِّ انتقال | **المالك** |
| المستندُ الرسميُّ للانتقال | **المالك** |
| بوّابةُ الاعتماد | محرّكُ الاعتمادِ المركزيُّ (قائم) |
| الأثرُ والتدقيق | `EventPublisher` (قائم) |

⛔ **ولا يُلفَّق مرجع**: §٥ المحظور ④ — والمرجعُ الأجوفُ يُقرأ خُضرةً وهو فراغ.

## ② الكياناتُ المتبقّيةُ بأسمائها وأسطحِها

| # | الكيان | أسطحُه | المساحة | المسارات |
|---|---|---|---|---|
| 1 | `activities` | 2 | DEP-01 | `Clients/activities.php` · `contracts/sales_activities.php` |
| 2 | `commercial_risks` | 1 | EX-CEO | `Clients/commercial_risks.php` |
| 3 | `contract_amendments` | 1 | DEP-01 | `Clients/contract_amendments.php` |
| 4 | `contract_commitments` | 2 | DEP-01 | `Clients/contract_commitments.php` · `contracts/contract_coverage_cycles.php` |
| 5 | `contract_events` | 1 | DEP-01 | `Clients/contract_events.php` |
| 6 | `contract_hour_policies` | 1 | DEP-05 | `Finance/operator_pay_policies_fin.php` |
| 7 | `contract_monthly_plan` | 1 | DEP-01 | `contracts/contract_baseline_targets.php` |
| 8 | `dvp_vp_pending_actions` | 1 | EX-DVP | `Portal/vp_pending_actions.php` |
| 9 | `employee_final_settlements` | 1 | DEP-07 | `Workforce/final_settlement.php` |
| 10 | `employee_roles` | 1 | DEP-07 | `Employees/employee_roles.php` |
| 11 | `equipment_documents` | 2 | DEP-04 | `Equipments/equipment_documents.php` · `fleet/asset_documents.php` |
| 12 | `equipment_operators` | 2 | DEP-07 · DEP-13 | `Employees/equipment_operators.php` · `workforce/wf_person_register.php` |
| 13 | `equipments_types` | 2 | DEP-04 | `Equipments/equipments_types.php` · `fleet/classification_directory.php` |
| 14 | `exec_action_followup` | 1 | EX-DVP | `Portal/vp_actions_followup.php` |
| 15 | `exec_assurance_report` | 1 | EX-CEO | `Portal/ceo_assurance_box.php` |
| 16 | `exec_doc_review_note` | 1 | EX-CEO | `portal/exec_doc_review_notes.php` |
| 17 | `exec_meeting` | 1 | EX-CEO | `portal/exec_leadership_meetings.php` |
| 18 | `exec_org_project` | 2 | EX-CEO · EX-DVP | `Portal/exec_org_projects.php` · `Portal/vp_departments.php` |
| 19 | `exec_request_queue` | 2 | EX-CEO · EX-DVP | `Portal/ceo_unified_queue.php` · `Portal/vp_approval_inbox.php` |
| 20 | `failure_codes` | 2 | DEP-04 · DEP-14 | `Equipments/manage_failure_codes.php` · `maintenance/fault_tree.php` |
| 21 | `fin_assets` | 1 | DEP-05 | `Finance/assets_fin.php` |
| 22 | `fin_budget_lines` | 1 | DEP-05 | `Finance/variance_monitor_fin.php` |
| 23 | `fin_budgets` | 1 | DEP-05 | `Finance/budget_form_fin.php` |
| 24 | `fin_cash_forecasts` | 1 | DEP-06 | `Finance/cash_forecast_fin.php` |
| 25 | `fin_chart_of_accounts` | 1 | DEP-06 | `Finance/accounts_fin.php` |
| 26 | `fin_cost_centers` | 1 | DEP-05 | `Finance/management_accounting_fin.php` |
| 27 | `fin_cost_records` | 1 | DEP-05 | `Finance/cost_report_fin.php` |
| 28 | `fin_maint_provision_rules` | 1 | DEP-05 | `Finance/periodic_events_fin.php` |
| 29 | `fin_operator_pay` | 1 | DEP-05 | `Finance/operator_pay_fin.php` |
| 30 | `fleet_depreciation_profile` | 2 | DEP-04 | `Equipments/fleet_depreciation_profiles.php` · `fleet/asset_life_reference.php` |
| 31 | `fleet_model` | 1 | DEP-04 | `Equipments/fleet_models.php` |
| 32 | `fleet_reservations` | 1 | DEP-11 | `Operations/fleet_calendar.php` |
| 33 | `flt_asset_full_history` | 1 | DEP-04 | `Fleet/asset_full_history.php` |
| 34 | `flt_code_reconciliation` | 1 | DEP-04 | `fleet/code_reconciliation.php` |
| 35 | `flt_daily_operating_log` | 1 | DEP-04 | `fleet/daily_operating_log.php` |
| 36 | `flt_dashboard_kpi` | 1 | DEP-04 | `fleet/fleet_board.php` |
| 37 | `flt_exception_register` | 1 | DEP-04 | `fleet/exception_register.php` |
| 38 | `flt_external_auditor_note` | 1 | DEP-04 | `fleet/external_auditor_notes.php` |
| 39 | `flt_financed_asset_recon` | 1 | DEP-04 | `fleet/financed_asset_recon.php` |
| 40 | `flt_inspection_card` | 1 | DEP-04 | `fleet/inspection_card.php` |
| 41 | `flt_management_decision` | 1 | DEP-04 | `fleet/management_decisions.php` |
| 42 | `flt_numbering_bridge` | 1 | DEP-04 | `fleet/numbering_bridge.php` |
| 43 | `flt_open_point` | 1 | DEP-04 | `fleet/open_points.php` |
| 44 | `flt_owner_reconciliation` | 1 | DEP-04 | `fleet/owner_reconciliation.php` |
| 45 | `flt_power_source` | 1 | DEP-04 | `fleet/power_sources.php` |
| 46 | `flt_register_operation_recon` | 1 | DEP-04 | `fleet/register_operation_recon.php` |
| 47 | `flt_source_conflict` | 1 | DEP-04 | `fleet/source_conflicts.php` |
| 48 | `flt_technical_state` | 1 | DEP-04 | `fleet/technical_state.php` |
| 49 | `flt_use_right_range` | 1 | DEP-04 | `fleet/use_right_ranges.php` |
| 50 | `hr_employees` | 1 | DEP-07 | `Employees/employees.php` |
| 51 | `hr_headcount_plan` | 1 | DEP-07 | `employees/hr_headcount_plan.php` |
| 52 | `hr_worker_leave_absence` | 1 | DEP-13 | `Workforce/worker_leave_absence.php` |
| 53 | `job_titles` | 1 | DEP-07 | `Employees/job_titles.php` |
| 54 | `mnt_diagnosis_request` | 1 | DEP-14 | `maintenance/diagnosis_requests.php` |
| 55 | `mnt_downtime_segment` | 1 | DEP-14 | `maintenance/downtime_segments.php` |
| 56 | `operations` | 1 | DEP-11 | `movement/movement_operations.php` |
| 57 | `ops_resource_move_order` | 1 | DEP-11 | `operations/ops_resource_move_orders.php` |
| 58 | `ops_seasonal_factor` | 1 | DEP-11 | `operations/ops_seasonal_factors.php` |
| 59 | `ops_worker_worklog` | 1 | DEP-13 | `Workforce/worker_worklog.php` |
| 60 | `portal_elements` | 1 | PLTF | `Portal/portal_elements.php` |
| 61 | `pricelists` | 1 | DEP-01 | `Clients/pricelists.php` |
| 62 | `proc_issue` | 1 | DEP-16 | `Procurement/issue_proc.php` |
| 63 | `proc_orderpoint` | 1 | DEP-16 | `Procurement/reordering_proc.php` |
| 64 | `proc_receipt_custody` | 1 | DEP-16 | `Procurement/receipt_custody_proc.php` |
| 65 | `products` | 1 | DEP-01 | `Clients/products.php` |
| 66 | `rate_books` | 1 | DEP-01 | `Clients/rate_books.php` |
| 67 | `sal_clients` | 1 | DEP-01 | `Clients/clients.php` |
| 68 | `site_closure_item` | 1 | DEP-12 | `operations/site_closure_items.php` |
| 69 | `site_dashboard_kpi` | 1 | DEP-12 | `operations/site_board.php` |
| 70 | `site_request_batch` | 1 | DEP-12 | `operations/site_request_batches.php` |
| 71 | `site_request_item` | 1 | DEP-12 | `operations/site_request_items.php` |
| 72 | `site_state_change_request` | 1 | DEP-12 | `operations/site_state_requests.php` |
| 73 | `site_supply_request` | 1 | DEP-12 | `operations/site_supply_requests.php` |
| 74 | `site_suspension` | 1 | DEP-12 | `operations/site_suspension.php` |
| 75 | `sup_aging_obligation` | 1 | DEP-02 | `Suppliers/supplier_aging.php` |
| 76 | `sup_allocation_payment_closure` | 1 | DEP-02 | `suppliers/supplier_payment_allocation.php` |
| 77 | `sup_allocation_unit_equipment` | 1 | DEP-02 | `suppliers/supplier_unit_equipment.php` |
| 78 | `sup_closure` | 1 | DEP-02 | `suppliers/supplier_slot_closure.php` |
| 79 | `sup_dictionary_rule_derivation` | 1 | DEP-02 | `Suppliers/supplier_inference_rules.php` |
| 80 | `sup_doc_guarantee` | 1 | DEP-02 | `suppliers/supplier_docs_guarantees.php` |
| 81 | `sup_handover_slot` | 1 | DEP-02 | `suppliers/supplier_slot_handover.php` |
| 82 | `sup_list_ref` | 1 | DEP-02 | `Suppliers/supplier_ref_lists.php` |
| 83 | `sup_offer_supplier_negotiation` | 1 | DEP-02 | `suppliers/supplier_offers.php` |
| 84 | `sup_onbehalf_advance_deduction` | 1 | DEP-02 | `suppliers/supplier_onbehalf_advances.php` |
| 85 | `sup_replacement_reserve` | 1 | DEP-02 | `suppliers/supplier_readiness_reserve.php` |
| 86 | `sup_report_accept` | 1 | DEP-02 | `Suppliers/supplier_review_report.php` |
| 87 | `sup_responsibility` | 1 | DEP-02 | `suppliers/supplier_responsibility_matrix.php` |
| 88 | `sup_slot_allocation_quota` | 1 | DEP-02 | `suppliers/supplier_quota_distribution.php` |
| 89 | `sup_target_supplier` | 1 | DEP-02 | `Suppliers/supplier_targets.php` |
| 90 | `tenders` | 1 | DEP-01 | `Clients/tenders.php` |
| 91 | `ticket_categories` | 1 | DEP-10 | `Tickets/ticket_categories_config.php` |
| 92 | `ticket_escalation_rules` | 1 | DEP-10 | `Tickets/ticket_escalation_config.php` |
| 93 | `ticket_recurrence_templates` | 1 | DEP-10 | `Tickets/ticket_recurrence.php` |
| 94 | `ticket_types` | 1 | DEP-10 | `Tickets/ticket_types_config.php` |
| 95 | `tkt_ticket_form` | 1 | DEP-10 | `Tickets/ticket_form.php` |
| 96 | `tkt_ticket_sla_config` | 1 | DEP-10 | `Tickets/ticket_sla_config.php` |
| 97 | `tkt_tickets_list` | 1 | DEP-10 | `Tickets/tickets_list.php` |
| 98 | `transfer_cost_rules` | 1 | DEP-15 | `Transport/transfer_cost_rules_config.php` |
| 99 | `transfer_types` | 1 | DEP-15 | `Transport/transfer_types_config.php` |
| 100 | `tre_bank_facility` | 1 | DEP-06 | `Finance/tre_facilities.php` |
| 101 | `trp_transfer_order_form` | 1 | DEP-15 | `Transport/transfer_order_form.php` |
| 102 | `trp_transfer_requests` | 1 | DEP-15 | `Transport/transfer_requests.php` |
| 103 | `trs_locations` | 1 | DEP-15 | `Transport/trs_locations_config.php` |
| 104 | `units_of_measure` | 1 | DEP-01 | `Clients/units_of_measure.php` |
| 105 | `wf_category` | 1 | DEP-13 | `workforce/wf_taxonomy.php` |
| 106 | `wf_equipment_shift_assignment` | 1 | DEP-13 | `workforce/wf_equipment_shift_assignment.php` |
| 107 | `wf_field_incident` | 1 | DEP-13 | `workforce/wf_field_incidents.php` |
| 108 | `wf_housing_units` | 1 | DEP-13 | `Workforce/housing_units.php` |
| 109 | `wf_nomination` | 1 | DEP-13 | `workforce/wf_nomination.php` |
| 110 | `wf_project_allocation` | 1 | DEP-13 | `workforce/wf_project_allocation.php` |
| 111 | `wf_qualification_matrix` | 1 | DEP-13 | `workforce/wf_qualification_matrix.php` |
| 112 | `workforce_requirement` | 1 | DEP-13 | `Workforce/workforce_requirement.php` |

**الإجمالي: 121 سطحًا على 112 كيانًا.**
