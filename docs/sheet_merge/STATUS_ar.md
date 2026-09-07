# حالةُ دمجِ «سجل حقول الورقة» — ما أُنجز وما بقي

**التاريخ:** 2026-09-07 · **الشركة:** 4 · **المدى:** 95 شاشةً تحمل البطاقة

---

## ① الخلاصة

| | العدد |
|---|---:|
| **شاشاتٌ أُنجزت** (‏البطاقةُ حُذفت وحقولُها صارت أعمدةً في جدولِ الشاشة) | **87** |
| **شاشاتٌ بقيت** (‏البطاقةُ كما هي، ولم يُمَسّ شيء) | **8** |
| أعمدةٌ أُنشئت في قاعدةِ البيانات | 1195 |
| أعمدةٌ قائمةٌ أُعيد استعمالُها (‏تُقرَأ ولا تُكتَب) | 40 |
| جداولُ عملٍ توسّعت | 81 |
| جداولُ قواقعَ مرشَّحةٌ للحذفِ لاحقًا | 87 |

---

## ② التوزيعُ على الإدارات

| الإدارة | أُنجزت | بقيت | الإجمالي |
|---|---:|---:|---:|
| `(الجذر)` | **1** | 0 | 1 |
| `Approvals` | **1** | 0 | 1 |
| `Clients` | **4** | 1 | 5 |
| `Contracts` | **3** | 1 | 4 |
| `Employees` | **2** | 0 | 2 |
| `FinRequests` | **2** | 0 | 2 |
| `Finance` | **11** | 0 | 11 |
| `Fleet` | **1** | 1 | 2 |
| `Maintenance` | **8** | 1 | 9 |
| `Operations` | **4** | 0 | 4 |
| `Opportunities` | **1** | 0 | 1 |
| `Portal` | **7** | 1 | 8 |
| `Procurement` | **18** | 0 | 18 |
| `Projects` | **1** | 0 | 1 |
| `Risk` | **3** | 0 | 3 |
| `Tickets` | **3** | 2 | 5 |
| `Timesheet` | **1** | 0 | 1 |
| `Transport` | **10** | 1 | 11 |
| `Workforce` | **6** | 0 | 6 |
| **المجموع** | **87** | **8** | **95** |

---

## ③ الشاشاتُ المُنجَزة — 87

