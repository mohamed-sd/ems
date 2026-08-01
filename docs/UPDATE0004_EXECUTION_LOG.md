# update0004 · سجلُّ التنفيذ

> صفٌّ لكل مهمة — البرومت §6⑤. الفرع: `feature/update0004`.

## الموجة ⓪ · الحسمُ النصي — 7/7

| المهمة | ما فُعل | الملفات | الاختبار | اجتهاد |
|---|---|---|---|---|
| D-01 | `NAV-01 §9.5`: «إدارةٌ موازيةٌ تحت المدير التنفيذي» → «إدارةٌ تحت مدير التشغيل إداريًّا باستقلالٍ فنيٍّ تام» — الموضعُ الوحيدُ المخالف، وORG-01/SEC-01 لم تُمسّا | `docs/sources/NAV-01.corrected.md` | diff يظهر التغييرَ وحدَه ✅ | — |
| D-02 | ترقيمُ TKT-01: `#6` الثاني (الأثر الحقيقي) → 7 · وما بعده 8→13 · `§11.1`→`§12.1` · إحالةُ WatchTowerService «مؤشرات §8» → §11 — تسعةُ تعديلات | `docs/sources/TKT-01.corrected.md` | مسحُ كل `§n` بعد التعديل — لا إحالة يتيمة ✅ | J-03 |
| D-03 | `sod_conflicts` «الثمانية في §6» → §5 · وحذفُ `organizational_levels` من §11.2① («إلى اثنين منفصلين») | `docs/sources/SEC-01.corrected.md` | diff بتعديلين فقط ✅ | J-01 |
| D-04 | UAT-01: الصفُّ الأخير ⑯→⑱ (`DEC-UAT-I`) · «بالستة عشر»→«بالثمانية عشر» (§12③) · «الستةَ عشرَ»→«الثمانيةَ عشرَ» (§13②) · «الشواهد العشرة»→«الأربعةَ عشرَ» (§13④) · ⑥ المكرر في §13 → ⑦ والنظافة → ⑧ · `SEC-01 §8.2`→`§7.2` — سبعةُ تعديلات | `docs/sources/UAT-01.corrected.md` | diff بسبعة مواضع ✅ | — |
| D-05 | الملحق: 13→12.2 · 14→12.3 · 15→12.4 · 16→12.5 · 17→12.6 · 17.1→12.6.1 — فلا يصطدم §13 بالأساس، وإحالاتُه الداخلية (§12.2–12.5) صارت مطابقة | `docs/sources/UAT-01_APPENDIX12.corrected.md` | فحصُ الإحالات الداخلية — متطابقة ✅ | J-02 |
| D-06 | سطرُ تثبيت `DEC-NAV-J` بعد عنوان `NAV-01 §4`: المجموعاتُ الثماني مستوًى ثانٍ داخل الأبواب الثمانية، إحالةً إلى §10① و`DEC-01 ②` | `docs/sources/NAV-01.corrected.md` | ✅ | — |
| D-07 | سجلُّ القرارات: `DEC-ORG-A` معكوسًا · `DEC-SEC-F/H` و`DEC-NAV-C/J` و`DEC-UAT-I` مغلقةً · `DEC-SEC-K` و`DEC-NAV-E` و`DEC-UAT-G` مفتوحةً بأحكام مضيّها · وقرارا النطاق | `docs/UPDATE0004_DECISIONS_ar.md` | — | — |

**ملاحظة قياس:** الحزمةُ اكتملت تسعَ وثائقَ في `docs/sources/` (الخمسُ الأصلية + `DEC-01` + ملحق §12 + `PLAN-05` + طلب تجربة النظام) منسوخةً من Downloads بلا مساس بالأصول، ومستخرَجةً نصًّا بـ`docx2md.php`.

## الموجة ① · ORG-01 البنية — 5/5

**القياس قبل التنفيذ:** 320 جدولًا · صفر جدول `org_*`/`permit_*`/`assignment_*` · `positions`=0 · `signing_authorities` قائم بperson_id=users.id · نمط الفريد المشروط القائم: عمود مولَّد NULL + UNIQUE (`primary_flag`/`live_type_key`).

