# برومت تنفيذ احترافي: إضافة «قسم الصيانة» إلى نظام EMS

> أرسل هذا الملف كاملاً إلى Claude لتنفيذ قسم الصيانة. النظام: PHP (mysqli) + MySQL على WAMP، مجلد المشروع `C:\wamp64\www\ems`. الواجهة عربية RTL.

---

## 0) تعليمات إلزامية للمنفّذ (اقرأ أولاً)
1. **ادرس الكود القائم قبل كتابة أي سطر**، وخصوصاً هذه الملفات لتلتزم بنفس النمط حرفياً:
   - `config.php` (اتصال `$conn` mysqli)، `includes/sessions.php`
   - `includes/permissions_helper.php` (دوال الصلاحيات)، `includes/dynamic_nav.php`
   - `includes/insidebar.php` (السايدبار)، `includes/topbar.php`، `includes/page_header.php`
   - شاشة CRUD مرجعية: `Suppliers/suppliers.php` + `admin/permissions/modules.php`
   - كرت المعدة: `Equipments/equipment_profile.php`
   - الحالة الحالية للمعدة: `Equipments/equipments_fleet.php` (دوال `normalize_equipment_availability_*`)، ومسار `movement/move_oprators.php`
   - تصنيف الأعطال: `Equipments/manage_failure_codes.php` + جدول `failure_codes` + `timesheet_failure_hours`
2. **لا تكسر أي شيء قائم.** كل تغييرات قاعدة البيانات **إضافية فقط** (CREATE / ALTER ADD COLUMN). ممنوع تعديل/حذف أعمدة أو جداول قائمة.
3. **خذ نسخة احتياطية من قاعدة البيانات** قبل تشغيل أي migration، وضع كل migration في `database/migrations/` بصيغة `YYYY_MM_DD_*.sql` (كنمط الموجود).
4. نفّذ **مرحلياً** (القسم 8) واختبر كل مرحلة قبل التالية.
5. اكتب كل النصوص الظاهرة بالعربية، وكل التعليقات البرمجية واضحة.

---

## 1) الهدف
إضافة قسم صيانة متكامل **كدور جديد** (على نمط بقية الأدوار في النظام)، يدير دورة الصيانة (بلاغ ← أمر صيانة ← فحص ← إغلاق) ويتكامل مع المعدات والتشغيل والتايم‌شيت ونظام الأعطال القائم، مع **توحيد كامل** للتصميم والترويسة والفورمات مع باقي الشاشات.

---

