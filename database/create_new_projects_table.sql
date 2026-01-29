-- إنشاء جدول جديد للمشاريع التشغيلية
-- Create new table for operational projects
-- Database: equipation_manage

-- حذف الجدول إذا كان موجوداً (اختياري)
-- DROP TABLE IF EXISTS `company_project`;

-- إنشاء الجدول الجديد
CREATE TABLE `company_project` (
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

-- إضافة بيانات تجريبية (اختياري)
INSERT INTO `company_project` 
  (`project_code`, `project_name`, `category`, `sub_sector`, `state`, `region`, `nearest_market`, `latitude`, `longitude`, `status`) 
VALUES
  ('PRJ-2026-001', 'مشروع طريق الخرطوم - بورتسودان', 'طرق', 'الطرق السريعة', 'الخرطوم', 'الخرطوم بحري', 'سوق ليبيا', '15.5527', '32.5599', 'نشط'),
  ('PRJ-2026-002', 'مشروع محطة مياه النيل الأزرق', 'مياه', 'محطات المياه', 'النيل الأزرق', 'الدمازين', 'سوق الدمازين', '11.7891', '34.3592', 'نشط');

-- عرض بنية الجدول
DESCRIBE `company_project`;

-- عرض البيانات المُدخلة
SELECT * FROM `company_project`;
