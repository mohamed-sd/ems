# آخر تحديثات قاعدة البيانات - Database Updates Log

## تاريخ التحديث: 3 فبراير 2026
**Database Version:** equipation_manage - Latest Export (Feb 03, 2026 at 08:17 PM)

## الجداول المحدثة - Updated Tables

### 🔄 التغييرات الهيكلية الهامة - Structural Changes

**دمج الجداول - Table Merging:**
- ✅ تم دمج جدول `company_project` (المشاريع الرئيسية) و `operationproject` (المشاريع التشغيلية) في جدول واحد `project`
- **السبب:** تبسيط البنية وإزالة التعقيد في العلاقات بين الجداول
- **النتيجة:** الآن جدول `project` يحتوي على جميع بيانات المشاريع مباشرة
- **التأثير:** جميع الجداول التي كانت تشير إلى `company_project` أو `operationproject` تم تحديثها لتشير إلى `project`

### 1. جدول العملاء - `clients` Table
**الحالة:** ✅ محدّث بالكامل

**الحقول الرئيسية:**
- `id` - معرف فريد
- `client_code` - كود العميل (فريد)
- `client_name` - اسم العميل
- `entity_type` - نوع الكيان
- `sector_category` - تصنيف القطاع
- `phone`, `email`, `whatsapp` - بيانات الاتصال
- `status` - حالة العميل (ENUM: نشط/متوقف)
- `created_by`, `created_at`, `updated_at` - تتبع التعديلات

**البيانات التجريبية:** 4 عملاء

---

### 2. جدول المناجم - `mines` Table ⭐ جديد
**الحالة:** ✅ تم الإضافة بنجاح

**هيكل الجدول (19 حقل):**

#### الحقول الأساسية:
- `id` INT(11) - المعرف الفريد
- `project_id` INT(11) - معرف المشروع (FK → company_project.id)
- `mine_code` VARCHAR(50) UNIQUE - كود المنجم (يجب أن يكون فريداً)
- `mine_name` VARCHAR(255) - اسم المنجم

#### تفاصيل المنجم:
- `manager_name` VARCHAR(255) - اسم مدير المنجم
- `mineral_type` VARCHAR(100) - نوع المعدن (ذهب، فضة، نحاس، إلخ)

#### نوع المنجم - `mine_type` ENUM:
1. حفرة مفتوحة
2. تحت أرضي
3. آبار
4. مهجور
5. مجمع معالجة/تركيز
6. موقع تخزين/مستودع
7. أخرى

- `mine_type_other` VARCHAR(100) - تفاصيل إذا اختير "أخرى"

#### نوع الملكية - `ownership_type` ENUM:
1. تعدين أهلي/تقليدي
2. شركة سودانية خاصة
3. شركة حكومية/قطاع عام
4. شركة أجنبية
5. مشروع مشترك (سوداني-أجنبي)
6. أخرى

- `ownership_type_other` VARCHAR(100) - تفاصيل إذا اختيرت "أخرى"

#### المقاييس والأبعاد:
- `mine_area` DECIMAL(10,2) - مساحة المنجم
- `mine_area_unit` ENUM('هكتار', 'كم²') - وحدة قياس المساحة
- `mining_depth` DECIMAL(10,2) - عمق التعدين (بالمتر)

#### التعاقد والإدارة:
- `contract_nature` ENUM:
  - موظف مباشر لدى المالك
  - مقاول/شركة مقاولات

#### الحالة والملاحظات:
- `status` TINYINT(1) - حالة المنجم (1=نشط، 0=غير نشط)
- `notes` TEXT - ملاحظات إضافية

#### التتبع الزمني:
- `created_by` INT(11) - معرف المستخدم الذي أضاف السجل
- `created_at` TIMESTAMP - تاريخ الإضافة
- `updated_at` TIMESTAMP - تاريخ آخر تحديث

**البيانات التجريبية:** 2 منجم مرتبطين بمشروعين مختلفين

**العلاقات:**
```
project (1) → (N) mines
```

**الفهارس:**
- PRIMARY KEY: `id`
- UNIQUE KEY: `mine_code`
- FOREIGN KEY: `project_id` → project.id

---

### 3. جدول عقود الموردين - `supplierscontracts` Table
**الحالة:** ✅ محدّث ومطوّر

**التحسينات:**
- إضافة حقل `project_id` لربط العقد بمشروع محدد
- كل مورّد يمكنه التعاقد على عدة مشاريع
- يحتوي على نفس حقول عقود المشاريع + معرف المورد

