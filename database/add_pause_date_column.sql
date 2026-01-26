-- إضافة عمود تاريخ الإيقاف لجدول contracts
ALTER TABLE `contracts` 
ADD COLUMN `pause_date` DATE DEFAULT NULL COMMENT 'تاريخ إيقاف العقد' AFTER `pause_reason`;

-- إضافة عمود تاريخ الاستئناف لجدول contracts
ALTER TABLE `contracts` 
ADD COLUMN `resume_date` DATE DEFAULT NULL COMMENT 'تاريخ استئناف العقد' AFTER `pause_date`;

-- إضافة عمود تاريخ الإيقاف لجدول supplierscontracts
ALTER TABLE `supplierscontracts` 
ADD COLUMN `pause_date` DATE DEFAULT NULL COMMENT 'تاريخ إيقاف العقد' AFTER `pause_reason`;

-- إضافة عمود تاريخ الاستئناف لجدول supplierscontracts
ALTER TABLE `supplierscontracts` 
ADD COLUMN `resume_date` DATE DEFAULT NULL COMMENT 'تاريخ استئناف العقد' AFTER `pause_date`;

-- إضافة عمود تاريخ الإيقاف لجدول drivercontracts
ALTER TABLE `drivercontracts` 
ADD COLUMN `pause_date` DATE DEFAULT NULL COMMENT 'تاريخ إيقاف العقد' AFTER `status`;

-- إضافة عمود تاريخ الاستئناف لجدول drivercontracts
ALTER TABLE `drivercontracts` 
ADD COLUMN `resume_date` DATE DEFAULT NULL COMMENT 'تاريخ استئناف العقد' AFTER `pause_date`;