| المهمة | ما فُعل | الملفات | الاختبار | اجتهاد |
|---|---|---|---|---|
| ORG-01 | الجداول الستة: `org_units` · `org_assignment_types` · `org_assignments` (المدة والنطاق إلزاميان · UQ طبيعي · **الفريد المشروط `active_site_mgr_key`** لمدير الحركة) · `assignment_capabilities` · `assignment_reporting_lines` · `assignment_audit` | `database/migrations/2026_08_02_org01_structure.sql` · `app/Core/TenantRegistry.php` | O-belt ①③④⑥ ✅ | J-09 |
| ORG-02 | لا عمود `head_person_id` — منظر `v_org_unit_heads` يشتق الرأس من التكليف النافذ (نوع `is_unit_head` + حالة active + سريان التاريخ) | الهجرة نفسها | ⑤ يظهر ويسقط بالإنهاء ✅ | J-05 |
| ORG-03 | بذر 13 نوعًا: 5 مركزية + 8 موقعية كلها `requires_functional_line=1` — وضابط البوابة صف (DEC-ORG-B) | الهجرة | عدّ 5+8 ✅ | — |
| ORG-04 | بذر الوحدات الأربع عشرة (§1.1) بطبقاتها: 8 تشغيلية (البنات تحت «التشغيل» والموارد البشرية معها بالتبعية الإدارية) · 4 موازية · 2 رقابية — للشركة 4 | الهجرة | عدّ 14 ✅ | J-06 |
| ORG-05 | جداول الأذونات الخمسة + بذر الأنواع التسعة + مصفوفة §5 = 24 صف موافقة مرتبة | الهجرة | عدّ 9 و24 وترتيب الحركة أولًا ✅ | J-07 · J-08 |

**الحزام:** `tests/org_structure_test.php` = **28/28** ✅ · `dump-schema` حُدِّث (323 جدولًا + منظر).

## الموجة ② · ORG-01 السلطة — 5/5

**القياس قبل التنفيذ:** `AuthorityGuard::sign` يفحص الذات ثم التفويض (`signing_authorities`) — لا مساس بمساره؛ `guard_denials` جاهز للتسجيل؛ الدور 1 = مدير التشغيل و-1 = الأعلى (`includes/roles.php`)؛ `fin_notifications.target_level` ENUM بلا قيمة لمدير التشغيل.

| المهمة | ما فُعل | الملفات | الاختبار | اجتهاد |
|---|---|---|---|---|
| ORG-06 | `OrgAuthorityResolver`: التكليفات النافذة وصلاحياتها وسقوفها · منتهٍ→403 مسجَّلة · خارج النطاق→403 · فوق السقف→409 بالمتاح · التغطية بالتقاطع (مشروع↔مواقعه) | `app/Services/Org/OrgAuthorityResolver.php` | O2·O3·O5 ✅ | J-11 |
| ORG-07 | الوصل بـ`AuthorityGuard` خلف `EMS_ORG_AUTHORITY` (off·monitor·enforce) + عمود `org_asg_id` في `approval_signatures` فالتوقيع بمرجع التكليف | `app/Core/AuthorityGuard.php` · `2026_08_02_org02_signature_asg_ref.sql` · `.env`(+example)=monitor | O8 ✅ | J-10 |
| ORG-08 | `AssignmentService`: 422 المدة/النطاق · 403 مصدر القرار · 409 التداخل وO1 · 422 الموقعي بلا خطين · إنشاء ذري بصلاحيات وخطوط وسجل · إنهاء «الطلب للفني والقرار لمن كلّف» (202) | `app/Services/Org/AssignmentService.php` | O1·O6·O6-ب·O6-و ✅ | J-12 |
| ORG-09 | `AssignmentExpiryJob` (سقوط آلي بساعة القاعدة + تنبيه 30 يومًا بعطالة يومية) · `DeputyResolver` (الغياب/التعليق يفعّلان النائب بسطر delegated · لا نيابة بعد الانتهاء) · كرون يومي | `app/Services/Org/AssignmentExpiryJob.php` · `DeputyResolver.php` · `Operations/cron_org_assignments.php` | O2·O4·O7 ✅ | — |
| ORG-10 | حزام O1→O8 كاملًا | `tests/org_authority_test.php` | **26/26** ✅ | — |

## الموجة ③ · ORG-01 الأذونات — 4/4

