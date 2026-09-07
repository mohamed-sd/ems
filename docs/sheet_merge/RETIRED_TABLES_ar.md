# دفترُ جداولِ «سجل حقول الورقة» — المرشَّحةُ للحذف

**تاريخُ التدوين:** 2026-09-07 · **الشركة:** 4 · **الجولة:** دمجُ حقولِ الورقةِ في جدولِ الشاشة

> **ما هذا الملفّ؟** كلُّ سطرٍ هنا **جدولُ قوقعةٍ لم يعد له قارئ**: بطاقتُه
> حُذفت من شاشتِها، وحقولُه صارت أعمدةً في جدولِ الشاشةِ الحقيقيّ.
> **وهو قائمةُ حذفٍ لاحقٍ لا سجلُّ توثيق** — لا يُدرَج فيه إلا ما انقطع قارئُه.

⛔ **ولا يُحذف جدولٌ منها قبلَ فحصَين**: أن لا يذكرَه المصدرُ (‏`grep`)،
وأن يكون خاليًا من صفٍّ لم تكتبه أداةُ التوليد.

## ① المرشَّحةُ للحذف — 44 جدولًا

| # | الشاشة | جدولُ القوقعةِ (يُحذف) | الجدولُ الحقيقيُّ الذي حلَّ محلَّه | أعمدةٌ نُقلت |
|---:|---|---|---|---:|
| 1 | `Approvals/hours_approval.php` | **`ops_hours_approval`** | `timesheet_approvals` | 19 |
| 2 | `Contracts/contracts.php` | **`sal_contracts`** | `contracts` | 43 |
| 3 | `Employees/employee_contracts.php` | **`hr_employee_contracts`** | `employee_contracts` | 14 |
| 4 | `Employees/employees.php` | **`hr_employees`** | `employees` | 14 |
| 5 | `FinRequests/request_form.php` | **`my_requests`** | `fin_requests` | 10 |
| 6 | `Finance/currencies_fin.php` | **`fina_currencies`** | `fin_currencies` | 9 |
| 7 | `Finance/dues_fin.php` | **`fina_dues`** | `fin_dues` | 12 |
| 8 | `Finance/periods_fin.php` | **`fina_periods_fin`** | `fin_financial_periods` | 16 |
| 9 | `Finance/tre_pay_batch.php` | **`tre_pay_batch`** | `tre_pay_batches` | 21 |
| 10 | `Maintenance/breakdown_intake.php` | **`mnt_breakdown_intake`** | `mnt_breakdown` | 42 |
| 11 | `Maintenance/external_repairs.php` | **`mnt_external_repairs`** | `mnt_external_repair` | 8 |
| 12 | `Maintenance/mnt_kpis.php` | **`mnt_kpis`** | `mnt_kpi_period` | 4 |
| 13 | `Maintenance/part_requests.php` | **`mnt_part_requests`** | `mnt_part_request` | 8 |
| 14 | `Maintenance/repeat_repairs.php` | **`mnt_repeat_repairs`** | `mnt_repeat_repair` | 13 |
| 15 | `Operations/monthly_plan.php` | **`ops_monthly_plan`** | `scr_op_monthly` | 25 |
| 16 | `Opportunities/client_need_rfq.php` | **`sal_client_need_rfq`** | `sal_client_needs` | 27 |
| 17 | `Portal/vp_approval_inbox.php` | **`exec_request_queue`** | `exec_approvals` | 19 |
| 18 | `Procurement/orders_proc.php` | **`prc_orders_proc`** | `proc_order` | 14 |
| 19 | `Procurement/proc_award_minutes.php` | **`prc_proc_award_minutes`** | `proc_award` | 16 |
| 20 | `Procurement/proc_delivery_track.php` | **`prc_proc_delivery_track`** | `proc_delivery_event` | 12 |
| 21 | `Procurement/proc_offers.php` | **`prc_proc_offers`** | `proc_offer` | 25 |
| 22 | `Procurement/proc_offers.php` | **`prc_offer_compare`** | `proc_offer` | 25 |
| 23 | `Procurement/proc_supplier_eval.php` | **`prc_proc_supplier_eval`** | `proc_supplier_eval` | 9 |
| 24 | `Procurement/requests_proc.php` | **`prc_requests`** | `proc_request` | 11 |
| 25 | `Procurement/warehouses.php` | **`wh_warehouses`** | `proc_warehouse` | 16 |
| 26 | `Procurement/wh_hazmat.php` | **`wh_hazmat`** | `proc_hazmat_control` | 16 |
| 27 | `Procurement/wh_issue_requests.php` | **`wh_issue_request_lines`** | `proc_issue_request` | 20 |
| 28 | `Procurement/wh_issue_requests.php` | **`wh_issue_requests`** | `proc_issue_request` | 20 |
| 29 | `Procurement/wh_month_close.php` | **`wh_month_close`** | `proc_wh_close` | 17 |
| 30 | `Procurement/wh_transfer.php` | **`wh_transfer`** | `proc_transfer` | 11 |
| 31 | `Projects/projects.php` | **`sal_projects`** | `project` | 21 |
| 32 | `Risk/risk_register.php` | **`rsk_risk_register`** | `risk_register` | 21 |
| 33 | `Risk/risk_treatments.php` | **`rsk_risk_treatments`** | `risk_treatments` | 15 |
| 34 | `Tickets/ticket_sla_config.php` | **`tkt_ticket_sla_config`** | `ticket_sla_policies` | 11 |
| 35 | `Timesheet/timesheet.php` | **`ops_timesheet`** | `timesheet` | 34 |
| 36 | `Transport/transfer_closure.php` | **`trp_transfer_closure`** | `trp_closure` | 11 |
| 37 | `Transport/transfer_damage_claims.php` | **`trp_transfer_damage_claims`** | `trp_damage_claim` | 12 |
| 38 | `Transport/transfer_origin_handover.php` | **`trp_transfer_origin_handover`** | `trp_origin_handover` | 9 |
| 39 | `Transport/transfer_permits.php` | **`trp_transfer_permits`** | `transfer_permits` | 8 |
| 40 | `Transport/transfer_requests.php` | **`trp_transfer_requests`** | `transfer_requests` | 12 |
| 41 | `Transport/transfer_trip_legs.php` | **`trp_transfer_trip_legs`** | `trp_trip_leg` | 11 |
| 42 | `Workforce/housing_units.php` | **`wf_housing_units`** | `housing_unit` | 8 |
| 43 | `Workforce/payroll_runs.php` | **`hr_payroll_runs`** | `payroll_runs` | 16 |
| 44 | `Workforce/worker_leave_absence.php` | **`hr_worker_leave_absence`** | `worker_leave_absence` | 9 |

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
DROP TABLE IF EXISTS `sal_contracts`;
DROP TABLE IF EXISTS `hr_employee_contracts`;
DROP TABLE IF EXISTS `hr_employees`;
DROP TABLE IF EXISTS `my_requests`;
DROP TABLE IF EXISTS `fina_currencies`;
DROP TABLE IF EXISTS `fina_dues`;
DROP TABLE IF EXISTS `fina_periods_fin`;
DROP TABLE IF EXISTS `tre_pay_batch`;
DROP TABLE IF EXISTS `mnt_breakdown_intake`;
DROP TABLE IF EXISTS `mnt_external_repairs`;
DROP TABLE IF EXISTS `mnt_kpis`;
DROP TABLE IF EXISTS `mnt_part_requests`;
DROP TABLE IF EXISTS `mnt_repeat_repairs`;
DROP TABLE IF EXISTS `ops_monthly_plan`;
DROP TABLE IF EXISTS `sal_client_need_rfq`;
DROP TABLE IF EXISTS `exec_request_queue`;
DROP TABLE IF EXISTS `prc_orders_proc`;
DROP TABLE IF EXISTS `prc_proc_award_minutes`;
DROP TABLE IF EXISTS `prc_proc_delivery_track`;
DROP TABLE IF EXISTS `prc_proc_offers`;
DROP TABLE IF EXISTS `prc_offer_compare`;
DROP TABLE IF EXISTS `prc_proc_supplier_eval`;
DROP TABLE IF EXISTS `prc_requests`;
DROP TABLE IF EXISTS `wh_warehouses`;
DROP TABLE IF EXISTS `wh_hazmat`;
DROP TABLE IF EXISTS `wh_issue_request_lines`;
DROP TABLE IF EXISTS `wh_issue_requests`;
DROP TABLE IF EXISTS `wh_month_close`;
DROP TABLE IF EXISTS `wh_transfer`;
DROP TABLE IF EXISTS `sal_projects`;
DROP TABLE IF EXISTS `rsk_risk_register`;
DROP TABLE IF EXISTS `rsk_risk_treatments`;
DROP TABLE IF EXISTS `tkt_ticket_sla_config`;
DROP TABLE IF EXISTS `ops_timesheet`;
DROP TABLE IF EXISTS `trp_transfer_closure`;
DROP TABLE IF EXISTS `trp_transfer_damage_claims`;
DROP TABLE IF EXISTS `trp_transfer_origin_handover`;
DROP TABLE IF EXISTS `trp_transfer_permits`;
DROP TABLE IF EXISTS `trp_transfer_requests`;
DROP TABLE IF EXISTS `trp_transfer_trip_legs`;
DROP TABLE IF EXISTS `wf_housing_units`;
DROP TABLE IF EXISTS `hr_payroll_runs`;
DROP TABLE IF EXISTS `hr_worker_leave_absence`;
```

> **والعكسُ قبلَ ذلك ممكن**: `database/migrations/2028_06_01_sheet_merge_fields_down.php`
> ينزع أعمدةَ الدمجِ من جداولِ العملِ، و`git revert` يُعيد الشاشات — فما دامت
> القواقعُ قائمةً فالجولةُ كلُّها قابلةٌ للردّ.
