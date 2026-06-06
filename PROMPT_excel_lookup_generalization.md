# برومبت: تعميم الربط بالمفاتيح الأجنبية (Lookup) في إطار Excel الموحّد

> انسخ كل ما تحت الخط والصقه في Claude Code داخل مجلد المشروع `c:/xampp/htdocs/ems`.

---

## السياق

أنت تعمل على نظام **EMS** (إدارة معدات وتعدين، PHP خام + MySQLi، متعدد الشركات). يوجد **إطار Excel موحّد** لكل عمليات الاستيراد/التصدير، نقطة دخوله `excel.php`، وكوده تحت:

```
app/Services/Excel/
├── ExcelService.php      # الواجهة (مصادقة + CSRF + صلاحيات + نطاق الشركة)
├── ExcelRegistry.php     # مصدر الحقيقة: تعريف كل الكيانات (build())
├── EntityDefinition.php  # وصف الكيان
├── Column.php            # وصف العمود
├── Validator.php         # التحقق صف-بصف + resolveLookup()
├── Importer.php          # preview/commit داخل Transaction
├── Exporter.php / TemplateBuilder.php / FileReader.php / Styler.php
└── README.md             # التوثيق الرسمي
```

## ما تم إنجازه مسبقاً (لا تكرره — ابنِ عليه)

أُضيفت **قدرة «بحث/Lookup»** للإطار تتيح للمستخدم إدخال **اسم أو كود** مقروء في ملف Excel، فيحوّله الإطار تلقائياً إلى **المفتاح الأجنبي الحقيقي** ويخزّنه، ويرفض الصف إن لم يُعثر على القيمة. تم تطبيقها على كيان **المشاريع** فقط (عمود العميل → `client_id`).

شكل إعداد `lookup` على أي `Column` (موجود فعلاً في `Column.php` و`Validator::resolveLookup()` و`Importer::commit()`):

```php
new Column('client', 'العميل (اسم أو كود)', [
    'required'   => true,
    'lookup'     => [
        'table'      => 'clients',                       // الجدول المرجعي
        'idColumn'   => 'id',                            // عمود المعرف فيه
        'storeIdIn'  => 'client_id',                     // عمود قاعدة البيانات لتخزين المعرف
        'matchBy'    => ['client_code', 'client_name'],  // المطابقة بالترتيب: الكود ثم الاسم
        'nameColumn' => 'client_name',                   // الاسم القانوني المُعاد كتابته
        'scoped'     => true,                            // تقييد البحث بنطاق الشركة (company_id)
        'softDelete' => 'is_deleted',                    // استبعاد المحذوف ناعماً (اختياري)
    ],
    'exportExpr' => "COALESCE((SELECT c.client_name FROM clients c WHERE c.id = project.client_id), project.client)",
]),
```

**آلية العمل:** عند المعاينة يبحث `Validator::resolveLookup()` بترتيب `matchBy`، فإن وُجد يخزّن المعرف في `storeIdIn` ويصحّح الاسم، وإن لم يوجد يُدرَج خطأ «القيمة غير موجودة في النظام» ويُستبعد الصف. عند التنفيذ يُدرج `Importer::commit()` عمود `storeIdIn` تلقائياً ضمن نفس عبارة الإدراج.

## المهمة

**عمّم الربط بالمفاتيح الأجنبية على كل الكيانات في النظام** بحيث يُدخل المستخدم اسماً/كوداً مقروءاً بدل الأرقام الخام، ويُربط السجل بالمعرف الصحيح. حالياً كيانات كثيرة تطلب أرقام معرّفات خام (مثل `project_id=12`, `supplier_id=3`) أو نصوصاً حرّة بلا ربط، وهذا غير عملي وقابل للخطأ.

### الكيانات والأعمدة المطلوب تحويلها (راجِع `ExcelRegistry.php`)

| الكيان | العمود الحالي | المرجع | `storeIdIn` | `matchBy` المقترح |
|--------|---------------|--------|-------------|-------------------|
| `contracts` | `project_id` (foreignKey رقمي) | `project` | `project_id` | `['project_code','name']` |
| `supplier_contracts` | `supplier_id` | `suppliers` | `supplier_id` | `['supplier_code','name']` |
| `supplier_contracts` | `project_id` | `project` | `project_id` | `['project_code','name']` |
| `driver_contracts` | `driver_id` | `drivers` | `driver_id` | `['driver_code','name']` |
| `driver_contracts` | `project_id` | `project` | `project_id` | `['project_code','name']` |
| `operations` | `project_id` (foreignKey رقمي) | `project` | `project_id` | `['project_code','name']` |
| `operations` | `supplier_id` | `suppliers` | `supplier_id` | `['supplier_code','name']` |
| `operations` | `equipment` (نص EQP-0001) | `equipments` | `equipment_id` أو ما يقابله | `['code','name']` |
| `operations` | `contract_id` | حسب الجدول الفعلي | راجِع المخطط | راجِع المخطط |
| `timesheet` | `driver` (نص EQP-0001) | `equipments`/`drivers` (راجِع) | حسب المخطط | راجِع المخطط |