| # | الشاشة | جدولُها في القاعدة | أعمدةٌ أُضيفت |
|---:|---|---|---:|
| 1 | `Approvals/hours_approval.php` | `timesheet_approvals` | 19 |
| 2 | `Clients/clients.php` | `clients` | 26 |
| 3 | `Clients/quotation_lines.php` | `sal_quotation_lines` | 22 |
| 4 | `Clients/quotation_negotiation.php` | `quotations` | 18 |
| 5 | `Clients/quotations.php` | `quotations` | 22 |
| 6 | `Contracts/claims.php` | `claims` | 25 |
| 7 | `Contracts/collections.php` | `fin_payments` | 7 |
| 8 | `Contracts/contracts.php` | `contracts` | 43 |
| 9 | `Employees/employee_contracts.php` | `employee_contracts` | 14 |
| 10 | `Employees/employees.php` | `employees` | 14 |
| 11 | `FinRequests/effect_map.php` | `fin_event_links` | 10 |
| 12 | `FinRequests/request_form.php` | `fin_requests` | 10 |
| 13 | `Finance/bank_reconciliation_fin.php` | `fin_bank_statement_lines` | 14 |
| 14 | `Finance/cfo_daily_board_fin.php` | `fin_budget_lines` | 6 |
| 15 | `Finance/currencies_fin.php` | `fin_currencies` | 9 |
| 16 | `Finance/dues_fin.php` | `fin_dues` | 12 |
| 17 | `Finance/financial_statements_fin.php` | `fin_chart_of_accounts` | 5 |
| 18 | `Finance/journal_form_fin.php` | `fin_journal_entries` | 11 |
| 19 | `Finance/periods_fin.php` | `fin_financial_periods` | 16 |
| 20 | `Finance/tax_fin.php` | `fin_tax_transactions` | 12 |
| 21 | `Finance/tre_beneficiary.php` | `tre_beneficiaries` | 12 |
| 22 | `Finance/tre_liquidity_board.php` | `tre_cash_move` | 5 |
| 23 | `Finance/tre_pay_batch.php` | `tre_pay_batches` | 21 |
| 24 | `Fleet/asset_full_history.php` | `fleet_equipment_history` | 5 |
| 25 | `Maintenance/breakdown_intake.php` | `mnt_breakdown` | 42 |
| 26 | `Maintenance/daily_care.php` | `mnt_daily_care` | 8 |
| 27 | `Maintenance/external_repairs.php` | `mnt_external_repair` | 8 |
| 28 | `Maintenance/mnt_kpis.php` | `mnt_kpi_period` | 4 |
| 29 | `Maintenance/part_requests.php` | `mnt_part_request` | 8 |
| 30 | `Maintenance/preventive_plans.php` | `mnt_plan` | 17 |
| 31 | `Maintenance/repeat_repairs.php` | `mnt_repeat_repair` | 13 |
| 32 | `Maintenance/workshop.php` | `scr_workshop` | 15 |
| 33 | `Operations/attendance.php` | `scr_attendance` | 11 |
| 34 | `Operations/monthly_close.php` | `scr_monthly_close` | 18 |
| 35 | `Operations/monthly_plan.php` | `scr_op_monthly` | 25 |
| 36 | `Operations/site_gate_equip.php` | `scr_site_gate_equip` | 16 |
| 37 | `Opportunities/client_need_rfq.php` | `sal_client_needs` | 27 |
| 38 | `Portal/my_achievement.php` | `achievement_records` | 9 |
| 39 | `Portal/my_portal.php` | `work_items` | 7 |
| 40 | `Portal/my_tasks.php` | `work_items` | 8 |
| 41 | `Portal/vp_actions_followup.php` | `exec_decisions` | 12 |
| 42 | `Portal/vp_approval_inbox.php` | `exec_approvals` | 19 |
| 43 | `Portal/vp_departments.php` | `org_units` | 12 |
| 44 | `Portal/vp_pending_actions.php` | `exec_approvals` | 7 |
| 45 | `Procurement/dashboard_proc.php` | `proc_request` | 5 |
| 46 | `Procurement/orders_proc.php` | `proc_order` | 14 |
| 47 | `Procurement/po_match.php` | `proc_invoice_match` | 15 |
| 48 | `Procurement/proc_award_minutes.php` | `proc_award` | 16 |
| 49 | `Procurement/proc_delivery_track.php` | `proc_delivery_event` | 12 |
| 50 | `Procurement/proc_offers.php` | `proc_offer` | 25 |
| 51 | `Procurement/proc_packages.php` | `proc_package` | 18 |
| 52 | `Procurement/proc_po_amendments.php` | `proc_po_amendment` | 17 |
| 53 | `Procurement/proc_rfq.php` | `proc_rfq` | 12 |
| 54 | `Procurement/proc_supplier_eval.php` | `proc_supplier_eval` | 9 |
| 55 | `Procurement/requests_proc.php` | `proc_request` | 11 |
| 56 | `Procurement/stock_proc.php` | `proc_stock_state` | 6 |
| 57 | `Procurement/warehouses.php` | `proc_warehouse` | 16 |
| 58 | `Procurement/wh_count.php` | `proc_count_session` | 11 |
| 59 | `Procurement/wh_hazmat.php` | `proc_hazmat_control` | 16 |
| 60 | `Procurement/wh_issue_requests.php` | `proc_issue_request` | 20 |
| 61 | `Procurement/wh_month_close.php` | `proc_wh_close` | 17 |
| 62 | `Procurement/wh_transfer.php` | `proc_transfer` | 11 |
| 63 | `Projects/projects.php` | `project` | 21 |
| 64 | `Risk/risk_register.php` | `risk_register` | 21 |
| 65 | `Risk/risk_reports.php` | `risk_export_log` | 9 |
| 66 | `Risk/risk_treatments.php` | `risk_treatments` | 15 |
| 67 | `Tickets/ticket_form.php` | `tickets` | 26 |
| 68 | `Tickets/ticket_sla_config.php` | `ticket_sla_policies` | 11 |
| 69 | `Tickets/tickets_list.php` | `tickets` | 10 |
| 70 | `Timesheet/timesheet.php` | `timesheet` | 34 |
| 71 | `Transport/transfer_closure.php` | `trp_closure` | 11 |
| 72 | `Transport/transfer_damage_claims.php` | `trp_damage_claim` | 12 |
| 73 | `Transport/transfer_fleet.php` | `scr_transfer_fleet` | 13 |
| 74 | `Transport/transfer_in_transit.php` | `transfer_orders` | 12 |
| 75 | `Transport/transfer_order_form.php` | `transfer_orders` | 23 |
| 76 | `Transport/transfer_orders_report.php` | `trp_kpi_period` | 1 |
| 77 | `Transport/transfer_origin_handover.php` | `trp_origin_handover` | 9 |
| 78 | `Transport/transfer_permits.php` | `transfer_permits` | 8 |
| 79 | `Transport/transfer_requests.php` | `transfer_requests` | 12 |
| 80 | `Transport/transfer_trip_legs.php` | `trp_trip_leg` | 11 |
| 81 | `Workforce/housing_units.php` | `housing_unit` | 8 |
| 82 | `Workforce/payroll_runs.php` | `payroll_runs` | 16 |
| 83 | `Workforce/project_contracts.php` | `scr_project_contracts` | 15 |
| 84 | `Workforce/recruitment_pipeline.php` | `rec_applications` | 16 |
| 85 | `Workforce/worker_leave_absence.php` | `worker_leave_absence` | 9 |
| 86 | `Workforce/worker_worklog.php` | `worker_qualification` | 10 |
| 87 | `user_capacities.php` | `user_capacities` | 7 |

