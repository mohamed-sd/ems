# إصلاح خطأ end_date في ربط المعدات بالسائقين

## المشكلة
```
Fatal error: Column 'end_date' cannot be null in save_equipment_drivers.php:66
```

عمود `end_date` في جدول `equipment_drivers` معرّف بأنه `NOT NULL`، لكن النظام يحاول إدراج قيمة NULL عندما لا يتم تحديد تاريخ نهاية.

## الحل المطبق (Quick Fix)

### 1. التعديل على الكود
تم تعديل [save_equipment_drivers.php](save_equipment_drivers.php) لاستخدام تاريخ مستقبلي بعيد (`2099-12-31`) بدلاً من NULL عندما لا يتم تحديد تاريخ نهاية.

```php
// السطر 54
$end_sql = $end_date !== '' ? "'" . mysqli_real_escape_string($conn, $end_date) . "'" : "'2099-12-31'";
```

**الفائدة:**
- يعمل مباشرة دون تعديل قاعدة البيانات
- يحافظ على التوافق مع البيانات الموجودة
- تاريخ 2099-12-31 يمثل "ربط مستمر" (بدون نهاية محددة)

---

## الحل البديل (اختياري - Proper Fix)

### 2. تعديل قاعدة البيانات

إذا كنت تفضل استخدام NULL الحقيقي بدلاً من التاريخ المستقبلي، قم بتشغيل السكريبت:

**الملف:** [database/fix_equipment_drivers_end_date_nullable.sql](../database/fix_equipment_drivers_end_date_nullable.sql)

```sql
-- تشغيل من phpMyAdmin أو سطر الأوامر
ALTER TABLE `equipment_drivers` 
MODIFY COLUMN `end_date` varchar(50) DEFAULT NULL;
```

**خطوات التطبيق:**
1. افتح phpMyAdmin
2. اختر قاعدة البيانات `equipation_manage`
3. اذهب إلى تبويب SQL
4. افتح الملف `database/fix_equipment_drivers_end_date_nullable.sql` وانسخ محتواه
5. الصق الكود وشغّل الاستعلام

**بعد تشغيل السكريبت:**

عدّل السطر 54 في [save_equipment_drivers.php](save_equipment_drivers.php) إلى:

```php
$end_sql = $end_date !== '' ? "'" . mysqli_real_escape_string($conn, $end_date) . "'" : "NULL";
```

---

## المقارنة بين الحلين

| الميزة | Quick Fix (التاريخ البعيد) | Proper Fix (NULL) |
|--------|------------------------|------------------|
| **لا يحتاج تعديل قاعدة بيانات** | ✅ نعم | ❌ لا |
| **يعمل مباشرة** | ✅ نعم | ⚠️ بعد ALTER TABLE |
| **منطقي قاعدة البيانات** | ⚠️ مقبول | ✅ ممتاز |
| **الاستعلامات** | `WHERE end_date > NOW()` | `WHERE end_date IS NULL` |
| **حجم البيانات** | نفسه | نفسه |

---

## ملاحظات مهمة

### عند استخدام Quick Fix:
- الربط المستمر (بدون نهاية) = `end_date = '2099-12-31'`
- للبحث عن الروابط النشطة: `WHERE end_date > CURDATE()`
- لإنهاء ربط: قم بتحديث `end_date` إلى التاريخ الفعلي

### عند استخدام Proper Fix:
- الربط المستمر = `end_date IS NULL`
- للبحث عن الروابط النشطة: `WHERE end_date IS NULL OR end_date > CURDATE()`
- لإنهاء ربط: قم بتحديث `end_date` إلى التاريخ الفعلي

---

## الملفات المعدلة

1. ✅ [Equipments/save_equipment_drivers.php](save_equipment_drivers.php) - استخدام '2099-12-31' بدلاً من NULL
2. ✅ [database/fix_equipment_drivers_end_date_nullable.sql](../database/fix_equipment_drivers_end_date_nullable.sql) - سكريبت اختياري لجعل العمود nullable

---

## الاختبار

بعد التطبيق، تأكد من:
- [x] إضافة سائق بدون تاريخ نهاية - يجب أن يعمل
- [x] إضافة سائق مع تاريخ نهاية - يجب أن يعمل
- [x] عرض المعدات في [equipments.php](equipments.php) - يجب أن يظهر السائقين بشكل صحيح
- [x] فلترة المعدات النشطة - يجب أن تعمل الفلاتر

---

## التطوير المستقبلي

يمكن إضافة:
- معالجة خاصة لعرض "مستمر" بدلاً من "2099-12-31" في الواجهة
- تنبيهات للروابط التي تقترب من نهايتها
- تجديد تلقائي للروابط المنتهية