## 2) القرارات المعتمدة (ملزمة — لا تجتهد خلافها)
1. **عزل كامل لكل شركة (الدور ليس عاماً):** لكل شركة **قسم صيانتها الخاص المعزول تماماً**. `company_id` **INT NOT NULL إجباري** على **كل** جداول الصيانة، وكل استعلام عرض/تعديل/حذف/تقرير **مقيّد بصرامة** بـ `company_id` الخاص بالمستخدم — لا ترى أي شركة بيانات صيانة شركة أخرى مطلقاً. (ملاحظة معمارية: صفّ تعريف الدور يُسجَّل في `roles` كنمط بقية الأدوار في النظام، لكن **العزل الفعلي يتحقق 100% على مستوى البيانات** عبر `company_id` الإجباري وربط كل مستخدم بشركته. إن طُلب لاحقاً فصل صفوف الأدوار فيزيائياً لكل شركة فهو مهمة منفصلة أوسع تمسّ منظومة الصلاحيات العامة).
2. **إنشاء دورين:** «ادارة الصيانة» (مدير، level=1) + «مشرف صيانة» (فرعي، level=2، parent=دور المدير). **دور المدير يأخذ كل الصلاحيات** (view/add/edit/delete على كل شاشات الصيانة)، و**دور المشرف يأخذ صلاحية العرض فقط** (`can_view=1` والباقي 0). أنشئ صفوف `role_permissions` صريحة لكلٍّ منهما — لا تعتمد على الوراثة لأنها للقائمة الجانبية فقط لا للصلاحيات.
3. **بلا اعتماد/موافقات في الصيانة إطلاقاً.** دورة أمر الصيانة: `بلاغ → تنفيذ → فحص → إغلاق` (+ ملغى). لا تستخدم `approval_*` في أي جدول/شاشة صيانة.
4. **البلاغ شاشة موحّدة لكل الإدارات** على نمط شاشة المحادثات: يصلها كل مستخدم مسجّل (تتجاوز فحص صلاحية الموديول مثل `chats/`)، مع **أيقونة + عدّاد في التوبار** (`includes/topbar.php`). البلاغ = تذكرة/فورم شامل فقط.
5. **حالة المعدة `availability_status` (دخول «تحت الصيانة») تبقى حصرياً بيد مدير الحركة والتشغيل** عبر `move_oprators` القائم. **الصيانة لا تكتب `availability_status` إطلاقاً عند الدخول.**
6. **إغلاق أمر الصيانة يعيد `availability_status='متاحة للعمل'` تلقائياً** + يحدّث `last_maintenance_date` (الموضع الوحيد الذي تكتب فيه الصيانة هذا الحقل).
7. **محور الصحة الجديد:** أضف حقل `equipment_health` لجدول `operations` (القسم 4.5). فتح أمر الصيانة ⇐ `معطلة`، إغلاقه ⇐ `سليمة`. مستقل عن `status` (التشغيل) و`availability_status` (الإتاحة).
8. **إعادة استخدام `failure_codes` كما هو** (تُخزَّن `failure_code_id` كمفتاح أجنبي فقط، بلا تعديل بياناته)، و**نقل ملكية شاشة `manage_failure_codes.php` من دور الأسطول إلى دور الصيانة** (تعديل `owner_role_id` للموديول، لتصبح حصراً للصيانة).
9. **ساعات الصيانة الوقائية من `timesheet.operator_hours` الفعلية.**
10. **توليد الوقائية:** قائمة «مستحقة» + زر «توليد أمر» يدوي (بلا cron).
11. **قطع الغيار وتكلفة العمالة: إدخال يدوي** (اسم/تكلفة/ساعات) في أسطر الأمر — **لا مخزون ولا رواتب** (لا تنشئ جدول مخزون).
12. **الصيانة تغطّي كل المعدات** (مملوكة للشركة أو لمورّد)، مع حقل **«جهة التكلفة»** (داخلي/خارجي) في أمر الصيانة.
13. **نتيجة التفتيش تُحدّث حقول المعدة** `equipment_condition` و`engine_condition` على الكرت + تُخزَّن في جدول التفتيش. الزيارة الميدانية = نوع تفتيش (مدموجة، لا شاشة منفصلة).
14. **تبويب «الصيانة» في كرت المعدة** (`equipment_profile.php`) مرئي **لكل من يصل للكرت** (قراءة).

---

## 3) معايير التوحيد الإلزامية (Design & Boilerplate)

### 3.1 رأس كل صفحة (انسخ هذا النمط من الشاشات القائمة)
```php
<?php
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

$current_role  = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id    = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌"); exit();
}

// تفعيل أعمدة الحذف الناعم إن لزم (نمط db_table_has_column الموجود)
// ... (كما في Suppliers/suppliers.php)

// 🔐 الصلاحيات (للشاشات المملوكة لدور الصيانة فقط — وليس شاشة البلاغ الموحّدة)
$page_permissions = check_page_permissions($conn, 'CODE_HERE');
$can_view   = $page_permissions['can_view'];
$can_add    = $page_permissions['can_add'];
$can_edit   = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];
if (!$can_view) { /* أعد التوجيه برسالة كنمط النظام */ }
```

### 3.2 التخطيط والتصميم
- السايدبار + التوبار: `include '../includes/insidebar.php';` (يستدعي `topbar.php` و`dynamic_nav.php` تلقائياً) — **استخدمه كما تستخدمه الشاشات القائمة**.
- ترويسة الصفحة الموحّدة `.main_head`: استخدم `includes/page_header.php` عبر تعيين `$header_title`, `$header_icon`, `$header_actions`, `$header_back` ثم `include` (راجع توثيق الملف). **لا تكتب ترويسة يدوية.**
- الأنماط: استخدم **فقط** `assets/css/ems.main.all.style.css` (نفس الـ selectors القائمة). لا تضف CSS مخصّصاً إلا للضرورة القصوى وداخل نفس روح التصميم (الألوان: navy/gold، خط Cairo، RTL).
- الجداول: **DataTables** بنفس إعداد الشاشات القائمة:
  - CSS: `/ems/assets/vendor/datatables/css/*`
  - JS: `../../includes/js/jquery-3.7.1.main.js` ثم `../../includes/js/jquery.dataTables.main.js`
  - اللغة: `language.url = "/ems/assets/i18n/datatables/ar.json"`
  - نمط الفورم (إظهار/إخفاء): `#xxxForm` مع `slideToggle` (كما في `admin/permissions/modules.php`).
