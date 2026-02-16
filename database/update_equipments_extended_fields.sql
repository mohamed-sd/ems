-- إضافة حقول إضافية لجدول equipments
-- تاريخ التنفيذ: 2026-02-16

-- 1. المعلومات الأساسية والتعريفية
ALTER TABLE `equipments` 
ADD COLUMN `serial_number` VARCHAR(100) NULL COMMENT 'رقم المعدة/الرقم التسلسلي' AFTER `name`,
ADD COLUMN `chassis_number` VARCHAR(100) NULL COMMENT 'رقم الهيكل/الهيكل الأساسي' AFTER `serial_number`;

-- 2. بيانات الصنع والموديل
ALTER TABLE `equipments`
ADD COLUMN `manufacturer` VARCHAR(100) NULL COMMENT 'الماركة/الشركة المصنعة' AFTER `chassis_number`,
ADD COLUMN `model` VARCHAR(100) NULL COMMENT 'الموديل/الطراز' AFTER `manufacturer`,
ADD COLUMN `manufacturing_year` INT(4) NULL COMMENT 'سنة الصنع' AFTER `model`,
ADD COLUMN `import_year` INT(4) NULL COMMENT 'سنة الاستيراد/البدء' AFTER `manufacturing_year`;

-- 3. الحالة الفنية والمواصفات
ALTER TABLE `equipments`
ADD COLUMN `equipment_condition` VARCHAR(50) NULL DEFAULT 'في حالة جيدة' COMMENT 'حالة المعدة' AFTER `import_year`,
ADD COLUMN `operating_hours` INT NULL COMMENT 'ساعات التشغيل' AFTER `equipment_condition`,
ADD COLUMN `engine_condition` VARCHAR(50) NULL DEFAULT 'جيدة' COMMENT 'حالة المحرك' AFTER `operating_hours`,
ADD COLUMN `tires_condition` VARCHAR(50) NULL DEFAULT 'N/A' COMMENT 'حالة الإطارات' AFTER `engine_condition`;

-- 4. بيانات الملكية
ALTER TABLE `equipments`
ADD COLUMN `actual_owner_name` VARCHAR(200) NULL COMMENT 'اسم المالك الفعلي' AFTER `tires_condition`,
ADD COLUMN `owner_type` VARCHAR(50) NULL COMMENT 'نوع المالك' AFTER `actual_owner_name`,
ADD COLUMN `owner_phone` VARCHAR(50) NULL COMMENT 'رقم هاتف المالك' AFTER `owner_type`,
ADD COLUMN `owner_supplier_relation` VARCHAR(100) NULL COMMENT 'علاقة المالك بالمورد' AFTER `owner_phone`;

-- 5. الوثائق والتسجيلات
ALTER TABLE `equipments`
ADD COLUMN `license_number` VARCHAR(100) NULL COMMENT 'رقم الترخيص/التسجيل' AFTER `owner_supplier_relation`,
ADD COLUMN `license_authority` VARCHAR(100) NULL COMMENT 'جهة الترخيص' AFTER `license_number`,
ADD COLUMN `license_expiry_date` DATE NULL COMMENT 'تاريخ انتهاء الترخيص' AFTER `license_authority`,
ADD COLUMN `inspection_certificate_number` VARCHAR(100) NULL COMMENT 'رقم شهادة الفحص' AFTER `license_expiry_date`,
ADD COLUMN `last_inspection_date` DATE NULL COMMENT 'تاريخ آخر فحص' AFTER `inspection_certificate_number`;

-- 6. الموقع والتوفر
ALTER TABLE `equipments`
ADD COLUMN `current_location` VARCHAR(255) NULL COMMENT 'الموقع الحالي' AFTER `last_inspection_date`,
ADD COLUMN `availability_status` VARCHAR(50) NULL DEFAULT 'متاحة للعمل' COMMENT 'حالة التوفر' AFTER `current_location`;

-- 7. البيانات المالية والقيمة
ALTER TABLE `equipments`
ADD COLUMN `estimated_value` DECIMAL(15,2) NULL COMMENT 'القيمة المقدرة للمعدة' AFTER `availability_status`,
ADD COLUMN `daily_rental_price` DECIMAL(10,2) NULL COMMENT 'سعر التأجير اليومي' AFTER `estimated_value`,
ADD COLUMN `monthly_rental_price` DECIMAL(10,2) NULL COMMENT 'سعر التأجير الشهري' AFTER `daily_rental_price`,
ADD COLUMN `insurance_status` VARCHAR(50) NULL COMMENT 'التأمين/الضمان' AFTER `monthly_rental_price`;

-- 8. ملاحظات وسجل الصيانة
ALTER TABLE `equipments`
ADD COLUMN `general_notes` TEXT NULL COMMENT 'ملاحظات عامة' AFTER `insurance_status`,
ADD COLUMN `last_maintenance_date` DATE NULL COMMENT 'تاريخ آخر صيانة' AFTER `general_notes`;

-- إضافة فهارس للبحث السريع
ALTER TABLE `equipments`
ADD INDEX `idx_serial_number` (`serial_number`),
ADD INDEX `idx_chassis_number` (`chassis_number`),
ADD INDEX `idx_manufacturer` (`manufacturer`),
ADD INDEX `idx_availability_status` (`availability_status`);

-- ملاحظة: الحقول UNIQUE لم يتم إضافتها لأن serial_number و chassis_number قد لا تكون متوفرة دائماً
-- يمكن إضافة UNIQUE لاحقاً إذا لزم الأمر
