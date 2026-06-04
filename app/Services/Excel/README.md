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

---

## مطابقة الأعمدة عند الاستيراد

تُطابَق أعمدة الملف **بالاسم** (مقارنة رأس الملف بـ `label`، مع تجاهل ما بين الأقواس والأسطر)، ثم **بالترتيب الموضعي** كحلّ احتياطي. لذا يعمل النموذج المُولَّد والملفات اليدوية المتوافقة الترتيب على حدّ سواء.

---

## الكيانات المسجّلة حالياً

**استيراد + تصدير:** `clients` · `suppliers` · `drivers` · `equipments` · `projects` · `equipment_types` (أنواع المعدات) · `failure_codes` (أكواد الأعطال) · `operations` (حركات التشغيل) · `timesheet` (ساعات العمل) · `contracts` (عقود المشاريع) · `supplier_contracts` (عقود الموردين) · `driver_contracts` (عقود السائقين).

**تصدير فقط (سجلّات/تقارير):** `activity_logs` (سجل النشاطات).

كل الكيانات مُدمجة في شاشاتها. الاستيراد مرتبط بصلاحية الإضافة (`can_add`)؛ الكيانات ذات المفاتيح الأجنبية (العقود، الحركات) تتحقّق من وجود المرجع ضمن نطاق الشركة قبل القبول.

### وضع التصدير فقط (للتقارير والسجلات)

```php
foreach (ems_excel_header_actions('activity_logs', 'سجل النشاطات', false, ['exportOnly' => true]) as $a) {
    $header_actions[] = $a;
}
// لا حاجة لاستدعاء ems_excel_render() — التصدير رابط مباشر بلا معالج.
```

الوسيط الرابع `$opts`: `['exportOnly' => true]` (تصدير فقط) أو `['template' => false]` (إخفاء زر النموذج).

## الترحيل من النظام القديم

الملفات القديمة (`download_*_template*.php`, `import_*_excel.php`, `export_*_excel.php`) **تبقى كما هي ولا تُحذف** (غير كاسر). لتحويل شاشة، استبدل أزرار Excel القديمة باستدعاء `ems_excel_header_actions()` + `ems_excel_render()` كما في شاشة العملاء. بعد التأكد، يمكن أرشفة الملفات القديمة لاحقاً.

## الأمان والأداء

- قراءة `setReadDataOnly` + إدراج عبر Prepared Statement واحد داخل Transaction (تراجع كامل عند الفشل).
- معاينة الصفوف الصحيحة تُخزَّن مؤقتاً في `storage/excel_imports/` (محميّة بـ `.htaccess`، مقيّدة بالمستخدم/الشركة، تنتهي خلال ساعة).
- حدود: 5 ميجابايت، الصيغ xlsx/xls/csv، حد صفوف لكل كيان.
