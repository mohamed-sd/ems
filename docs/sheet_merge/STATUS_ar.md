# حالةُ دمجِ «سجل حقول الورقة» — ما أُنجز وما بقي

**التاريخ:** 2026-09-07 · **الشركة:** 4 · **المدى:** 95 شاشةً تحمل البطاقة

---

## ① الخلاصة

| | العدد |
|---|---:|
| **شاشاتٌ أُنجزت** (‏البطاقةُ حُذفت وحقولُها صارت أعمدةً في جدولِ الشاشة) | **45** |
| **شاشاتٌ بقيت** (‏البطاقةُ كما هي، ولم يُمَسّ شيء) | **50** |
| أعمدةٌ أُنشئت في قاعدةِ البيانات | 699 |
| أعمدةٌ قائمةٌ أُعيد استعمالُها | 15 |
| جداولُ عملٍ توسّعت | 45 |
| صفوفٌ مُلئت ببياناتٍ تجريبيّة | 2,402 |
| جداولُ قواقعَ مرشَّحةٌ للحذفِ لاحقًا | 44 |

---

## ② التوزيعُ على الإدارات

| الإدارة | أُنجزت | بقيت | الإجمالي |
|---|---:|---:|---:|
| `(الجذر)` | **0** | 1 | 1 |
| `Approvals` | **1** | 0 | 1 |
| `Clients` | **1** | 4 | 5 |
| `Contracts` | **1** | 3 | 4 |
| `Employees` | **2** | 0 | 2 |
| `FinRequests` | **1** | 1 | 2 |
| `Finance` | **4** | 7 | 11 |
| `Fleet` | **0** | 2 | 2 |
| `Maintenance` | **6** | 3 | 9 |
| `Operations` | **1** | 3 | 4 |
| `Opportunities` | **1** | 0 | 1 |
| `Portal` | **1** | 7 | 8 |
| `Procurement` | **12** | 6 | 18 |
| `Projects` | **1** | 0 | 1 |
| `Risk` | **2** | 1 | 3 |
| `Tickets` | **1** | 4 | 5 |
| `Timesheet` | **1** | 0 | 1 |
| `Transport` | **6** | 5 | 11 |
| `Workforce` | **3** | 3 | 6 |
| **المجموع** | **45** | **50** | **95** |

---

## ③ الشاشاتُ المُنجَزة — 45

| # | الشاشة | جدولُها في القاعدة | أعمدةٌ أُضيفت |
|---:|---|---|---:|
| 1 | `Approvals/hours_approval.php` | `timesheet_approvals` | 19 |
| 2 | `Clients/quotation_lines.php` | `sal_quotation_lines` | 22 |
| 3 | `Contracts/contracts.php` | `contracts` | 43 |
| 4 | `Employees/employee_contracts.php` | `employee_contracts` | 14 |
| 5 | `Employees/employees.php` | `employees` | 14 |
| 6 | `FinRequests/request_form.php` | `fin_requests` | 10 |
| 7 | `Finance/currencies_fin.php` | `fin_currencies` | 9 |
| 8 | `Finance/dues_fin.php` | `fin_dues` | 12 |
| 9 | `Finance/periods_fin.php` | `fin_financial_periods` | 16 |
| 10 | `Finance/tre_pay_batch.php` | `tre_pay_batches` | 21 |
| 11 | `Maintenance/breakdown_intake.php` | `mnt_breakdown` | 42 |
| 12 | `Maintenance/daily_care.php` | `mnt_daily_care` | 8 |
| 13 | `Maintenance/external_repairs.php` | `mnt_external_repair` | 8 |
| 14 | `Maintenance/mnt_kpis.php` | `mnt_kpi_period` | 4 |
| 15 | `Maintenance/part_requests.php` | `mnt_part_request` | 8 |
| 16 | `Maintenance/repeat_repairs.php` | `mnt_repeat_repair` | 13 |
| 17 | `Operations/monthly_plan.php` | `scr_op_monthly` | 25 |
| 18 | `Opportunities/client_need_rfq.php` | `sal_client_needs` | 27 |
| 19 | `Portal/vp_approval_inbox.php` | `exec_approvals` | 19 |
| 20 | `Procurement/orders_proc.php` | `proc_order` | 14 |
| 21 | `Procurement/po_match.php` | `proc_invoice_match` | 15 |
| 22 | `Procurement/proc_award_minutes.php` | `proc_award` | 16 |
| 23 | `Procurement/proc_delivery_track.php` | `proc_delivery_event` | 12 |
| 24 | `Procurement/proc_offers.php` | `proc_offer` | 25 |
| 25 | `Procurement/proc_supplier_eval.php` | `proc_supplier_eval` | 9 |
| 26 | `Procurement/requests_proc.php` | `proc_request` | 11 |
| 27 | `Procurement/warehouses.php` | `proc_warehouse` | 16 |
| 28 | `Procurement/wh_hazmat.php` | `proc_hazmat_control` | 16 |
| 29 | `Procurement/wh_issue_requests.php` | `proc_issue_request` | 20 |
| 30 | `Procurement/wh_month_close.php` | `proc_wh_close` | 17 |
| 31 | `Procurement/wh_transfer.php` | `proc_transfer` | 11 |
| 32 | `Projects/projects.php` | `project` | 21 |
| 33 | `Risk/risk_register.php` | `risk_register` | 21 |
| 34 | `Risk/risk_treatments.php` | `risk_treatments` | 15 |
| 35 | `Tickets/ticket_sla_config.php` | `ticket_sla_policies` | 11 |
| 36 | `Timesheet/timesheet.php` | `timesheet` | 34 |
| 37 | `Transport/transfer_closure.php` | `trp_closure` | 11 |
| 38 | `Transport/transfer_damage_claims.php` | `trp_damage_claim` | 12 |
| 39 | `Transport/transfer_origin_handover.php` | `trp_origin_handover` | 9 |
| 40 | `Transport/transfer_permits.php` | `transfer_permits` | 8 |
| 41 | `Transport/transfer_requests.php` | `transfer_requests` | 12 |
| 42 | `Transport/transfer_trip_legs.php` | `trp_trip_leg` | 11 |
| 43 | `Workforce/housing_units.php` | `housing_unit` | 8 |
| 44 | `Workforce/payroll_runs.php` | `payroll_runs` | 16 |
| 45 | `Workforce/worker_leave_absence.php` | `worker_leave_absence` | 9 |

