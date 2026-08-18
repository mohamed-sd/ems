# برومبت: الموجة 2 — إعادة تسمية أعمدة المفاتيح + المجلد والملفات (`driver_*` → `employee_*`) (Claude Code)

> انسخ كل ما تحت الخط والصقه في Claude Code داخل مجلد المشروع `c:/xampp/htdocs/ems`.
> **لا تبدأ هذه الموجة إلا بعد نجاح الموجة 1 (تحويل `drivers` → `employees`) واستقرارها** (راجع `PROMPT_employees_migration.md`).
> **لا تستخدم git إطلاقاً.** نسخة احتياطية يدوية للملفات وقاعدة البيانات (mysqldump)، والعمل على نسخة staging أولاً، مع اختبار انحدار شامل قبل الإنتاج.

---

## 1. الهدف

بعد أن صار `employees` سجل الموظفين الموحّد (الموجة 1)، نُكمل النظافة: **إزالة كلمة «driver» من أسماء الأعمدة** التي تشير إلى الموظف أو تصفه، وتحويلها إلى `employee_*`. **تغيير تجميلي بحت — لا يغيّر أي منطق أو بيانات.**

> هذا تغيير حسّاس (لا توجد شبكة أمان View للأعمدة كما في الجداول)، لذا نفّذه **عموداً عموداً، جدولاً جدولاً**، كلّ خطوة مستقلة باختبارها الخاص، وفق النمط الآمن أدناه.

## 2. الأعمدة المستهدفة (تحقّق منها بنفسك أولاً)

**أعمدة مفاتيح تشير إلى الموظف (`employees.id`) — تُعاد تسميتها إلى `employee_id`:**
1. `equipment_drivers.driver_id` → `employee_id`
2. `drivercontracts.driver_id` → `employee_id`
3. `timesheet.driver` → `employee_id`

**أعمدة وصف داخل `employees` نفسه (اختيارية لإكمال النظافة):**
4. `employees.driver_code` → `employee_code`
5. `employees.driver_status` → `employee_status`
6. `employees.driver_photo` → `employee_photo`

### ⚠️ لا تلمس هذه (ليست أعمدة موظف):
- **`timesheet.operator`** — يحمل **معرّف عملية تشغيل (`operations.id`)**، لا شخصاً. اتركه كما هو تماماً.
- `operations.equipment`, `operations.supplier_id`, `equipment_drivers.equipment_id` — ليست موظفين.
- أي عمود اسمه `driver` داخل بيانات سجلّ (audit_log/JSON) — بيانات لا مخطّط.

> **إلزامي:** قبل أي تعديل، نفّذ **جرداً آلياً (grep) بنفسك** على `database/equipation_manage.sql` وكل `*.php` للتأكد من القائمة الكاملة الفعلية للأعمدة التي تحمل `driver`/`operator`، وأي جداول/مراجع إضافية لم تُذكر أعلاه. أنتج `database/wave2_columns_inventory.md`.

## 3. النمط الآمن لإعادة تسمية كل عمود (Zero-downtime، قابل للتراجع)

لكل عمود مفتاح (1–3) **على حدة**:

1. **أضِف العمود الجديد وانسخ القيم:** `ALTER TABLE x ADD COLUMN employee_id <نفس النوع> NULL; UPDATE x SET employee_id = driver_id;` (نفس النوع تماماً للعمود القديم).
2. **حوّل كل مراجع الكود لهذا الجدول/العمود** من `driver_id` إلى `employee_id` (من قائمة الجرد): SELECT/JOIN/INSERT/UPDATE/WHERE.
3. **اختبر شاشات هذا الجدول** على staging والتأكد أنها تعمل وتعرض نفس البيانات.
4. **أسقِط العمود القديم:** `ALTER TABLE x DROP COLUMN driver_id;` (بعد التأكد أنه لم يبقَ أي مرجع له — أعِد grep).

> بديل أبسط لو فضّلت ذرّية كاملة على staging: `ALTER TABLE x CHANGE driver_id employee_id <النوع>;` **مع** تحويل كل مراجع الكود **في نفس الدفعة** قبل الاختبار. لكن النمط أعلاه (إضافة ← تحويل ← إسقاط) أأمن لأنه قابل للتراجع في كل لحظة.

