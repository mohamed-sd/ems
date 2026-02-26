-- تحديث جدول drivers ليشمل جميع الحقول الشاملة
-- نظام إدارة المشغلين/السائقين - النسخة الشاملة

ALTER TABLE `drivers` 
-- 1. المعلومات الأساسية والتعريفية
ADD COLUMN `driver_code` VARCHAR(50) NULL COMMENT 'الرمز/الكود الفريد للمشغل' AFTER `name`,
ADD COLUMN `nickname` VARCHAR(255) NULL COMMENT 'اسم الشهرة/الكنية' AFTER `driver_code`,

-- 2. بيانات الهوية والتوثيق
ADD COLUMN `identity_type` VARCHAR(50) NULL COMMENT 'نوع الهوية' AFTER `nickname`,
ADD COLUMN `identity_number` VARCHAR(100) NULL COMMENT 'رقم الهوية' AFTER `identity_type`,
ADD COLUMN `identity_expiry_date` DATE NULL COMMENT 'تاريخ انتهاء الهوية' AFTER `identity_number`,

-- 3. رخصة القيادة والمهارات
ADD COLUMN `license_number` VARCHAR(100) NULL COMMENT 'رقم رخصة القيادة' AFTER `identity_expiry_date`,
ADD COLUMN `license_type` VARCHAR(100) NULL COMMENT 'نوع رخصة القيادة' AFTER `license_number`,
ADD COLUMN `license_expiry_date` DATE NULL COMMENT 'تاريخ انتهاء رخصة القيادة' AFTER `license_type`,
ADD COLUMN `license_issuer` VARCHAR(255) NULL COMMENT 'جهة إصدار الرخصة' AFTER `license_expiry_date`,

-- 4. التخصص والمهارات
ADD COLUMN `specialized_equipment` TEXT NULL COMMENT 'نوع المعدة المتخصص فيها (متعدد)' AFTER `license_issuer`,

-- 5. سنوات الخبرة والكفاءة
ADD COLUMN `years_in_field` INT NULL COMMENT 'سنوات العمل في المجال' AFTER `specialized_equipment`,
ADD COLUMN `years_on_equipment` INT NULL COMMENT 'سنوات العمل على هذا النوع من المعدات' AFTER `years_in_field`,
ADD COLUMN `skill_level` VARCHAR(50) NULL COMMENT 'مستوى الكفاءة المهنية' AFTER `years_on_equipment`,
ADD COLUMN `certificates` TEXT NULL COMMENT 'الشهادات والتدريبات' AFTER `skill_level`,

-- 6. علاقة العمل والتبعية
ADD COLUMN `owner_supervisor` VARCHAR(255) NULL COMMENT 'اسم المالك/المشرف المباشر' AFTER `certificates`,
ADD COLUMN `supplier_id` INT NULL COMMENT 'المورد الذي يعمل معه' AFTER `owner_supervisor`,
ADD COLUMN `employment_affiliation` VARCHAR(100) NULL COMMENT 'تبعية المشغل' AFTER `supplier_id`,
ADD COLUMN `salary_type` VARCHAR(50) NULL COMMENT 'نوع الراتب/الأجر' AFTER `employment_affiliation`,
ADD COLUMN `monthly_salary` DECIMAL(10,2) NULL COMMENT 'المبلغ الشهري التقريبي' AFTER `salary_type`,

-- 7. البيانات التواصلية
ADD COLUMN `email` VARCHAR(255) NULL COMMENT 'البريد الإلكتروني' AFTER `monthly_salary`,
ADD COLUMN `phone_alternative` VARCHAR(50) NULL COMMENT 'رقم هاتف بديل' AFTER `phone`,
ADD COLUMN `address` TEXT NULL COMMENT 'العنوان' AFTER `email`,

-- 8. تقييم الأداء والسلوك
ADD COLUMN `performance_rating` VARCHAR(50) NULL COMMENT 'تقييم الكفاءة التشغيلية' AFTER `address`,
ADD COLUMN `behavior_record` VARCHAR(50) NULL COMMENT 'سجل السلوك والانضباط' AFTER `performance_rating`,
ADD COLUMN `accident_record` VARCHAR(50) NULL COMMENT 'سجل الحوادث والأعطال' AFTER `behavior_record`,

-- 9. الصحة والسلامة
ADD COLUMN `health_status` VARCHAR(50) NULL COMMENT 'الحالة الصحية' AFTER `accident_record`,
ADD COLUMN `health_issues` TEXT NULL COMMENT 'المشاكل الصحية المعروفة' AFTER `health_status`,
ADD COLUMN `vaccinations_status` VARCHAR(50) NULL COMMENT 'التطعيمات والفحوصات' AFTER `health_issues`,

-- 10. المراجع والسجل
ADD COLUMN `previous_employer` VARCHAR(255) NULL COMMENT 'اسم جهة التوظيف السابقة' AFTER `vaccinations_status`,
ADD COLUMN `employment_duration` VARCHAR(100) NULL COMMENT 'مدة العمل معهم' AFTER `previous_employer`,
ADD COLUMN `reference_contact` VARCHAR(255) NULL COMMENT 'مرجع للاتصال' AFTER `employment_duration`,
ADD COLUMN `general_notes` TEXT NULL COMMENT 'ملاحظات عامة' AFTER `reference_contact`,

-- 11. الحالة والتفعيل
ADD COLUMN `driver_status` VARCHAR(50) NULL DEFAULT 'نشط' COMMENT 'حالة المشغل' AFTER `general_notes`,
ADD COLUMN `start_date` DATE NULL COMMENT 'تاريخ البدء الفعلي' AFTER `driver_status`,
ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ التسجيل في النظام' AFTER `start_date`;

-- إضافة Foreign Key للمورد
ALTER TABLE `drivers` 
ADD CONSTRAINT `fk_drivers_supplier` 
FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) 
ON DELETE SET NULL ON UPDATE CASCADE;

-- إضافة Index للبحث السريع
ALTER TABLE `drivers` 
ADD INDEX `idx_driver_code` (`driver_code`),
ADD INDEX `idx_driver_name` (`name`),
ADD INDEX `idx_driver_status` (`driver_status`),
ADD INDEX `idx_supplier_id` (`supplier_id`);
