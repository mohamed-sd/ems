# كنسٌ شاملٌ لقيودِ CHECK

**القياس:** 2026-08-12 01:12 · النسخة: `auto_pre_up_20260803_082509_equipation_manage.sql`

قواعدُ عملٍ في النسخة: **169** · حيّةٌ الآن: **28** · **مفقودةٌ: 141**

| # | القيد | الجدول | صفوف | **مخالفون** | الحال |
|---|---|---|---|---|---|
| 1 | `ck_snap_window` | `achievement_snapshots` | 41 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 2 | `ck_ahr_explained` | `asset_hour_reconciliations` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 3 | `ck_aos_pct` | `asset_ownership_shares` | 80 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 4 | `ck_recon_decided` | `bank_recon_matches` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 5 | `ck_recon_diff_reason` | `bank_recon_matches` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 6 | `ck_bank_line_amount` | `bank_statement_lines` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 7 | `ck_stmt_closed` | `bank_statements` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 8 | `ck_stmt_span` | `bank_statements` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 9 | `ck_led_enums_not_empty` | `capacity_consumption_ledger` | 50 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 10 | `ck_led_reversal_ref` | `capacity_consumption_ledger` | 50 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 11 | `ck_dispute_evidence` | `claim_lines` | 302 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 12 | `ck_dispute_flag_mirror` | `claim_lines` | 302 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 13 | `ck_dispute_resolution` | `claim_lines` | 302 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 14 | `ck_ccl_planned` | `client_contract_lines` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 15 | `ck_ccl_price` | `client_contract_lines` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 16 | `ck_ccl_qty` | `client_contract_lines` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 17 | `ck_ccl_share` | `client_contract_lines` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 18 | `ck_ccl_span` | `client_contract_lines` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 19 | `ck_ccl_tax_ref` | `client_contract_lines` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 20 | `ck_cb_counts` | `contract_baseline` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 21 | `ck_cg_amount` | `contract_guarantees` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 22 | `ck_cg_percent` | `contract_guarantees` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 23 | `ck_chp_expired_note` | `contract_hour_policies` | 49 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 24 | `ck_cmp_month_fmt` | `contract_monthly_plan` | 20 | **تعذّر القياس** | FUNCTION equipation_manage.regexp_like d |
| 25 | `ck_cmp_qty` | `contract_monthly_plan` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 26 | `ck_cos_closed` | `contract_operational_sites` | 118 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 27 | `ck_cos_name` | `contract_operational_sites` | 118 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 28 | `ck_cos_span` | `contract_operational_sites` | 118 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 29 | `ck_cps_advance_type` | `contract_payment_schedule` | 21 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 30 | `ck_cps_amounts` | `contract_payment_schedule` | 21 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 31 | `ck_cps_due` | `contract_payment_schedule` | 21 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 32 | `ck_cps_month_fmt` | `contract_payment_schedule` | 21 | **تعذّر القياس** | FUNCTION equipation_manage.regexp_like d |
| 33 | `ck_cps_percent` | `contract_payment_schedule` | 21 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 34 | `ck_cps_window` | `contract_payment_schedule` | 21 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 35 | `ck_price_index_ref` | `contract_price_index_readings` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 36 | `ck_price_index_value` | `contract_price_index_readings` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 37 | `ck_price_term_base` | `contract_price_terms` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 38 | `ck_price_term_cap` | `contract_price_terms` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 39 | `ck_price_term_pass` | `contract_price_terms` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 40 | `ck_price_term_threshold` | `contract_price_terms` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 41 | `ck_crp_counts` | `contract_resource_plan` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 42 | `ck_crp_ended` | `contract_resource_plan` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 43 | `ck_crp_productive` | `contract_resource_plan` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 44 | `ck_crp_share` | `contract_resource_plan` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 45 | `ck_crp_window` | `contract_resource_plan` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 46 | `ck_crp_zero_share` | `contract_resource_plan` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 47 | `ck_csl_enums_not_empty` | `coverage_settlement_lines` | 28 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 48 | `ck_dp_posted_needs_approval` | `deduction_proposals` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 49 | `ck_dt_approval` | `deduction_types` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 50 | `ck_adv_amount` | `employee_advances` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 51 | `ck_adv_doc` | `employee_advances` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 52 | `ck_adv_inst` | `employee_advances` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 53 | `ck_adv_recovered` | `employee_advances` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 54 | `ck_ec_eos_days` | `employee_contracts` | 252 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 55 | `ck_ec_leave_days` | `employee_contracts` | 252 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 56 | `ck_fs_approved` | `employee_final_settlements` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 57 | `ck_fs_cancel` | `employee_final_settlements` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 58 | `ck_fs_hands` | `employee_final_settlements` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 59 | `ck_fs_net` | `employee_final_settlements` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 60 | `ck_fs_offset` | `employee_final_settlements` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 61 | `ck_eo_pct` | `entity_ownership` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 62 | `ck_eval_approved` | `evaluations` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 63 | `ck_alloc_amount` | `fin_collection_allocations` | 214 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 64 | `ck_alloc_fx` | `fin_collection_allocations` | 214 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 65 | `ck_ffe_fx_pair` | `fin_financial_events` | 5257 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 66 | `ck_fxd_amount` | `fin_fx_differences` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 67 | `ck_fxd_currency` | `fin_fx_differences` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 68 | `ck_mprov_rate` | `fin_maint_provision_rules` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 69 | `ck_mprov_span` | `fin_maint_provision_rules` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 70 | `ck_mprov_amount` | `fin_maint_provisions` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 71 | `ck_mprov_rule_src` | `fin_maint_provisions` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 72 | `ck_collection_bank_ref` | `fin_payments` | 1066 | **24** ⚠ | يُعلَن |
| 73 | `ck_fp_allocated` | `fin_payments` | 1066 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 74 | `chk_party_payment_needs_settlement` | `fin_requests` | 21 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 75 | `ck_taxret_filed` | `fin_tax_returns` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 76 | `ck_fd_close_needs_decision` | `financing_deviations` | 205 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 77 | `chk_fm_ends` | `founding_mode` | 2 | **1** ⚠ | يُعلَن |
| 78 | `ck_icl_not_self` | `intercompany_loans` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 79 | `ck_meter_reset_doc` | `meter_readings` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 80 | `ck_meter_value` | `meter_readings` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 81 | `ck_mp_hours` | `monthly_performance` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 82 | `ck_mpd_hours` | `monthly_performance_downtime` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 83 | `ck_container_cap` | `op_containers` | 804 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 84 | `ck_oag_value_strict` | `ownership_access_grants` | 22 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 85 | `ck_absence_pct` | `payroll_absence_types` | 33 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 86 | `ck_deduction_amount` | `payroll_deductions` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 87 | `ck_deduction_doc` | `payroll_deductions` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 88 | `ck_deduction_src` | `payroll_deductions` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 89 | `ck_payroll_run_period` | `payroll_runs` | 74 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 90 | `ck_protection_pct` | `payroll_settings` | 1 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 91 | `ck_time_input_doc` | `payroll_time_inputs` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 92 | `ck_time_input_qty` | `payroll_time_inputs` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 93 | `chk_bg_24h` | `permission_exceptions` | 0 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 94 | `ck_rfq_award_qty` | `rfq_awards` | 21 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 95 | `ck_rfq_line_award` | `rfq_lines` | 29 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 96 | `ck_rfq_line_qty` | `rfq_lines` | 29 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 97 | `ck_rfq_quote_price` | `rfq_quotes` | 21 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 98 | `ck_sa_dates` | `seat_assignments` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 99 | `ck_cov_dates` | `substitute_coverages` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 100 | `ck_cov_reason_governed` | `substitute_coverages` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 101 | `ck_sadv_rec_amount` | `supplier_advance_recoveries` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 102 | `ck_sadv_rec_doc` | `supplier_advance_recoveries` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 103 | `ck_sadv_amount` | `supplier_advance_requests` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 104 | `ck_sadv_doc` | `supplier_advance_requests` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 105 | `ck_sadv_recovered` | `supplier_advance_requests` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 106 | `ck_sup_capacity_daily` | `supplier_capacity` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 107 | `ck_sup_capacity_readiness` | `supplier_capacity` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 108 | `ck_sup_capacity_replace` | `supplier_capacity` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 109 | `ck_charge_rule_cap` | `supplier_charge_rules` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 110 | `ck_charge_rule_rate` | `supplier_charge_rules` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 111 | `ck_sup_closure_doc` | `supplier_contract_closures` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 112 | `ck_sup_closure_release` | `supplier_contract_closures` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 113 | `ck_sup_line_price` | `supplier_contract_lines` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 114 | `ck_sup_advance_payment` | `supplier_contracts` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 115 | `ck_sup_guarantee_amount` | `supplier_contracts` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 116 | `ck_sup_guarantee_days` | `supplier_contracts` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 117 | `ck_sup_eval_ratio` | `supplier_evaluation_lines` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 118 | `ck_sup_eval_scale` | `supplier_evaluation_weights` | 5 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 119 | `ck_sup_eval_weight` | `supplier_evaluation_weights` | 5 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 120 | `ck_sup_eval_decided` | `supplier_evaluations` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 121 | `ck_sup_eval_period` | `supplier_evaluations` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 122 | `ck_sup_eval_reason` | `supplier_evaluations` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 123 | `ck_penalty_rule_cap` | `supplier_penalty_rules` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 124 | `ck_penalty_rule_override` | `supplier_penalty_rules` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 125 | `ck_penalty_rule_rate` | `supplier_penalty_rules` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 126 | `ck_rfq_awarded` | `supplier_rfqs` | 21 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 127 | `ck_rfq_cancel` | `supplier_rfqs` | 21 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 128 | `ck_sup_bank_verified` | `suppliers` | 70 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 129 | `ck_tax_cancel` | `tax_invoices` | 285 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 130 | `ck_tax_ref` | `tax_invoices` | 285 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 131 | `ck_tax_total` | `tax_invoices` | 285 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 132 | `ck_order_tariff_source` | `transfer_orders` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 133 | `ck_tariff_limits` | `transfer_tariffs` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 134 | `ck_tariff_rate` | `transfer_tariffs` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 135 | `ck_tariff_span` | `transfer_tariffs` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 136 | `ck_ue_financial_posted` | `unit_effects` | 20 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 137 | `ck_uc_scope` | `user_capacities` | 76 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 138 | `ck_uc_source` | `user_capacities` | 76 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 139 | `ck_uc_state` | `user_capacities` | 76 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 140 | `ck_uc_window` | `user_capacities` | 76 | 0 | ✘ ALTER command denied to user 'ems_app'@'localhost' |
| 141 | `ck_vk_reason` | `visibility_keys` | 1 | **1** ⚠ | يُعلَن |

**رُمِّم: 0**

### بمخالفينَ أحياء — قرارُ مالكٍ لا قرارُ مُرحِّل
- **`ck_collection_bank_ref`** · `fin_payments` · **24** مخالفًا من 1066
  - `((`direction` <> _utf8mb4'collection') or ((`bank_ref` is not null) and (`bank_ref` <> _utf8mb4'')))`
- **`chk_fm_ends`** · `founding_mode` · **1** مخالفًا من 2
  - `((`enabled` = 0) or (`ends_at` is not null))`
- **`ck_vk_reason`** · `visibility_keys` · **1** مخالفًا من 1
  - `((`mode` = _utf8mb4'inherit') or (`reason` is not null))`
