# حالةُ دمجِ «سجل حقول الورقة» — ما أُنجز وما بقي

**التاريخ:** 2026-09-07 · **الشركة:** 4 · **المدى:** 95 شاشةً تحمل البطاقة

---

## ① الخلاصة

| | العدد |
|---|---:|
| **شاشاتٌ أُنجزت** (‏البطاقةُ حُذفت وحقولُها صارت أعمدةً في جدولِ الشاشة) | **70** |
| **شاشاتٌ بقيت** (‏البطاقةُ كما هي، ولم يُمَسّ شيء) | **25** |
| أعمدةٌ أُنشئت في قاعدةِ البيانات | 1028 |
| أعمدةٌ قائمةٌ أُعيد استعمالُها (‏تُقرَأ ولا تُكتَب) | 38 |
| جداولُ عملٍ توسّعت | 67 |
| جداولُ قواقعَ مرشَّحةٌ للحذفِ لاحقًا | 70 |

---

## ② التوزيعُ على الإدارات

| الإدارة | أُنجزت | بقيت | الإجمالي |
|---|---:|---:|---:|
| `(الجذر)` | **0** | 1 | 1 |
| `Approvals` | **1** | 0 | 1 |
| `Clients` | **4** | 1 | 5 |
| `Contracts` | **3** | 1 | 4 |
| `Employees` | **2** | 0 | 2 |
| `FinRequests` | **2** | 0 | 2 |
| `Finance` | **8** | 3 | 11 |
| `Fleet` | **1** | 1 | 2 |
| `Maintenance` | **7** | 2 | 9 |
| `Operations` | **1** | 3 | 4 |
| `Opportunities` | **1** | 0 | 1 |
| `Portal` | **3** | 5 | 8 |
| `Procurement` | **16** | 2 | 18 |
| `Projects` | **1** | 0 | 1 |
| `Risk` | **3** | 0 | 3 |
| `Tickets` | **3** | 2 | 5 |
| `Timesheet` | **1** | 0 | 1 |
| `Transport` | **9** | 2 | 11 |
| `Workforce` | **4** | 2 | 6 |
| **المجموع** | **70** | **25** | **95** |

---