- الأيقونات: FontAwesome (نفس الأصناف المستخدمة). الروابط المطلقة عبر `ems_url()` إن وُجدت.
- رسائل النجاح/الخطأ: نمط `?msg=...✔ / ❌` + `.success-message` (كما في الشاشات القائمة).

### 3.3 قواعد البيانات والأمان
- **كل جداول الصيانة** `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`.
- **الربط بجدول `equipments` وغيره عبر مفاتيح رقمية `id` فقط** (لتفادي تعارض الترميز مع جداول `utf8_general_ci`).
- **عزل الشركات (إجباري):** كل جدول صيانة يحمل `company_id INT NOT NULL` (بلا سماح بقيمة فارغة)، وكل استعلام عرض/تعديل/حذف/تقرير **مقيّد إجبارياً بـ `company_id`** الخاص بالمستخدم (كنمط `suppliers.php`). أي إدراج بلا `company_id` صالح يجب أن يُرفض. مستخدم شركة لا يصل إطلاقاً لبيانات شركة أخرى.
- **الحذف الناعم:** أعمدة `is_deleted TINYINT(1) DEFAULT 0, deleted_at DATETIME NULL, deleted_by INT NULL` في كل جدول صيانة رئيسي، واستبعاد `COALESCE(is_deleted,0)=0` في العرض.
- **Prepared statements** لكل عمليات الكتابة (INSERT/UPDATE/DELETE) ولأي مدخلات مستخدم (لا تضمين مباشر).
- سجّل العمليات في `activity_logs` إن كان ذلك متّسقاً مع بقية الشاشات.

### 3.4 تسجيل الشاشات والصلاحيات
- كل شاشة صيانة جديدة تُسجَّل في جدول `modules` (`code` = المسار، `owner_role_id` = دور الصيانة، `is_link=1`, `icon`, `display_order`) — عبر SQL ضمن الـ migration (وليس يدوياً)، بنفس بنية بيانات `modules` القائمة.
- أنشئ صفوف `role_permissions` لدور المدير **وللمشرف** (نسخة مطابقة).

---

## 4) تغييرات قاعدة البيانات (migration واحد أو أكثر في `database/migrations/`)

### 4.1 الأدوار
- INSERT في `roles`: «ادارة الصيانة» (parent_role_id=NULL, level=1, role_scope='gloable', status='1').
- INSERT في `roles`: «مشرف صيانة» (parent_role_id = id دور المدير, level=2, status='1').

### 4.2 الشاشات (`modules`) — owner_role_id = دور «ادارة الصيانة»
سجّل: البلاغات، أوامر الصيانة، التفتيش، الخطة الوقائية، إعدادات الصيانة (الكتالوجات). 
**ونقل الملكية:** `UPDATE modules SET owner_role_id = <maintenance_role_id> WHERE code LIKE '%manage_failure_codes.php%';` (نقل تصنيف الأعطال للصيانة).
> ملاحظة شاشة البلاغ: بما أنها موحّدة للجميع، عامِلها مثل `chats/` (وصول لكل مستخدم) — يكفي تسجيلها كموديول للعرض في التوبار، مع استثناء مسارها في فحص الصلاحية إن لزم (راجع `enforce_current_page_view_permission`).

### 4.3 الصلاحيات (`role_permissions`)
- **المدير:** صفوف كاملة `can_view=can_add=can_edit=can_delete=1` على **كل** شاشات الصيانة.
- **المشرف:** صفوف **عرض فقط** `can_view=1` و`can_add=can_edit=can_delete=0` على نفس الشاشات.

