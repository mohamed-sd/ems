# دمج جدول company_project في project
# Merging company_project table into project

## السبب - Reason
تم دمج جدول `company_project` (المشاريع الرئيسية) و `operationproject` (المشاريع التشغيلية) في جدول واحد `project` لتبسيط البنية وإزالة التعقيد غير الضروري.

Previously, the system had two separate tables:
- `company_project` - Main/parent projects
- `operationproject` - Operational projects (linked to company_project + clients)

Now unified into a single table:
- `project` - All projects with complete data

## التأثيرات - Impact

### ✅ الجداول المحدثة - Updated Tables:

1. **mines** - تم تحديث `project_id` ليشير إلى `project` بدلاً من `company_project`
   ```sql
   -- قبل:
   project_id → company_project.id
   
   -- بعد:
   project_id → project.id
   ```

2. **project** - أصبح الجدول الرئيسي لكل المشاريع
   - حذف حقل `company_project_id` (لم يعد مطلوباً)
   - الآن يحتوي على جميع بيانات المشروع مباشرة

### 📊 البيانات الموجودة - Existing Data:

جدول `project` يحتوي حالياً على **5 مشاريع**:
1. مشروع الروسية (ID: 1) → له منجم واحد
2. مشروع فاروس (ID: 2) → له منجم واحد
3. مشروع الروسيه جديد (ID: 3)
4. مشروع طريق الخرطوم - بورتسودان (ID: 4)
5. مشروع الطريق الدائري (ID: 5)

### 🔗 العلاقات الجديدة - New Relationships:

```
project (المشاريع)
    ├── clients (العملاء) - via company_client_id
    ├── mines (المناجم) - via project_id
    ├── contracts (العقود) - via project
    ├── operations (التشغيل) - via project
    └── supplierscontracts (عقود الموردين) - via project_id
```

## التوافق مع الملفات الموجودة - Compatibility

### ✅ الملفات التي تعمل بشكل صحيح:

1. **Projects/oprationprojects.php** - يستخدم `project` مباشرة ✅
2. **Projects/project_mines.php** - يستخدم `project_id` للربط مع المشاريع ✅
3. **Contracts/contracts.php** - يستخدم `project` للربط ✅
4. **Suppliers/supplierscontracts.php** - يستخدم `project_id` ✅

### ⚠️ تحديثات مطلوبة (إن وجدت):

إذا كان هناك أي ملفات تشير إلى `company_project` أو `operationproject` يجب تحديثها:

```php
// قبل:
$query = "SELECT * FROM operationproject WHERE id = $id";

// بعد:
$query = "SELECT * FROM project WHERE id = $id";
```

## الاستعلامات المحدثة - Updated Queries

### عرض المشاريع مع المناجم:
```sql
SELECT 
    p.*,
    (SELECT COUNT(*) FROM mines WHERE project_id = p.id) as mines_count
FROM project p
WHERE p.status = 1;
```

### عرض المشاريع مع العملاء:
```sql
SELECT 
    p.*,
    c.client_name,
    c.sector_category
FROM project p
LEFT JOIN clients c ON p.company_client_id = c.id
WHERE p.status = 1;
```

### عرض المناجم مع بيانات المشروع:
```sql
SELECT 
    m.*,
    p.name AS project_name,
    p.location,
    p.state
FROM mines m
JOIN project p ON m.project_id = p.id
WHERE m.status = 1;
```

## خطوات التطبيق - Implementation Steps

### إذا كنت تقوم بترقية من نسخة قديمة:

```sql
-- 1. النسخ الاحتياطي
mysqldump -u root -p equipation_manage > backup_before_merge.sql

-- 2. تحديث جدول المناجم (إذا كان يشير لجدول قديم)
ALTER TABLE mines 
MODIFY `project_id` int(11) NOT NULL COMMENT 'معرف المشروع من جدول operationproject';

-- 3. تحديث أي مفاتيح أجنبية (إن وجدت)
-- ALTER TABLE mines DROP FOREIGN KEY fk_mines_project;
-- ALTER TABLE mines ADD CONSTRAINT fk_mines_project 
--   FOREIGN KEY (project_id) REFERENCES project(id) ON DELETE CASCADE;

-- 4. التحقق من البيانات
SELECT 
    p.id,
    p.name,
    COUNT(m.id) as mines_count
FROM project p
LEFT JOIN mines m ON p.id = m.project_id
GROUP BY p.id;
```

### للتثبيت الجديد:
فقط قم باستيراد ملف `equipation_manage.sql` الرئيسي - كل شيء جاهز!

```bash
mysql -u root -p equipation_manage < database/equipation_manage.sql
```

## ملاحظات هامة - Important Notes

1. ✅ **لا حاجة لتعديل الواجهات** - جميع الملفات تستخدم `project` بالفعل
2. ✅ **البيانات محفوظة** - لم يتم فقدان أي بيانات في عملية الدمج
3. ✅ **الأداء محسّن** - تقليل عدد الـ JOINs المطلوبة للاستعلامات
4. ⚠️ **المفاتيح الفريدة** - تأكد من أن `project_code` فريد في `project`

## الفوائد - Benefits

1. 🚀 **بنية أبسط** - جدول واحد بدلاً من اثنين
2. ⚡ **استعلامات أسرع** - JOIN أقل = أداء أفضل
3. 🔧 **صيانة أسهل** - نقطة واحدة للحقيقة (Single Source of Truth)
4. 📝 **كود أنظف** - تقليل التعقيد في الواجهات

---

**تاريخ التطبيق:** 3 فبراير 2026  
**الإصدار:** v2.1  
**الحالة:** ✅ مكتمل ومطبق
