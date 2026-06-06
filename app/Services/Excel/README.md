# إطار Excel الموحّد — دليل المطوّر

> المرجع الرسمي الوحيد للتعامل مع ملفات Excel/CSV (تصدير، نموذج، استيراد) في نظام EMS.
> الموقع: `app/Services/Excel/` — مساحة الأسماء `App\Services\Excel` (PSR-4 عبر `app/bootstrap.php`).

## نظرة عامة

```
excel.php  ──►  ExcelService  ──►  { TemplateBuilder | Exporter | Importer(Validator, FileReader) }
                                             │
                                          Styler (الهوية الذهبية/السوداء)
                                             ▲
                                       ExcelRegistry ◄── EntityDefinition + Column
```

نقطة الدخول الوحيدة: **`/excel.php`**

| الطلب | الوصف |
|------|-------|
| `GET excel.php?entity=clients&action=template` | تنزيل نموذج الاستيراد |
| `GET excel.php?entity=clients&action=export` | تنزيل تصدير البيانات |
| `POST excel.php?entity=clients&action=import_preview` | معاينة وتحقق (JSON) — يتطلب `excel_file` + `csrf_token` |
| `POST excel.php?entity=clients&action=import_commit` | تنفيذ الاستيراد (JSON) — يتطلب `token` + `csrf_token` |

الحُرّاس المطبّقة تلقائياً: المصادقة، CSRF، صلاحية الوحدة (view للتصدير، add للاستيراد)، عزل الشركة، فحص الامتداد/الحجم، حد الصفوف، Transaction.

---

## كيف تضيف Excel لشاشة جديدة (3 خطوات)

### 1) عرّف الكيان في `ExcelRegistry::build()`

```php
$defs['warehouses'] = new EntityDefinition('warehouses', 'المخازن', 'warehouses', [
    new Column('code', 'كود المخزن',  ['required' => true, 'unique' => true, 'example' => 'WH-001']),
    new Column('name', 'اسم المخزن',  ['required' => true, 'width' => 30]),
    new Column('manager', 'المسؤول'),
    new Column('phone', 'الهاتف', ['type' => Column::TYPE_PHONE]),
    new Column('status', 'الحالة', ['type' => Column::TYPE_ENUM, 'enum' => ['نشط','متوقف'], 'default' => 'نشط']),
], [
    'moduleCode'   => 'warehouses',     // رمز الوحدة في جدول modules لفحص الصلاحية
    'instructions' => ['الحقول المطلوبة: الكود + الاسم.'],
]);
```

خيارات `EntityDefinition` المهمة:
- `companyScoped` (افتراضي true)، `companyColumn` (افتراضي `company_id`).
- `softDeleteColumn` (افتراضي `is_deleted`؛ ضعها `null` إن لم يوجد).
- `createdByColumn` (افتراضي `created_by`؛ ضعها `null` إن لم يوجد).
- `exportOrderBy`، `maxRows`.

> الإطار يتحقق تلقائياً من وجود الأعمدة في الجدول قبل الإدراج (`db_table_has_column`)، فلا يكسر إن غاب عمود.

### 2) أضف الأزرار في الشاشة

```php
require_once __DIR__ . '/../includes/excel_ui.php';
foreach (ems_excel_header_actions('warehouses', 'المخازن', $can_add) as $a) {
    $header_actions[] = $a;
}
```

### 3) اطبع نافذة المعالج مرّة واحدة قرب نهاية الصفحة

```php
<?php ems_excel_render(); ?>
```

هذا كل شيء — التصدير والنموذج والاستيراد متعدد الخطوات (رفع ← معاينة ← تنفيذ) يعمل تلقائياً.

---

## خصائص `Column`

| الخاصية | النوع | الوصف |
|---------|------|-------|
| `field` | string | اسم العمود في قاعدة البيانات |
| `label` | string | التسمية العربية المعروضة (تُستخدم لمطابقة رأس الملف عند الاستيراد) |
| `type` | string | `string\|int\|float\|date\|email\|phone\|enum` |
| `required` | bool | حقل مطلوب |
| `unique` | bool | فريد ضمن نطاق الشركة (يُفحص داخل الملف وفي قاعدة البيانات) |
| `enum` | array | القيم المقبولة عندما `type = enum` |
| `default` | mixed | قيمة افتراضية عند الفراغ |
| `example` | string | مثال يظهر في النموذج |
| `width` | int | عرض العمود في Excel |
| `foreignKey` | array | `['table'=>..,'column'=>..,'scoped'=>bool]` للتحقق من وجود القيمة |
| `importable` / `exportable` | bool | إظهار العمود في الاستيراد/التصدير |
| `exportExpr` | string | تعبير SQL للتصدير (مثل `DATE_FORMAT(...)`) |
| `lookup` | array | بحث: يحوّل اسماً/كوداً مقروءاً إلى مفتاح أجنبي (انظر أدناه) |

