# بيانُ تعبئة القاعدة — كلُّ جدولٍ ومن أين يمتلئ

> **التكليف**: «أريد أن تكون جميعُ جداول النظام فيها بياناتٌ شبه حقيقيةٍ ومترابطة — **فقط لا تلمس جداول الأدوار والصفحات والصلاحيات**».
> **الحالة عند التحرير** (2026-08-02 · `75edc33` · فرع `feature/update0007`): **375 جدولًا + 6 مناظر** · **250 مملوءًا** · **125 فارغًا**.
> **القاعدة الحاكمة**: البياناتُ تُولَّد **من الدورة** لا بالحقن — فالجدولُ الذي يمتلئ بحقنٍ مباشر يمتلئ **بلا حدثٍ ولا قيدٍ ولا أثر**، فيبدو ممتلئًا وهو كاذب. وما لا مسارَ له في الشاشات يُبذر مرجعيًّا ويُعلَن.

---

## أ · المحظورةُ صراحةً — لا تُلمس

**الأدوارُ والصفحاتُ والصلاحيات** — تبقى كما هي بلا إدراجٍ ولا تعديلٍ ولا حذف:

`roles` · `role_permissions` · `report_role_permissions` · `modules` · `nav_items` · `nav_redirects` · `link_groups` · `permission_templates` · `permission_template_versions` · `template_permissions` · `permission_change_requests` · `permission_approval_steps` · `permission_exceptions` · `permission_audit_events` · `permission_review_cycles` · `permission_review_lines` · `effective_permissions` · `perm_shadow_diffs` · `visibility_keys` · `visibility_audit_log` · `sensitive_field_policies` · `sensitive_access_grants` · `sensitive_read_log` · `ownership_access_grants` · `portal_elements` · `workspace_cards` · `workspace_layouts`

> أُضيفت `portal_elements`/`workspace_*` لأنها **تعريفُ الشاشات وبطاقاتها** لا بياناتُ أعمال.

## ب · تحتاج إذنًا صريحًا — على الحدّ

| الجدول | لماذا هو على الحدّ | المقترح |
|---|---|---|
| `signing_authorities` | «سقوفُ التوقيع» — سلطةٌ ماليةٌ لا صلاحيةُ شاشة. وبدونها **يسقط اختبارُ «فوق السقف» و«انتهاء التفويض»** (شرطٌ في §9 من التكليف) | **يُبذر** ما لم تمنع |
| `org_assignments` · `org_assignment_types` · `assignment_capabilities` · `assignment_reporting_lines` | التكليفاتُ التنظيمية (من يشغل أي منصب وبأي نطاق) — بعضُها يشتق منه قدراتٌ تشبه الصلاحيات | **تُبذر** ما لم تمنع · و`assignment_capabilities` **تُترك** إن رأيتها من الصلاحيات |
| `users` | إنشاءُ حسابٍ جديد (مثلًا الإدارة العامة) ليس تعديلًا للأدوار، **لكنه يسند دورًا** | لا يُنشأ حسابٌ جديدٌ ولا يُعدَّل دور — وأستعمل الحسابات الـ25 القائمة |
| `roles` | **إنشاءُ دور «الإدارة العامة» صار ممنوعًا** بقرارك الأخير — وكان شرطَ اختبار «ثلاثُ موافقاتٍ من بينها المدير العام» | **يسقط** ذلك السيناريو، أو يُؤدَّى بدورٍ قائم (17 أو 1) بوصفه المفوَّض — قرارُك |

## ج · تمتلئ من الدورة التشغيلية — 78 جدولًا

هذه **لا تُبذر**: تمتلئ حين تمرّ الدورةُ من الشاشات والنقاط الرسمية، وامتلاؤها **هو** الشاهد.