### 4.4 جداول الصيانة (9 جداول جديدة، بادئة `mnt_`)
أنشئها وفق القرارات (كلها utf8mb4_unicode_ci + **`company_id INT NOT NULL`** + code + الحذف الناعم + الطوابع الزمنية). الحقول المطلوبة (راجع ملف `maintenance_erd.mermaid` للارتباطات):
1. `mnt_breakdown` — تذكرة البلاغ الموحّدة: `equipment_id, project_id, reported_by, reporter_dept, report_datetime, failure_code_id (FK→failure_codes), severity, is_stopped, description, attachment, order_id (FK→mnt_order), state(جديد/قيد التقييم/محوّل/مغلق)`.
2. `mnt_order` — أمر الصيانة: `breakdown_id, plan_id, inspection_id, equipment_id, project_id, source(بلاغ/وقائي/تفتيش), maint_type, priority, cost_party(داخلي/خارجي), vendor_id (FK→suppliers), workshop, technician_id (FK→users), supervisor_id, failure_code_id (FK→failure_codes), diagnosis, root_cause_id (FK→mnt_lookup), actions_taken, work_start, work_end, downtime_hours, labor_cost, parts_cost, external_cost, total_cost, inspection_result(ناجح/راسب), stage/state(بلاغ/تنفيذ/فحص/إغلاق/ملغى)`.
3. `mnt_order_labor` — أسطر العمالة (إدخال يدوي): `order_id, employee_id (FK→users), role, hours, hourly_rate, cost`.
4. `mnt_order_part` — أسطر القطع (إدخال يدوي بلا مخزون): `order_id, part_name, category, quantity, unit_cost, subtotal, is_major_component`.
5. `mnt_inspection` — التفتيش/الزيارة: `inspection_type(دوري/زيارة ميدانية/استلام/بعد حادث/...), equipment_id, project_id, inspector_id (FK→users), scheduled_date, score, overall_result, tech_readiness_state, state`.
6. `mnt_inspection_line` — بنود الاستمارة: `inspection_id, component, condition(سليم/ملاحظة/حرج), recommendation`.
7. `mnt_plan` — الخطة الوقائية: `name, scope, equipment_id, category_id (FK→equipments_types), trigger_basis(ساعات/زمن), interval_value, tolerance, last_done_date, last_done_meter, next_due_date, next_due_meter, state`.
8. `mnt_plan_task` — مهام الخطة: `plan_id, name, task_type (FK→mnt_lookup), component, est_hours`.
9. `mnt_lookup` — كتالوج موحّد: `type(سبب عطل/سبب توقّف/نوع مهمة/ورشة), name, extra`.

### 4.5 تعديل جدول `operations` — محور الصحة الجديد
```sql
ALTER TABLE operations
  ADD COLUMN equipment_health ENUM('سليمة','معطلة') NOT NULL DEFAULT 'سليمة'
      COMMENT 'الصحة الفنية للمعدة (مستقلة عن status التشغيلي)' AFTER status,
  ADD COLUMN health_reason VARCHAR(150) NULL COMMENT 'سبب العطل، مثل: صيانة' AFTER equipment_health,
  ADD COLUMN health_updated_at DATETIME NULL,
  ADD COLUMN health_updated_by INT NULL;
```
- **عند فتح أمر صيانة** لمعدة لها تشغيل ساري (`operations.status` نشط): اضبط `equipment_health='معطلة', health_reason='صيانة'`.
- **عند إغلاق الأمر:** اضبط `equipment_health='سليمة', health_reason=NULL`.
- لا تمسّ `operations.status` (التشغيل) إطلاقاً. اعرض المحورين منفصلين في الواجهات.

### 4.6 الفهارس
أضف فهارس على: `mnt_order(equipment_id, company_id, state)`, `mnt_breakdown(equipment_id, company_id, state)`, `mnt_inspection(equipment_id)`, `mnt_plan(equipment_id, next_due_date)`, و`operations(equipment_health)`.

---

## 5) الشاشات والملفات (مجلد `Maintenance/`)
كل الشاشات تلتزم بمعايير القسم 3 (نفس الترويسة/التوبار/DataTables/الفورم).

