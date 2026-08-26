# قراراتٌ تنتظر المالك — بوّابةُ W13.5

> **البند 14 من أمرِ 2026-08-26**: ملفٌّ واحدٌ `الحالي ← المقترَح ← المطلوبُ حسمُه`.
> ⛔ **مولَّدٌ من المخزن — لا يُحرَّر يدويًّا**: `php tools/repair01_w135_owner_file.php`.
> **تاريخُ القياس:** 2026-08-27

⛔ **والمقترَحُ هنا معروضٌ لا مكتوب.** عمودُ التوصيةِ ليس قرارَ مالك — وهذا
بالضبط ما كلَّفَنا `DEC-OPEN-16`. فلا يتغيّر المخزنُ حتّى تُجيب.

---

## ① قراراتٌ بنيويّةٌ — نصٌّ في المخزنِ بلا سندٍ منك

المراجعةُ العكسيّةُ (‏البند 12) قارنت المخزنَ بمصنَّفِك. هذانِ يحملانِ حكمًا
**وخانةُ قرارِ المالكِ خاليةٌ في ورقتَيه معًا**.

---

## ② حاجبٌ بنيويٌّ مفتوحٌ — يوقف المرحلةَ الخامسةَ عشرة

### `DEC-OPEN-17`  ·  من يملك Entity Routing Registry وكتالوج أنواع الطلب؟

- **الحالةُ القائمة:** السجل مركزي والمالك غير مسمًّى
- **الخيارات:** الحوكمة · البلاغات · مشترك
- **التوصية:** الحوكمة تملكه وكل إدارة تسجّل نوعها بمرجعها

---

## ③ أسماءٌ معروضةٌ غيرُ معتمَدة — 63 اسمًا

**الحالي** هو الاسمُ المعروضُ اليومَ فعلًا، و**المقترَح** هو هو —
فالمطلوبُ **توقيعٌ لا تسمية**. وما تريد تغييرَه اكتبْ بدلَه.

