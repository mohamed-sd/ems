-- Migration: إضافة حقول المعدات الأساسية والاحتياطية
-- التاريخ: 2026-02-21
-- الوصف: إضافة حقول جديدة لتقسيم عدد المعدات إلى أساسية واحتياطية

-- إضافة الحقول إلى جدول contractequipments
ALTER TABLE `contractequipments`
ADD COLUMN `equip_count_basic` INT(11) DEFAULT 0 COMMENT 'عدد المعدات الأساسية' AFTER `equip_count`,
ADD COLUMN `equip_count_backup` INT(11) DEFAULT 0 COMMENT 'عدد المعدات الاحتياطية' AFTER `equip_count_basic`;

-- إضافة الحقول إلى جدول suppliercontractequipments
ALTER TABLE `suppliercontractequipments`
ADD COLUMN `equip_count_basic` INT(11) DEFAULT 0 COMMENT 'عدد المعدات الأساسية' AFTER `equip_count`,
ADD COLUMN `equip_count_backup` INT(11) DEFAULT 0 COMMENT 'عدد المعدات الاحتياطية' AFTER `equip_count_basic`;

-- إضافة الحقول إلى جدول drivercontractequipments
ALTER TABLE `drivercontractequipments`
ADD COLUMN `equip_count_basic` INT(11) DEFAULT 0 COMMENT 'عدد المعدات الأساسية' AFTER `equip_count`,
ADD COLUMN `equip_count_backup` INT(11) DEFAULT 0 COMMENT 'عدد المعدات الاحتياطية' AFTER `equip_count_basic`;