**المبيعاتُ والعقود** — `contract_resource_plan` · `contract_price_terms` · `contract_price_revisions` · `contract_price_index_readings` · `contract_advances` · `guarantees` · `contract_snapshots` · `contract_lifecycle_events` · `change_approvals`
**الموردون** — `supplier_rfqs` · `rfq_lines` · `rfq_quotes` · `rfq_awards` · `supplier_capacity` · `supplier_charge_rules` · `supplier_penalty_rules` · `supplier_advance_requests` · `supplier_advance_recoveries` · `supplier_contract_closures` · `supplier_evaluations` · `supplier_evaluation_lines` · `supplier_evaluation_weights`
**الحاوياتُ والمقاعد** — `container_consumption` · `seat_assignments` (بعد حسم ن-٣) · `coverage_settlement_lines` · `substitute_coverages`
**الأسطولُ والمعدات** — `fleet_equipment_component` · `asset_hour_reconciliations` · `monthly_performance` · `monthly_performance_downtime`
**التشغيلُ والتايم شيت** — `daily_plans` · `daily_plan_lines` · `shift_period_logs` · `unit_effects` · `unit_state_changes` · `attendance_days`
**القوى العاملة** — `worker_qualification` · `worker_restricted_site` · `worker_evaluation_kpi` · `employee_advances` · `employee_contract_amendments` · `deduction_proposals` · `worker_settlement` · `worker_settlement_line` · `employee_final_settlements` · `employee_final_settlement_lines` · `payroll_runs` · `payroll_lines` · `payroll_run_blocks` · `payroll_deductions` · `payroll_time_inputs`
**التوظيف** — `rec_vacancies` · `rec_applications` · `rec_stage_log`
**الإيرادُ والتحصيل** — `tax_invoices` · `credit_debit_notes` · `fin_collection_allocations` · `fin_cash_forecasts` · `fin_tax_returns` · `bank_statements` · `bank_statement_lines` · `bank_recon_matches` · `waivers_reversals`
**التمويلُ والملكية** — `financing_operations` · `financed_assets` · `financing_installments` · `financing_deviations` · `asset_ownership_shares` · `entity_ownership` · `entity_licenses`
**الصيانةُ والمخصصات** — `fin_maint_provisions`
**الاستثناءاتُ والحوكمة** — `exception_requests` · `exception_approvals` · `exception_usages`
**النقلُ والأذون** — `permit_requests` · `permit_approval_actions` · `permit_status_history`
**البلاغات** — `ticket_responses` · `ticket_communications` · `ticket_holds` · `ticket_effects` · `ticket_attachments`
**بين الشركات** — `intercompany_dues` · `intercompany_loans` (تستلزم الشركةَ الثانية)

## د · بذرٌ مرجعيٌّ — قوائمُ وإعداداتٌ تسبق الدورة

تُملأ من المستندات أو باجتهادٍ معلن، **لأن الدورةَ لا تقوم بدونها**:

`persons` · `positions` · `person_positions` · `person_relationships` (سجلُّ الأشخاص والمناصب) · `payroll_settings` · `fin_maint_provision_rules` · `transfer_tariffs` · `approval_workflow_rules` · `governance_flags` · `entity_licenses` (الرخص) — **ومعها إثراءُ القوائم المملوءة أصلًا** إن كانت ناقصة: `units_of_measure` (7) · `equipments_types` (3 فقط) · `stop_reason_codes` (6) · `failure_codes` · `pay_models` (15) · `pay_components` (6) · `incentive_rules` (2) · `fin_chart_of_accounts` (16 فقط — دليلُ حساباتٍ هزيل) · `fin_cost_centers` (12) · `fin_fx_rates` (4) · `shift_patterns` (5) · `job_titles` (16).

## هـ · تمتلئ آليًّا — لا تُمَسّ بيدٍ