| الاسمُ المعروض | المجموعة | الإدارة | المسار |
|---|---|---|---|
| التسعير اليومي | إضافات المالك | — | `Finance/daily_pricing_fin.php` |
| إغلاق البلاغ وتأكيده | الإغلاق وانضباطه | — | `Tickets/admin_close.php` |
| الاستقبال والتصنيف لتوجيه البلاغات الجديدة | التوجيه الآلي | — | `Tickets/intake_classify.php` |
| تصنيفات الأعطال والبلاغات | السجلات والملفات | — | `Tickets/ticket_categories_config.php` |
| مهل البلاغات وتصعيدها | المهل والتصعيد | — | `Tickets/ticket_escalation_config.php` |
| أزمنة الاستجابة والإنجاز | اللوحة | — | `Tickets/ticket_sla_config.php` |
| إعدادات البلاغات للأنواع والمهل والتصعيد | الأنواع والمهل والتصعيد | — | `Tickets/ticket_types_config.php` |
| تقرير من يتأخر ومن لا يستجيب | نقرأ تقارير إدارتي | — | `Tickets/watchtower.php` |
| دفتر الأسعار بالشرائح | التأجير قصير الأمد | DEP-01 | `Clients/rate_books.php` |
| المناقصات | العمليات | DEP-01 | `Clients/tenders.php` |
| اللوحة التجارية للعقود | التقارير والتحليلات | DEP-01 | `Contracts/commercial_board.php` |
| المعدات | سجل الأصول | DEP-04 | `Equipments/equipments_fleet.php` |
| تصنيف الأعطال وأسبابها | استقبال العطل وتشخيصه | DEP-04 | `Equipments/manage_failure_codes.php` |
| ربط الأصل بساعات تشغيله | الإهلاك | DEP-04 | `Finance/asset_hours_link.php` |
| احتساب إهلاك الفترة | الإهلاك | DEP-04 | `Finance/depr_run.php` |
| أسعار الصرف | التأسيس المرجعي | DEP-05 | `Finance/currencies_fin.php` |
| مخصص الصيانة والعمرات | الإقفال والموازنة | DEP-05 | `Finance/maintenance_provision_fin.php` |
| كشف حساب المورد الشهري | الذمم الدائنة | DEP-05 | `Finance/supplier_statement_fin.php` |
| صندوق بلاغات الإدارة | استقبال البلاغات | DEP-05 | `Tickets/dept_inbox.php` |
| الخزينة والصناديق | التأسيس المرجعي | DEP-06 | `Finance/accounts_fin.php` |
| سقوف سلطة الالتزام والدفع | السقوف وفصل الواجبات | DEP-06 | `Finance/tre_authority_caps.php` |
| مراحل دورتي الدفع والقبض | السقوف وفصل الواجبات | DEP-06 | `Finance/tre_cycle_stages.php` |
| مصفوفة فصل الواجبات الثلاثة عشر | السقوف وفصل الواجبات | DEP-06 | `Finance/tre_sod_matrix.php` |
| الأدوار الوظيفية | البيانات المرجعية | DEP-07 | `Employees/employee_roles.php` |
| المسميات الوظيفية | التوظيف والتعاقد | DEP-07 | `Employees/job_titles.php` |
| معالج إعداد الموظف | السجلات الرئيسية | DEP-08 | `admin/sec_employee_wizard.php` |
| قوالب الصلاحيات | الإعدادات | DEP-08 | `Governance/auth_profiles.php` |
| حدود المبالغ | التدقيق والمراجعة | DEP-08 | `Governance/authority_caps.php` |
| جلسات النيابة | المعاملات والسجلات | DEP-08 | `Governance/impersonations.php` |
| التراخيص والكفالات | الحوكمة | DEP-08 | `Governance/licenses_guarantees.php` |
| التفويض بالتوقيع | الاعتمادات العليا | DEP-08 | `Governance/signing_authority.php` |
| مواقع التنفيذ | السجلات الرئيسية | DEP-08 | `Projects/sites.php` |
| البلاغات المتكررة وسببها الجذري | متابعة الصيانة | DEP-10 | `Tickets/ticket_recurrence.php` |
| تحويل البلاغ وتفريعه لمسارات | استقبال العطل وتشخيصه | DEP-10 | `Tickets/ticket_workstreams_board.php` |
| لوحة مدير التشغيل | التقارير والتحليلات | DEP-11 | `admin/ops_manager_board.php` |
| خريطة التشغيل اليومية | التقارير والتحليلات | DEP-11 | `movement/map_page.php` |
| الورديات | العمليات | DEP-11 | `movement/movement_operations.php` |
| تسويات الموظفين | العمليات | DEP-13 | `Workforce/employee_settlements.php` |
| عقود العاملين | العمليات | DEP-13 | `Workforce/worker_contract.php` |
| ملف العامل التشغيلي | المعاملات والسجلات | DEP-13 | `Workforce/worker_register.php` |
| سجل الأحداث التشغيلية | التقارير والتحليلات | DEP-13 | `Workforce/worker_worklog.php` |
| احتياج القوى والتخطيط | التوظيف والتعاقد | DEP-13 | `Workforce/workforce_requirement.php` |
| قواعد تحميل تكلفة الترحيل | البيانات المرجعية | DEP-15 | `Transport/transfer_cost_rules_config.php` |
| إعدادات الترحيل | البيانات المرجعية | DEP-15 | `Transport/transfer_types_config.php` |
| المواقع | البيانات المرجعية | DEP-15 | `Transport/trs_locations_config.php` |
| كتالوج الأصناف والقطع الحرجة | استقبال الاحتياج | DEP-16 | `Procurement/items_proc.php` |
| حدود إعادة الطلب | البيانات المرجعية | DEP-16 | `Procurement/reordering_proc.php` |
| موردو المشتريات | البيانات المرجعية | DEP-16 | `Procurement/suppliers_proc.php` |
| المراسلات | العمليات | EX-CEO | `chats/index.php` |
| طلباتي | المركز التنفيذي | EX-CEO | `FinRequests/my_requests.php` |
| الاعتمادات المتأخرة والوثائق | التقارير والتحليلات | EX-CEO | `Reports/approval_lag_report.php` |
| سجل الوحدات اليومية | التقارير والتحليلات | EX-CEO | `Reports/daily_units_report.php` |
| هامش الربح للعقد والواقعة | الرقابة العليا | EX-CEO | `Reports/margin_report.php` |
| مركز التقارير التنفيذية | الأداء المؤسسي | EX-CEO | `Reports/reports.php` |
| صلاحيات المراجع داخل النظام | الميثاق والخطة والمهام | IAF | `Audit/iaf_authorities.php` |
| اختصاصات المراجعة العشرون | الميثاق والخطة والمهام | IAF | `Audit/iaf_competencies.php` |
| الرئيسية | مساحتي الشخصية | PLATFORM | `main/global_search.php` |
| قريبا | مجال العمل اليومي | PLATFORM | `main/soon.php` |
| إيكوبيشن ¦ بطاقة المستخدم | مجال العمل اليومي | PLATFORM | `main/user_profile.php` |
| المعاونون والنيابة المؤقتة | الحسابات والأدوار | WS-MY | `main/all_assistants.php` |
| موافقات التكليف | التقارير للجهة المشرفة | WS-MY | `Portal/ceo_assignments.php` |
| تقارير المراجعة الداخلية | التقارير للجهة المشرفة | WS-MY | `Portal/ceo_audit_reports.php` |
| تقارير الإدارة التنفيذية | التقارير والتصدير | WS-MY | `Portal/ceo_reports.php` |

