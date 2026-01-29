-- إنشاء جدول العملاء
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
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول العملاء';

-- إضافة بيانات تجريبية
INSERT INTO `company_clients` (`client_code`, `client_name`, `entity_type`, `sector_category`, `phone`, `email`, `whatsapp`, `status`, `created_by`) VALUES
('C001', 'شركة النفط الوطنية', 'شركة حكومية', 'النفط والغاز', '0912345678', 'oil@example.com', '0912345678', 'نشط', 1),
('C002', 'وزارة البنية التحتية', 'جهة حكومية', 'البنية التحتية', '0923456789', 'infrastructure@gov.sd', '0923456789', 'نشط', 1),
('C003', 'شركة الطرق السريعة', 'شركة خاصة', 'الطرق والجسور', '0934567890', 'highways@example.com', '0934567890', 'نشط', 1);