أعمدة الوصف (4–6) داخل `employees`: استخدم نفس النمط، وانتبه أنها تُقرأ في عدة شاشات (سجل الموظفين، الملف الشخصي، `Timesheet/get_drivers.php`، اللوحة الحيّة `movement/map_page.php`، إطار Excel) — حوّل كل مراجعها.

## 4. اقرأ/افحص أولاً (لإكمال الجرد)
- `database/equipation_manage.sql` (تعريفات الأعمدة الفعلية).
- كل ما يلمس الأعمدة الثلاثة: `Equipments/equipments_drivers.php`, `Equipments/add_drivers.php`, `movement/*` (project_drivers, move_oprators, movement_operations, save_equipment_drivers, map_page), `Drivers/*` (drivers, driver_profile, driver_truck_history, drivercontracts, drivercontracts_details, get_driver_data, deactivate_driver_handler), `Timesheet/*` (get_drivers, timesheet, view_timesheet, timesheet_details, get_timesheet_data), إطار Excel (`app/Services/Excel/ExcelRegistry.php`).

## 5. ترتيب التنفيذ المقترح (الأقل خطراً أولاً)
1. أعمدة الوصف الداخلية (4–6) في `employees` — معزولة نسبياً.
2. `equipment_drivers.driver_id` → `employee_id`.
3. `drivercontracts.driver_id` → `employee_id`.
4. `timesheet.driver` → `employee_id` (الأكثر انتشاراً — احذر عدم لمس `operator`).

بعد كل عمود: توقّف، اختبر، ثم انتقل للتالي.

---

## 5-ب. إعادة تسمية المجلد والملفات (الجزء الثاني من الموجة 2)

> بعد إنهاء إعادة تسمية الأعمدة، أكمِل النظافة: **أعِد تسمية مجلد الموارد البشرية وملفاته** من «Drivers/driver*» إلى «Employees/employee*». تغيير مسارات بحت — لا منطق يتغيّر — لكنه يمسّ **التنقّل والصلاحيات** فانتبه لجدول `modules`.

### خريطة إعادة التسمية (تحقّق من القائمة الفعلية بنفسك أولاً)
| القديم | الجديد |
|---|---|
| مجلد `Drivers/` | مجلد `Employees/` |
| `Drivers/drivers.php` | `Employees/employees.php` |
| `Drivers/driver_profile.php` | `Employees/employee_profile.php` |
| `Drivers/driver_truck_history.php` | `Employees/employee_equipment_history.php` |
| `Drivers/get_driver_data.php` | `Employees/get_employee_data.php` |
| `Drivers/deactivate_driver_handler.php` | `Employees/deactivate_employee_handler.php` |
| `Drivers/drivercontracts.php` | `Employees/employee_contracts.php` |
| `Drivers/drivercontracts_details.php` | `Employees/employee_contracts_details.php` |
| `Drivers/driver_contract_actions_handler.php` | `Employees/employee_contract_actions_handler.php` |
| `Drivers/get_driver_contract_equipments.php` | `Employees/get_employee_contract_equipments.php` |
| `Drivers/showcontractdriver.php` | `Employees/showcontractemployee.php` |
| باقي ملفات `Drivers/` (deactivate_driver_modals.html, get_mine_contracts.php, get_project_hours.php, get_project_mines.php...) | انقلها إلى `Employees/` بأسماء مكافئة (driver→employee) |

> الملفات ذات الاسم «driver» في مجلدات أخرى (`Timesheet/get_drivers.php`, `Equipments/equipments_drivers.php`/`add_drivers.php`, `movement/project_drivers.php`/`save_equipment_drivers.php`) — **اختيارية**: أدرِجها في الجرد وقرّر تسميتها لاحقاً، لكن **لا تكسر مساراتها** الآن إن لم تُعِد تسميتها.