---

## ④ الشاشاتُ الباقية — 8

**سببٌ واحدٌ يجمعها كلَّها:** لم يُقطَع بجدولِها فلم يُخمَّن —
**وعمودٌ يُنشَأ في الجدولِ الخطأ خطأٌ دائمٌ في القاعدة.**

| السبب | العدد |
|---|---:|
| لا جدول عرض في الصفحة | **5** |
| قرار المالك: تخطَّ | **2** |
| قرار المالك: تخطَّ — شاشة مصفوفة مخطط لا بيانات عمل | **1** |

### أماكنُها بالتفصيل

**لا جدول عرض في الصفحة — 5 شاشة**

- `Clients/client_contacts.php`
- `Maintenance/dashboard_mnt.php`
- `Tickets/gov_dept_crp.php`
- `Tickets/ticket_contextual_open.php`
- `Transport/transfer_dashboard.php`

**قرار المالك: تخطَّ — 2 شاشة**

- `Contracts/commercial_board.php`
- `Portal/vp_dashboard.php`

**قرار المالك: تخطَّ — شاشة مصفوفة مخطط لا بيانات عمل — 1 شاشة**

- `Fleet/fleet_schema_matrix.php`

---

## ⑤ ما يلزم لإكمالِ الباقي

سؤالٌ واحدٌ لكلِّ شاشة: **أيُّ جدولٍ في القاعدةِ يملك حقولَ هذه الشاشة؟**
وبإجابتِه تمرُّ الشاشةُ في الأداةِ نفسِها بلا عملٍ جديد:

```bash
php database/migrations/2028_06_01_sheet_merge_fields.php   # يُنشئ ما نقص
php tools/sheet_merge/seed_demo.php --apply                 # يملأ البيانات
```

## ⑥ التراجع

```bash
php database/migrations/2028_06_01_sheet_merge_fields_down.php   # ينزع الـ699 عمودًا
git checkout -- .                                                 # يُعيد الشاشات
```

> والقواقعُ الـ44 **لم تُحذف بعد** — فما دامت قائمةً فالجولةُ كلُّها قابلةٌ للردّ.
تفصيلُها في `docs/sheet_merge/RETIRED_TABLES_ar.md`.
