-- ═══════════════════════════════════════════════════════════════════════════
-- EMS · تحويل timesheet.date من نص (VARCHAR 30) إلى نوع DATE حقيقي
-- @date 2026-06-08
--
-- المشكلة: العمود كان نصّياً فكسر الاستعلامات الزمنية (BETWEEN/>=) والفرز الصحيح
--          والتحليلات، وألزم استخدام STR_TO_DATE(t.date,'%Y-%m-%d') في كل مكان
--          (main/dashboard.php، Drivers/driver_profile.php، التقارير، الـAPI…).
--
-- لماذا التحويل في المكان آمن: كل القيم الحالية بصيغة 'YYYY-MM-DD' (تُحقَّق أدناه)،
-- وMariaDB يحوّل هذه السلاسل إلى DATE تلقائياً دون فقدان. وبعد التحويل تبقى كل
-- استدعاءات STR_TO_DATE وLIKE تعمل (تحويل DATE↔نص ضمني)، فلا ينكسر أي كود.
--
-- ┌─ قبل التطبيق على أي بيئة (وخاصةً الإنتاج u359449619_ems) ─────────────────┐
-- │ 1) خذ نسخة احتياطية:                                                       │
-- │    mysqldump -uUSER -p DBNAME timesheet > backup_timesheet_2026_06_08.sql  │
-- │ 2) شغّل فحص التحقّق — يجب أن تكون النتيجة 0 قبل المتابعة:                    │
-- │    SELECT COUNT(*) FROM timesheet                                          │
-- │     WHERE date IS NULL OR date NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$';  │
-- │    إن ظهرت صفوف غير مطابقة، صحّحها أولاً بالقسم (0) المُعلّق أدناه.          │
-- └───────────────────────────────────────────────────────────────────────────┘
-- ═══════════════════════════════════════════════════════════════════════════

-- (0) تنظيف اختياري للصيغ الأخرى إن وُجدت في بيئة بياناتها غير نظيفة (مُعلّق):
--     UPDATE timesheet SET date = DATE_FORMAT(STR_TO_DATE(date,'%d-%m-%Y'),'%Y-%m-%d')
--       WHERE date REGEXP '^[0-9]{2}-[0-9]{2}-[0-9]{4}$';
--     UPDATE timesheet SET date = DATE_FORMAT(STR_TO_DATE(date,'%d/%m/%Y'),'%Y-%m-%d')
--       WHERE date REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$';

-- (1) التحويل في المكان إلى DATE.
ALTER TABLE `timesheet` MODIFY `date` DATE NOT NULL;

-- (2) فهرس يُسرّع الاستعلامات الزمنية والفرز (BETWEEN / >= / ORDER BY date).
ALTER TABLE `timesheet` ADD INDEX IF NOT EXISTS `idx_timesheet_date` (`date`);

-- ═══════════════════════════════════════════════════════════════════════════
-- تراجُع (Rollback) إن لزم — يعيد العمود نصّاً (البيانات تبقى 'YYYY-MM-DD'):
--   ALTER TABLE `timesheet` DROP INDEX `idx_timesheet_date`;
--   ALTER TABLE `timesheet` MODIFY `date` VARCHAR(30) NOT NULL;
-- ═══════════════════════════════════════════════════════════════════════════