| المهمة | ما فُعل | الملفات | الاختبار | اجتهاد |
|---|---|---|---|---|
| ORG-11 | `PermitGate`: طلب → موافقات متسلسلة → approved بvalid_until من ساعة القاعدة → استهلاك used · وأحداث PermitApproved/Used/Expired سطور `permit_status_history` · والمنع في `guard_denials` بكود `permit_gate` | `app/Services/Org/PermitGate.php` | ①③④ ✅ | — |
| ORG-12 | التسلسل: خطوة قبل سابقتها → **409** · موافق بلا تفويض لدوره → **403** (الدور يُحل من التكليفات النافذة للمجالات التشغيلية الست، وبusers.role للموازية fleet/hr/material_owner) · إذن منتهٍ يُستعمل → **423** + مسح دوري في الكرون | نفسه + `Operations/cron_org_assignments.php` | ①②④ ✅ | J-13 |
| ORG-13 | الوصل بالمواضع التسعة خلف `EMS_PERMIT_GATE` (off افتراضًا · monitor يسجّل ويمضي · enforce يمنع · `EMS_PERMIT_GATE_SITES` للتجربة بموقع واحد): دخول معدة + إنهاء تشغيل (خروج) + تعمل/جاهزة (خدمة) + دخول مشغّل في `movement_operations.php` · دخول مشتريات `receipt_custody_proc.php` · خروج مواد `issue_proc.php` · دخول فني `Maintenance/orders.php` · خروج عامل `final_settlement.php` | الخمسة + `includes/permit_gate.php` | مسبار monitor فرعي ✅ | J-14 |
| ORG-14 | صندوق «أذونات المواقع» في الصندوق الجامع — بند واحد لكل إذن معلَّق بدوره الحالي | `app/Services/Finance/ApprovalsInboxService.php` | ⑥ ✅ | — |

**الحزام:** `tests/permit_gate_test.php` = **24/24** ✅ · lint كل الملفات المعدلة نظيف.

## الموجة ④ · ORG-01 الشاشات — 4/4

| المهمة | ما فُعل | الملفات | الاختبار | اجتهاد |
|---|---|---|---|---|
| ORG-15 | شاشة التكليفات: إنشاء بخطي التبعية للموقعي · إنهاء/تعليق عبر AssignmentService · سجل التدقيق ظاهرًا | `admin/org_assignments.php` | HTTP 200 + علامات ✅ | — |
| ORG-16 | شاشة الهيكل: الطبقات الثلاث شجرةً والرأس مشتق من v_org_unit_heads و«بلا تكليف نافذ» معلَنة | `admin/org_structure.php` | HTTP ✅ | — |
| ORG-17 | شاشة الأذونات: طلب · الخطوات بترتيبها · الموافقة للخطوة المفتوحة وحدها والباقي «بانتظار ما قبلها» | `admin/org_permits.php` | HTTP ✅ | — |
| ORG-18 | لوحة مدير التشغيل: المجموعات السبع وكل رقم بإجرائه · «ما ينتظر قراره» ببنود مرتبة **بساعات الانتظار ومجموعها** | `admin/ops_manager_board.php` | HTTP + نص «بالساعات لا بالعدد» ✅ | — |

**التسجيل:** هجرة `2026_08_02_org04_screens_registration.sql` (4 موديولات · صلاحيات الدورين 1 و6 · 6 روابط تنقل). **الحزام:** `tests/org_screens_http_test.php` = **11/11** ✅ (ومنها حجب الدور 12 خادميًّا).

## الموجة ⑤ · SEC المخرَجات قبل البرمجة — 6/6 (بحكم DEC-SEC-K: مسودة معلَّمة)

**القياس قبل التنفيذ:** `job_titles`=16 (رقيق: name·is_operator) · `employee_roles`=9 · `roles`=25 (role_scope: gloable/mine) · `role_permissions`=1008 · `modules`=212 (بعد شاشات ④) · الأوصاف الوظيفية المعتمدة **لم تصل** (DEC-SEC-K مفتوح).

