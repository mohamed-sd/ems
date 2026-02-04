# تحديث نظام عقود السائقين - ملخص التغييرات

## نظرة عامة
تم تحديث نظام عقود السائقين ليطابق نظام عقود الموردين بالكامل مع التسلسل الهرمي الكامل:
**مشروع → منجم → عقد المشروع → عقد السائق**

---

## التغييرات في قاعدة البيانات

### 1. تحديث جدول `drivercontracts`
**الملف:** `database/equipation_manage.sql`

**الحقول المضافة:**
- `mine_id` INT - معرف المنجم (بعد project_id)
- `project_contract_id` INT - معرف عقد المشروع
- `pause_reason` TEXT - سبب الإيقاف
- `pause_date` DATE - تاريخ الإيقاف
- `resume_date` DATE - تاريخ الاستئناف
- `termination_type` VARCHAR(50) - نوع الإنهاء
- `termination_reason` TEXT - سبب الإنهاء
- `merged_with` INT - دمج مع عقد آخر

**الفهارس المضافة:**
- `idx_drivercontracts_mine_id`
- `idx_drivercontracts_project_contract_id`

**ملف الترحيل:** `database/add_mine_id_to_drivercontracts.sql`

### 2. جدول جديد: `driver_contract_notes`
**الملف:** `database/create_driver_contract_notes_table.sql`

**الحقول:**
- `id` - معرف تلقائي
- `contract_id` - معرف عقد السائق
- `note` - الملاحظة/الإجراء المتخذ
- `created_at` - تاريخ الإضافة

**الغرض:** تتبع سجل جميع الإجراءات على عقود السائقين (تجديد، تسوية، إيقاف، استئناف، إنهاء، دمج)

### 3. جدول جديد: `drivercontractequipments`
**الملف:** `database/create_drivercontractequipments_table.sql`

**الحقول:**
- `contract_id` - معرف عقد السائق
- `equipment_type` - نوع المعدة
- `equipment_size` - حجم/قدرة المعدة
- `equipment_count` - عدد المعدات
- `target_hours_per_month` - ساعات مستهدفة شهرياً
- `total_monthly_hours` - إجمالي ساعات شهرياً
- `total_contract_hours` - إجمالي ساعات العقد
- `equipment_category` - تصنيف (معدة/آلية)

---

## الملفات المُحدّثة

### 1. Drivers/drivercontracts.php
**التغييرات الرئيسية:**

#### إضافة التحقق من معرف السائق (بداية الملف)
```php
if (!isset($_GET['id'])) {
  header("Location: drivers.php");
  exit();
}
$driver_id = intval($_GET['id']);
```

#### نظام القوائم المتتالية (3 مستويات)
**الحقول في الفورم:**
1. **المشروع** (`project_id`) - قائمة رئيسية
2. **المنجم** (`mine_id`) - تُحمّل عند اختيار المشروع
3. **عقد المشروع** (`project_contract_id`) - تُحمّل عند اختيار المنجم

**JavaScript المضاف:**
```javascript
$('#project_id').on('change', function() {
  // تحميل المناجم من ../Suppliers/get_project_mines.php
});

$('#mine_id').on('change', function() {
  // تحميل العقود من ../Suppliers/get_mine_contracts.php
});
```

#### تحديث استعلام INSERT
```sql
INSERT INTO drivercontracts (
  contract_signing_date, driver_id, project_id, mine_id, project_contract_id, ...
) VALUES (...)
```

#### تحديث استعلام SELECT للجدول
```sql
SELECT dc.*, p.name AS project_name, m.mine_name, m.mine_code, c.id AS contract_number
FROM drivercontracts dc
LEFT JOIN project p ON dc.project_id = p.id
LEFT JOIN mines m ON dc.mine_id = m.id
LEFT JOIN contracts c ON dc.project_contract_id = c.id
WHERE dc.driver_id = $driver_id
```

