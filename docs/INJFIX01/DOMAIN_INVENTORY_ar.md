# جردُ الكتّابِ والقرّاءِ — المجالاتُ الستة

> مقامُ المسح: **1257** ملفَّ إنتاجٍ حيّ (بلا tests/tools/docs/storage).

## السلطة

| المسار | قرّاء | كتّاب | الكتّابُ بأسمائِهم |
|---|---:|---:|---|
| `gov_authority_grants` | 8 | 4 | `C:/wamp64/www/ems/database/migrations/2027_06_13_govauth01_model.php` · `C:/wamp64/www/ems/database/migrations/2027_06_20_owner_delegated_decisions.php` · `C:/wamp64/www/ems/database/migrations/2027_06_22_auditor_detach_sod.php` · `C:/wamp64/www/ems/Governance/auth_grants.php` |
| `gov_authority_limits` | 3 | 1 | `C:/wamp64/www/ems/database/migrations/2027_01_17_fix_fn09_limit_kind.php` |
| `gov_delegations` | 2 | 1 | `C:/wamp64/www/ems/database/migrations/2027_06_13_govauth01_model.php` |
| `gov_cap_history` | 3 | 2 | `C:/wamp64/www/ems/database/migrations/2027_06_24_caps_flexible_history.php` · `C:/wamp64/www/ems/Governance/authority_caps.php` |
| `v_effective_authority` | 2 | 0 | — |

## الاعتماد

| المسار | قرّاء | كتّاب | الكتّابُ بأسمائِهم |
|---|---:|---:|---|
| `approval_requests` | 10 | 1 | `C:/wamp64/www/ems/includes/approval_workflow.php` |
| `approval_steps` | 10 | 1 | `C:/wamp64/www/ems/includes/approval_workflow.php` |
| `approval_workflow_rules` | 13 | 4 | `C:/wamp64/www/ems/database/migrations/2027_01_29_inj0219_deduction_decision_chain.php` · `C:/wamp64/www/ems/database/migrations/2027_01_30_wf_rules_hygiene.php` · `C:/wamp64/www/ems/database/migrations/2027_07_03_ladders_single_source.php` · `C:/wamp64/www/ems/database/migrations/2027_07_12_ladder_actor_role_bridge.php` |
| `gov_ladders` | 12 | 5 | `C:/wamp64/www/ems/database/migrations/2027_05_30_lad01_ladders_breakglass_caps.php` · `C:/wamp64/www/ems/database/migrations/2027_05_31_lad01_cap_check_fix.php` · `C:/wamp64/www/ems/database/migrations/2027_06_17_caps_resolution_delegated.php` · `C:/wamp64/www/ems/database/migrations/2027_07_04_caps_derive_six.php` · `C:/wamp64/www/ems/Governance/authority_caps.php` |
| `gov_ladder_steps` | 9 | 3 | `C:/wamp64/www/ems/database/migrations/2027_05_30_lad01_ladders_breakglass_caps.php` · `C:/wamp64/www/ems/database/migrations/2027_05_31_lad01_cap_check_fix.php` · `C:/wamp64/www/ems/database/migrations/2027_06_07_uxw01_live_corrections.php` |
| `gov_journey_ladders` | 2 | 2 | `C:/wamp64/www/ems/database/migrations/2027_08_03_journey_ladders.php` · `C:/wamp64/www/ems/database/migrations/2027_08_22_ladder_entity_key_bridge.php` |
| `v_approval_rules_effective` | 4 | 0 | — |
| `authority_signatures` | ⛔ | ⛔ | **غيرُ موجودٍ في القاعدة** — اسمٌ بلا مُسمًّى، ولا يُقرأ صفرُه «مسارًا ميتًا» |
| `gov_approval_decisions` | 2 | 1 | `C:/wamp64/www/ems/app/Services/Governance/GovernanceM14Service.php` |

## الصلاحيات