| المهمة | ما فُعل | الملفات | الاختبار | اجتهاد |
|---|---|---|---|---|
| SEC-01 | قاموس المسميات مسودةً من النظام الحي: العائلات الـ13 (DEC-SEC-F) · الـ16 القائمة مصنفة ★ بعدد موظفي كلٍّ · 13 مسمًّى مقترحًا ناقصًا من الأدوار | `docs/sec01/SEC-D1_dictionary_draft_ar.md` + المولّد `tools/sec01_deliverables.php` | 16 JT · 84 علامة ★ ✅ | J-15 |
| SEC-02 | المصفوفة الكاملة (212×25=5300 صفًّا) بقاعدة تحويل معلنة: الرايات الأربع → الأفعال الـ16 (والاعتمادية الست لا تُشتق من راية) + نطاق مقترح من role_scope | `SEC-D2_matrix_208x25.csv` + ملخص | 5300 صف والأعمدة الـ16 ✅ | J-16 |
| SEC-03 | خريطة الأدوار الـ25 بستة أعمدة ولا صف بلا قرار (5↔6 دمج معلَّق بDEC-NAV-E · 25 تفكيك · 15/24 مجالان رقابيان) | `SEC-D3_roles_map_ar.md` | 25 صفًّا ✅ | — |
| SEC-04 | مصفوفة المصادر الـ17 (§11.3) بقياس حي لكل مصدر وقرار ترحيله | `SEC-D4_sources_17_ar.md` | ✅ | — |
| SEC-05 | المصالحة صفًّا صفًّا: 310 ملفات قرص × 205 كودات مسجلة → 198 متطابقة · **64 شاشة غير مسجَّلة محتملة** · **7 أشباح** — قرار مقترح لكل صف، ولا قلب للحارس قبله | `SEC-D5_screens_reconciliation.csv` + تقرير | 317 صفًّا بأربعة أعمدة ✅ | J-17 |
| SEC-06 | مراجعة المصادر الأربعة: ① غائب (K) ② حي ③ مؤجَّل لUAT ④ جاهز نموذجًا — والخلاصة للتوقيع | `SEC-D6_four_sources_review_ar.md` | ✅ | — |

**الحزام:** `tests/sec_deliverables_test.php` = **28/28** ✅

## الموجة ⑥ · SEC البنية — 7/7

**القياس قبل التنفيذ:** الأسلاف الخمسة: `job_titles`=16 رقيقًا · `user_capacities`=30 · `positions`=0 · `guard_policies`=9 (حراس GOV-01 التجارية) · `exception_requests/approvals`=0 · وصفر جدول من قاموس §15.

| المهمة | ما فُعل | الاختبار | اجتهاد |
|---|---|---|---|
| SEC-07 | `persons` (عام عبر المنصة §14) + `person_relationships` (بكيانها — موظف المورد بلا موظف وهمي) | ⑤ ✅ | — |
| SEC-08 | `hr_dictionaries` + بذر 6 علاقات و13 عائلة (DEC-SEC-F) و7 مستويات برتبها + **توسيع `job_titles`** بأعمدة §12 الخمسة عشر وترميز الـ16 بمسودة D1 (★) | ②③ ✅ | J-18 |
| SEC-09 | `person_positions` بالطبقات والنطاق الإلزامي وUQ الطبيعي — وuser_capacities/positions أسلاف تبقى للجسر بلا ترحيل بيانات (0 صف حي يلزم نقله الآن) | ⑤ ✅ | J-19 |
| SEC-10 | القوالب الثلاثية: هوية (42 مبذورة: 6 سقوف علاقة +13+7+16) · إصدارات (لا تعديل بأثر رجعي) · محتوى بFK النسخة وdeny يغلب | ③ ✅ | — |
| SEC-11 | `permission_exceptions` (لا مفتوح المدة · **CHECK كسر الزجاج ≤24h**) + `sensitive_access_grants` (دائم وظيفي بسجل اطلاع) + دورة التغيير (طلب + خطوات بقواعد ديناميكية) — وأسلاف exception_* تبقى لGOV-01 | ④ ✅ | J-20 |
| SEC-12 | `sod_conflicts` بذر الثمانية (§5) + `guard_override_policies` بذر الـ17 (§7.2: 8 never · 7 break_glass · 2 compensating) + `sensitive_field_policies` (6 سياسات أساس) | ③ ✅ | J-21 |
| SEC-13 | `effective_permissions` (المشتق) + `permission_audit_events` (Insert-only بوسم founding_mode) + دورتا المراجعة + `founding_mode` بوضعين مطفأين و**CHECK لا enabled بلا ends_at** | ①④ ✅ | — |

