-- تحديث جدول الموردين لإضافة الحقول الجديدة
-- تاريخ: 2026-02-05

-- إضافة الحقول الأساسية
ALTER TABLE `suppliers` 
ADD COLUMN IF NOT EXISTS `supplier_code` VARCHAR(100) DEFAULT NULL COMMENT 'الرمز/الكود للمورد' AFTER `name`;

ALTER TABLE `suppliers` 
ADD COLUMN IF NOT EXISTS `supplier_type` ENUM('فرد', 'شركة', 'وسيط', 'مالك', 'جهة حكومية') DEFAULT NULL COMMENT 'نوع المورد' AFTER `supplier_code`;

ALTER TABLE `suppliers` 
ADD COLUMN IF NOT EXISTS `dealing_nature` VARCHAR(255) DEFAULT NULL COMMENT 'طبيعة التعامل' AFTER `supplier_type`;

ALTER TABLE `suppliers` 
ADD COLUMN IF NOT EXISTS `equipment_types` TEXT DEFAULT NULL COMMENT 'أنواع المعدات (مفصولة بفواصل)' AFTER `dealing_nature`;

-- البيانات القانونية
ALTER TABLE `suppliers` 
ADD COLUMN IF NOT EXISTS `commercial_registration` VARCHAR(100) DEFAULT NULL COMMENT 'رقم التسجيل التجاري/الرخصة' AFTER `equipment_types`;

ALTER TABLE `suppliers` 
ADD COLUMN IF NOT EXISTS `identity_type` VARCHAR(100) DEFAULT NULL COMMENT 'نوع الهوية' AFTER `commercial_registration`;

ALTER TABLE `suppliers` 
ADD COLUMN IF NOT EXISTS `identity_number` VARCHAR(100) DEFAULT NULL COMMENT 'رقم الهوية/التسجيل' AFTER `identity_type`;

ALTER TABLE `suppliers` 
ADD COLUMN IF NOT EXISTS `identity_expiry_date` DATE DEFAULT NULL COMMENT 'تاريخ انتهاء الهوية' AFTER `identity_number`;

-- البيانات التواصلية
ALTER TABLE `suppliers` 
ADD COLUMN IF NOT EXISTS `email` VARCHAR(255) DEFAULT NULL COMMENT 'البريد الإلكتروني' AFTER `identity_expiry_date`;

ALTER TABLE `suppliers` 
ADD COLUMN IF NOT EXISTS `phone_alternative` VARCHAR(50) DEFAULT NULL COMMENT 'رقم هاتف بديل' AFTER `email`;

ALTER TABLE `suppliers` 
ADD COLUMN IF NOT EXISTS `full_address` TEXT DEFAULT NULL COMMENT 'العنوان الكامل' AFTER `phone_alternative`;

ALTER TABLE `suppliers` 
ADD COLUMN IF NOT EXISTS `contact_person_name` VARCHAR(255) DEFAULT NULL COMMENT 'اسم جهة الاتصال الأساسية' AFTER `full_address`;

ALTER TABLE `suppliers` 
ADD COLUMN IF NOT EXISTS `contact_person_phone` VARCHAR(50) DEFAULT NULL COMMENT 'هاتف جهة الاتصال' AFTER `contact_person_name`;

ALTER TABLE `suppliers` 
ADD COLUMN IF NOT EXISTS `financial_registration_status` ENUM('مسجل رسميا', 'غير مسجل', 'تحت التسجيل', 'معفى من التسجيل') DEFAULT NULL COMMENT 'حالة التسجيل المالي' AFTER `contact_person_phone`;

-- إضافة timestamp
ALTER TABLE `suppliers` 
ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `financial_registration_status`;

ALTER TABLE `suppliers` 
ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;