**الجداول المرتبطة:**
- `suppliercontractequipments` - تفاصيل المعدات لكل عقد مورد
- `supplier_contract_notes` - سجل تدقيق لإجراءات العقود

---

### 4. جدول معدات عقود الموردين - `suppliercontractequipments` Table
**الحالة:** ✅ محدّث بالكامل

**الحقول الرئيسية:**
- `contract_id` - معرف عقد المورد
- `equip_type`, `equip_size`, `equip_count` - تفاصيل المعدات
- `equip_shifts` - عدد الورديات
- `shift_hours` - ساعات الوردية
- `equip_monthly_target` - الهدف الشهري
- `equip_total_contract` - إجمالي ساعات العقد
- `equip_price` - السعر
- `equip_price_currency` - العملة (دولار/جنيه)
- عدد المشغلين، المشرفين، الفنيين، المساعدين

**النمط:** يطابق تماماً هيكل جدول `contractequipments`

---

### 5. جدول معدات العقود - `contractequipments` Table
**الحالة:** ✅ محدّث مع حقول الورديات

**الحقول المضافة:**
- `shift1_start`, `shift1_end` - مواعيد الوردية الأولى
- `shift2_start`, `shift2_end` - مواعيد الوردية الثانية
- `shift_hours` - إجمالي ساعات الوردية
- `equip_operators`, `equip_supervisors`, `equip_technicians`, `equip_assistants` - أعداد الطواقم
- `equip_price_currency` - تمييز عملة السعر

**البيانات:** 9 سجلات معدات موزعة على 4 عقود مختلفة

---

## الواجهات البرمجية المرتبطة - Related Interface Files

### إدارة المناجم:
1. **[Projects/project_mines.php](../Projects/project_mines.php)**
   - واجهة CRUD كاملة لإدارة المناجم
   - نماذج منبثقة (Modal) للإضافة والتعديل
   - DataTables للعرض مع البحث والفرز
   - التحقق من فرادة كود المنجم
   - حقول شرطية (تظهر حسب الاختيار)

2. **[Projects/view_projects.php](../Projects/view_projects.php)**
   - إضافة عمود "عدد المناجم" في جدول المشاريع
   - رابط قابل للنقر يأخذك لصفحة المناجم
   - استعلام فرعي لحساب عدد المناجم تلقائياً
   - تصميم بشارة (Badge) باللون البنفسجي مع أيقونة جبل

### عقود الموردين:
1. **[Suppliers/supplierscontracts.php](../Suppliers/supplierscontracts.php)**
   - إدارة عقود الموردين مع اختيار المشروع
   - حساب الساعات التلقائي
   - ربط كل عقد بمشروع محدد

2. **[Suppliers/supplier_contract_actions_handler.php](../Suppliers/supplier_contract_actions_handler.php)**
   - معالج JSON API لإجراءات دورة حياة عقود الموردين
   - التجديد، التسوية، الإيقاف، الاستئناف، الإنهاء، الدمج

3. **[Suppliers/get_supplier_contract_equipments.php](../Suppliers/get_supplier_contract_equipments.php)**
   - AJAX endpoint لجلب معدات عقد المورد

---

## استعلامات SQL الشائعة - Common SQL Queries

### 1. عرض المشاريع مع عدد المناجم:
```sql
SELECT p.*, 
       (SELECT COUNT(*) FROM mines WHERE project_id = p.id AND status = 1) as mines_count
FROM project p
WHERE p.status = 1
ORDER BY p.id DESC;
```

### 2. عرض جميع المناجم لمشروع معين:
```sql
SELECT m.*, p.name AS project_name
FROM mines m
JOIN project p ON m.project_id = p.id
WHERE m.project_id = ? AND m.status = 1
ORDER BY m.created_at DESC;
```

### 3. البحث عن منجم بالكود:
```sql
SELECT * FROM mines 
WHERE mine_code = ? AND status = 1
LIMIT 1;
```

### 4. إحصائيات المناجم حسب نوع الملكية:
```sql
SELECT ownership_type, COUNT(*) as count
FROM mines
WHERE status = 1
GROUP BY ownership_type
ORDER BY count DESC;
```

### 5. عرض عقود المورد لمشروع محدد:
```sql
SELECT sc.*, s.name AS supplier_name, p.name AS project_name
FROM supplierscontracts sc
JOIN suppliers s ON sc.supplier_id = s.id
JOIN project p ON sc.project_id = p.id
WHERE sc.project_id = ?
ORDER BY sc.contract_signing_date DESC;
```