---

## ④ الشاشاتُ الباقية — 50

**سببٌ واحدٌ يجمعها كلَّها:** لم يُقطَع بجدولِها فلم يُخمَّن —
**وعمودٌ يُنشَأ في الجدولِ الخطأ خطأٌ دائمٌ في القاعدة.**

| السبب | العدد |
|---|---:|
| جدول القاعدة غير مقطوع به | **32** |
| جدول القاعدة تطالب به أكثر من شاشة | **6** |
| جدول العرض ملتبس | **6** |
| لا جدول عرض في الصفحة | **5** |
| جدول القاعدة ملتبس: حسابات مصرفية أم أسطر كشف | **1** |

### أماكنُها بالتفصيل

**جدول القاعدة غير مقطوع به — 32 شاشة**

- `Contracts/collections.php`
- `Contracts/commercial_board.php`
- `FinRequests/effect_map.php`
- `Finance/cfo_daily_board_fin.php`
- `Finance/financial_statements_fin.php`
- `Finance/journal_form_fin.php`
- `Finance/tax_fin.php`
- `Finance/tre_beneficiary.php`
- `Finance/tre_liquidity_board.php`
- `Fleet/asset_full_history.php`
- `Fleet/fleet_schema_matrix.php`
- `Maintenance/workshop.php`
- `Operations/attendance.php`
- `Operations/monthly_close.php`
- `Operations/site_gate_equip.php`
- `Portal/my_achievement.php`
- `Portal/my_portal.php`
- `Portal/my_tasks.php`
- `Portal/vp_actions_followup.php`
- `Portal/vp_dashboard.php`
- `Portal/vp_departments.php`
- `Portal/vp_pending_actions.php`
- `Procurement/dashboard_proc.php`
- `Procurement/stock_proc.php`
- `Procurement/wh_count.php`
- `Risk/risk_reports.php`
- `Transport/transfer_fleet.php`
- `Transport/transfer_in_transit.php`
- `Transport/transfer_orders_report.php`
- `Workforce/project_contracts.php`
- `Workforce/recruitment_pipeline.php`
- `user_capacities.php`

**جدول القاعدة تطالب به أكثر من شاشة — 6 شاشة**

- `Clients/clients.php`
- `Clients/quotation_negotiation.php`
- `Clients/quotations.php`
- `Tickets/ticket_form.php`
- `Tickets/tickets_list.php`
- `Transport/transfer_order_form.php`

**جدول العرض ملتبس — 6 شاشة**

- `Contracts/claims.php`
- `Maintenance/preventive_plans.php`
- `Procurement/proc_packages.php`
- `Procurement/proc_po_amendments.php`
- `Procurement/proc_rfq.php`
- `Workforce/worker_worklog.php`

**لا جدول عرض في الصفحة — 5 شاشة**

- `Clients/client_contacts.php`
- `Maintenance/dashboard_mnt.php`
- `Tickets/gov_dept_crp.php`
- `Tickets/ticket_contextual_open.php`
- `Transport/transfer_dashboard.php`

**جدول القاعدة ملتبس: حسابات مصرفية أم أسطر كشف — 1 شاشة**

- `Finance/bank_reconciliation_fin.php`

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
