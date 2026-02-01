-- إضافة الحقول الناقصة إلى جدول supplierscontracts لجعله مطابقاً لجدول contracts

-- التحقق من وجود الحقول قبل إضافتها
ALTER TABLE `supplierscontracts`
ADD COLUMN IF NOT EXISTS `equip_shifts_contract` INT(11) DEFAULT 0 COMMENT 'عدد الورديات في العقد' AFTER `contract_duration_days`,
ADD COLUMN IF NOT EXISTS `shift_contract` INT(11) DEFAULT 0 COMMENT 'ساعات الوردية للعقد' AFTER `equip_shifts_contract`,
ADD COLUMN IF NOT EXISTS `equip_total_contract_daily` INT(11) DEFAULT 0 COMMENT 'إجمالي العقد اليومي' AFTER `shift_contract`,
ADD COLUMN IF NOT EXISTS `total_contract_permonth` INT(11) DEFAULT 0 COMMENT 'إجمالي العقد شهرياً' AFTER `equip_total_contract_daily`,
ADD COLUMN IF NOT EXISTS `total_contract_units` INT(11) DEFAULT 0 COMMENT 'إجمالي وحدات العقد' AFTER `total_contract_permonth`,
ADD COLUMN IF NOT EXISTS `price_currency_contract` VARCHAR(50) DEFAULT NULL COMMENT 'عملة العقد (دولار/جنيه)' AFTER `witness_two`,
ADD COLUMN IF NOT EXISTS `paid_contract` VARCHAR(100) DEFAULT NULL COMMENT 'المبلغ المدفوع' AFTER `price_currency_contract`,
ADD COLUMN IF NOT EXISTS `payment_time` VARCHAR(50) DEFAULT NULL COMMENT 'وقت الدفع (مقدم/مؤخر)' AFTER `paid_contract`,
ADD COLUMN IF NOT EXISTS `guarantees` TEXT DEFAULT NULL COMMENT 'الضمانات' AFTER `payment_time`,
ADD COLUMN IF NOT EXISTS `payment_date` DATE DEFAULT NULL COMMENT 'تاريخ الدفع' AFTER `guarantees`;

-- ملاحظة: إذا كانت قاعدة البيانات لا تدعم IF NOT EXISTS في ALTER TABLE، 
-- استخدم الأوامر التالية بدلاً من ذلك (قم بإزالة التعليق وحذف الأمر أعلاه):

/*
-- إضافة عدد الورديات في العقد
ALTER TABLE `supplierscontracts`
ADD COLUMN `equip_shifts_contract` INT(11) DEFAULT 0 COMMENT 'عدد الورديات في العقد' AFTER `contract_duration_days`;

-- إضافة ساعات الوردية للعقد
ALTER TABLE `supplierscontracts`
ADD COLUMN `shift_contract` INT(11) DEFAULT 0 COMMENT 'ساعات الوردية للعقد' AFTER `equip_shifts_contract`;

-- إضافة إجمالي العقد اليومي
ALTER TABLE `supplierscontracts`
ADD COLUMN `equip_total_contract_daily` INT(11) DEFAULT 0 COMMENT 'إجمالي العقد اليومي' AFTER `shift_contract`;

-- إضافة إجمالي العقد شهرياً
ALTER TABLE `supplierscontracts`
ADD COLUMN `total_contract_permonth` INT(11) DEFAULT 0 COMMENT 'إجمالي العقد شهرياً' AFTER `equip_total_contract_daily`;

-- إضافة إجمالي وحدات العقد
ALTER TABLE `supplierscontracts`
ADD COLUMN `total_contract_units` INT(11) DEFAULT 0 COMMENT 'إجمالي وحدات العقد' AFTER `total_contract_permonth`;

-- إضافة الحقول المالية
ALTER TABLE `supplierscontracts`
ADD COLUMN `price_currency_contract` VARCHAR(50) DEFAULT NULL COMMENT 'عملة العقد (دولار/جنيه)' AFTER `witness_two`;

ALTER TABLE `supplierscontracts`
ADD COLUMN `paid_contract` VARCHAR(100) DEFAULT NULL COMMENT 'المبلغ المدفوع' AFTER `price_currency_contract`;

ALTER TABLE `supplierscontracts`
ADD COLUMN `payment_time` VARCHAR(50) DEFAULT NULL COMMENT 'وقت الدفع (مقدم/مؤخر)' AFTER `paid_contract`;

ALTER TABLE `supplierscontracts`
ADD COLUMN `guarantees` TEXT DEFAULT NULL COMMENT 'الضمانات' AFTER `payment_time`;

ALTER TABLE `supplierscontracts`
ADD COLUMN `payment_date` DATE DEFAULT NULL COMMENT 'تاريخ الدفع' AFTER `guarantees`;
*/
