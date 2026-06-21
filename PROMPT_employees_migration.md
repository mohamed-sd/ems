# برومبت شامل: تحويل `drivers` إلى سجل الموظفين الموحّد `employees` (Claude Code)

> انسخ كل ما تحت الخط والصقه في Claude Code داخل مجلد المشروع `c:/xampp/htdocs/ems`.
> **هذا تغيير جوهري واسع المدى يمسّ مكاناً مركزياً. نفّذه على فرع git جديد ونسخة قاعدة بيانات تجريبية (staging) أولاً، مع اختبار انحدار شامل قبل أي تطبيق على الإنتاج.**

---

## 1. السياق

نظام EMS: PHP خام + MySQLi، عربي RTL، متعدّد الشركات (عزل بـ`company_id`)، يعمل على XAMPP، بأسلوب استعلامات mysqli مباشرة مع `mysqli_real_escape_string` و`db_table_has_column`. لا توجد قيود مفاتيح أجنبية (FK) مفروضة على مستوى قاعدة البيانات؛ الروابط بين الجداول **بالقيمة (id value)**.

جدول `drivers` الحالي (~43 عموداً) هو فعلياً **سجل موظفين** (الاسم، الهوية، الرخصة، المهارات، الصحة، الراتب، الأداء، السلوك، الحوادث، المراجع، التواصل)، لكنه مسمّى ومُتعامَل معه كـ«سائقين» فقط.

## 2. الهدف

**إلغاء مفهوم «جدول سائق» نهائياً.** قسم الموارد البشرية يصبح **سجل موظفين موحّداً `employees`** يضمّ كل القوى العاملة، و«السائق/المشغّل» مجرّد **نوع موظف** (`employee_type`). الحالة النهائية: **لا يوجد كائن مُستخدَم باسم `drivers`** (لا جدول ولا View دائم)؛ يبقى الجدول الأصلي مؤرشفاً فقط كنسخة احتياطية.

**لماذا آمن:** نرحّل البيانات إلى `employees` **بحفظ نفس قيم المفاتيح الأساسية (`id`)**، فتبقى كل القيم المرجعية في النظام (`equipment_drivers.driver_id`, `drivercontracts.driver_id`, `timesheet.driver`...) صحيحة تشير إلى `employees` دون أي تعديل عليها.

## 3. القرارات المعتمدة (مدمجة — نفّذها كما هي)
- **أنواع الموظفين** (`employee_type`): `سائق/مشغّل` · `مساعد` · `فني` · `مبنشر` · `مشرف` · `إداري` · `فني ورشة` · `أمن` · `أخرى`.
- **single-table:** حقول التشغيل تبقى أعمدة على `employees` (لا جدول امتداد منفصل الآن).
- **أسماء أعمدة المفاتيح في الجداول الأخرى تبقى كما هي** (`driver_id`, `timesheet.driver`...) وتشير إلى `employees.id` — **لا تُعاد تسميتها** (لتقليل نطاق الخطر).
- **ربط الموظف بحساب `users`:** مؤجَّل (ليس في هذه المرحلة).
- الكود الفيزيائي `driver_code` يبقى كاسم عمود؛ يُعرض في الواجهة كـ«كود الموظف».

## 4. اقرأ أولاً (مصدر الحقيقة — لا تكتب قبل قراءتها)
- مخطّط: `database/equipation_manage.sql` (تعريف `drivers` وكل ما يرجع إليه).
- شاشات الموارد البشرية: `Drivers/drivers.php`, `driver_profile.php`, `driver_truck_history.php`, `deactivate_driver_handler.php`, `get_driver_data.php`, `drivercontracts.php`, `drivercontracts_details.php`.
- ربط المعدات/التشغيل: `Equipments/equipments_drivers.php`, `Equipments/add_drivers.php`, `movement/project_drivers.php`, `movement/move_oprators.php`, `movement/movement_operations.php`, `movement/save_equipment_drivers.php`, `movement/map_page.php`.
- التايم شيت: `Timesheet/get_drivers.php`, `Timesheet/timesheet.php`, `Timesheet/view_timesheet.php`, `Timesheet/timesheet_details.php`.
- البنية: `config.php`, `includes/permissions_helper.php` (`db_table_has_column`, `check_page_permissions`)، إطار Excel (`includes/excel_ui.php`, `app/Services/Excel/ExcelRegistry.php`).

---

## 5. خطوات التنفيذ (بالترتيب، مع توقّف للتحقق بعد كل خطوة)

