-- ========================================
-- ملف تحديثات قاعدة البيانات الشاملة
-- EMS (Equipment Management System)
-- Database: equipation_manage
-- Date: 2026-01-29
-- ========================================

-- استخدام قاعدة البيانات
USE `equipation_manage`;

-- ========================================
-- 1. إنشاء جدول المشاريع التشغيلية (company_project)
-- ========================================
CREATE TABLE IF NOT EXISTS `company_project` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `project_code` VARCHAR(50) NOT NULL COMMENT 'كود المشروع',
  `project_name` VARCHAR(200) NOT NULL COMMENT 'اسم المشروع',
  `category` VARCHAR(100) NOT NULL COMMENT 'تصنيف المشروع',
  `sub_sector` VARCHAR(200) NOT NULL COMMENT 'القطاع الفرعي',
  `state` VARCHAR(100) NOT NULL COMMENT 'الولاية',
  `region` VARCHAR(100) NOT NULL COMMENT 'المنطقة',
  `nearest_market` VARCHAR(100) NOT NULL COMMENT 'أقرب سوق',
  `latitude` VARCHAR(50) NOT NULL COMMENT 'خط العرض',
  `longitude` VARCHAR(50) NOT NULL COMMENT 'خط الطول',
  `status` ENUM('نشط','متوقف','مكتمل') DEFAULT 'نشط' COMMENT 'حالة المشروع',
  `created_by` INT(11) NULL COMMENT 'المستخدم الذي أضاف المشروع',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإنشاء',
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ آخر تحديث',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_code` (`project_code`),
  KEY `idx_category` (`category`),
  KEY `idx_state` (`state`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول المشاريع التشغيلية';

-- إضافة بيانات تجريبية للمشاريع
INSERT INTO `company_project` 
  (`project_code`, `project_name`, `category`, `sub_sector`, `state`, `region`, `nearest_market`, `latitude`, `longitude`, `status`) 
VALUES
  ('PRJ-2026-001', 'مشروع طريق الخرطوم - بورتسودان', 'طرق وجسور', 'الطرق السريعة', 'الخرطوم', 'الخرطوم بحري', 'سوق ليبيا', '15.5527', '32.5599', 'نشط'),
  ('PRJ-2026-002', 'مشروع محطة مياه النيل الأزرق', 'مياه وصرف صحي', 'محطات المياه', 'النيل الأزرق', 'الدمازين', 'سوق الدمازين', '11.7891', '34.3592', 'نشط'),
  ('PRJ-2026-003', 'مشروع بناء جسر النيل', 'بنية تحتية', 'جسور', 'الخرطوم', 'الخرطوم', 'سوق العربي', '15.5007', '32.5599', 'نشط')
ON DUPLICATE KEY UPDATE `project_name` = VALUES(`project_name`);

-- ========================================
-- 2. إنشاء جدول العملاء (company_clients)
-- ========================================
CREATE TABLE IF NOT EXISTS `company_clients` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `client_code` VARCHAR(50) NOT NULL COMMENT 'كود العميل',
  `client_name` VARCHAR(255) NOT NULL COMMENT 'اسم العميل',
  `entity_type` VARCHAR(100) NULL COMMENT 'نوع الكيان',
  `sector_category` VARCHAR(100) NULL COMMENT 'تصنيف القطاع',
  `phone` VARCHAR(50) NULL COMMENT 'رقم الهاتف',
  `email` VARCHAR(100) NULL COMMENT 'البريد الإلكتروني',
  `whatsapp` VARCHAR(50) NULL COMMENT 'رقم الواتساب',
  `status` ENUM('نشط', 'متوقف') NOT NULL DEFAULT 'نشط' COMMENT 'حالة العميل',
  `created_by` INT(11) NULL COMMENT 'معرف المستخدم الذي أضاف العميل',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإضافة',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ آخر تحديث',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_client_code` (`client_code`),
  KEY `idx_client_name` (`client_name`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول العملاء';

-- إضافة بيانات تجريبية للعملاء
INSERT INTO `company_clients` (`client_code`, `client_name`, `entity_type`, `sector_category`, `phone`, `email`, `whatsapp`, `status`, `created_by`) VALUES
('CL-001', 'شركة النفط الوطنية', 'حكومي', 'نفط وغاز', '0912345678', 'oil@example.com', '0912345678', 'نشط', 1),
('CL-002', 'وزارة البنية التحتية', 'حكومي', 'بنية تحتية', '0923456789', 'infrastructure@gov.sd', '0923456789', 'نشط', 1),
('CL-003', 'شركة الطرق السريعة', 'خاص', 'نقل ومواصلات', '0934567890', 'highways@example.com', '0934567890', 'نشط', 1)
ON DUPLICATE KEY UPDATE `client_name` = VALUES(`client_name`);

-- ========================================
-- 3. إضافة أعمدة جديدة إلى جدول operationproject
-- ========================================

-- إضافة عمود company_project_id
ALTER TABLE `operationproject` 
ADD COLUMN IF NOT EXISTS `company_project_id` INT(11) NULL COMMENT 'معرف المشروع من جدول company_project' AFTER `id`;

-- إضافة فهرس لعمود company_project_id
ALTER TABLE `operationproject` 
ADD INDEX IF NOT EXISTS `idx_company_project_id` (`company_project_id`);

-- إضافة عمود company_client_id
ALTER TABLE `operationproject` 
ADD COLUMN IF NOT EXISTS `company_client_id` INT(11) NULL COMMENT 'معرف العميل من جدول company_clients' AFTER `company_project_id`;

-- إضافة فهرس لعمود company_client_id
ALTER TABLE `operationproject` 
ADD INDEX IF NOT EXISTS `idx_company_client_id` (`company_client_id`);

-- ========================================
-- 4. إضافة عمود user_id إلى جدول contract_notes
-- ========================================

-- التحقق من وجود جدول contract_notes
CREATE TABLE IF NOT EXISTS `contract_notes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `contract_id` INT(11) NOT NULL COMMENT 'معرف العقد',
  `note` TEXT NOT NULL COMMENT 'نص الملاحظة',
  `user_id` INT(11) NULL DEFAULT 0 COMMENT 'معرف المستخدم الذي أضاف الملاحظة',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإضافة',
  PRIMARY KEY (`id`),
  KEY `idx_contract_id` (`contract_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول ملاحظات العقود';

-- إضافة عمود user_id إذا لم يكن موجوداً
ALTER TABLE `contract_notes` 
ADD COLUMN IF NOT EXISTS `user_id` INT(11) NULL DEFAULT 0 COMMENT 'معرف المستخدم الذي أضاف الملاحظة' AFTER `note`;

-- إضافة فهرس لعمود user_id
ALTER TABLE `contract_notes` 
ADD INDEX IF NOT EXISTS `idx_user_id` (`user_id`);

-- ========================================
-- 5. التحقق من الجداول والأعمدة المضافة
-- ========================================

-- عرض بنية جدول company_project
SELECT 'جدول المشاريع التشغيلية (company_project):' AS 'Status';
DESCRIBE `company_project`;

-- عرض بنية جدول company_clients
SELECT 'جدول العملاء (company_clients):' AS 'Status';
DESCRIBE `company_clients`;

-- عرض بنية جدول operationproject
SELECT 'جدول المشاريع التشغيلية (operationproject):' AS 'Status';
DESCRIBE `operationproject`;

-- عرض بنية جدول contract_notes
SELECT 'جدول ملاحظات العقود (contract_notes):' AS 'Status';
DESCRIBE `contract_notes`;

-- ========================================
-- 6. عرض إحصائيات البيانات
-- ========================================

SELECT 
    'إحصائيات قاعدة البيانات بعد التحديث' AS 'Report',
    (SELECT COUNT(*) FROM company_project) AS 'عدد المشاريع',
    (SELECT COUNT(*) FROM company_clients) AS 'عدد العملاء',
    (SELECT COUNT(*) FROM operationproject) AS 'عدد العمليات التشغيلية',
    (SELECT COUNT(*) FROM contract_notes) AS 'عدد ملاحظات العقود';

-- ========================================
-- ملاحظات مهمة:
-- ========================================
-- 1. تأكد من أخذ نسخة احتياطية قبل تنفيذ هذا الملف
-- 2. تم استخدام IF NOT EXISTS لتجنب الأخطاء عند التنفيذ المتكرر
-- 3. تم استخدام ON DUPLICATE KEY UPDATE للبيانات التجريبية
-- 4. جميع الجداول تستخدم InnoDB engine للدعم الكامل للمفاتيح الأجنبية
-- 5. جميع الجداول تستخدم utf8mb4 للدعم الكامل للعربية
-- ========================================

SELECT '✅ تم تنفيذ جميع التحديثات بنجاح!' AS 'Status';
