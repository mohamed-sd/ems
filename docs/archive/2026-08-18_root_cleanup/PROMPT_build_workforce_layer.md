# برومبت: بناء «طبقة القوى التشغيلية» (EQUIP-OPE-S04) — Claude Code

> انسخ كل ما تحت الخط والصقه في Claude Code داخل مجلد المشروع `c:/wamp64/www/ems`.
> هذا البرومبت عقدٌ تنفيذيٌّ مُلزِم: يُنفِّذ مواصفات المستند `EQUIP-OPE-S04` كـ**طبقةٍ جديدةٍ مستقلّةٍ (Bolt-on)** فوق النظام الحالي **دون كسرٍ للنظام القائم ولا لهوية وشكل الشاشات**، مع تسجيل ملاحظاتٍ مستقبليةٍ لدمج `employee` و`worker` في كيانٍ واحد لاحقاً.

---

## 0) المهمّة في سطر
أنشئ وحدةً جديدةً `Workforce/` تُطبّق دورة القوى التشغيلية الميدانية (تسجيل · مهارات · عقد · تخصيص L4 · تقييم · إجازات وغياب · تحرّك ونقل · تسوية · احتياج)، بجداولها الجديدة وViewها، **بالقراءة فقط** من الإرث، **وصفر تعديلٍ** على أي جدولٍ أو شاشةٍ أو علاقةٍ قائمة.

## 1) اقرأ أولاً (مصدر الحقيقة — لا تكتب قبل فهمها)
- `config.php` (الاتصال + `db_table_has_column` + عزل الشركة) · `includes/permissions_helper.php` (`check_page_permissions`) · `includes/sessions.php`.
- `inheader.php` · `insidebar.php` · `includes/page_header.php` · `includes/excel_ui.php` · `includes/employee_types.php`.
- `Employees/employees.php` (نمط الشاشة المرجعي الكامل) · `Oprators/oprators.php` · `Oprators/get_contract_stats.php` (منطق الحصص) · `movement/` (إسناد/تحرّك المشغّلين) · `timesheet` (جدول الساعات، عمود `operator_hours`/`employee_id`).
- `REPORT_OPE_S04_workforce_implementation.md` (المعمارية المعتمدة وسجل القرارات).

## 2) المبادئ الحاكمة (غير قابلة للتفاوض)
1. **صفر `ALTER TABLE`** على أي جدولٍ قائم. تهجيرات `CREATE TABLE`/`CREATE VIEW` فقط.
2. **صفر تعديلٍ** لملفات الشاشات القائمة. كل الجديد داخل `Workforce/` و`app/Services/Workforce/`.
3. **الربط بالقيمة** نحو الإرث (لا قيود FK مفروضةٌ على جداولٍ قديمة). FK داخليٌّ بين جداول الطبقة فقط.
4. **قابلية تراجعٍ كاملة:** حذف جداول/شاشات الطبقة يعيد النظام لحالته الأصلية تماماً.
5. **اختبار انحدار:** بعد كل موجة، شغّل الشاشات القديمة (الموظفون/العقود/التشغيل/التايم‌شيت/الحركة/التقارير) وتأكّد أن نتائجها لم تتغيّر.

## 3) توحيد هوية وشكل الشاشات (إلزاميٌّ لمنع كسر التصميم)
كل شاشةٍ جديدةٍ **يجب** أن تتبع نفس بنية الشاشات القائمة حرفياً، بنفس ترتيب التضمين:
```php
<?php
session_start();                                  // أو include '../includes/sessions.php';
include '../config.php';
include '../includes/permissions_helper.php';
$page_permissions = check_page_permissions($conn, 'Workforce/<screen>.php');
if (!$page_permissions['can_view']) { header("Location: ../login.php?msg=..."); exit(); }
$page_title = "إيكوبيشن | <عنوان الشاشة>";
include '../inheader.php';
include '../insidebar.php';
// رأس الصفحة الموحّد:
$header_title = '...'; $header_icon = 'fas fa-...'; $header_actions = [...];
include '../includes/page_header.php';
```
قواعد التصميم:
- **لا CSS مضمَّن ولا inline styles.** كل التنسيق من `assets/css/ems.main.all.style.css` وأصول `assets/` القائمة، وبنفس أصناف (classes) الشاشات الحالية (`.main_head`, `.head_actions`, الجداول، البطاقات، الـbadges، الأزرار).
- استخدم `includes/page_header.php` لرأس الصفحة (لا تكتب رأساً يدوياً) و`includes/excel_ui.php` لأزرار التصدير.
- واجهةٌ عربيةٌ RTL، ونفس أيقونات Font Awesome، ونفس أنماط الرسائل (`?msg=...`) المعتمدة.
- جداول السجلّات بنفس شكل جدول `employees.php` (أعمدة الإجراءات أولاً، روابط البطاقة، الـpills للحالة).

