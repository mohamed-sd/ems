# دفترُ جداولِ «سجل حقول الورقة» — المرشَّحةُ للحذف

**تاريخُ التدوين:** 2026-09-07 · **الشركة:** 4 · **الجولة:** دمجُ حقولِ الورقةِ في جدولِ الشاشة

> **ما هذا الملفّ؟** كلُّ سطرٍ هنا **جدولُ قوقعةٍ لم يعد له قارئ**: بطاقتُه
> حُذفت من شاشتِها، وحقولُه صارت أعمدةً في جدولِ الشاشةِ الحقيقيّ.
> **وهو قائمةُ حذفٍ لاحقٍ لا سجلُّ توثيق** — لا يُدرَج فيه إلا ما انقطع قارئُه.

⛔ **ولا يُحذف جدولٌ منها قبلَ فحصَين**: أن لا يذكرَه المصدرُ (‏`grep`)،
وأن يكون خاليًا من صفٍّ لم تكتبه أداةُ التوليد.

## ① المرشَّحةُ للحذف — 87 جدولًا

| # | الشاشة | جدولُ القوقعةِ (يُحذف) | الجدولُ الحقيقيُّ الذي حلَّ محلَّه | أعمدةٌ نُقلت |
|---:|---|---|---|---:|
| 1 | `Approvals/hours_approval.php` | **`ops_hours_approval`** | `timesheet_approvals` | 19 |
| 2 | `Clients/clients.php` | **`sal_clients`** | `clients` | 26 |
| 3 | `Clients/quotation_negotiation.php` | **`sal_quotation_negotiation`** | `quotations` | 18 |
| 4 | `Clients/quotations.php` | **`sal_quotations`** | `quotations` | 22 |
| 5 | `Contracts/claims.php` | **`sal_claims`** | `claims` | 25 |
| 6 | `Contracts/collections.php` | **`fina_collections`** | `fin_payments` | 7 |
| 7 | `Contracts/contracts.php` | **`sal_contracts`** | `contracts` | 43 |
| 8 | `Employees/employee_contracts.php` | **`hr_employee_contracts`** | `employee_contracts` | 14 |
| 9 | `Employees/employees.php` | **`hr_employees`** | `employees` | 14 |
| 10 | `FinRequests/effect_map.php` | **`fina_effect_map`** | `fin_event_links` | 10 |
| 11 | `FinRequests/request_form.php` | **`my_requests`** | `fin_requests` | 10 |
| 12 | `Finance/bank_reconciliation_fin.php` | **`tre_bank_reconciliation_fin`** | `fin_bank_statement_lines` | 14 |
| 13 | `Finance/cfo_daily_board_fin.php` | **`fina_dashboard_kpi`** | `fin_budget_lines` | 6 |
| 14 | `Finance/currencies_fin.php` | **`fina_currencies`** | `fin_currencies` | 9 |
| 15 | `Finance/dues_fin.php` | **`fina_dues`** | `fin_dues` | 12 |
| 16 | `Finance/financial_statements_fin.php` | **`fina_financial_statements_fin`** | `fin_chart_of_accounts` | 5 |
| 17 | `Finance/journal_form_fin.php` | **`fina_journal_form_fin`** | `fin_journal_entries` | 11 |
| 18 | `Finance/periods_fin.php` | **`fina_periods_fin`** | `fin_financial_periods` | 16 |
| 19 | `Finance/tax_fin.php` | **`fina_tax_fin`** | `fin_tax_transactions` | 12 |
| 20 | `Finance/tre_beneficiary.php` | **`tre_beneficiary`** | `tre_beneficiaries` | 12 |
| 21 | `Finance/tre_liquidity_board.php` | **`tre_dashboard_kpi`** | `tre_cash_move` | 5 |
| 22 | `Finance/tre_pay_batch.php` | **`tre_pay_batch`** | `tre_pay_batches` | 21 |
| 23 | `Fleet/asset_full_history.php` | **`flt_asset_full_history`** | `fleet_equipment_history` | 5 |
| 24 | `Maintenance/breakdown_intake.php` | **`mnt_breakdown_intake`** | `mnt_breakdown` | 42 |
| 25 | `Maintenance/external_repairs.php` | **`mnt_external_repairs`** | `mnt_external_repair` | 8 |
| 26 | `Maintenance/mnt_kpis.php` | **`mnt_kpis`** | `mnt_kpi_period` | 4 |
| 27 | `Maintenance/part_requests.php` | **`mnt_part_requests`** | `mnt_part_request` | 8 |
| 28 | `Maintenance/preventive_plans.php` | **`mnt_preventive_plans`** | `mnt_plan` | 17 |
| 29 | `Maintenance/repeat_repairs.php` | **`mnt_repeat_repairs`** | `mnt_repeat_repair` | 13 |
| 30 | `Maintenance/workshop.php` | **`mnt_workshop`** | `scr_workshop` | 15 |
| 31 | `Operations/attendance.php` | **`hr_attendance`** | `scr_attendance` | 11 |
| 32 | `Operations/monthly_close.php` | **`ops_monthly_close`** | `scr_monthly_close` | 18 |
| 33 | `Operations/monthly_plan.php` | **`ops_monthly_plan`** | `scr_op_monthly` | 25 |
| 34 | `Operations/site_gate_equip.php` | **`site_gate_equip`** | `scr_site_gate_equip` | 16 |
| 35 | `Opportunities/client_need_rfq.php` | **`sal_client_need_rfq`** | `sal_client_needs` | 27 |
| 36 | `Portal/my_achievement.php` | **`my_achievement`** | `achievement_records` | 9 |
| 37 | `Portal/my_portal.php` | **`my_portal`** | `work_items` | 7 |
| 38 | `Portal/my_tasks.php` | **`my_tasks`** | `work_items` | 8 |
| 39 | `Portal/vp_actions_followup.php` | **`exec_action_followup`** | `exec_decisions` | 12 |
| 40 | `Portal/vp_approval_inbox.php` | **`exec_request_queue`** | `exec_approvals` | 19 |
| 41 | `Portal/vp_departments.php` | **`exec_org_project`** | `org_units` | 12 |
| 42 | `Portal/vp_pending_actions.php` | **`dvp_vp_pending_actions`** | `exec_approvals` | 7 |
| 43 | `Procurement/dashboard_proc.php` | **`prc_dashboard_kpi`** | `proc_request` | 5 |
| 44 | `Procurement/orders_proc.php` | **`prc_orders_proc`** | `proc_order` | 14 |
| 45 | `Procurement/proc_award_minutes.php` | **`prc_proc_award_minutes`** | `proc_award` | 16 |
| 46 | `Procurement/proc_delivery_track.php` | **`prc_proc_delivery_track`** | `proc_delivery_event` | 12 |
| 47 | `Procurement/proc_offers.php` | **`prc_proc_offers`** | `proc_offer` | 25 |
| 48 | `Procurement/proc_offers.php` | **`prc_offer_compare`** | `proc_offer` | 25 |
| 49 | `Procurement/proc_packages.php` | **`prc_proc_packages`** | `proc_package` | 18 |
| 50 | `Procurement/proc_packages.php` | **`prc_package_lines`** | `proc_package` | 18 |
| 51 | `Procurement/proc_po_amendments.php` | **`prc_proc_po_amendments`** | `proc_po_amendment` | 17 |
| 52 | `Procurement/proc_rfq.php` | **`prc_proc_rfq`** | `proc_rfq` | 12 |
| 53 | `Procurement/proc_supplier_eval.php` | **`prc_proc_supplier_eval`** | `proc_supplier_eval` | 9 |
| 54 | `Procurement/requests_proc.php` | **`prc_requests`** | `proc_request` | 11 |
| 55 | `Procurement/stock_proc.php` | **`wh_stock_proc`** | `proc_stock_state` | 6 |
| 56 | `Procurement/warehouses.php` | **`wh_warehouses`** | `proc_warehouse` | 16 |
| 57 | `Procurement/wh_count.php` | **`wh_count`** | `proc_count_session` | 11 |
| 58 | `Procurement/wh_hazmat.php` | **`wh_hazmat`** | `proc_hazmat_control` | 16 |
| 59 | `Procurement/wh_issue_requests.php` | **`wh_issue_request_lines`** | `proc_issue_request` | 20 |
| 60 | `Procurement/wh_issue_requests.php` | **`wh_issue_requests`** | `proc_issue_request` | 20 |
| 61 | `Procurement/wh_month_close.php` | **`wh_month_close`** | `proc_wh_close` | 17 |
| 62 | `Procurement/wh_transfer.php` | **`wh_transfer`** | `proc_transfer` | 11 |
| 63 | `Projects/projects.php` | **`sal_projects`** | `project` | 21 |
| 64 | `Risk/risk_register.php` | **`rsk_risk_register`** | `risk_register` | 21 |
| 65 | `Risk/risk_reports.php` | **`rsk_risk_reports`** | `risk_export_log` | 9 |
| 66 | `Risk/risk_treatments.php` | **`rsk_risk_treatments`** | `risk_treatments` | 15 |
| 67 | `Tickets/ticket_form.php` | **`tkt_ticket_form`** | `tickets` | 26 |
| 68 | `Tickets/ticket_sla_config.php` | **`tkt_ticket_sla_config`** | `ticket_sla_policies` | 11 |
| 69 | `Tickets/tickets_list.php` | **`tkt_tickets_list`** | `tickets` | 10 |
| 70 | `Timesheet/timesheet.php` | **`ops_timesheet`** | `timesheet` | 34 |
| 71 | `Transport/transfer_closure.php` | **`trp_transfer_closure`** | `trp_closure` | 11 |
| 72 | `Transport/transfer_damage_claims.php` | **`trp_transfer_damage_claims`** | `trp_damage_claim` | 12 |
| 73 | `Transport/transfer_fleet.php` | **`trp_transfer_fleet`** | `scr_transfer_fleet` | 13 |
| 74 | `Transport/transfer_in_transit.php` | **`trp_transfer_in_transit`** | `transfer_orders` | 12 |
| 75 | `Transport/transfer_order_form.php` | **`trp_transfer_order_form`** | `transfer_orders` | 23 |
| 76 | `Transport/transfer_orders_report.php` | **`trp_transfer_orders_report`** | `trp_kpi_period` | 1 |
| 77 | `Transport/transfer_origin_handover.php` | **`trp_transfer_origin_handover`** | `trp_origin_handover` | 9 |
| 78 | `Transport/transfer_permits.php` | **`trp_transfer_permits`** | `transfer_permits` | 8 |
| 79 | `Transport/transfer_requests.php` | **`trp_transfer_requests`** | `transfer_requests` | 12 |
| 80 | `Transport/transfer_trip_legs.php` | **`trp_transfer_trip_legs`** | `trp_trip_leg` | 11 |
| 81 | `Workforce/housing_units.php` | **`wf_housing_units`** | `housing_unit` | 8 |
| 82 | `Workforce/payroll_runs.php` | **`hr_payroll_runs`** | `payroll_runs` | 16 |
| 83 | `Workforce/project_contracts.php` | **`hr_project_contracts`** | `scr_project_contracts` | 15 |
| 84 | `Workforce/recruitment_pipeline.php` | **`hr_recruitment_pipeline`** | `rec_applications` | 16 |
| 85 | `Workforce/worker_leave_absence.php` | **`hr_worker_leave_absence`** | `worker_leave_absence` | 9 |
| 86 | `Workforce/worker_worklog.php` | **`ops_worker_worklog`** | `worker_qualification` | 10 |
| 87 | `user_capacities.php` | **`my_user_capacities`** | `user_capacities` | 7 |