> الجداول المرجعية وأعمدتها (كود/اسم) المؤكّدة من السجل: `clients(client_code, client_name)`، `suppliers(supplier_code, name)`، `drivers(driver_code, name)`، `project(project_code, name)`، `equipments(code, name)`، `equipments_types(form, type)`.

### حالتان مهمتان في التصميم

1. **يوجد عمود نصّي منفصل + عمود معرّف** (مثل `project.client` + `project.client_id`): اجعل `field` = العمود النصّي، و`storeIdIn` = عمود المعرّف. (نموذج المشاريع الحالي.)

2. **يوجد عمود المعرّف فقط** (مثل `contracts.project_id` — لا يوجد عمود اسم نصّي): اجعل `field` = عمود المعرّف نفسه و`storeIdIn` = نفس العمود. المنطق الحالي يعمل في هذه الحالة لأن المعرّف يكتب فوق الاسم، لكن **تحقّق من ذلك بنفسك** وأضِف:
   - `exportExpr` بحيث يعرض التصدير الاسم المقروء بدل الرقم، لضمان دورة تصدير↔استيراد سليمة. مثال للعقود:
     `"(SELECT p.name FROM project p WHERE p.id = contracts.project_id)"`.
   - حدّث `label` إلى «… (اسم أو كود)» و`example` ليصبح اسماً مقروءاً بدل رقم.
   - أزِل `'type' => Column::TYPE_INT` من هذه الأعمدة (المدخل صار نصاً)، وأبقِ `required`.

> إن رأيت أن الحالة 2 تحتاج تحسيناً في النواة (مثل خيار `displayExpr` أو تجنّب كتابة الاسم عندما `field === storeIdIn`)، عدّل `Validator.php`/`Column.php` بشكل غير كاسر وحدّث `README.md`.

### قيود إلزامية

- **لا تكسر** أي شاشة قائمة. الإطار يتحقق من وجود الأعمدة عبر `db_table_has_column` قبل الإدراج — حافظ على ذلك.
- **تحقّق من المخطط الفعلي** قبل أي ربط: اقرأ `database/equipation_manage.sql` (وأي migration ذي صلة) لتأكيد أسماء الجداول والأعمدة الحقيقية (`storeIdIn`, `idColumn`, أعمدة code/name، عمود `company_id`، عمود `is_deleted`). لا تخمّن.
- حافظ على **عزل الشركة** (`scoped => true`) و**استبعاد المحذوف** حيثما ينطبق.
- استخدم **Prepared Statements** فقط، وأبقِ الرسائل بالعربية بنفس الأسلوب الحالي.
- الأعمدة الغامضة (مثل `timesheet.driver`, `operations.contract_id`, `operations.equipment_category`) — **افحص علاقتها الحقيقية في المخطط وفي شاشتها الأصلية** (`Timesheet/timesheet.php`, `Oprators/oprators.php`) قبل التحويل. إن بقيت العلاقة غير واضحة، **اترك العمود كما هو وأدرِجه في تقرير «يحتاج قراراً بشرياً»** بدل التخمين.

### خطوات التنفيذ المقترحة

1. اقرأ `app/Services/Excel/README.md` و`ExcelRegistry.php` و`Validator.php` بالكامل لفهم الوضع الحالي.
2. استخرج من `database/equipation_manage.sql` مخطط كل جدول مرجعي وكل جدول هدف، وثبّت أسماء الأعمدة الفعلية.
3. حوّل الأعمدة في `ExcelRegistry.php` كياناً كياناً وفق الجدول أعلاه والحالتين، مع `exportExpr` مناسب لكل عمود معرّف.
4. إن لزم تحسين نواة الإطار للحالة 2، نفّذه بشكل غير كاسر.
5. حدّث `README.md` بالكيانات المحوّلة وأي تغيير في النواة.

### التحقق (إلزامي قبل الإنهاء)

- `php -l` على كل ملف معدّل (يجب ألا يوجد خطأ صياغة).
- لكل كيان محوّل: جهّز ملف Excel تجريبياً وتتبّع المسار `import_preview` ثم `import_commit` وتأكّد أن:
  - الاسم/الكود الصحيح يُربط ويملأ عمود المعرّف الحقيقي.
  - الاسم/الكود غير الموجود **يُرفض** مع رسالة واضحة.
  - التصدير يُخرج الاسم المقروء (وليس الرقم)، وإعادة استيراد الملف المُصدَّر تنجح (دورة كاملة).
- شغّل النظام محلياً على XAMPP وافتح شاشة واحدة على الأقل لكل كيان محوّل للتأكد من ظهور الأزرار وعمل المعالج.

### المخرجات المطلوبة

- تعديلات `ExcelRegistry.php` (وأي ملف نواة عند اللزوم) + `README.md`.
- جدول ملخّص: كل كيان/عمود تم تحويله، والـ `storeIdIn` و`matchBy` المستخدمين.
- قائمة «يحتاج قراراً بشرياً» لأي عمود غامض لم يُحوّل ولماذا.

**ابدأ بقراءة الملفات وتأكيد المخطط، ثم نفّذ التحويل، ثم تحقّق.**
