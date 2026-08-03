-- CMP-03 ⑥+: مخزن صفوف الشاشات الوليدة — سجل بيني حقيقي (لا تلفيق)
-- الشاشات الـ51 المبنية بتصميم SCR-DES بلا جداول أصلية بعد: صفوفها تُدخل
-- من فورم الإضافة الموحد وتُخزن هنا بحمولة معنونة بأسماء أعمدة المستند،
-- وأعمدة الحوكمة الآلية (الكيان/المنشئ/تاريخ الإنشاء/الحالة) أعمدة حقيقية.
-- حين يُبنى الجدول الأصلي لشاشةٍ ما (مهمة اللحاق) تُرحَّل صفوفها منه ويُحرر.
CREATE TABLE IF NOT EXISTS `cmp03_screen_rows` (
  `id` INT NOT NULL AUTO_INCREMENT COMMENT 'معرف فريد',
  `company_id` INT NOT NULL COMMENT 'الكيان المالك — عزل المستأجر',
  `canonical_file` VARCHAR(80) NOT NULL COMMENT 'الشاشة القانونية (nav09_file_map)',
  `payload` JSON NOT NULL COMMENT 'قيم الأعمدة معنونةً بأسماء المستند الحرفية',
  `status` VARCHAR(40) NOT NULL DEFAULT 'مسودة' COMMENT 'الحالة',
  `is_seed` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'صف بذرة تجريبية (يعاد بذره بأمان)',
  `created_by` INT DEFAULT NULL COMMENT 'المنشئ users.id',
  `created_by_name` VARCHAR(120) DEFAULT NULL COMMENT 'اسم المنشئ وصفته لحظة الإدخال',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'لحظة الإنشاء',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_cmp03_screen` (`company_id`, `canonical_file`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='CMP-03: صفوف الشاشات الوليدة حتى تولد جداولها الأصلية';