## 4) الصلاحيات والتنقّل (نمط النظام القائم)
- سجّل كل شاشةٍ صفّاً في جدول `modules` (`code` = المسار النسبي مثل `Workforce/worker_register.php`) عبر تهجير بيانات (INSERT)، **لا تعديل بنية**.
- امنح الصلاحيات في `role_permissions` للدور **3 (مدير المشغلين = الموارد البشرية)**.
- بوّابة كل شاشةٍ عبر `check_page_permissions` (view/add/edit/delete) كما في القائم.
- ⚠️ **تحذيرٌ مؤكَّدٌ من الكود:** `check_page_permissions` يطابق بـ`code LIKE %...%` و**يفشل مفتوحاً (يمنح كل الصلاحيات) إن لم يُسجَّل الموديول**. لذا: (أ) سجّل صفّ الموديول **في نفس تهجير الشاشة** قبل نشرها؛ (ب) استخدم **كود المسار الكامل الفريد** تفادياً لتطابقٍ خاطئٍ مع كودٍ آخر يحويه.
- أضف بنود القائمة في `insidebar.php` ضمن كتلة الدور 3 **دون المساس** بباقي الكتل (إضافةٌ فقط).

## 5) معايير قاعدة البيانات (عالمية)
- `utf8mb4_unicode_ci`، كل جدولٍ يحمل `company_id` ويطبّق نفس عزل الشركة.
- تسميةٌ موحّدة: بادئة `worker_`/`workforce_`، snake_case، مفتاح خارجي `<entity>_id`، مفتاح أساسي `id`.
- فهارس على `company_id`, `worker_id`, `project_id`, `state`, والمفاتيح الرابطة.
- **Prepared Statements** في كل مسار كتابة (لا concatenation). **Transactions** للكتابة متعدّدة الجداول (مثل النقل: إغلاق تخصيص المصدر + فتح الوجهة ذرّياً).
- آلات الحالة (`state`) محروسةٌ بحارس انتقالٍ في طبقة الخدمة + تسجيلٌ في `activity_logs`.

## 6) القرارات المعتمدة (طبّقها كما هي)
1. `worker_contract` **مستقلٌّ تماماً** عن `drivercontracts`.
2. `worker_category` **جديدةٌ** (مشغّل/سائق · فني · مهندس · مشرف · مراقب · عمالة مساندة) مع **تعيينٍ آليٍّ أوليٍّ** من `employees.employee_type` (سائق/مشغّل ← مشغّل/سائق)، قابلةٌ للتعديل.
3. ربط L4→L5 عبر **VIEW** بمطابقةٍ دقيقةٍ مؤكَّدةٍ من الكود: `timesheet.operator = operations.id` (= `worker_allocation.operation_id`) **و** `timesheet.employee_id = worker_profile.employee_id` — **بلا أي عمودٍ جديدٍ في `timesheet`**.
4. الاعتمادات ودورة الحياة عبر **آلات حالةٍ مستقلّةٍ** داخل جداول الطبقة (لا `approval_requests`).
5. المالية: **إدخالٌ يدويٌّ** + عمود تعليقٍ مرجعيٍّ (`*_finance_note`) بجوار كل حقلٍ ماليٍّ للرجوع إليه عند إنشاء الإدارة المالية.
6. مرشّحو الاحتياج (8.10): حقلٌ نصيٌّ يدويٌّ الآن + تعليقُ خطّاف تكاملٍ لاحق.
7. السكن: جدولٌ جديد `housing_unit`؛ **العهدة مؤجّلة** (حقلٌ مرجعيٌّ `custody_received` فارغٌ الآن).
8. `worker_profile` يُنشأ **عند التصنيف يدوياً** من موظفٍ قائم (لا إنشاء تلقائي).
9. **هرم الإسناد (مؤكَّدٌ عكسياً):** L3 = `operations` (الآلية↔المشروع/العقد) · **L4 = `equipment_drivers`** (العامل↔الآلية بنوع الوردية — هو التخصيص الفعلي القائم) · L5 = `timesheet`. لذا `worker_allocation` **لا يكرّر** واقعة العامل↔الآلية، بل **طبقة إثراءٍ** تقرأ `equipment_drivers` بالقيمة (`equipment_driver_id`) و`operations` (`operation_id`) وتضيف فقط حقول المستند الناقصة. السقف «L4 ≤ L3» = عدد صفوف `equipment_drivers` النشطة لمعدةٍ/ورديةٍ ≤ `drivercontracts.daily_operators` — يفرضه محرّك الحصص **بالقراءة** دون لمس القائم.
10. المعدات المطقّمة: أعمدة `crew_role` + `lead_allocation_id` في `worker_allocation` (طبقة الإثراء).
11. الحالة الميدانية `presence_state`: **VIEW محسوبٌ** يكتمل تدريجياً.
12. التكويد: **يدويٌّ** كما في `employee_code`.

## 7) علاقة الموظف بالعامل والمفاتيح (جوهري)
- `worker_profile` امتدادٌ **1:1** لـ`employees`؛ يحمل `employee_id` **UNIQUE** يشير بالقيمة إلى `employees.id`.
- **كل الجداول والشاشات الجديدة تتعامل داخلياً مع `worker_id` (= `worker_profile.id`)**، لا مع `employee_id`.
- نقطة الجسر الوحيدة لقراءة بيانات الموظف أو مطابقة جداول الإرث (`equipment_drivers`/`drivercontracts`/`timesheet` التي تحمل `employee_id`) هي `worker_profile`:
  `worker_*.worker_id → worker_profile.employee_id = legacy.employee_id`.
