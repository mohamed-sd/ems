# `RPR-03` §٦ — مساراتُ قرارِ الصلاحية

> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/rpr03_permission_decision_paths.php --md` · اللقطة `—(بلا نافذة)`

## ثلاثةُ أصنافٍ لا صنفان

| الصنف | العدد | المعيار |
|---|---|---|
| `CANONICAL` | **270** | يستدعي `permissions_helper` ولا يقرأ جدولًا بنفسِه |
| ⛔ `DIRECT_DECISION` | **88** | **يقرأ جدولَ صلاحيةٍ بنفسِه ليقرّر** — المسارُ الثاني |
| `ADMIN_SURFACE` | **47** | يكتب فيها: بياناتُه لا قرارُه — ولا يُعدُّ ظلمًا |

⛔ **ولا يُعدُّ ملفٌّ في مقامَين**: الكتابةُ تغلب القراءةَ، فشاشةُ الإدارةِ تقرأ
حتمًا لتعرض ما تكتب.

## القارئون المستقلّون

- `/Approvals/attribution_board.php`
- `/Contracts/client_statement.php`
- `/Contracts/collections.php`
- `/Contracts/commercial_board.php`
- `/Contracts/contract_baseline.php`
- `/Contracts/contract_guarantees.php`
- `/Contracts/contract_lifecycle.php`
- `/Contracts/contract_lines.php`
- `/Contracts/contract_monthly_plan.php`
- `/Contracts/contract_obligations.php`
- `/Contracts/contract_payment_schedule.php`
- `/Contracts/contract_resource_plan.php`
- `/Contracts/contract_sites.php`
- `/Contracts/penalties.php`
- `/Contracts/plan_actual_link.php`
- `/Contracts/price_terms.php`
- `/Contracts/tax_invoices.php`
- `/Equipments/meter_readings.php`
- `/Finance/approvals_inbox.php`
- `/Operations/containers.php`
- `/Operations/daily_plan.php`
- `/Portal/portal_elements.php`
- `/Portal/visibility_audit.php`
- `/Portal/visibility_keys.php`
- `/Portal/visibility_simulator.php`
- `/Procurement/requests_proc.php`
- `/Projects/sites.php`
- `/Risk/gov_dept_rsk.php`
- `/Suppliers/rfq_requests.php`
- `/Suppliers/settlements.php`
- `/Suppliers/supplier_advances.php`
- `/Suppliers/supplier_capacity.php`
- `/Suppliers/supplier_closure.php`
- `/Suppliers/supplier_contract_lines.php`
- `/Suppliers/supplier_documents.php`
- `/Suppliers/supplier_evaluation.php`
- `/Suppliers/supplier_profile.php`
- `/Suppliers/supplier_rules.php`
- `/Tickets/ticket_workstreams_board.php`
- `/Tickets/watchtower.php`
- `/Transport/transfer_tariffs.php`
- `/Workforce/contract_registry.php`
- `/Workforce/employee_advances.php`
- `/Workforce/employee_settlements.php`
- `/Workforce/final_settlement.php`
- `/Workforce/payroll_runs.php`
- `/admin/ops_manager_board.php`
- `/admin/org_assignments.php`
- `/admin/org_permits.php`
- `/admin/org_structure.php`
- `/admin/perm_system.php`
- `/admin/permissions/nav_items.php`
- `/admin/sec_employee_wizard.php`
- `/admin/sec_governance.php`
- `/app/Services/Security/PermSourceService.php`
- `/app/Services/Security/PermissionResolver.php`
- `/app/Services/Security/PermissionTemplateService.php`
- `/company/auth.php`
- `/database/migrations/2027_01_05_nav_unify_roles_27_30_gaps.php`
- `/database/migrations/2027_01_15_fix_fn01_fn02_finance_nav.php`
- `/database/migrations/2027_01_28_inj0128_role9_reports_group.php`
- `/database/migrations/2027_02_12_role26_granted_nav_activate.php`
- `/database/migrations/2027_02_13_owner_nav_activate_and_movement_mgr.php`
- `/database/migrations/2027_04_02_guard_classification_nav.php`
- `/database/migrations/2027_05_16_hr_users_grant_drop.php`
- `/database/migrations/2027_06_13_govauth01_model.php`
- `/database/migrations/2027_06_16_govauth01_partial_switch.php`
- `/database/migrations/2027_06_20_owner_delegated_decisions.php`
- `/database/migrations/2027_06_21_template_union_reseed.php`
- `/database/migrations/2027_06_22_auditor_detach_sod.php`
- `/database/migrations/2027_06_25_authority_caps_register.php`
- `/database/migrations/2027_07_24_tkt_dept_inbox_merge.php`
- `/database/migrations/2027_08_17_nav_timesheet_link_to_view.php`
- `/database/migrations/2027_08_18_equipments_hidden_from_suppliers_space.php`
- `/database/migrations/2027_08_19_nav_role2_timesheet_entry_restore.php`
- `/includes/dept_gov_space.php`
- `/includes/finreq_badges.php`
- `/includes/gov_columns.php`
- `/includes/perm_explain_live.php`
- `/includes/role_board.php`
- `/includes/saved_views_endpoint.php`
- `/includes/sec013.php`
- `/includes/sod_guard.php`
- `/includes/sod_map.php`
- `/includes/unified_nav.php`
- `/insidebar.php`
- `/main/project_users.php`
- `/user_capacities.php`

## خطوةُ صفرٍ — إعادةُ قياسِ خطِّ الأساس

خطُّ الأساسِ: **مساران و٨٧ قارئًا** · **والمقيسُ الآنَ 2 مسارًا و88 قارئًا مستقلًّا**.

**`مساراتُ قرارِ الصلاحية` = 2** — والقبولُ **١**.

`Track RPR-03 ج blocked at stage: توحيدُ 88 قارئًا مستقلًّا`