---

## ④ أسطحٌ حيّةٌ بلا إدارةٍ مالكة — 37 سطحًا

**كلُّها في جذرِ المستودعِ بلا مجلَّدٍ** — فلا إشارةَ فيها تُشتقُّ منها ملكيّةٌ.
وقاعدةُ «مالكُ المجلَّدِ الغالب» لا تنطبق، **ونسبتُها بالتخمينِ تخترع ملكيّة**.

والبند 18 يقول: قد يكون بعضُها **قدرةَ منصّةٍ لا إدارة** — فاكتبْ أمامَ كلٍّ
إمّا رمزَ إدارةٍ (`DEP-01`..`DEP-17`) وإمّا `PLATFORM` وإمّا `RETIRE`.

| السطح | حكمُ ملكيّتِه اليوم | حارسُه | الإدارة؟ |
|---|---|---|---|
| `admin_close.php` | LEGACY | SELF_EARLY |  |
| `aprovment.php` | LEGACY | SHELL |  |
| `contract_report.php` | LEGACY | SELF_EARLY |  |
| `contractall.php` | LEGACY | SELF_EARLY |  |
| `daily_pricing_fin.php` | LEGACY | SELF_EARLY |  |
| `deliy.php` | LEGACY | SHELL |  |
| `deriver.php` | LEGACY | SHELL |  |
| `driverAndsupplerscontract.php` | LEGACY | SELF_EARLY |  |
| `employee_card.php` | LEGACY | SHELL |  |
| `equipment_hours_preventive.php` | LEGACY | SHELL |  |
| `equipments_drivers.php` | LEGACY | SELF_EARLY |  |
| `equipments_reports.php` | LEGACY | SHELL |  |
| `failure_report.php` | LEGACY | SHELL |  |
| `fleet_failures.php` | LEGACY | SHELL |  |
| `guard_denials_report.php` | LEGACY | SHELL |  |
| `inquiry.php` | LEGACY | SHELL |  |
| `intake_classify.php` | LEGACY | SELF_EARLY |  |
| `monthly_plan.php` | LEGACY | SELF_EARLY |  |
| `move_oprators.php` | LEGACY | SELF_EARLY |  |
| `new_reports.php` | LEGACY | SHELL |  |
| `project_drivers.php` | LEGACY | SELF_EARLY |  |
| `project_profile.php` | LEGACY | SHELL |  |
| `projects_reports.php` | LEGACY | SHELL |  |
| `read_log.php` | LEGACY | SELF_EARLY |  |
| `readiness_cert.php` | LEGACY | SELF_EARLY |  |
| `return_to_service.php` | LEGACY | SELF_EARLY |  |
| `select_project.php` | LEGACY | SHELL |  |
| `showcontractemployee.php` | LEGACY | SELF_EARLY |  |
| `ticket_categories_config.php` | LEGACY | SHELL |  |
| `ticket_escalation_config.php` | LEGACY | SHELL |  |
| `ticket_sla_config.php` | LEGACY | SHELL |  |
| `ticket_types_config.php` | LEGACY | SHELL |  |
| `timesheet_reports.php` | LEGACY | SHELL |  |
| `timesheetdeliy.php` | LEGACY | SHELL |  |
| `view_timesheet.php` | LEGACY | SELF_EARLY |  |
| `watchtower.php` | LEGACY | SHELL |  |
| `wh_returns.php` | LEGACY | SELF_EARLY |  |

---

## ما لا ينتظرك — أُنجز وأُثبت

| البند | المقيس |
|---|---|
| المقامُ التنظيميّ | 17 إدارةً و4 وحداتٍ خارجَ التسلسل ✔ |
| مصالحةُ الملكيّة | 739 سطحًا بحكمٍ وقاعدةٍ · صفرُ مجهولٍ · صفرُ حقيقةٍ في إدارتَين ✔ |
| التقاطعاتُ الخمسة | 330 سطحًا مُصنَّفًا مصدرًا أو إسقاطًا ✔ |
| سقّاطةُ السطحِ الجديد | اثنا عشرَ شرطًا · مبنيّةٌ وتردُّ الناقصَ ✔ |
| سندُ قرارِ المالك | 9 حقولٍ · وقيدٌ يمنع اعتمادًا بلا مرجع ✔ |
| تسييجُ دَينِ المالية | 175 سطحًا مُصنَّفًا ✔ |
| قرارُ الأشباح | 334 صفًّا بقرارٍ وسببٍ ✔ |

**والبوّابةُ اليومَ: 15 من 19 خضراء.** والأربعةُ الباقيةُ هي ما في هذا الملفّ.

