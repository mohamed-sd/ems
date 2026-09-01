# الـ58 في السايدبار — **بتصييرٍ حيٍّ لا بقراءةِ جدول**

> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/navarch/silent_drop_sidebar_report.php` @ `7d60374f` · 2026-09-01 17:37
> **المُصيِّرُ** `includes/navarch_renderer.php::navarch_render` — هو نفسُه الذي يرسم سايدبارَ المستخدم.

## ⓪ الخلاصة

| الحكم | العدد |
|---|---:|
| ✅ يظهر في السايدبار | **52** |
| ⬜ لا يظهر — ولا يُنتظَر | **6** |
| **الإجمالي** | **58** |

**وثلاثةُ مِسمارٍ لا مِسمارٌ واحد** (§9 · §10 · §11): بندُ الدورةِ في السايدبار ·
والشخصيُّ في «مساحتي» · والتبويبُ **داخلَ أبيه**. فما حكمُه «لا يظهر — ولا يُنتظَر»
**ليس نقصًا**: صنفُه في ورقةِ الدليلِ نفسِها يمنعه من أن يكون بندَ قائمة.

## ① الدورُ الذي قِيس به كلُّ مساحة

| المساحة | الدور | الحكم |
|---|---:|---|
| `DEP-01` | 12 | دورٌ مربوطٌ (PRIMARY) |
| `DEP-02` | 2 | دورٌ مربوطٌ (PRIMARY) |
| `DEP-04` | 3 | دورٌ مربوطٌ (PRIMARY) |
| `DEP-05` | 17 | دورٌ مربوطٌ (PRIMARY) |
| `DEP-06` | 21 | دورٌ مربوطٌ (PRIMARY) |
| `DEP-07` | 4 | دورٌ مربوطٌ (PRIMARY) |
| `DEP-08` | 15 | دورٌ مربوطٌ (PRIMARY) |
| `DEP-14` | 13 | دورٌ مربوطٌ (PRIMARY) |
| `DEP-15` | 23 | دورٌ مربوطٌ (PRIMARY) |
| `DEP-16` | 16 | دورٌ مربوطٌ (PRIMARY) |
| `DEP-17` | 25 | دورٌ مربوطٌ (PRIMARY) |
| `EX-DVP` | 9 | ⚠ لا دورَ مربوطٌ لها — قِيس بالدورِ 9 المصرَّحِ بـ12 من 12 مسارًا |

## ② الجدول

| # | المساحة | اسمُ الشاشة | Screen ID | المسار | صنفُ الموضع | أيظهر في السايدبار؟ | أين | المجموعة | الترتيب | الحكمُ ولماذا |
|---|---|---|---|---|---|---|---|---|---|---|
| 1 | DEP-01 — إدارة المبيعات التعاقدية والعقود | جهات اتصال العملاء | `SCR-0034` | [clients/client_contacts.php](../../../clients/client_contacts.php) | TAB_CHILD | ⬜ لا يظهر — ولا يُنتظَر | يُفتح من أبيه تبويبًا | — |  | تبويبٌ تابعٌ (`TAB_CHILD`) — ولا صفَّ تفويضٍ له في `nav_items` |
| 2 | DEP-01 — إدارة المبيعات التعاقدية والعقود | بنود العروض | `SCR-0043` | [clients/quotation_lines.php](../../../clients/quotation_lines.php) | TAB_CHILD | ⬜ لا يظهر — ولا يُنتظَر | يُفتح من أبيه تبويبًا | — |  | تبويبٌ تابعٌ (`TAB_CHILD`) — ولا صفَّ تفويضٍ له في `nav_items` |
| 3 | DEP-02 — إدارة الموردين | جهات الاتصال والمفوضون | `SCR-0575` | [suppliers/supplier_contacts.php](../../../suppliers/supplier_contacts.php) | TAB_CHILD | ⬜ لا يظهر — ولا يُنتظَر | يُفتح من أبيه تبويبًا | — |  | تبويبٌ تابعٌ (`TAB_CHILD`) — ولا صفَّ تفويضٍ له في `nav_items` |
| 4 | DEP-04 — إدارة الأسطول والأصول | التحقق من المصدر | `SCR-0652` | [fleet/asset_intake.php](../../../fleet/asset_intake.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-04 | تسجيل الأصل ودخوله | 1 من 5 | بندُ دورةٍ `PRIMARY` — §9 |
| 5 | DEP-04 — إدارة الأسطول والأصول | أمر التفتيش | `SCR-0652` | [fleet/asset_intake.php](../../../fleet/asset_intake.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-04 | تسجيل الأصل ودخوله | 1 من 5 | بندُ دورةٍ `PRIMARY` — §9 |
| 6 | DEP-04 — إدارة الأسطول والأصول | التفعيل وإعادة الخدمة | `SCR-0652` | [fleet/asset_intake.php](../../../fleet/asset_intake.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-04 | تسجيل الأصل ودخوله | 1 من 5 | بندُ دورةٍ `PRIMARY` — §9 |
| 7 | DEP-04 — إدارة الأسطول والأصول | حركة الموقع والمشروع | `SCR-0654` | [fleet/asset_assignments.php](../../../fleet/asset_assignments.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-04 | إدخال الأصل للتشغيل | 1 من 1 | بندُ دورةٍ `PRIMARY` — §9 |
| 8 | DEP-04 — إدارة الأسطول والأصول | الجاهزية الشهرية | `SCR-0655` | [fleet/asset_readiness.php](../../../fleet/asset_readiness.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-04 | الرقابة الفنية والجاهزية | 4 من 4 | بندُ دورةٍ `PRIMARY` — §9 |
| 9 | DEP-04 — إدارة الأسطول والأصول | الخروج الدائم | `SCR-0656` | [fleet/asset_exit.php](../../../fleet/asset_exit.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-04 | خروج الأصل من الخدمة | 1 من 1 | بندُ دورةٍ `PRIMARY` — §9 |
| 10 | DEP-05 — الإدارة المالية | الرقابة الائتمانية وحدود العملاء | `SCR-0684` | [finance/acc_credit_control.php](../../../finance/acc_credit_control.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-05 | الذمم المدينة | 3 من 3 | بندُ دورةٍ `PRIMARY` — §9 |
| 11 | DEP-05 — الإدارة المالية | الضرائب والقيمة المضافة | `SCR-0166` | [finance/tax_fin.php](../../../finance/tax_fin.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-05 | التسويات والمحاسبة العامة | 2 من 4 | بندُ دورةٍ `PRIMARY` — §9 |
| 12 | DEP-05 — الإدارة المالية | مطابقات الحسابات | `SCR-0686` | [finance/acc_reconciliations.php](../../../finance/acc_reconciliations.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-05 | التسويات والمحاسبة العامة | 4 من 4 | بندُ دورةٍ `PRIMARY` — §9 |
| 13 | DEP-05 — الإدارة المالية | ميزان المراجعة | `SCR-0687` | [finance/acc_trial_balance.php](../../../finance/acc_trial_balance.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-05 | الإقفال | 1 من 4 | بندُ دورةٍ `PRIMARY` — §9 |
| 14 | DEP-05 — الإدارة المالية | إقفال الفترة المحاسبية | `SCR-0164` | [finance/periods_fin.php](../../../finance/periods_fin.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-05 | التأسيس والقوائم المرجعية | 3 من 5 | بندُ دورةٍ `PRIMARY` — §9 |
| 15 | DEP-05 — الإدارة المالية | القوائم المالية | `SCR-0146` | [finance/financial_statements_fin.php](../../../finance/financial_statements_fin.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-05 | الإقفال | 3 من 4 | بندُ دورةٍ `PRIMARY` — §9 |
| 16 | DEP-06 — إدارة الخزينة | سجل المستفيدين والتحقق | `SCR-0168` | [finance/tre_beneficiary.php](../../../finance/tre_beneficiary.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-06 | التأسيس | 2 من 2 | بندُ دورةٍ `PRIMARY` — §9 |
| 17 | DEP-06 — إدارة الخزينة | سجل الأدوات المالية | `SCR-0692` | [finance/tre_instruments.php](../../../finance/tre_instruments.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-06 | دورة القبض | 2 من 2 | بندُ دورةٍ `PRIMARY` — §9 |
| 18 | DEP-06 — إدارة الخزينة | التحويلات بين الحسابات | `SCR-0696` | [finance/tre_transfers.php](../../../finance/tre_transfers.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-06 | دورة الصرف | 4 من 5 | بندُ دورةٍ `PRIMARY` — §9 |
| 19 | DEP-06 — إدارة الخزينة | تنفيذ عمليات الصرف الأجنبي | `SCR-0697` | [finance/tre_fx_deals.php](../../../finance/tre_fx_deals.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-06 | دورة الصرف | 5 من 5 | بندُ دورةٍ `PRIMARY` — §9 |
| 20 | DEP-06 — إدارة الخزينة | خطابات الضمان والاعتمادات المستندية | `SCR-0698` | [finance/tre_guarantees.php](../../../finance/tre_guarantees.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-06 | الرقابة والإقفال الشهري | 3 من 5 | بندُ دورةٍ `PRIMARY` — §9 |
| 21 | DEP-06 — إدارة الخزينة | الجرد النقدي للخزائن | `SCR-0700` | [finance/tre_cash_count.php](../../../finance/tre_cash_count.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-06 | الرقابة والإقفال الشهري | 5 من 5 | بندُ دورةٍ `PRIMARY` — §9 |
| 22 | DEP-07 — إدارة الموارد البشرية | مستندات الموظف | `SCR-0723` | [employees/hr_employee_documents.php](../../../employees/hr_employee_documents.php) | TAB_CHILD | ⬜ لا يظهر — ولا يُنتظَر | يُفتح من أبيه أو بالرابط | — |  | §9: صنفُه `TAB_CHILD` **لا يدخل السايدبارَ بحكمِ نوعِه** |
| 23 | DEP-07 — إدارة الموارد البشرية | التهيئة والمباشرة | `SCR-0724` | [employees/hr_onboarding.php](../../../employees/hr_onboarding.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-07 | التوظيف والتعاقد | 5 من 7 | بندُ دورةٍ `PRIMARY` — §9 |
| 24 | DEP-07 — إدارة الموارد البشرية | الحركات الوظيفية | `SCR-0725` | [employees/hr_job_movements.php](../../../employees/hr_job_movements.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-07 | التوظيف والتعاقد | 7 من 7 | بندُ دورةٍ `PRIMARY` — §9 |
| 25 | DEP-07 — إدارة الموارد البشرية | التدريب والكفاءة | `SCR-0726` | [employees/hr_training.php](../../../employees/hr_training.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-07 | الدوام والإجازات | 3 من 6 | بندُ دورةٍ `PRIMARY` — §9 |
| 26 | DEP-07 — إدارة الموارد البشرية | تقييم الأداء الوظيفي | `SCR-0727` | [employees/hr_performance.php](../../../employees/hr_performance.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-07 | الدوام والإجازات | 4 من 6 | بندُ دورةٍ `PRIMARY` — §9 |
| 27 | DEP-07 — إدارة الموارد البشرية | المزايا والتأمينات | `SCR-0729` | [employees/hr_benefits.php](../../../employees/hr_benefits.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-07 | استحقاق الأجور وصرفها | 1 من 4 | بندُ دورةٍ `PRIMARY` — §9 |
| 28 | DEP-08 — إدارة الحوكمة والالتزام | سجل السياسات | `SCR-0742` | [governance/policies.php](../../../governance/policies.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-08 | الكيانات والتفويض بالتوقيع | 2 من 5 | بندُ دورةٍ `PRIMARY` — §9 |
| 29 | DEP-08 — إدارة الحوكمة والالتزام | تقويم الامتثال | `SCR-0744` | [governance/compliance_calendar.php](../../../governance/compliance_calendar.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-08 | الكيانات والتفويض بالتوقيع | 4 من 5 | بندُ دورةٍ `PRIMARY` — §9 |
| 30 | DEP-08 — إدارة الحوكمة والالتزام | التقديمات النظامية | `SCR-0745` | [governance/regulatory_filings.php](../../../governance/regulatory_filings.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-08 | المستندات والالتزام النظامي | 2 من 2 | بندُ دورةٍ `PRIMARY` — §9 |
| 31 | DEP-08 — إدارة الحوكمة والالتزام | تضارب المصالح | `SCR-0747` | [governance/conflict_disclosures.php](../../../governance/conflict_disclosures.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-08 | الأدوار والصلاحيات | 2 من 10 | بندُ دورةٍ `PRIMARY` — §9 |
| 32 | DEP-08 — إدارة الحوكمة والالتزام | الأطراف ذات العلاقة | `SCR-0748` | [governance/related_parties.php](../../../governance/related_parties.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-08 | الأدوار والصلاحيات | 3 من 10 | بندُ دورةٍ `PRIMARY` — §9 |
| 33 | DEP-08 — إدارة الحوكمة والالتزام | الهدايا والضيافة | `SCR-0749` | [governance/gifts_hospitality.php](../../../governance/gifts_hospitality.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-08 | الأدوار والصلاحيات | 4 من 10 | بندُ دورةٍ `PRIMARY` — §9 |
| 34 | DEP-08 — إدارة الحوكمة والالتزام | إقرارات مدونة السلوك | `SCR-0750` | [governance/conduct_acknowledgements.php](../../../governance/conduct_acknowledgements.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-08 | الأدوار والصلاحيات | 5 من 10 | بندُ دورةٍ `PRIMARY` — §9 |
| 35 | DEP-08 — إدارة الحوكمة والالتزام | بلاغات النزاهة المحمية | `SCR-0752` | [governance/integrity_reports.php](../../../governance/integrity_reports.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-08 | الاستثناء والرقابة | 4 من 8 | بندُ دورةٍ `PRIMARY` — §9 |
| 36 | DEP-08 — إدارة الحوكمة والالتزام | التحقيقات | `SCR-0753` | [governance/investigations.php](../../../governance/investigations.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-08 | الاستثناء والرقابة | 5 من 8 | بندُ دورةٍ `PRIMARY` — §9 |
| 37 | DEP-08 — إدارة الحوكمة والالتزام | سجل الإخلالات | `SCR-0754` | [governance/breaches.php](../../../governance/breaches.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-08 | الاستثناء والرقابة | 6 من 8 | بندُ دورةٍ `PRIMARY` — §9 |
| 38 | DEP-08 — إدارة الحوكمة والالتزام | الإجراءات التصحيحية | `SCR-0755` | [governance/corrective_actions.php](../../../governance/corrective_actions.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-08 | الاستثناء والرقابة | 7 من 8 | بندُ دورةٍ `PRIMARY` — §9 |
| 39 | DEP-08 — إدارة الحوكمة والالتزام | متابعة نتائج المراجعة | `SCR-0756` | [governance/audit_followup.php](../../../governance/audit_followup.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-08 | الاستثناء والرقابة | 8 من 8 | بندُ دورةٍ `PRIMARY` — §9 |
| 40 | DEP-08 — إدارة الحوكمة والالتزام | اللجان وحوكمة الاجتماعات | `SCR-0757` | [governance/committees.php](../../../governance/committees.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-08 | الأنظمة والقوائم المرجعية | 4 من 5 | بندُ دورةٍ `PRIMARY` — §9 |
| 41 | DEP-14 — إدارة الصيانة | بنود أمر العمل | `SCR-0904` | [maintenance/work_orders.php](../../../maintenance/work_orders.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-14 | دورة العطل | 5 من 8 | بندُ دورةٍ `PRIMARY` — §9 |
| 42 | DEP-14 — إدارة الصيانة | الإصلاح الخارجي ومطالبات الضمان | `SCR-0660` | [maintenance/external_repairs.php](../../../maintenance/external_repairs.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-14 | دورة العطل | 8 من 8 | بندُ دورةٍ `PRIMARY` — §9 |
| 43 | DEP-14 — إدارة الصيانة | العناية اليومية والتشحيم | `SCR-0661` | [maintenance/daily_care.php](../../../maintenance/daily_care.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-14 | الصيانة الوقائية | 2 من 2 | بندُ دورةٍ `PRIMARY` — §9 |
| 44 | DEP-15 — إدارة النقل والترحيل | تجهيز المغادرة والتسليم الأصلي | `SCR-0664` | [transport/transfer_origin_handover.php](../../../transport/transfer_origin_handover.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-15 | دورة الترحيل | 3 من 6 | بندُ دورةٍ `PRIMARY` — §9 |
| 45 | DEP-15 — إدارة النقل والترحيل | مطالبات التلف والحوادث | `SCR-0666` | [transport/transfer_damage_claims.php](../../../transport/transfer_damage_claims.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-15 | دورة الترحيل | 6 من 6 | بندُ دورةٍ `PRIMARY` — §9 |
| 46 | DEP-16 — إدارة المشتريات التشغيلية | أعضاء حزمة التجميع | `SCR-0669` | [procurement/proc_packages.php](../../../procurement/proc_packages.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-16 | طلبات الشراء وتجميعها | 2 من 2 | بندُ دورةٍ `PRIMARY` — §9 |
| 47 | DEP-16 — إدارة المشتريات التشغيلية | بنود عروض الموردين | `SCR-0671` | [procurement/proc_offers.php](../../../procurement/proc_offers.php) | TAB_CHILD | ⬜ لا يظهر — ولا يُنتظَر | يُفتح من أبيه أو بالرابط | — |  | §9: صنفُه `TAB_CHILD` **لا يدخل السايدبارَ بحكمِ نوعِه** |
| 48 | DEP-17 — إدارة المخازن | إسناد أمناء المخازن | `SCR-0817` | [procurement/wh_custodians.php](../../../procurement/wh_custodians.php) | TAB_CHILD | ⬜ لا يظهر — ولا يُنتظَر | يُفتح من أبيه تبويبًا | — |  | تبويبٌ تابعٌ (`TAB_CHILD`) — ولا صفَّ تفويضٍ له في `nav_items` |
| 49 | DEP-17 — إدارة المخازن | بنود طلب الصرف الوارد | `SCR-0677` | [procurement/wh_issue_requests.php](../../../procurement/wh_issue_requests.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ DEP-17 | دورة الصرف | 1 من 4 | بندُ دورةٍ `PRIMARY` — §9 |
| 50 | EX-DVP — مساحة نائب الرئيس | لوحة النائب | `SCR-0793` | [portal/vp_dashboard.php](../../../portal/vp_dashboard.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ EX-DVP | الرؤية بالنطاق | 1 من 2 | بندُ دورةٍ `PRIMARY` — §9 |
| 51 | EX-DVP — مساحة نائب الرئيس | الإدارات — نطاقي والشركة | `SCR-0791` | [portal/vp_departments.php](../../../portal/vp_departments.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ EX-DVP | الرؤية بالنطاق | 2 من 2 | بندُ دورةٍ `PRIMARY` — §9 |
| 52 | EX-DVP — مساحة نائب الرئيس | التقرير اليومي للنائب | `SCR-0794` | [portal/vp_daily_report.php](../../../portal/vp_daily_report.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ EX-DVP | التقارير الدورية | 1 من 3 | بندُ دورةٍ `PRIMARY` — §9 |
| 53 | EX-DVP — مساحة نائب الرئيس | مراجعة الأداء الأسبوعية | `SCR-0795` | [portal/vp_weekly_report.php](../../../portal/vp_weekly_report.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ EX-DVP | التقارير الدورية | 2 من 3 | بندُ دورةٍ `PRIMARY` — §9 |
| 54 | EX-DVP — مساحة نائب الرئيس | المراجعة الشهرية للنائب | `SCR-0796` | [portal/vp_monthly_review.php](../../../portal/vp_monthly_review.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ EX-DVP | التقارير الدورية | 3 من 3 | بندُ دورةٍ `PRIMARY` — §9 |
| 55 | EX-DVP — مساحة نائب الرئيس | صندوق اعتمادات النائب | `SCR-0806` | [portal/vp_approval_inbox.php](../../../portal/vp_approval_inbox.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ EX-DVP | صناديق القرار | 1 من 1 | بندُ دورةٍ `PRIMARY` — §9 |
| 56 | EX-DVP — مساحة نائب الرئيس | الإجراءات والقرارات المطلوبة مني | `SCR-0807` | [portal/vp_pending_actions.php](../../../portal/vp_pending_actions.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ EX-DVP | المتابعة | 1 من 3 | بندُ دورةٍ `PRIMARY` — §9 |
| 57 | EX-DVP — مساحة نائب الرئيس | متابعة قرارات النائب | `SCR-0808` | [portal/vp_actions_followup.php](../../../portal/vp_actions_followup.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ EX-DVP | المتابعة | 2 من 3 | بندُ دورةٍ `PRIMARY` — §9 |
| 58 | EX-DVP — مساحة نائب الرئيس | الإنابات والتفويضات | `SCR-0783` | [portal/exec_delegations.php](../../../portal/exec_delegations.php) | PRIMARY | ✅ يظهر في السايدبار | سايدبارُ EX-DVP | المتابعة | 3 من 3 | بندُ دورةٍ `PRIMARY` — §9 |
