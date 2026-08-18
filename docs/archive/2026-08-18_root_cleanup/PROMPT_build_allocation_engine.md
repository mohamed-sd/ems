# برومبت: بناء «محرّك الحصص» — خدمة فرض صارمة مركزية (Claude Code)

> انسخ كل ما تحت الخط والصقه في Claude Code داخل مجلد المشروع `c:/xampp/htdocs/ems`.

---

## الخلفية والمشكلة

نظام EMS (PHP + MySQLi، عربي RTL، متعدّد الشركات) يدير هرم حصص تعاقدية:
**عميل ← مشروع ← عقد مورّد ← نوع معدة ← معدّات ← مشغّلون ← ساعات**.
سعة كل عقد محفوظة في الجداول، لكن **لا يوجد فرض موحّد** يمنع أن «مجموع الأبناء يتجاوز سعة الأب». التقارير تعرض «المتبقي» فقط، وتقصّ السالب إلى صفر (تُخفي التجاوز)، ومسارات الإضافة الفعلية لا تفحص السقف. تشخيص فعلي على البيانات أثبت وجود تجاوزات حقيقية.

**المطلوب:** بناء **خدمة فرض صارمة مركزية (Allocation/Quota Engine)** = نقطة حقيقة واحدة تُستدعى من **كل مسارات الكتابة** التي تُسند معدّات أو مشغّلين، فترفض أي عملية تتجاوز السقف التعاقدي قبل تنفيذها.

## اقرأ أولاً (مصدر الحقيقة لمنطق المطابقة)

افهم هذه الملفات بدقّة قبل أي كتابة — الخدمة يجب أن تطابق منطقها:

- `Oprators/get_contract_stats.php` — **المرجع الأهم**: كيف تُحسب الحصص (المتعاقد) والمُسنَد فعلياً لكل (مورّد + نوع).
- `movement/move_oprators.php` و `Oprators/oprators.php` — مسار **إضافة/تعديل تشغيل** (action=`save_operation` / `add_new_operation`).
- `movement/movement_operations.php` — مسارات `add_new_operation` و `add_new_driver`.
- `movement/project_drivers.php` و `movement/add_drivers.php` و `movement/save_equipment_drivers.php` — مسارات **إسناد المشغّلين**.
- `config.php`, `includes/permissions_helper.php` (دالة `db_table_has_column`, عزل الشركة), `app/bootstrap.php` (محمّل `App\`).
- `database/quota_overallocation_report.sql` — استعلامات التشخيص (نفس منطق الكشف الذي يجب أن يطابقه الفرض).

## بنية الجداول والحصص (مؤكَّدة)

- **سعة المعدات** (لكل عقد مورّد + نوع): `suppliercontractequipments` — `contract_id` (=`supplierscontracts.id`), `equip_type` (=`equipments_types.id`, varchar يحمل رقماً), `equip_count` (إجمالي), `equip_count_basic`, `equip_count_backup`, `equip_operators` (عدد المشغّلين المتعاقد), `equip_total_contract` (ساعات).
- **عقد المورّد**: `supplierscontracts` — `id`, `project_id`, `supplier_id`, `status`, وساعات العقد (`forecasted_contracted_hours` أو ما يقابله — تحقّق من الاسم الفعلي).
- **الإسناد الفعلي للمعدّات**: `operations` — `equipment` (=equipments.id), `equipment_type`, `equipment_category` ('أساسي'/'احتياطي'), `project_id`, `supplier_id`, `contract_id`, `status` (1=ساري).
- **إسناد المشغّلين**: `equipment_drivers` — `equipment_id`, `driver_id`, `status`.
- **استهلاك الساعات**: `timesheet` — `operator` (=operations.id), `total_work_hours`, `status`.
- مطابقة النوع (كما في get_contract_stats): `operations.equipment_type = type_id` **أو** `equipments.type = type_id`. الأعمدة النصّية تُحوَّل عبر CAST.

## القواعد التي يجب فرضها (Hard Enforcement)

عند أي محاولة إسناد، ارفض إن أدّت إلى تجاوز أي من:

1. **عدد المعدات لكل (عقد مورّد + نوع):** `allocated_count + 1 > SUM(equip_count)` ⇒ رفض.
2. **فئة أساسي/احتياطي:** إضافة «أساسي» لا تتجاوز `SUM(equip_count_basic)`، و«احتياطي» لا تتجاوز `SUM(equip_count_backup)`.
3. **إجمالي معدات عقد المورّد** (كل الأنواع): لا يتجاوز `SUM(equip_count)` للعقد.
4. **عدد المشغّلين لكل (عقد مورّد + نوع):** المشغّلون النشطون لا يتجاوزون `SUM(equip_operators)`.
5. **القواعد الهيكلية الموجودة (وحّدها داخل المحرّك):** معدّة لا تكون في تشغيلين ساريين؛ سائق لا يكون نشطاً على أكثر من معدّة.
6. **(اختياري/تحذير) الساعات:** استهلاك التايم شيت مقابل ساعات العقد — ابدأها **تحذيراً** لا منعاً (لأنها تراكمية بمرور الوقت).

> العدّ يكون **ضمن نطاق الشركة والمشروع والمورّد** وبحالة `status=1`، وعند **التعديل** استثنِ السجل الحالي من العدّ (حتى لا يحسب نفسه).

## التصميم المطلوب — خدمة واحدة مركزية

أنشئ تحت `app/Services/Allocation/` (مساحة الأسماء `App\Services\Allocation`, تُحمّل عبر `app/bootstrap.php`):

- **`AllocationEngine`** — الواجهة الوحيدة. دوال مثل:
  - `canAssignEquipment(array $ctx): AllocationResult` — يفحص القواعد 1–3 و5 لإسناد معدّة.
  - `canAssignOperator(array $ctx): AllocationResult` — يفحص القاعدة 4 و5 لإسناد مشغّل.
  - `assertCanAssignEquipment(...)` / `assertCanAssignOperator(...)` — ترمي استثناءً عند الرفض.
  - يأخذ السياق: company_id, project_id, supplier_id, contract_id, equip_type, equipment_category, equipment_id, driver_id, و`excludeOperationId`/`excludeRelationId` للتعديل.
- **`AllocationResult`** — كائن: `allowed (bool)`, `code`, `message` (عربي واضح: «تجاوز الحصة: المتعاقد X، المُسنَد Y، لا يمكن إضافة المزيد»), وتفاصيل (`capacity`, `used`, `requested`, `level`).
- **`CapacityRepository`** — استعلامات السعة والمُسنَد (Prepared Statements)، مبنية على نفس منطق `get_contract_stats.php` بالضبط. مرنة للمخطط عبر `db_table_has_column`.
- **`AllocationException`** — استثناء مخصّص يحمل `AllocationResult`.

**مبادئ إلزامية:**
- **Prepared Statements** فقط، **عزل صارم** بالشركة/المشروع.
- **الفحص داخل نفس Transaction** قبل الإدراج، ويفضّل قفل تنافسي (`SELECT ... FOR UPDATE` على صفوف السعة أو على عدّ السجلات) لمنع **حالة السباق** (طلبان متزامنان يتجاوزان السقف معاً). وثّق الخيار المتّبع.
- **رسائل عربية** بأسلوب النظام، وأكواد ثابتة لكل قاعدة.
- **لا منطق مكرّر:** كل المسارات تستدعي نفس المحرّك (مصدر حقيقة واحد).

## الدمج في مسارات الكتابة (دون كسر)

استبدل/أضِف الفحص في كل مسار إسناد، قبل الـ INSERT/UPDATE، باستدعاء المحرّك:

- إضافة/تعديل تشغيل: `move_oprators.php`, `Oprators/oprators.php`, `movement/movement_operations.php` (add_new_operation, save_operation).
- إسناد مشغّل: `movement/project_drivers.php`, `movement/add_drivers.php`, `movement/save_equipment_drivers.php`, `movement/movement_operations.php` (add_new_driver).
- إن وُجدت طبقة `api/` (تطبيقات Flutter): مرّر كل نقاط الإضافة عبر المحرّك أيضاً، وأعِد **422** مع رسالة المحرّك عند الرفض.

عند الرفض: في الويب اعرض رسالة الخطأ العربية (Toast/`msg`)، وفي الـ API أعِد JSON `{success:false, message, data:{capacity,used,requested}}` بكود 422. **لا تنفّذ الكتابة.**

## تنظيف البيانات المتجاوزة الحالية (قبل التفعيل)

التشخيص أظهر تجاوزات قائمة (لا يمكن تفعيل الفرض الصارم فوقها بأثر رجعي دون كشفها). سلّم:
- سكربت/أمر تشخيص يطبع **السجلات المتجاوزة بالتحديد** (إعادة استخدام منطق `database/quota_overallocation_report.sql`).
- **لا تحذف بياناً تلقائياً.** بدلاً من ذلك أنشئ تقريراً (CLI أو صفحة محمية) يَسرد الخروقات ليقرّر المستخدم يدوياً، مع توصية واضحة.

## التحقق (إلزامي قبل الإنهاء)

1. `php -l` على كل ملفات `app/Services/Allocation/` والملفات المعدّلة.
2. **اختبارات منطقية** (سكربت CLI تحت `scripts/` أو tests): سيناريوهات على بيانات تجريبية —
   - إسناد ضمن الحصة ⇒ يُقبل.
   - إسناد يتخطّى السقف بمعدّة واحدة ⇒ يُرفض برسالة صحيحة.
   - تجاوز أساسي مع توفّر احتياطي ⇒ يُرفض على الفئة.
   - تجاوز عدد المشغّلين ⇒ يُرفض.
   - **التعديل** لسجل قائم لا يَحسب نفسه (لا رفض كاذب).
   - معدّة في تشغيلين / سائق على معدّتين ⇒ يُرفض.
   - **اختبار تزامن** (إن أمكن) للتأكد أن القفل يمنع التجاوز المتزامن.
3. شغّل `database/quota_overallocation_report.sql` بعد الدمج وتأكّد أن أي إضافة جديدة لا تزيد الخروقات.
4. تأكّد أن النظام الحالي يعمل: جرّب إضافة تشغيل/مشغّل ضمن الحصة (ينجح) وفوقها (يُمنع برسالة واضحة) من الواجهة.

## المخرجات المطلوبة

- مجلد `app/Services/Allocation/` (Engine + Result + Repository + Exception) + `app/Services/Allocation/README.md` يشرح القواعد والاستخدام والأكواد.
- تعديلات مسارات الكتابة لاستدعاء المحرّك (موضّحة في ملخّص).
- أداة تقرير الخروقات الحالية.
- ملخّص: القواعد المفروضة، الملفات المعدّلة، استراتيجية القفل، نتائج الاختبارات، وتأكيد عدم كسر النظام.

**ابدأ بقراءة `get_contract_stats.php` و`quota_overallocation_report.sql` ومسارات الإضافة، ثم ابنِ المحرّك، ثم ادمجه، ثم تحقّق.**