### 5.1 `Maintenance/breakdowns.php` — البلاغ الموحّد
- وصول لكل مستخدم مسجّل (نمط `chats/`). فورم بلاغ شامل: المعدة، المشروع/الموقع، القسم المُبلِّغ، التاريخ/الوقت، `failure_code_id` (من `failure_codes`)، الخطورة، هل المعدة متوقفة، الوصف، مرفق.
- قائمة البلاغات مع فلترة وحالة. لمسؤول الصيانة: زر «إصدار أمر صيانة» يحوّل البلاغ إلى `mnt_order`.
- **عدّاد البلاغات «الجديدة»** يُعرض كـ badge في التوبار (5.6) وفي قائمة الأوامر.

### 5.2 `Maintenance/orders.php` — أوامر الصيانة (المحور)
- قائمة + فورم بمراحل: بلاغ ← تنفيذ ← فحص ← إغلاق. أسطر عمالة وأسطر قطع (إدخال يدوي)، حساب `total_cost` آلياً. حقل «جهة التكلفة».
- **عند الفتح:** اضبط `operations.equipment_health='معطلة'` للمعدة (4.5).
- **عند الإغلاق:** اشترط `actions_taken + root_cause + inspection_result=ناجح` ← ثم: `equipment_health='سليمة'`، `equipments.availability_status='متاحة للعمل'` + `last_maintenance_date=الآن`، أعِد جدولة الخطة الوقائية، واكتب سطر تاريخ للمعدة.

### 5.3 `Maintenance/inspections.php` — التفتيش (يشمل الزيارة كنوع)
- فورم استمارة + بنود (`mnt_inspection_line`). نتيجة `tech_readiness_state`.
- **عند الإكمال:** حدّث `equipments.equipment_condition` و`engine_condition` بنتيجة التفتيش + خزّنها في الجدول.

### 5.4 `Maintenance/preventive_plans.php` — الخطة الوقائية
- خطط ومهام. احسب `next_due_meter` من **`timesheet.operator_hours` الفعلية** المتراكمة للمعدة.
- قائمة «مستحقة الآن» + زر **«توليد أمر»** يدوي ينشئ `mnt_order` بنوع وقائي.

### 5.5 `Maintenance/master_data.php` — إعدادات/كتالوجات
- إدارة `mnt_lookup` (أسباب الأعطال، أسباب التوقّف، أنواع المهام، الورش).
- رابط إلى شاشة تصنيف الأعطال المنقولة (`manage_failure_codes.php`) بعد تحويل ملكيتها للصيانة.

### 5.6 التوبار — أيقونة البلاغات
- في `includes/topbar.php` ضمن `.ems-topbar-actions`: أضف أيقونة «البلاغات» (مثل نمط أيقونات التوبار الموجودة) مع **badge بعدد البلاغات الجديدة** (استعلام مُحسَّن، مخزَّن مؤقتاً كنمط cache دور المستخدم في نفس الملف). تربط إلى `breakdowns.php`.

### 5.7 كرت المعدة — تبويب «الصيانة»
- في `Equipments/equipment_profile.php` أضف قسم/تبويب «الصيانة» (مرئي لكل من يصل للكرت) يعرض: أوامر الصيانة لهذه المعدة، آخر صيانة، ساعات التوقّف، والمؤشرات (MTBF/MTTR/التكلفة/نسبة الجاهزية) محسوبة عبر VIEW/استعلام على `mnt_order` — بنفس نمط الأقسام الموجودة في الكرت.

### 5.8 تقرير الصيانة (ضمن `emsreports`)
- سجّل `report_code` للصيانة في `report_role_permissions` (عبر نمط `admin/reports_permissions.php`)، وابنِ تقرير المؤشرات بنفس بنية تقارير `emsreports`.

---

## 6) منطق الحالة (تلخيص حاسم — لا تخالفه)
| المحور | الحقل | من يكتبه |
|---|---|---|
| الإتاحة (دخول الصيانة) | `equipments.availability_status` = 'تحت الصيانة' | **مدير الحركة والتشغيل فقط** (`move_oprators`، بلا تغيير) |
| الإتاحة (الخروج) | `equipments.availability_status` = 'متاحة للعمل' | **آلياً عند إغلاق أمر الصيانة** |
| الصحة الفنية | `operations.equipment_health` (سليمة/معطلة) | **الصيانة** (فتح→معطلة، إغلاق→سليمة) |
| التشغيل | `operations.status` | التشغيل (بلا مساس من الصيانة) |
| الحالة الفنية للمعدة | `equipment_condition`/`engine_condition` | **التفتيش** عند الإكمال |