### الخطوة 0 — الأمان والجرد (لا تغيير وظيفي)
1. أنشئ **فرع git جديد** ونفّذ كل شيء على **نسخة staging من قاعدة البيانات** أولاً. تأكّد من **نسخة احتياطية كاملة**.
2. **جرد آلي شامل** بـ grep في كل `*.php` (وأي SQL) لكل أنماط: `FROM drivers`, ` drivers `, `JOIN drivers`, `INTO drivers`, `UPDATE drivers`, `DELETE FROM drivers`, `` `drivers` ``, و**`ALTER TABLE drivers`** (الإضافة الديناميكية وقت التشغيل) و**`SHOW COLUMNS FROM drivers`**.
3. أنتج **`database/employees_migration_inventory.md`**: جدول بكل ملف/سطر/نوع المرجع. هذا مرجع العمل واختبار الانحدار.

### الخطوة 1 — إنشاء `employees` والترحيل بحفظ المفاتيح
1. أنشئ `database/employees_create_and_migrate.sql`:
   - `CREATE TABLE employees LIKE drivers;` ثم أضِف الأعمدة الجديدة، **أو** اكتب `CREATE TABLE employees (...)` يطابق كل أعمدة `drivers` بالاسم والنوع تماماً + الأعمدة الجديدة:
     - `employee_type VARCHAR(40) NOT NULL DEFAULT 'سائق/مشغّل'`.
     - حقول عامة ناقصة: `birth_date DATE NULL`, `nationality VARCHAR(80) NULL`, `blood_type VARCHAR(8) NULL`, `whatsapp VARCHAR(50) NULL`, `emergency_contact_name VARCHAR(150) NULL`, `emergency_contact_relation VARCHAR(80) NULL`, `emergency_contact_phone VARCHAR(50) NULL`, `license_issue_date DATE NULL`, `license_grade VARCHAR(40) NULL`, `license_photo VARCHAR(255) NULL`, `medical_report_path VARCHAR(255) NULL`.
   - **رحّل بحفظ المفاتيح:** `INSERT INTO employees (<كل أعمدة drivers الصريحة>) SELECT <نفس الأعمدة> FROM drivers;` مع إبقاء عمود `id` كما هو، وكل السجلات تأخذ `employee_type='سائق/مشغّل'`.
   - حافظ على `AUTO_INCREMENT` بحيث يلي أعلى `id` حالي (تجنّب تعارض المفاتيح مستقبلاً).
2. **تحقّق فوري:** `SELECT COUNT(*)` متطابق، و`SELECT MAX(id)` متطابق، وعيّنة صفوف عشوائية متطابقة الحقول بين `drivers` و`employees`.

### الخطوة 2 — إعادة كتابة كل مراجع الكود `drivers` → `employees`
1. من قائمة الجرد، حوّل **كل** `SELECT/JOIN/INSERT/UPDATE/DELETE` من `drivers` إلى `employees` في كل ملف.
2. **أزِل أنماط `ALTER TABLE drivers ... ADD COLUMN` و`SHOW COLUMNS FROM drivers` الديناميكية** — لم تعد لازمة (الأعمدة دائمة في `employees`). استبدلها عند اللزوم بـ`db_table_has_column($conn,'employees',...)`.
3. **لا تغيّر أسماء أعمدة المفاتيح** في الجداول الأخرى (`driver_id`, `t.driver`...) — تبقى تشير إلى `employees.id`.
4. إن وُجد كيان `drivers` في إطار Excel (`ExcelRegistry`)، حدّث جدوله إلى `employees` (مع إبقاء سلوك التصدير/الاستيراد).

### الخطوة 3 — شبكة أمان انتقالية ثم الأرشفة النهائية
1. **مؤقتاً أثناء التحويل فقط:** أنشئ View للقراءة `CREATE VIEW drivers AS SELECT <أعمدة drivers الأصلية بالترتيب> FROM employees;` — يضمن أن أي مرجع فاتك لا يكسر النظام فوراً أثناء العمل.
2. بعد التأكد (بالخطوة 7) أن **كل** المراجع حُوّلت ونجح اختبار الانحدار:
   - **احذف الـ View** (`DROP VIEW drivers`).
   - **أعد تسمية الجدول الأصلي** `RENAME TABLE drivers TO drivers_legacy_backup;` (لا يُحذف — أرشيف).
   - الحالة النهائية: **لا كائن باسم `drivers` يُستخدم** في الكود أو القاعدة.