---

## الربط بمفتاح أجنبي عبر الاسم/الكود (`lookup`)

للشاشات المعتمدة على مفاتيح أجنبية (مثل المشاريع المرتبطة بالعملاء)، لا يكفي تخزين
الاسم النصّي فقط — يجب ربط السجل بالمعرف الحقيقي (`client_id`). خاصية `lookup` تحلّ
ذلك: يدخل المستخدم **اسم العميل أو كوده**، فيبحث عنه الإطار ويخزّن المعرف في العمود
المخصّص ويعيد كتابة الاسم القانوني، **ويرفض الصف** إن لم يُعثر على القيمة.

```php
new Column('client', 'العميل (اسم أو كود)', [
    'required'   => true,
    'lookup'     => [
        'table'      => 'clients',                       // الجدول المرجعي
        'idColumn'   => 'id',                            // عمود المعرف فيه
        'storeIdIn'  => 'client_id',                     // عمود قاعدة البيانات لتخزين المعرف
        'matchBy'    => ['client_code', 'client_name'],  // المطابقة: الكود أولاً ثم الاسم
        'nameColumn' => 'client_name',                   // الاسم القانوني المُعاد كتابته في client
        'scoped'     => true,                            // تقييد البحث بنطاق الشركة
        'softDelete' => 'is_deleted',                    // استبعاد المحذوف ناعماً (اختياري)
    ],
    // التصدير يعرض الاسم المرتبط فعلياً (عبر المعرف) مع رجوع للنص المخزّن:
    'exportExpr' => "COALESCE((SELECT c.client_name FROM clients c WHERE c.id = project.client_id), project.client)",
]),
```

كيف يعمل: عند المعاينة يبحث `Validator::resolveLookup()` بترتيب `matchBy`؛ إن وُجد
يخزّن `storeIdIn` (المعرف) ويصحّح الاسم؛ وإن لم يوجد يُدرَج خطأ «القيمة غير موجودة في
النظام» ويُستبعد الصف. عند التنفيذ يُدرج `Importer::commit()` عمود `storeIdIn` تلقائياً
ضمن نفس عبارة الإدراج. الفرق عن `foreignKey`: الأخير يتحقق فقط من وجود قيمة رقمية كما
هي، بينما `lookup` يقبل اسماً مقروءاً ويحوّله إلى معرف ويربطه.

### حالتان لتركيب `lookup`

1. **عمود نصّي منفصل + عمود معرّف** (مثل `project.client` + `project.client_id`):
   اجعل `field` = العمود النصّي و`storeIdIn` = عمود المعرّف. يكتب الإطار الاسم القانوني
   في `field` والمعرف في `storeIdIn`.

2. **عمود المعرّف فقط** (مثل `contracts.project_id`, `operations.equipment` — لا يوجد
   عمود اسم نصّي منفصل): اجعل `field` = عمود المعرّف نفسه و`storeIdIn` = نفس العمود.
   في هذه الحالة لا يكتب الإطار الاسم فوق العمود (`field === storeIdIn`)، بل يخزّن المعرف
   مباشرةً. **أضِف `exportExpr`** يعرض الاسم/الكود المقروء بدل الرقم لضمان دورة
   تصدير↔استيراد سليمة، مثل:
   `"(SELECT p.name FROM project p WHERE p.id = contracts.project_id)"`.
   ولا تضع `'type' => Column::TYPE_INT` ولا `foreignKey` على عمود الحالة 2 — المدخل صار
   نصاً مقروءاً، وفحص `foreignKey` يسبق `lookup` ويرفض النص.

### الأعمدة المربوطة بـ `lookup` حالياً

| الكيان | العمود (`field`) | الجدول المرجعي | `storeIdIn` | `matchBy` | الحالة |
|--------|------------------|-----------------|-------------|-----------|--------|
| `projects` | `client` | `clients` | `client_id` | `client_code`, `client_name` | 1 |
| `equipments` | `suppliers` | `suppliers` | `suppliers` | `supplier_code`, `name` | 2 |
| `equipments` | `type` | `equipments_types` | `type` | `type`, `form` | 2 |
| `contracts` | `project_id` | `project` | `project_id` | `project_code`, `name` | 2 |
| `supplier_contracts` | `supplier_id` | `suppliers` | `supplier_id` | `supplier_code`, `name` | 2 |
| `supplier_contracts` | `project_id` | `project` | `project_id` | `project_code`, `name` | 2 |
| `driver_contracts` | `driver_id` | `drivers` | `driver_id` | `driver_code`, `name` | 2 |
| `driver_contracts` | `project_id` | `project` | `project_id` | `project_code`, `name` | 2 |
| `operations` | `equipment` | `equipments` | `equipment` | `code`, `name` | 2 |
| `operations` | `project_id` | `project` | `project_id` | `project_code`, `name` | 2 |
| `operations` | `supplier_id` | `suppliers` | `supplier_id` | `supplier_code`, `name` | 2 |
| `timesheet` | `driver` | `drivers` | `driver` | `driver_code`, `name` | 2 |