---

## 7) ما يجب عدم كسره
- لا تعدّل بنية أي جدول قائم سوى `ALTER ADD COLUMN` المذكورة لـ `operations`.
- لا تغيّر منطق الحالة في `move_oprators.php` ولا محرّك الموافقات (يبقى للتشغيل فقط).
- لا تنشئ مخزوناً ولا رواتب. لا تكرّر بيانات `failure_codes` (استخدم الـ id).
- لا تكسر `dynamic_nav`/`permissions_helper`؛ أضِف فقط بيانات الأدوار/الشاشات/الصلاحيات.
- احترم العزل حسب `company_id` في كل استعلام.

---

## 8) منهجية ومراحل التنفيذ
1. **M1 — قاعدة البيانات:** كل الـ migration (الأدوار، الشاشات، الصلاحيات، الجداول الـ9، ALTER operations، الفهارس). تحقّق من نجاحها على نسخة.
2. **M2 — البنية والوصول:** إنشاء مجلد `Maintenance/` + شاشة فارغة بالترويسة الموحّدة للتأكد من الصلاحيات والقائمة الجانبية، وأيقونة التوبار للبلاغ.
3. **M3 — البلاغ + أمر الصيانة** (المسار الأساسي) مع منطق الحالة (القسم 6).
4. **M4 — التفتيش + الخطة الوقائية + الكتالوجات + نقل تصنيف الأعطال.**
5. **M5 — تبويب الكرت + التقرير/المؤشرات.**
6. **M6 — اختبار شامل** (القسم 9) ثم إنشاء مستخدم «مسؤول الصيانة».

---

## 9) معايير القبول (Checklist)
- [ ] دور «ادارة الصيانة» + «مشرف صيانة» يظهران في `main/users.php` وقائمة الأدوار.
- [ ] مستخدم الصيانة يرى **فقط** شاشات الصيانة في القائمة الجانبية، ويُمنع من شاشات الأدوار الأخرى.
- [ ] المدير لديه **كل الصلاحيات**، والمشرف **عرض فقط** (لا إضافة/تعديل/حذف) ويستطيع فتح الشاشات فعلاً (لا «القائمة ظاهرة والوصول مرفوض»).
- [ ] **عزل الشركات:** مستخدم صيانة شركة لا يرى أي بلاغ/أمر/خطة/تفتيش لشركة أخرى (اختبار عزل صريح).
- [ ] شاشة البلاغ يصلها **كل** مستخدم (من أي دور) عبر التوبار، والعدّاد يعمل.
- [ ] إنشاء بلاغ → إصدار أمر → فتح الأمر يضبط `operations.equipment_health='معطلة'` مع بقاء `status` كما هو.
- [ ] إغلاق الأمر يعيد `equipment_health='سليمة'` و`availability_status='متاحة للعمل'` ويحدّث `last_maintenance_date`.
- [ ] `availability_status='تحت الصيانة'` لا تُكتب من الصيانة عند الدخول (تبقى من التشغيل).
- [ ] التفتيش يحدّث `equipment_condition`/`engine_condition`.
- [ ] الخطة الوقائية تحسب الاستحقاق من `timesheet.operator_hours`، وزر التوليد ينشئ أمراً.
- [ ] تبويب «الصيانة» في كرت المعدة يعرض الأوامر والمؤشرات لكل من يفتح الكرت.
- [ ] كل الجداول utf8mb4 بـ **`company_id NOT NULL`**، وكل استعلام معزول بالشركة، بحذف ناعم، وكل الكتابات prepared statements.
- [ ] التصميم/الترويسة/التوبار/DataTables مطابقة لبقية الشاشات (لا انحراف بصري).
- [ ] لا أخطاء، ولا كسر لأي شاشة قائمة (خصوصاً الأسطول والتشغيل والكرت).