#### تحديث عرض الجدول
**الأعمدة الجديدة:**
- رقم العقد
- المشروع والمنجم (عمود مدمج)
- عقد المشروع (#رقم)
- ساعات شهرياً (مع تنسيق)
- إجمالي الساعات (مع تنسيق)

**الروابط المحدثة:**
- تم تغيير `showcontractdriver.php` إلى `drivercontracts_details.php`
- تمت إزالة رابط التعديل (سيتم من صفحة التفاصيل)

---

### 2. Drivers/driver_contract_actions_handler.php (جديد)
**نسخة من:** `Suppliers/supplier_contract_actions_handler.php`

**الإجراءات المتوفرة:**
1. **renewal** - تجديد العقد (تحديث تواريخ البدء والانتهاء)
2. **settlement** - تسوية العقد (زيادة أو نقصان الساعات)
3. **pause** - إيقاف العقد (مع سبب وتاريخ)
4. **resume** - استئناف العقد (مع خيار تمديد أو خصم أيام الإيقاف)
5. **terminate** - إنهاء العقد (عادي أو مبكر)
6. **merge** - دمج عقدين

**نمط API:**
- طلب POST مع `action` و `contract_id`
- استجابة JSON: `{success: bool, message: string}`
- جميع الإجراءات مُسجلة في `driver_contract_notes`

**التغييرات من نسخة الموردين:**
- تم استبدال `supplierscontracts` بـ `drivercontracts`
- تم استبدال `supplier_contract_notes` بـ `driver_contract_notes`

---

### 3. Drivers/drivercontracts_details.php (جديد)
**نسخة من:** `Suppliers/supplierscontracts_details.php`

**التغييرات الرئيسية:**

#### استعلام SQL المحدث
```sql
SELECT sc.*, 
       d.name AS driver_name,
       op.name AS project_name,
       m.mine_name, m.mine_code
FROM drivercontracts sc
LEFT JOIN drivers d ON sc.driver_id = d.id
LEFT JOIN project op ON sc.project_id = op.id
LEFT JOIN contracts c ON sc.project_contract_id = c.id
LEFT JOIN mines m ON c.mine_id = m.id
WHERE sc.id = $contract_id
```

#### عرض معلومات السائق والمشروع
```php
<span class="info-label">اسم السائق</span>
<span class="info-value">
  <?php 
  echo htmlspecialchars($row['project_name']);
  if (!empty($row['mine_name'])) {
      echo ' - ' . htmlspecialchars($row['mine_name']);
      if (!empty($row['mine_code'])) {
          echo ' (' . htmlspecialchars($row['mine_code']) . ')';
      }
  }
  if (!empty($row['project_contract_id'])) {
      echo ' - عقد #' . htmlspecialchars($row['project_contract_id']);
  }
  ?>
</span>
```

**الأزرار والإجراءات:**
- تجديد العقد (Renewal Modal)
- تسوية العقد (Settlement Modal)
- إيقاف العقد (Pause Modal)
- استئناف العقد (Resume Modal)
- إنهاء العقد (Terminate Modal)
- دمج العقود (Merge Modal)

**API Endpoint:** `driver_contract_actions_handler.php`

**الاستبدالات التلقائية:**
- `المورد` → `السائق`
- `الموردين` → `السائقين`
- `supplier_name` → `driver_name`
- `supplier_id` → `driver_id`
- `suppliers.php` → `drivers.php`

---

### 4. Drivers/get_driver_contract_equipments.php (جديد)
**نسخة من:** `Suppliers/get_supplier_contract_equipments.php`

**الاستبدالات:**
- `suppliercontractequipments` → `drivercontractequipments`
- جميع مراجع `supplier` → `driver`

**الغرض:** AJAX endpoint لجلب معدات عقد السائق (سيتم استخدامه إذا أضفنا نظام إدارة المعدات لاحقاً)

---

## خطوات التنفيذ (للمطورين)

### 1. تحديث قاعدة البيانات
نفذ السكريبتات التالية بالترتيب:

```sql
-- 1. تحديث جدول drivercontracts
SOURCE database/add_mine_id_to_drivercontracts.sql;

-- 2. إنشاء جدول سجل الإجراءات
SOURCE database/create_driver_contract_notes_table.sql;

-- 3. إنشاء جدول معدات العقود (اختياري)
SOURCE database/create_drivercontractequipments_table.sql;
```

### 2. تحديث البيانات الموجودة
إذا كان لديك عقود سائقين موجودة، قم بتحديثها يدوياً:

```sql
-- مثال: ربط العقود القديمة بمناجم ومشاريع
UPDATE drivercontracts dc
LEFT JOIN contracts c ON c.project_id = dc.project_id
SET dc.mine_id = c.mine_id, 
    dc.project_contract_id = c.id
WHERE dc.mine_id IS NULL AND c.id IS NOT NULL;
```

### 3. اختبار الوظائف الجديدة

**اختبار القوائم المتتالية:**
1. افتح صفحة السائقين → اختر سائق → أضف عقد
2. اختر مشروع → تحقق من تحميل المناجم
3. اختر منجم → تحقق من تحميل العقود
4. أكمل بيانات العقد → احفظ
5. تحقق من ظهور البيانات في الجدول بشكل صحيح

**اختبار صفحة التفاصيل:**
1. افتح عقد سائق → انقر على أيقونة العرض
2. تحقق من ظهور: اسم السائق + المشروع + المنجم + رقم العقد
3. جرّب كل زر من أزرار الإجراءات

**اختبار الإجراءات:**
- تجديد: حدّث تواريخ → احفظ → تحقق من التحديث
- تسوية: زد/اخصم ساعات → احفظ → تحقق من الحساب
- إيقاف: أوقف العقد → تحقق من تغيير الحالة
- استئناف: استأنف → جرّب خيار التمديد والخصم
- إنهاء: أنهِ العقد → تحقق من الحالة النهائية
- دمج: ادمج عقدين → تحقق من نقل البيانات

---

## الملفات المعتمدة (Shared)

نظام عقود السائقين يستخدم نفس ملفات AJAX الخاصة بالموردين:
- `Suppliers/get_project_mines.php` - جلب مناجم المشروع
- `Suppliers/get_mine_contracts.php` - جلب عقود المنجم

هذا يضمن الاتساق والتوافق بين الأنظمة.

---

## النتائج المتوقعة

### قبل التحديث:
- عقد السائق → مرتبط بالمشروع مباشرة
- لا يوجد ربط بالمنجم أو العقد الرئيسي
- لا يوجد سجل تتبع للإجراءات

### بعد التحديث:
✅ عقد السائق → مرتبط بالمشروع + المنجم + عقد المشروع  
✅ قوائم متتالية مع 3 مستويات  
✅ صفحة تفاصيل كاملة مع جميع الإجراءات  
✅ سجل تدقيق كامل لكل إجراء  
✅ مطابقة كاملة لنظام عقود الموردين  

---

## ملاحظات مهمة

### 1. Foreign Keys (اختياري)
تم توفير أوامر إضافة Foreign Keys في ملفات الترحيل، لكنها معطلة بشكل افتراضي.  
لتفعيلها، قم بإزالة التعليق وتشغيل الأوامر.

### 2. البيانات الموجودة
العقود القديمة ستحتوي على `mine_id = NULL` و `project_contract_id = NULL`.  
يجب تحديثها يدوياً أو عن طريق سكريبت UPDATE.

### 3. الأذونات
تأكد من صلاحيات المستخدمين للوصول إلى:
- `Drivers/drivercontracts.php`
- `Drivers/drivercontracts_details.php`
- `Drivers/driver_contract_actions_handler.php`

### 4. سجل الإجراءات
كل إجراء يُسجل تلقائياً في `driver_contract_notes` مع التاريخ والتفاصيل.  
يمكن عرض هذا السجل في صفحة التفاصيل (قسم "سجل الإجراءات").

---

## الملفات المُنشأة/المُعدّلة

### قاعدة البيانات:
- ✅ `database/equipation_manage.sql` - تحديث schema
- ✅ `database/add_mine_id_to_drivercontracts.sql` - ترحيل
- ✅ `database/create_driver_contract_notes_table.sql` - جدول جديد
- ✅ `database/create_drivercontractequipments_table.sql` - جدول جديد

### PHP Files:
- ✅ `Drivers/drivercontracts.php` - تحديث كامل
- ✅ `Drivers/drivercontracts_details.php` - ملف جديد
- ✅ `Drivers/driver_contract_actions_handler.php` - ملف جديد
- ✅ `Drivers/get_driver_contract_equipments.php` - ملف جديد

### الملفات المشتركة (Shared):
- ✅ `Suppliers/get_project_mines.php` - موجود
- ✅ `Suppliers/get_mine_contracts.php` - موجود

---

## الدعم والصيانة

لأي مشاكل أو استفسارات:
1. تحقق من ملف `README_DRIVER_CONTRACTS.md`
2. راجع أخطاء SQL في لوج MySQL
3. افحص Console للأخطاء JavaScript
4. تأكد من تشغيل جميع سكريبتات الترحيل

---

**تاريخ التحديث:** فبراير 2026  
**الإصدار:** 2.0  
**الحالة:** ✅ مكتمل وجاهز للاستخدام
