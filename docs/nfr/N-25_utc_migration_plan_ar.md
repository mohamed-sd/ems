# N-25 · خطة ترحيل التخزين إلى UTC — **جاهزة ولا تُشغَّل** (مؤجَّلة بقرار §1.3)

> الشرط قبل التشغيل: توقيع المالك + نافذة توقف + لقطة مختبرة الاستعادة.

## ① التمهيد
```sql
-- تحميل جداول المناطق الزمنية (مرة واحدة · صلاحية إدارية):
--   mysql_tzinfo_to_sql (لينكس) أو حزمة timezone_2025a لويندوز → mysql.time_zone*
SELECT CONVERT_TZ('2026-01-15 12:00:00', 'Africa/Cairo', 'UTC'); -- يجب ألا يعيد NULL
```

## ② الجرد الآلي لأعمدة الوقت
```sql
SELECT table_name, column_name, data_type
  FROM information_schema.columns
 WHERE table_schema = DATABASE()
   AND data_type IN ('datetime') -- timestamp يتحول بالجلسة ولا يُمس
 ORDER BY table_name;
```

## ③ التحويل الواعي بالتوقيت الصيفي — جدولًا جدولًا
```sql
-- لكل (جدول، عمود) من الجرد — داخل نافذة التوقف:
SET @before := (SELECT COUNT(*) FROM <t>);
UPDATE <t> SET <col> = CONVERT_TZ(<col>, 'Africa/Cairo', 'UTC') WHERE <col> IS NOT NULL;
SET @after := (SELECT COUNT(*) FROM <t>);
-- قبول: @before = @after وROW_COUNT() = عدد غير الفارغ — يُسجَّل في محضر الترحيل
```

## ④ قلب الكتّاب لحظة واحدة
- `SET GLOBAL time_zone = '+00:00'` + `time_zone='+00:00'` في my.ini.
- `EMS_APP_TIMEZONE=UTC` في `.env`.
- التحويل للعرض في طبقة العرض وحدها (دالة عرض مركزية تُضاف حينها).

## ⑤ الرجوع المختبر
عكس ③ بالدالة نفسها (`UTC` → `Africa/Cairo`) + إعادة العلمين — بلا فقد لأن
`CONVERT_TZ` عكوسة على النطاق المعني، وبلقطة ما قبل الترحيل ضمانًا مطلقًا.

## ⑥ ما يُستثنى
- أعمدة `DATE` المحضة (لا ساعة فيها — يوم العمل مفهوم محلي).
- `timestamp` (يتبع الجلسة أصلًا).
- السجلات التاريخية قبل 1970 إن وُجدت.
