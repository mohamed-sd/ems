# `RPR-02` #٤ — ربطُ المعاملةِ بآلةِ حالتِها

> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/rpr02_state_model_bind.php --md` · اللقطة `SNAP-99ba83f7-20260830-100210`

## الصفرُ كان جسرًا مكسورًا لا غيابَ آلة

آلاتُ الحالةِ **مؤلَّفةٌ فعلًا**: **117** كيانًا في عشرةِ جداولِ موجاتٍ بانتقالاتِها وشروطِها ومستنداتِها وبوّاباتِها. **لكنّها مؤلَّفةٌ باسمِ الكيانِ المفاهيميِّ المفرد** والسجلُّ يحمل **اسمَ الجدولِ المقيسِ الجمعَ** ⇒ فالجسرُ يسقط على اصطلاحِ تسميةٍ لا على غياب. ⛔ **وصفرٌ سببُه جسرٌ مكسورٌ يُقرأ «لا آلاتِ حالةٍ» وهو كذبٌ على النفس.**

## قواعدُ الجسرِ الثلاث

| القاعدة | الأسطح | المعنى |
|---|---:|---|
| `M1_EXACT` | **7** | الاسمان متطابقان حرفًا |
| `M2_PLURAL` | **8** | الجدولُ جمعٌ والآلةُ مفردٌ |
| `M3_SINGULAR` | **0** | الجدولُ مفردٌ والآلةُ جمعٌ |
| ⛔ `BACKLOG` | **112** | لا آلةَ مؤلَّفةً — على 108 كيانًا |

⛔ **وشرطٌ صلبٌ فوقَ الثلاث**: الاسمُ المقابلُ **جدولٌ قائمٌ في المخطَّط** — فلا يُربط سطحٌ بآلةِ حالةِ كيانٍ لا وجودَ له.

⇒ المقياسُ **#٤ 11.8٪** (‏15 من 127).

## الباقي — **تأليفٌ لا قياس**

تأليفُ آلةِ حالةٍ يعني لكلِّ انتقال: **مالكَه · شرطَه المسبقَ · مستندَه الرسميَّ · بوّابةَ اعتمادِه · قاعدةَ إعادةِ الفتحِ · قاعدةَ التصحيح** — وممنوعَه الصريحَ مُسبَّبًا. **وهذه أحكامُ أعمالٍ تُؤلَّف ولا تُقاس**، و§٥ المحظور ④ يمنع تلفيقَها. ◆ **والمرجعُ الأجوفُ أسوأُ من غيابِه**: يُقرأ خُضرةً وهو فراغ.

| الكيان | أسطحُه |
|---|---:|
| `iaf_findings` | 3 |
| `fin_notifications` | 2 |
| `work_items` | 2 |
| `action_execution_log` | 1 |
| `asset_ownership_shares` | 1 |
| `dr_drills` | 1 |
| `ems_event_deliveries` | 1 |
| `ems_job_queue` | 1 |
| `entity_licenses` | 1 |
| `entity_ownership` | 1 |
| `equipment_drivers` | 1 |
| `exec_audit_reports` | 1 |
| `fin_acc_specializations` | 1 |
| `fin_accountants` | 1 |
| `fin_approval_chain` | 1 |
| `fin_authority_caps` | 1 |
| `fin_backflow_log` | 1 |
| `fin_cycle_stages` | 1 |
| `fin_dues` | 1 |
| `fin_obl_avoidance` | 1 |
| `fin_obl_register` | 1 |
| `fin_obl_schedule` | 1 |
| `fin_role_migration` | 1 |
| `fin_routing_matrix` | 1 |
| `fin_treasury_roles` | 1 |
| `financing_deviations` | 1 |
| `gov_authority_grants` | 1 |
| `gov_authority_limits` | 1 |
| `gov_doc_registry` | 1 |
| `gov_doc_variance` | 1 |
| `gov_ladders` | 1 |
| `gov_orphan_links` | 1 |
| `guard_policies` | 1 |
| `iaf_authorities` | 1 |
| `iaf_charter` | 1 |
| `iaf_competencies` | 1 |
| `iaf_engagements` | 1 |
| `iaf_independence` | 1 |
| `iaf_plan` | 1 |
| `iaf_quality_reviews` | 1 |
| `iaf_universe` | 1 |
| `iaf_workpapers` | 1 |
| `impersonation_sessions` | 1 |
| `legal_entities` | 1 |
| `nav_redirects` | 1 |
| `ownership_access_grants` | 1 |
| `payroll_lines` | 1 |
| `payroll_settings` | 1 |
| `permission_template_versions` | 1 |
| `personal_notifications` | 1 |
| `persons` | 1 |
| `portal_activity_log` | 1 |
| `proc_stock_move` | 1 |
| `rec_applications` | 1 |
| `requests` | 1 |
| `rfq_awards` | 1 |
| `risk_kris` | 1 |
| `scr_access_review` | 1 |
| `scr_asset_recon` | 1 |
| `scr_attendance` | 1 |
| `scr_break_glass` | 1 |
| `scr_business_models` | 1 |
| `scr_canonical_names` | 1 |
| `scr_code_bridge` | 1 |
| `scr_consumption_rate` | 1 |
| `scr_contract_review` | 1 |
| `scr_deductions` | 1 |
| `scr_doc_types` | 1 |
| `scr_equipment_quota` | 1 |
| `scr_equipment_sourcing` | 1 |
| `scr_exceptions` | 1 |
| `scr_fin_assets` | 1 |
| `scr_fin_changes` | 1 |
| `scr_fin_models` | 1 |
| `scr_founding_mode` | 1 |
| `scr_guards` | 1 |
| `scr_monthly_close` | 1 |
| `scr_op_codes` | 1 |
| `scr_op_monthly` | 1 |
| `scr_op_qual` | 1 |
| `scr_ownership_links` | 1 |
| `scr_perm_explain` | 1 |
| `scr_portal_users` | 1 |
| `scr_production` | 1 |
| `scr_project_contracts` | 1 |
| `scr_release_stamp` | 1 |
| `scr_rotation` | 1 |
| `scr_sensitive_fields` | 1 |
| `scr_shift_log` | 1 |
| `scr_site_gate_equip` | 1 |
| `scr_site_gate_person` | 1 |
| `scr_site_shift_plan` | 1 |
| `scr_site_work_calendar` | 1 |
| `scr_state_machines` | 1 |
| `scr_transfer_fleet` | 1 |
| `scr_transfer_permits` | 1 |
| `scr_unbilled` | 1 |
| `scr_unit_perf` | 1 |
| `scr_workshop` | 1 |
| `sec_sod_pairs` | 1 |
| `signing_authorities` | 1 |
| `task_evidence` | 1 |
| `ticket_effects` | 1 |
| `ticket_events` | 1 |
| `ticket_workstreams` | 1 |
| `timesheet_failure_hours` | 1 |
| `unit_effects` | 1 |
| `workspace_navigation_log` | 1 |