### الخطوة 4 — شاشة «سجل الموظفين» (تحويل `Drivers/drivers.php`)
- حوّلها إلى **«سجل الموظفين»** بنموذج **ديناميكي حسب `employee_type`** (على نمط فورم التايم شيت `Timesheet/timesheet.php`):
  - **الحقول العامة** (تظهر لكل الأنواع): الاسم، الكود، الهوية، الميلاد، الجنسية، فصيلة الدم، الصحة، الهاتف/واتساب، العنوان، جهة الطوارئ، الراتب/الانتماء، الأداء/السلوك/الحوادث، المراجع، الملاحظات، الحالة.
  - **الحقول الخاصة بالتشغيل** (تظهر لأنواع التشغيل فقط: سائق/مشغّل، مبنشر...): الرخصة (رقمها/نوعها/إصدارها/انتهائها/درجتها/صورتها/الجهة)، `specialized_equipment`, `years_on_equipment`, `skill_level على الآلية`.
- أضِف **قائمة اختيار «نوع الموظف»** في أعلى النموذج تتحكّم بإظهار/إخفاء الأقسام (JS).
- أضِف **فلتر/تبويب حسب النوع** في جدول العرض، وسمِّ العمود «كود الموظف» (العمود يبقى `driver_code`).
- حدّث `driver_profile.php` ليعرض الحقول العامة الجديدة و«نوع الموظف».
- حافظ على: عزل الشركة، الصلاحيات (`check_page_permissions`)، فرادة الكود ضمن الشركة، CSRF، Prepared Statements، إطار Excel، DataTables.

### الخطوة 5 — فلترة قوائم «السائق» بأنواع التشغيل
- في كل قائمة اختيار سائق/مشغّل (`Timesheet/get_drivers.php`, إسناد المعدات في `add_drivers.php`/`project_drivers.php`/`movement_operations.php`, ومنطق التشغيل): أضِف شرط `employee_type IN ('سائق/مشغّل', 'مبنشر', ...أنواع التشغيل)` حتى لا يظهر موظف إداري/أمن في قوائم تشغيل المعدات.

---

## 6. التحقق (إلزامي قبل الإنهاء — كله على نسخة staging)

1. **صياغة:** `php -l` على **كل** ملف معدّل — بلا أخطاء.
2. **سلامة البيانات:**
   - `COUNT(*)`, `MAX(id)` متطابقة بين `drivers_legacy_backup` و`employees`.
   - عيّنات JOIN عبر `equipment_drivers.driver_id` و`drivercontracts.driver_id` و`timesheet.driver` تعطي **نفس النتائج** قبل/بعد (نفس الأسماء لنفس الأرقام).
3. **اختبار انحدار شامل** لكل شاشة في ملف الجرد، والتأكد أنها تعمل وتعرض نفس البيانات:
   - سجل الموظفين (إضافة/تعديل/حذف لكل نوع)، الملف الشخصي، تاريخ القيادة، الإيقاف/التفعيل.
   - عقود السائق وتفاصيلها، إسناد المعدات (`add_drivers`, `project_drivers`).
   - التشغيل (`move_oprators`, `movement_operations`, `map_page`).
   - التايم شيت (الإدخال، العرض، قوائم السائق، التفاصيل).
   - التقارير وإطار Excel (تصدير/استيراد الموظفين).
4. **إعادة grep**: تأكّد أنه **لم يبقَ أي مرجع `drivers`** غير محوَّل في الكود قبل حذف الـ View.
5. **بعد النجاح فقط:** احذف الـ View، أعد تسمية الجدول الأصلي للأرشفة، ثم أعِد اختباراً سريعاً للشاشات الأساسية.
6. **خطة تراجع (Rollback):** موثّقة — استعادة النسخة الاحتياطية وإرجاع الفرع عند أي فشل في الانحدار.

## 7. المخرجات المطلوبة
- `database/employees_migration_inventory.md` (الجرد الكامل).
- `database/employees_create_and_migrate.sql` (الإنشاء + الترحيل بحفظ المفاتيح) + سكربت الأرشفة النهائي (DROP VIEW + RENAME).
- كل الملفات المحوّلة (المراجع + شاشة سجل الموظفين + الفلترة + تحديث Excel/الملف الشخصي).
- ملخّص نهائي: عدد المراجع المحوّلة، الملفات المتأثّرة، نتائج فحوص السلامة والانحدار، وتأكيد أنه **لم يبقَ أي كائن `drivers` مستخدم**، وأي افتراض اتخذته.

**ابدأ بالفرع + النسخة الاحتياطية + الجرد، ثم أنشئ `employees` ورحّل بحفظ المفاتيح، ثم حوّل كل المراجع، ثم ابنِ شاشة سجل الموظفين والفلترة، ثم تحقّق بالكامل على نسخة قبل الإنتاج، وأخيراً أرشِف الجدول القديم.**