### آلية إعادة التسمية الآمنة لكل ملف
1. **جرد شامل** لكل المراجع للمسار القديم في كل `*.php` وHTML/JS: `include`/`require`، `href="..."`، `window.location`، `fetch('...')`، `action="..."`، وفي **قاعدة البيانات: عمود `modules.code`** (مثل `'Drivers/drivers.php'`) وأي `check_page_permissions($conn, 'Drivers/...')` أو رمز وحدة مثل `'drivers'`.
2. **انقل/أعِد تسمية الملف** إلى الاسم الجديد.
3. **حوّل كل المراجع** للمسار الجديد (الكود + `UPDATE modules SET code='Employees/employees.php' WHERE code='Drivers/drivers.php'` لكل صف).
4. **(اختياري للأمان)** اترك ملفاً قديماً قصيراً في المكان القديم يعيد التوجيه `header('Location: ../Employees/employees.php');` فقط للروابط/الإشارات المرجعية القديمة — ثم احذفه لاحقاً.
5. **اختبر التنقّل والصلاحيات** بعد كل ملف (تظهر الشاشة في القائمة، وتُفحص صلاحيتها، وكل الروابط تعمل).

> **حرج:** صلاحيات النظام تعتمد على `modules.code` المطابق للمسار. أي مسار في `modules` لم يُحدَّث = الشاشة تفقد صلاحيتها أو تختفي من القائمة. حدّث `modules.code` لكل صف متأثّر **وتحقّق منه**.

## 6. التحقق (إلزامي بعد كل عمود وبعد الكل — على staging)
1. `php -l` على كل ملف معدّل — بلا أخطاء.
2. **سلامة:** بعد النسخ وقبل الإسقاط، `SELECT COUNT(*) WHERE employee_id <> driver_id` = صفر (تطابق تام). بعد الإسقاط، JOINات عبر العمود الجديد تعطي نفس النتائج.
3. **انحدار شامل** لكل شاشة في قائمة الجرد: إسناد المعدات، عقود السائق، التشغيل، التايم شيت (إدخال/عرض/قوائم)، سجل الموظفين، الملف الشخصي، التقارير، Excel.
4. **إعادة grep** للتأكد أنه **لم يبقَ أي مرجع للاسم القديم** (`driver_id`/`timesheet.driver`/`driver_code`...) قبل إسقاط العمود.
5. **التنقّل والصلاحيات** (بعد إعادة تسمية الملفات): كل شاشة منقولة تظهر في القائمة الديناميكية، تُفحص صلاحيتها بنجاح، وكل روابطها/تحويلاتها/نداءات AJAX تعمل بالمسار الجديد. تأكّد أن **`modules.code`** محدّث لكل صف، وأنه **لم يبقَ أي مرجع للمسار القديم** (`Drivers/...`).
6. **خطة تراجع:** استعادة النسخة الاحتياطية اليدوية عند أي فشل (بدون git).

## 7. المخرجات المطلوبة
- `database/wave2_columns_inventory.md` (الجرد الكامل للأعمدة والمسارات ومراجعها).
- سكربتات SQL في `database/` لكل عمود (ADD+UPDATE ثم DROP، أو CHANGE) + سكربت `UPDATE modules SET code=...` لتحديث مسارات الشاشات المنقولة.
- كل الملفات المحوّلة + المجلد والملفات المُعاد تسميتها.
- ملخّص: الأعمدة المُعاد تسميتها، المجلد/الملفات المُعاد تسميتها مع خريطتها، عدد المراجع المحوّلة (كود + `modules`)، نتائج فحوص السلامة والانحدار والتنقّل/الصلاحيات، وتأكيد أنه **لم يبقَ أي اسم عمود `driver_*` يشير إلى موظف ولا أي مسار `Drivers/`**، وأن `timesheet.operator` لم يُمسّ.

**ابدأ بالنسخة الاحتياطية اليدوية والجرد. أولاً أعِد تسمية الأعمدة عموداً عموداً بالنمط الآمن، ثم أعِد تسمية المجلد والملفات ملفاً ملفاً مع تحديث `modules` وكل المراجع، مع اختبار انحدار + تنقّل/صلاحيات بعد كل خطوة قبل الإنتاج.**