`ems_business_events` · `ems_event_deliveries` · `ems_processed_events` · `ems_job_queue` · `capacity_outbox` · `capacity_gap_watch` · `capacity_financial_event_links` · `processed_operations` · `action_execution_log` · `assignment_audit` · `audit_logs` · `activity_logs` · `guard_denials` · `contract_events` · `fleet_equipment_history` · `workspace_navigation_log` · `attendance_sweep_notices` · `fin_notifications` · `trs_notifications` · `tkt_notifications`

> **قاعدة:** إن بقي جدولٌ من هذه فارغًا بعد الدورة، فذلك **عيبٌ في المروحة أو الناشر** — لا نقصٌ في البيانات. وهذا من أثمن ما تكشفه التجربة.

## و · لا تُملأ عمدًا

`super_admins` · `super_admin_password_resets` · `company_user_password_resets` · `ems_sessions` (المخزن `files`) · `ems_event_dead_letter` · `capacity_shadow_diffs` · `worker_backup` · `api_tokens` · `tenants` · `drivercontracts` · `drivercontractequipments` · `driver_contract_notes` (مهجورةٌ بعد توحيد `employees`) · `workspace_prefs`

---

## ز · الجداولُ المملوءةُ ولكن هزيلة

ممتلئةٌ اسمًا وفارغةٌ معنًى — تُثرى ضمن الدورة لا بالحقن:

| الجدول | الآن | المستهدف |
|---|---|---|
| `clients` | 9 (أكثرُها تجريبيّ) | 3 حقيقيون + تنقيةُ الباقي |
| `project` | 35 (20 مولَّدةٌ آليًّا بلا عميل) | 5 حقيقية · **ومنها واحدٌ بثلاثة مواقع** |
| `sites` | 19 — واحدٌ لكل مشروع | 8 · بينها مشروعٌ بثلاثة |
| `contracts` | 10 | 6 بنماذج العمل الأربعة + منتهٍ + مجدَّد |
| `suppliers` | 16 | 12 مصنَّفون بأنواع معداتهم ومناطقهم |
| `equipments` | 26 | 40 (20 عاملة · 10 متقطعة · 5 احتياط · 5 خارج الخدمة) |
| `employees` | 57 | 60 مصنَّفين (40 تشغيل · 10 إداريون · 5 فنيون · 5 عمّالُ موردين) |
| `claims` | 1 | 3 لكل عقدٍ نافذ |
| `fin_journal_entries` | 9 | كلُّ أثرٍ ماليٍّ في الدورة |
| `timesheet` · `unit_entries` | 366 · 146 | ~7000 صفًّا عبر ثلاثة أشهر |
| `settlements` | 48 | 3 لكل مورد |
| `mnt_order` | 13 | 20 · منها 3 بانتظار قطعة |

---

## ح · ملاحظةٌ حاكمة — البيئةُ تتحرك تحت التجربة

`schema_migrations` تُظهر هجراتٍ طُبِّقت **اليوم أثناء هذه الجلسة**: `2026_11_02_act01` (14:33) · `2026_11_03_nav01` (14:40) · `2026_11_04_org_v4_restructure` (18:13) · `2026_11_05_screen_view_rows` (18:18) · `2026_11_06_recruitment_pipeline` (20:17). وعددُ الجداول ارتفع من **364 إلى 381** خلال الجلسة نفسِها، والفرعُ تحوَّل من `main` إلى `feature/update0007`.

**والتجربةُ لا تُدار على شجرةٍ متحركة**: كلُّ إصلاحٍ سيُنسب إلى تغييرٍ لم نُحدثه، وكلُّ إعادةٍ ستقارن بحالةٍ مختلفة. **المطلوب: تجميدُ الفرع والقاعدة طوال نافذة التجربة** — أو إعلانُ نافذةٍ يتوقف فيها التطوير.

**اللقطةُ المعتمدة الآن**: `storage/backups/uat_pre_20260802b_snapshot.sql` (19.0 م.ب · بعد الهجرات الخمس) — وتُلغى `uat_pre_20260802_snapshot.sql` السابقةُ لأنها تسبقها.