## ③ الشاشاتُ المُنجَزة — 70

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
| 14 | `Finance/currencies_fin.php` | `fin_currencies` | 9 |
| 15 | `Finance/dues_fin.php` | `fin_dues` | 12 |
| 16 | `Finance/journal_form_fin.php` | `fin_journal_entries` | 11 |
| 17 | `Finance/periods_fin.php` | `fin_financial_periods` | 16 |
| 18 | `Finance/tax_fin.php` | `fin_tax_transactions` | 12 |
| 19 | `Finance/tre_beneficiary.php` | `tre_beneficiaries` | 12 |
| 20 | `Finance/tre_pay_batch.php` | `tre_pay_batches` | 21 |
| 21 | `Fleet/asset_full_history.php` | `fleet_equipment_history` | 5 |
| 22 | `Maintenance/breakdown_intake.php` | `mnt_breakdown` | 42 |
| 23 | `Maintenance/daily_care.php` | `mnt_daily_care` | 8 |
| 24 | `Maintenance/external_repairs.php` | `mnt_external_repair` | 8 |
| 25 | `Maintenance/mnt_kpis.php` | `mnt_kpi_period` | 4 |
| 26 | `Maintenance/part_requests.php` | `mnt_part_request` | 8 |
| 27 | `Maintenance/preventive_plans.php` | `mnt_plan` | 17 |
| 28 | `Maintenance/repeat_repairs.php` | `mnt_repeat_repair` | 13 |
| 29 | `Operations/monthly_plan.php` | `scr_op_monthly` | 25 |
| 30 | `Opportunities/client_need_rfq.php` | `sal_client_needs` | 27 |
| 31 | `Portal/vp_actions_followup.php` | `exec_decisions` | 12 |
| 32 | `Portal/vp_approval_inbox.php` | `exec_approvals` | 19 |
| 33 | `Portal/vp_departments.php` | `org_units` | 12 |
| 34 | `Procurement/orders_proc.php` | `proc_order` | 14 |
| 35 | `Procurement/po_match.php` | `proc_invoice_match` | 15 |
| 36 | `Procurement/proc_award_minutes.php` | `proc_award` | 16 |
| 37 | `Procurement/proc_delivery_track.php` | `proc_delivery_event` | 12 |
| 38 | `Procurement/proc_offers.php` | `proc_offer` | 25 |
| 39 | `Procurement/proc_packages.php` | `proc_package` | 18 |
| 40 | `Procurement/proc_po_amendments.php` | `proc_po_amendment` | 17 |
| 41 | `Procurement/proc_supplier_eval.php` | `proc_supplier_eval` | 9 |
| 42 | `Procurement/requests_proc.php` | `proc_request` | 11 |
| 43 | `Procurement/stock_proc.php` | `proc_stock_state` | 6 |
| 44 | `Procurement/warehouses.php` | `proc_warehouse` | 16 |
| 45 | `Procurement/wh_count.php` | `proc_count_session` | 11 |
| 46 | `Procurement/wh_hazmat.php` | `proc_hazmat_control` | 16 |
| 47 | `Procurement/wh_issue_requests.php` | `proc_issue_request` | 20 |
| 48 | `Procurement/wh_month_close.php` | `proc_wh_close` | 17 |
| 49 | `Procurement/wh_transfer.php` | `proc_transfer` | 11 |
| 50 | `Projects/projects.php` | `project` | 21 |
| 51 | `Risk/risk_register.php` | `risk_register` | 21 |
| 52 | `Risk/risk_reports.php` | `risk_export_log` | 9 |
| 53 | `Risk/risk_treatments.php` | `risk_treatments` | 15 |
| 54 | `Tickets/ticket_form.php` | `tickets` | 26 |
| 55 | `Tickets/ticket_sla_config.php` | `ticket_sla_policies` | 11 |
| 56 | `Tickets/tickets_list.php` | `tickets` | 10 |
| 57 | `Timesheet/timesheet.php` | `timesheet` | 34 |
| 58 | `Transport/transfer_closure.php` | `trp_closure` | 11 |
| 59 | `Transport/transfer_damage_claims.php` | `trp_damage_claim` | 12 |
| 60 | `Transport/transfer_in_transit.php` | `transfer_orders` | 12 |
| 61 | `Transport/transfer_order_form.php` | `transfer_orders` | 23 |
| 62 | `Transport/transfer_orders_report.php` | `trp_kpi_period` | 1 |
| 63 | `Transport/transfer_origin_handover.php` | `trp_origin_handover` | 9 |
| 64 | `Transport/transfer_permits.php` | `transfer_permits` | 8 |
| 65 | `Transport/transfer_requests.php` | `transfer_requests` | 12 |
| 66 | `Transport/transfer_trip_legs.php` | `trp_trip_leg` | 11 |
| 67 | `Workforce/housing_units.php` | `housing_unit` | 8 |
| 68 | `Workforce/payroll_runs.php` | `payroll_runs` | 16 |
| 69 | `Workforce/recruitment_pipeline.php` | `rec_applications` | 16 |
| 70 | `Workforce/worker_leave_absence.php` | `worker_leave_absence` | 9 |

---

## ④ الشاشاتُ الباقية — 25

**سببٌ واحدٌ يجمعها كلَّها:** لم يُقطَع بجدولِها فلم يُخمَّن —
**وعمودٌ يُنشَأ في الجدولِ الخطأ خطأٌ دائمٌ في القاعدة.**

| السبب | العدد |
|---|---:|
| جدول القاعدة غير مقطوع به | **18** |
| لا جدول عرض في الصفحة | **5** |
| جدول العرض ملتبس | **2** |

### أماكنُها بالتفصيل

**جدول القاعدة غير مقطوع به — 18 شاشة**

- `Contracts/commercial_board.php`
- `Finance/cfo_daily_board_fin.php`
- `Finance/financial_statements_fin.php`
- `Finance/tre_liquidity_board.php`
- `Fleet/fleet_schema_matrix.php`
- `Maintenance/workshop.php`
- `Operations/attendance.php`
- `Operations/monthly_close.php`
- `Operations/site_gate_equip.php`
- `Portal/my_achievement.php`
- `Portal/my_portal.php`
- `Portal/my_tasks.php`
- `Portal/vp_dashboard.php`
- `Portal/vp_pending_actions.php`
- `Procurement/dashboard_proc.php`
- `Transport/transfer_fleet.php`
- `Workforce/project_contracts.php`
- `user_capacities.php`

**لا جدول عرض في الصفحة — 5 شاشة**

- `Clients/client_contacts.php`
- `Maintenance/dashboard_mnt.php`
- `Tickets/gov_dept_crp.php`
- `Tickets/ticket_contextual_open.php`
- `Transport/transfer_dashboard.php`

**جدول العرض ملتبس — 2 شاشة**

- `Procurement/proc_rfq.php`
- `Workforce/worker_worklog.php`

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