**الحزام:** `tests/sec_structure_test.php` = **39/39** ✅ · القاعدة صارت 342 جدولًا · `dump-schema` محدَّث.

## الموجة ⑦ · SEC الاشتقاق — 5/5

| المهمة | ما فُعل | الملفات | الاختبار | اجتهاد |
|---|---|---|---|---|
| SEC-14 | `PermissionResolver::resolve`: يجمع الطبقات السبع (قوالب المراكز بأصنافها + تكليفات ORG + الاستثناءات + المنح الحساس) ثم deny فوقها · سقف العلاقة لا يرفعه إلا كسر زجاج · **الأقل سقفًا يسري** · **تكليف يقيّد قالب «كل المواقع» — التقاطع لا الاتحاد** · بلا نطاق → 422 | `app/Services/Security/PermissionResolver.php` | S16·S17·deny·422 ✅ | J-22 |
| SEC-15 | `rebuild()`: إعادة بناء `effective_permissions` — حذف وبناء ذري، والمنع لا يُخزن (المشتق «ما يُملك» بعد الدمج) | نفسه | S1 ✅ | — |
| SEC-16 | `PermissionExplainService`: صيغة مثال §12 حرفيًّا — sources بمُددها وdenies بمصدر الحكم وnote «تسقط بانتهاء…» | `PermissionExplainService.php` | S12 (سماحًا ومنعًا) ✅ | — |
| SEC-17 | `PositionService`: preview (⑨) · submit (المركز معلَّق + طلب تغيير بدرجة المخاطرة + خطوات §8 — **والرباعية لا تُختصر لمدير الإدارة**) · نقل بلا إنهاء القديم → 422 · تداخل (المسمى×النطاق) → 409 · `activate` لا يعمل قبل اكتمال الإلزامي ثم يفعّل ويعيد البناء | `PositionService.php` | S3·S6·409·⑪ ✅ | — |
| SEC-18 | `ExpiryJob`: إسقاط الاستثناءات (NOW) والمراكز (CURDATE) في لحظتها + أسطر PermissionExpired/PositionEnded + إعادة بناء لمن مُسّ + تنبيه 30 يومًا بعطالة — وكرون `Governance/cron_permissions.php` | `ExpiryJob.php` + الكرون | S5·S27 ✅ | — |

**الحزام:** `tests/permission_resolver_test.php` = **19/19** ✅ (S1·S2·S3·S5·S6·S12·S16·S17 + deny/422)

## الموجة ⑧ · SEC الحراس — 4/4

| المهمة | ما فُعل | الملفات | الاختبار | اجتهاد |
|---|---|---|---|---|
| SEC-19 | `SelfGrantGuard`: منح النفس واعتماد الذات → 403 بنيويًّا ولو مدير الصلاحيات — وكل رفض في `guard_denials` | `SelfGrantGuard.php` | S8·S9 ✅ | — |
| SEC-20 | `SegregationOfDutiesGuard`: فحص عند حساب الصلاحية — اجتماع طرفَي تعارضٍ من الثمانية → **409 مع عرض التعارض باسمه**؛ والاستثناء بالرقابة التعويضية إلا ما ضابطه NULL (تصعيد السلطة الذاتي لا يُستثنى) | `SegregationOfDutiesGuard.php` | S18 (بأربعة أوجه) ✅ | — |
| SEC-21 | `SensitiveFieldGuard`: الترشيح في الخادم — الحقل غير المملوك **لا يُعاد أصلًا** والجزئي بآخر أربعة أرقام؛ وكل قراءة سطر في `sensitive_read_log` بمرجع المنح — **والجدول كان قائمًا بمخطط LEG-01 فوُسِّع لا استُبدل** | `SensitiveFieldGuard.php` · هجرتا read_log | S11·S26 ✅ | J-23 |
| SEC-22 | `BreakGlassService`: منح ≤24h · **يقرأ `guard_override_policies` فلا يتجاوز never** · لا يمنح النفس · بلاغ حوكمة آلي (نوع «بلاغ حوكمة وصلاحيات» حرج للدور 15) · ومسح 48h يُسقط غير المراجَع ويُصعِّد — في كرون الحوكمة | `BreakGlassService.php` + الكرون | S22·S23 + البلاغ ✅ | — |

**الحزام:** `tests/sec_guards_test.php` = **23/23** ✅
