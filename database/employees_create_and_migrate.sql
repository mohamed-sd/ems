-- =====================================================================
--  الموجة 1: إنشاء سجل الموظفين الموحّد employees والترحيل من drivers
--  بحفظ المفاتيح الأساسية (id) — فتبقى كل المراجع (driver_id/timesheet.driver)
--  صحيحة تشير إلى employees.id دون أي تعديل.
--  يُشغَّل مرّة واحدة. النسخة الاحتياطية مأخوذة مسبقاً (rollback).
--  MariaDB 10.4+ · DB: equipation_manage
-- =====================================================================
SET NAMES utf8mb4;

-- 1) نسخة هيكلية مطابقة تماماً لـ drivers (أعمدة/أنواع/فهارس/مفتاح أساسي)
CREATE TABLE IF NOT EXISTS `employees` LIKE `drivers`;

-- 2) ترحيل بحفظ المفاتيح: الأعمدة متطابقة الآن فينجح SELECT * (id محفوظ كما هو)
INSERT INTO `employees` SELECT * FROM `drivers`;

-- 3) إضافة أعمدة سجل الموظفين الجديدة (بعد الترحيل؛ الصفوف القائمة تأخذ الافتراضات)
ALTER TABLE `employees`
    ADD COLUMN IF NOT EXISTS `employee_type` VARCHAR(40) NOT NULL DEFAULT 'سائق/مشغّل' AFTER `id`,
    ADD COLUMN IF NOT EXISTS `birth_date` DATE NULL,
    ADD COLUMN IF NOT EXISTS `nationality` VARCHAR(80) NULL,
    ADD COLUMN IF NOT EXISTS `blood_type` VARCHAR(8) NULL,
    ADD COLUMN IF NOT EXISTS `whatsapp` VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS `emergency_contact_name` VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS `emergency_contact_relation` VARCHAR(80) NULL,
    ADD COLUMN IF NOT EXISTS `emergency_contact_phone` VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS `license_issue_date` DATE NULL,
    ADD COLUMN IF NOT EXISTS `license_grade` VARCHAR(40) NULL,
    ADD COLUMN IF NOT EXISTS `license_photo` VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS `medical_report_path` VARCHAR(255) NULL,
    ADD INDEX IF NOT EXISTS `idx_employees_type` (`employee_type`);

-- 4) كل الصفوف المُرحّلة = نوع «سائق/مشغّل» (الافتراض يطبّقه عمود employee_type)
--    (تصريح احتياطي لضمان القيمة حتى لو شُغّل ALTER لاحقاً)
UPDATE `employees` SET `employee_type` = 'سائق/مشغّل' WHERE `employee_type` IS NULL OR `employee_type` = '';

-- 5) ضبط AUTO_INCREMENT ليلي أعلى id (تفادي تعارض المفاتيح مستقبلاً)
--    (يُضبط تلقائياً من InnoDB بعد إدراج id صريح، وهذا تأكيد إضافي يُنفّذ من التطبيق)

-- =====================================================================
--  ملاحظة: سكربت الأرشفة النهائي (بعد نجاح اختبار الانحدار) منفصل:
--    DROP VIEW IF EXISTS drivers;  (إن وُجد VIEW انتقالي)
--    RENAME TABLE drivers TO drivers_legacy_backup;
--  انظر employees_archive_drivers.sql
-- =====================================================================