| المسار | قرّاء | كتّاب | الكتّابُ بأسمائِهم |
|---|---:|---:|---|
| `role_permissions` | 151 | 47 | `C:/wamp64/www/ems/admin/permissions/role_permissions.php` · `C:/wamp64/www/ems/app/Services/Security/PermSourceService.php` · `C:/wamp64/www/ems/company/auth.php` · `C:/wamp64/www/ems/Contracts/contract_obligations.php` · `C:/wamp64/www/ems/database/migrations/2026_08_06_sec_governance_lockdown.php` · `C:/wamp64/www/ems/database/migrations/2027_01_03_team_group_project_users.php` …+41 |
| `gov_profile_items` | 10 | 2 | `C:/wamp64/www/ems/database/migrations/2027_08_13_equipments_for_suppliers_mgr.php` · `C:/wamp64/www/ems/database/migrations/2027_08_21_finreq_merge_request_screens.php` |
| `gov_role_profiles` | 10 | 2 | `C:/wamp64/www/ems/database/migrations/2027_06_13_govauth01_model.php` · `C:/wamp64/www/ems/database/migrations/2027_06_16_govauth01_partial_switch.php` |

## الأحداث

| المسار | قرّاء | كتّاب | الكتّابُ بأسمائِهم |
|---|---:|---:|---|
| `ems_business_events` | 34 | 5 | `C:/wamp64/www/ems/app/Core/EventPublisher.php` · `C:/wamp64/www/ems/app/Services/Bus/EventDeliveryWorker.php` · `C:/wamp64/www/ems/app/Services/Bus/EventOutboxFanout.php` · `C:/wamp64/www/ems/app/Services/Finance/FxRevaluationService.php` · `C:/wamp64/www/ems/database/migrations/2027_05_25_eng01_backfill_constraints.php` |
| `fin_financial_events` | 51 | 15 | `C:/wamp64/www/ems/app/Core/EventPublisher.php` · `C:/wamp64/www/ems/app/Services/CompensationService.php` · `C:/wamp64/www/ems/app/Services/Contract/ContractSignedEffects.php` · `C:/wamp64/www/ems/app/Services/Contract/PlanActualLinkService.php` · `C:/wamp64/www/ems/app/Services/Finance/EventStateMachine.php` · `C:/wamp64/www/ems/app/Services/Finance/PostingService.php` …+9 |
| `ems_event_consumers` | 5 | 1 | `C:/wamp64/www/ems/app/Core/EventDispatcher.php` |
| `ems_event_deliveries` | 13 | 5 | `C:/wamp64/www/ems/app/Core/EventDispatcher.php` · `C:/wamp64/www/ems/app/Services/Bus/EventDeliveryWorker.php` · `C:/wamp64/www/ems/database/migrations/2027_05_24_eng01_shared_engines.php` · `C:/wamp64/www/ems/database/migrations/2027_06_02_bus_fix_broken_subscriptions.php` · `C:/wamp64/www/ems/database/migrations/2027_06_03_bus_method_level_audit.php` |
| `capacity_outbox` | 3 | 1 | `C:/wamp64/www/ems/app/Services/Capacity/CapacityOutbox.php` |

## التنقل