### 6. حساب إجمالي الساعات المتعاقد عليها لمورد:
```sql
SELECT 
    s.name AS supplier_name,
    SUM(sc.forecasted_contracted_hours) AS total_contracted_hours,
    COUNT(sc.id) AS contracts_count
FROM supplierscontracts sc
JOIN suppliers s ON sc.supplier_id = s.id
WHERE sc.supplier_id = ?
GROUP BY s.id;
```

### 7. عرض المشاريع مع بيانات العملاء:
```sql
SELECT p.*, c.client_name, c.sector_category
FROM project p
LEFT JOIN clients c ON p.company_client_id = c.id
WHERE p.status = 1
ORDER BY p.create_at DESC;
```

---

## ملفات SQL المتاحة - Available SQL Files

| الملف | الوصف |
|------|------|
| `equipation_manage.sql` | 🟢 **الملف الرئيسي** - تصدير كامل لقاعدة البيانات (آخر تحديث: 3 فبراير 2026) |
| `create_project_mines_table.sql` | إنشاء جدول المناجم (مدمج في الملف الرئيسي) |
| `add_payment_fields.sql` | إضافة حقول الدفع لجدول contracts |
| `create_suppliercontractequipments_table.sql` | إنشاء جدول معدات عقود الموردين |
| `add_missing_fields_to_supplierscontracts.sql` | إضافة الحقول الناقصة لعقود الموردين |

---

## خطوات التطبيق - Implementation Steps

### إذا كانت قاعدة البيانات جديدة:
```bash
# الخطوة 1: إنشاء قاعدة البيانات
mysql -u root -p
CREATE DATABASE equipation_manage CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit;

# الخطوة 2: استيراد الملف الرئيسي
mysql -u root -p equipation_manage < database/equipation_manage.sql
```

### إذا كنت تريد التحديث من نسخة قديمة:
```bash
# قم بعمل نسخة احتياطية أولاً
mysqldump -u root -p equipation_manage > backup_$(date +%Y%m%d).sql

# ثم قم بتطبيق السكريبتات بالترتيب:
mysql -u root -p equipation_manage < database/create_project_mines_table.sql
mysql -u root -p equipation_manage < database/add_missing_fields_to_supplierscontracts.sql
mysql -u root -p equipation_manage < database/create_suppliercontractequipments_table.sql
```

---

## التحقق من التحديثات - Verification Queries

### التحقق من وجود جدول المناجم:
```sql
SHOW TABLES LIKE 'mines';
DESCRIBE mines;
```

### التحقق من عدد السجلات:
```sql
SELECT 
    (SELECT COUNT(*) FROM mines) as mines_count,
    (SELECT COUNT(*) FROM clients) as clients_count,
    (SELECT COUNT(*) FROM supplierscontracts) as supplier_contracts_count,
    (SELECT COUNT(*) FROM suppliercontractequipments) as supplier_equipment_count;
```

### التحقق من العلاقات:
```sql
-- عدد المناجم لكل مشروع
SELECT 
    p.name AS project_name,
    COUNT(m.id) as mines_count
FROM project p
LEFT JOIN mines m ON p.id = m.project_id
GROUP BY p.id
ORDER BY mines_count DESC;
```

---

## ملاحظات هامة - Important Notes

1. **الترميز:** جميع الجداول تستخدم `utf8mb4_unicode_ci` لدعم اللغة العربية بشكل كامل
2. **المفاتيح الفريدة:** تأكد من فرادة `mine_code` في جدول المناجم
3. **النسخ الاحتياطي:** يُنصح بعمل نسخة احتياطية قبل أي تحديث
4. **الفهرسة:** جميع الجداول مفهرسة بشكل صحيح للأداء الأمثل
5. **العلاقات:** استخدم `ON DELETE CASCADE` بحذر عند إعداد المفاتيح الأجنبية
6. **الحالة:** دائماً استخدم `status = 1` في استعلامات البحث للسجلات النشطة
7. **التواريخ:** استخدم `TIMESTAMP` للتتبع التلقائي للتعديلات

---

## الإصدارات السابقة - Version History

| التاريخ | الإصدار | التغييرات |
|---------|---------|-----------|
| 2026-02-03 | v2.1 | إضافة جدول المناجم + تحديث عقود الموردين |
| 2026-02-01 | v2.0 | إضافة جدول العملاء + تحديث العلاقات |
| 2026-01-25 | v1.5 | إضافة حقول الدفع للعقود |
| 2026-01-20 | v1.0 | الإصدار الأساسي |

---

**آخر تحديث:** 3 فبراير 2026  
**المطوّر:** فريق EMS  
**الحالة:** ✅ جاهز للإنتاج