> `softDelete => 'is_deleted'` مُفعّل على `project` و`suppliers` و`clients` (تملك العمود)؛
> أما `drivers` فلا تملك `is_deleted` ولذلك لا يُضبط لها `softDelete`.
> `scoped => true` لكل جدول يملك `company_id` (clients/suppliers/drivers/project/equipments)؛
> أما `equipments_types` فلا تملك `company_id` (أنواع عامة) فيُضبط `scoped => false` لبحث النوع.

**أعمدة لم تُربط (لا مفتاح مقروء):** `operations.contract_id` و`timesheet.operator`
تشيران إلى `contracts.id`/`operations.id` وهما بلا عمود كود/اسم مقروء — تبقى أرقاماً خام.
و`operations.equipment_category` نص حر (أساسي/احتياطي) و`operations.equipment_type`
حقل مشتق للعرض — لا يُربطان.

---

## مطابقة الأعمدة عند الاستيراد

تُطابَق أعمدة الملف **بالاسم** (مقارنة رأس الملف بـ `label`، مع تجاهل ما بين الأقواس والأسطر)، ثم **بالترتيب الموضعي** كحلّ احتياطي. لذا يعمل النموذج المُولَّد والملفات اليدوية المتوافقة الترتيب على حدّ سواء.

---

## الكيانات المسجّلة حالياً

**استيراد + تصدير:** `clients` · `suppliers` · `drivers` · `equipments` · `projects` · `equipment_types` (أنواع المعدات) · `failure_codes` (أكواد الأعطال) · `operations` (حركات التشغيل) · `timesheet` (ساعات العمل) · `contracts` (عقود المشاريع) · `supplier_contracts` (عقود الموردين) · `driver_contracts` (عقود السائقين).

**تصدير فقط (سجلّات/تقارير):** `activity_logs` (سجل النشاطات).

كل الكيانات مُدمجة في شاشاتها. الاستيراد مرتبط بصلاحية الإضافة (`can_add`)؛ الكيانات ذات المفاتيح الأجنبية (العقود، الحركات، ساعات العمل) تقبل **اسماً/كوداً مقروءاً** عبر `lookup` وتحوّله إلى المعرف الصحيح ضمن نطاق الشركة، وترفض الصف إن لم يُعثر على المرجع (انظر جدول «الأعمدة المربوطة بـ `lookup`» أعلاه).

### وضع التصدير فقط (للتقارير والسجلات)

```php
foreach (ems_excel_header_actions('activity_logs', 'سجل النشاطات', false, ['exportOnly' => true]) as $a) {
    $header_actions[] = $a;
}
// لا حاجة لاستدعاء ems_excel_render() — التصدير رابط مباشر بلا معالج.
```

الوسيط الرابع `$opts`: `['exportOnly' => true]` (تصدير فقط) أو `['template' => false]` (إخفاء زر النموذج).

## الترحيل من النظام القديم

اكتمل الترحيل: كل الشاشات (العملاء، الموردون، السائقون، المشاريع، المعدات بشاشتيها) تستخدم الآن الإطار الموحّد، وحُذفت كل ملفات النظام القديم (`download_*_template*.php`, `import_*_excel.php`, `export_*_excel.php`) بعد التأكد من عدم وجود أي مرجع لها. لتحويل أي شاشة جديدة، استبدل أزرار Excel باستدعاء `ems_excel_header_actions()` + `ems_excel_render()` (انظر أي شاشة حالية كمرجع) — لا تُنشئ ملفات استيراد/تصدير مستقلة.

## الأمان والأداء

- قراءة `setReadDataOnly` + إدراج عبر Prepared Statement واحد داخل Transaction (تراجع كامل عند الفشل).
- معاينة الصفوف الصحيحة تُخزَّن مؤقتاً في `storage/excel_imports/` (محميّة بـ `.htaccess`، مقيّدة بالمستخدم/الشركة، تنتهي خلال ساعة).
- حدود: 5 ميجابايت، الصيغ xlsx/xls/csv، حد صفوف لكل كيان.