| المسار | قرّاء | كتّاب | الكتّابُ بأسمائِهم |
|---|---:|---:|---|
| `nav_items` | 97 | 62 | `C:/wamp64/www/ems/admin/permissions/nav_items.php` · `C:/wamp64/www/ems/database/migrations/2026_08_02_nav16_doors_groups.php` · `C:/wamp64/www/ems/database/migrations/2026_08_02_nav18_regroup.php` · `C:/wamp64/www/ems/database/migrations/2026_11_04_org_v4_restructure.php` · `C:/wamp64/www/ems/database/migrations/2027_01_03_team_group_project_users.php` · `C:/wamp64/www/ems/database/migrations/2027_01_05_nav_unify_roles_27_30_gaps.php` …+56 |
| `nav_canonical` | 30 | 11 | `C:/wamp64/www/ems/database/migrations/2027_06_30_uxui_matrix_v3.php` · `C:/wamp64/www/ems/database/migrations/2027_07_06_two_axis_decision_application.php` · `C:/wamp64/www/ems/database/migrations/2027_07_07_decision_actor_or_source.php` · `C:/wamp64/www/ems/database/migrations/2027_07_10_two_axis_domain_constraint.php` · `C:/wamp64/www/ems/database/migrations/2027_07_15_register_unlisted_routes.php` · `C:/wamp64/www/ems/database/migrations/2027_07_16_ops_output_doc.php` …+5 |
| `nav_canonical_current` | 12 | 6 | `C:/wamp64/www/ems/database/migrations/2027_06_30_uxui_matrix_v3.php` · `C:/wamp64/www/ems/database/migrations/2027_07_25_tkt_dept_inbox_current_retire.php` · `C:/wamp64/www/ems/database/migrations/2027_07_28_stage_title_conversational.php` · `C:/wamp64/www/ems/database/migrations/2027_08_17_nav_timesheet_link_to_view.php` · `C:/wamp64/www/ems/database/migrations/2027_08_19_nav_role2_timesheet_entry_restore.php` · `C:/wamp64/www/ems/database/migrations/2027_08_21_finreq_merge_request_screens.php` |
| `link_groups` | 52 | 28 | `C:/wamp64/www/ems/admin/permissions/link_groups.php` · `C:/wamp64/www/ems/database/migrations/2026_08_02_nav16_doors_groups.php` · `C:/wamp64/www/ems/database/migrations/2026_08_02_nav18_regroup.php` · `C:/wamp64/www/ems/database/migrations/2026_11_04_org_v4_restructure.php` · `C:/wamp64/www/ems/database/migrations/2027_01_03_team_group_project_users.php` · `C:/wamp64/www/ems/database/migrations/2027_01_05_nav_unify_roles_27_30_gaps.php` …+22 |
| `nav_route_group` | 8 | 4 | `C:/wamp64/www/ems/database/migrations/2027_08_14_nav_ten_groups.php` · `C:/wamp64/www/ems/database/migrations/2027_08_15_nav_ten_groups_v2.php` · `C:/wamp64/www/ems/database/migrations/2027_08_16_nav_twelve_groups.php` · `C:/wamp64/www/ems/database/migrations/2027_08_21_finreq_merge_request_screens.php` |

## الإقفال

| المسار | قرّاء | كتّاب | الكتّابُ بأسمائِهم |
|---|---:|---:|---|
| `fin_financial_periods` | 17 | 3 | `C:/wamp64/www/ems/database/migrations/2027_03_02_fiscal_calendar_extend_next_year.php` · `C:/wamp64/www/ems/database/migrations/2027_04_25_open_period_2026_06_for_trial.php` · `C:/wamp64/www/ems/Finance/periods_fin.php` |
| `scr_monthly_close` | 2 | 0 | — |
| `fin_closing_items` | 2 | 1 | `C:/wamp64/www/ems/Finance/periods_fin.php` |

## دفترُ القيد

| المسار | قرّاء | كتّاب | الكتّابُ بأسمائِهم |
|---|---:|---:|---|
| `fin_journal_entries` | 21 | 9 | `C:/wamp64/www/ems/app/Services/Finance/PostingService.php` · `C:/wamp64/www/ems/app/Services/Governance/GovernanceM14Service.php` · `C:/wamp64/www/ems/database/migrations/2027_02_04_restore_lost_check_constraints.php` · `C:/wamp64/www/ems/database/migrations/2027_04_30_purge_reversal_chain_artifacts.php` · `C:/wamp64/www/ems/database/seeds/uat0001/07_financial_cycle.php` · `C:/wamp64/www/ems/database/seeds/uat0001/08_settlements_payroll.php` …+3 |
| `fin_journal_lines` | 13 | 5 | `C:/wamp64/www/ems/app/Services/Finance/PostingService.php` · `C:/wamp64/www/ems/database/migrations/2027_04_30_purge_reversal_chain_artifacts.php` · `C:/wamp64/www/ems/Finance/fin_helpers.php` · `C:/wamp64/www/ems/Finance/journal_form_fin.php` · `C:/wamp64/www/ems/includes/fx.php` |