## ② ثلاثةٌ **لا تُحذف** — جداولُ عملٍ حقيقيّةٌ حُقنت فيها أعمدةُ الورقة

> بطاقةُ هذه الشاشاتِ كانت تقرأ **جدولَ عملٍ حقيقيًّا** لا قوقعةً، فحذفُه حذفُ بيانات.
> والمطلوبُ فيها لاحقًا **نزعُ أعمدةِ `gN` وحدَها** لا إسقاطُ الجدول.

| الشاشة | الجدول | لماذا يبقى |
|---|---|---|
| `Clients/quotation_lines.php` | `sal_quotation_lines` | جدولُ عملٍ حيٌّ (‏0 صفًّا) وأعمدتُه غيرُ `gN` عاملة |
| `Maintenance/daily_care.php` | `mnt_daily_care` | جدولُ عملٍ حيٌّ (‏0 صفًّا) وأعمدتُه غيرُ `gN` عاملة |
| `Procurement/po_match.php` | `proc_order` | جدولُ عملٍ حيٌّ (‏22 صفًّا) وأعمدتُه غيرُ `gN` عاملة |

## ③ ما يُنفَّذ عندَ قرارِ الحذف

```sql
-- بعدَ التحقّقِ من انقطاعِ القارئِ لكلِّ اسم:
DROP TABLE IF EXISTS `ops_hours_approval`;
DROP TABLE IF EXISTS `sal_clients`;
DROP TABLE IF EXISTS `sal_quotation_negotiation`;
DROP TABLE IF EXISTS `sal_quotations`;
DROP TABLE IF EXISTS `sal_claims`;
DROP TABLE IF EXISTS `fina_collections`;
DROP TABLE IF EXISTS `sal_contracts`;
DROP TABLE IF EXISTS `hr_employee_contracts`;
DROP TABLE IF EXISTS `hr_employees`;
DROP TABLE IF EXISTS `fina_effect_map`;
DROP TABLE IF EXISTS `my_requests`;
DROP TABLE IF EXISTS `tre_bank_reconciliation_fin`;
DROP TABLE IF EXISTS `fina_dashboard_kpi`;
DROP TABLE IF EXISTS `fina_currencies`;
DROP TABLE IF EXISTS `fina_dues`;
DROP TABLE IF EXISTS `fina_financial_statements_fin`;
DROP TABLE IF EXISTS `fina_journal_form_fin`;
DROP TABLE IF EXISTS `fina_periods_fin`;
DROP TABLE IF EXISTS `fina_tax_fin`;
DROP TABLE IF EXISTS `tre_beneficiary`;
DROP TABLE IF EXISTS `tre_dashboard_kpi`;
DROP TABLE IF EXISTS `tre_pay_batch`;
DROP TABLE IF EXISTS `flt_asset_full_history`;
DROP TABLE IF EXISTS `mnt_breakdown_intake`;
DROP TABLE IF EXISTS `mnt_external_repairs`;
DROP TABLE IF EXISTS `mnt_kpis`;
DROP TABLE IF EXISTS `mnt_part_requests`;
DROP TABLE IF EXISTS `mnt_preventive_plans`;
DROP TABLE IF EXISTS `mnt_repeat_repairs`;
DROP TABLE IF EXISTS `mnt_workshop`;
DROP TABLE IF EXISTS `hr_attendance`;
DROP TABLE IF EXISTS `ops_monthly_close`;
DROP TABLE IF EXISTS `ops_monthly_plan`;
DROP TABLE IF EXISTS `site_gate_equip`;
DROP TABLE IF EXISTS `sal_client_need_rfq`;
DROP TABLE IF EXISTS `my_achievement`;
DROP TABLE IF EXISTS `my_portal`;
DROP TABLE IF EXISTS `my_tasks`;
DROP TABLE IF EXISTS `exec_action_followup`;
DROP TABLE IF EXISTS `exec_request_queue`;
DROP TABLE IF EXISTS `exec_org_project`;
DROP TABLE IF EXISTS `dvp_vp_pending_actions`;
DROP TABLE IF EXISTS `prc_dashboard_kpi`;
DROP TABLE IF EXISTS `prc_orders_proc`;
DROP TABLE IF EXISTS `prc_proc_award_minutes`;
DROP TABLE IF EXISTS `prc_proc_delivery_track`;
DROP TABLE IF EXISTS `prc_proc_offers`;
DROP TABLE IF EXISTS `prc_offer_compare`;
DROP TABLE IF EXISTS `prc_proc_packages`;
DROP TABLE IF EXISTS `prc_package_lines`;
DROP TABLE IF EXISTS `prc_proc_po_amendments`;
DROP TABLE IF EXISTS `prc_proc_rfq`;
DROP TABLE IF EXISTS `prc_proc_supplier_eval`;
DROP TABLE IF EXISTS `prc_requests`;
DROP TABLE IF EXISTS `wh_stock_proc`;
DROP TABLE IF EXISTS `wh_warehouses`;
DROP TABLE IF EXISTS `wh_count`;
DROP TABLE IF EXISTS `wh_hazmat`;
DROP TABLE IF EXISTS `wh_issue_request_lines`;
DROP TABLE IF EXISTS `wh_issue_requests`;
DROP TABLE IF EXISTS `wh_month_close`;
DROP TABLE IF EXISTS `wh_transfer`;
DROP TABLE IF EXISTS `sal_projects`;
DROP TABLE IF EXISTS `rsk_risk_register`;
DROP TABLE IF EXISTS `rsk_risk_reports`;
DROP TABLE IF EXISTS `rsk_risk_treatments`;
DROP TABLE IF EXISTS `tkt_ticket_form`;
DROP TABLE IF EXISTS `tkt_ticket_sla_config`;
DROP TABLE IF EXISTS `tkt_tickets_list`;
DROP TABLE IF EXISTS `ops_timesheet`;
DROP TABLE IF EXISTS `trp_transfer_closure`;
DROP TABLE IF EXISTS `trp_transfer_damage_claims`;
DROP TABLE IF EXISTS `trp_transfer_fleet`;
DROP TABLE IF EXISTS `trp_transfer_in_transit`;
DROP TABLE IF EXISTS `trp_transfer_order_form`;
DROP TABLE IF EXISTS `trp_transfer_orders_report`;
DROP TABLE IF EXISTS `trp_transfer_origin_handover`;
DROP TABLE IF EXISTS `trp_transfer_permits`;
DROP TABLE IF EXISTS `trp_transfer_requests`;
DROP TABLE IF EXISTS `trp_transfer_trip_legs`;
DROP TABLE IF EXISTS `wf_housing_units`;
DROP TABLE IF EXISTS `hr_payroll_runs`;
DROP TABLE IF EXISTS `hr_project_contracts`;
DROP TABLE IF EXISTS `hr_recruitment_pipeline`;
DROP TABLE IF EXISTS `hr_worker_leave_absence`;
DROP TABLE IF EXISTS `ops_worker_worklog`;
DROP TABLE IF EXISTS `my_user_capacities`;
```

> **والعكسُ قبلَ ذلك ممكن**: `database/migrations/2028_06_01_sheet_merge_fields_down.php`
> ينزع أعمدةَ الدمجِ من جداولِ العملِ، و`git revert` يُعيد الشاشات — فما دامت
> القواقعُ قائمةً فالجولةُ كلُّها قابلةٌ للردّ.