- **لا عاملَ بلا موظف**؛ ولا تكرارٌ للبيانات الشخصية في الطبقة الجديدة.

## 8) ملاحظات الدمج المستقبلي (إلزاميّ التسجيل)
صمّم بحيث يكون دمج `employee` و`worker` في كيانٍ واحدٍ لاحقاً **عمليةً نظيفة**، وسجّل ذلك في ثلاثة مواضع:
1. **ملف ملاحظات** `Workforce/FUTURE_MERGE_NOTES.md` يشرح: الهدف النهائي دمج `worker_profile` داخل `employees` وإلغاء جسر `employee_id`، والخطوات، والأعمدة المتأثّرة.
2. **تعليق SQL** على كل جدولٍ ذي صلة: `COMMENT='FUTURE: worker↔employee merge — see Workforce/FUTURE_MERGE_NOTES.md'`.
3. **علامة كود موحّدة** عند كل نقطة جسرٍ بين المفتاحين: `// FUTURE-MERGE: employee_id↔worker_id bridge — to be collapsed`.
قاعدةٌ تصميميّةٌ تخدم الدمج: لا تُخزّن أي بياناتٍ شخصيةٍ في `worker_profile` (تبقى في `employees`)، فيصبح الدمج لاحقاً نقلَ أعمدةٍ تشغيليةٍ فقط.

## 9) المعمارية المختصرة المعتمدة (8 شاشات · 14 جدولاً · 3 Views)
**الشاشات (`Workforce/`):** `worker_register.php` (8.1 +تبويبا المهارات 8.2 والسجل المجمّع 8.9) · `worker_contract.php` (8.3) · `worker_allocation.php` (8.4) · `worker_evaluation.php` (8.5) · `worker_leave_absence.php` (8.6+8.13) · `worker_movement.php` (8.11+8.12) · `worker_settlement.php` (8.7) · `workforce_requirement.php` (8.10). + إعدادات السكن (مرجعي). التايم‌شيت (8.8): تقرير/VIEW لا شاشة.

**الجداول:** `worker_profile` · `worker_qualification` (يشمل الترقية/التدرّج) · `worker_backup` · `worker_restricted_site` · `worker_contract` · `worker_allocation` · `worker_evaluation` · `worker_evaluation_kpi` · `worker_leave_absence` · `worker_settlement` · `worker_settlement_line` · `worker_movement` · `workforce_requirement` · `housing_unit`.

**Views:** `v_worker_presence` · `v_worker_billable_hours` · `v_worker_worklog`.

## 10) طبقة الخدمة والمحرّكات
أنشئ `app/Services/Workforce/` كنقطة حقيقةٍ واحدةٍ تستضيف: محرّك الحصص (L4 ≤ L3 من `operations`) · الجاهزية البشرية (state + اللياقة + صلاحية الرخص قبل التخصيص) · الاعتمادات (مهمّة مجدوَلة على `worker_qualification.expiry_date`) · التناوب (من `worker_contract`) · التغطية (`worker_backup`/`worker_leave_absence`) · الأحداث (الحوافز/الجزاءات من `worker_evaluation`) · التخطيط (Views مقابل `workforce_requirement`). تُستدعى من الشاشات الجديدة فقط.

## 11) خطة التنفيذ (موجاتٌ — سلِّم واختبر بين كلٍّ منها)
- **م0:** مجلد `Workforce/` + `app/Services/Workforce/` + تهجيرات الإنشاء + تسجيل الموديولات/الصلاحيات + `FUTURE_MERGE_NOTES.md`.
- **م1:** 8.1 سجل العامل (`worker_profile` + تبويب المهارات 8.2) + محرّك الاعتمادات.
- **م2:** 8.3 العقد + 8.4 التخصيص L4 فوق محرّك الحصص.
- **م3:** تقرير/VIEW التايم‌شيت + 8.6/8.13 الإجازات والغياب + 8.11/8.12 التحرّك والنقل.
- **م4:** 8.5 التقييم + 8.7 التسوية + 8.10 الاحتياج والتخطيط.
- **م5:** Views المجمَّعة (8.9) ولوحة المؤشّرات.

## 12) التسليم لكل موجة
- ملفات التهجير (`database/migrations/2026_..._workforce_<n>.sql`) **للمراجعة قبل التطبيق** — لا تطبّق تهجيراً مدمّراً تلقائياً.
- ملفات الشاشات + الخدمة.
- ملاحظةٌ موجزةٌ بما أُنشئ، ونتيجة اختبار الانحدار للشاشات القديمة، وتأكيد «صفر تعديلٍ على القائم».

> ابدأ بالموجة 0 ثم الموجة 1 فقط، وقف للمراجعة قبل المتابعة.
