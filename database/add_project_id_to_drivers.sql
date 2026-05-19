-- ═══════════════════════════════════════════════════════════════
-- إضافة حقل project_id إلى جدول drivers
-- Add project_id field to drivers table for project assignment
-- ═══════════════════════════════════════════════════════════════
-- التاريخ: 2026-05-19
-- الوصف: إضافة إمكانية ربط المشغلين بمشروع رئيسي
-- ═══════════════════════════════════════════════════════════════

-- إضافة حقل project_id إذا لم يكن موجوداً
ALTER TABLE `drivers`
ADD COLUMN IF NOT EXISTS `project_id` INT(11) NULL COMMENT 'معرف المشروع المرتبط' AFTER `company_id`;

-- إضافة مؤشر على project_id لتحسين الأداء
ALTER TABLE `drivers`
ADD INDEX IF NOT EXISTS `idx_drivers_project_id` (`project_id`);

-- إضافة علاقة Foreign Key (اختياري - يمكن تفعيله إذا لزم الأمر)
-- ALTER TABLE `drivers`
-- ADD CONSTRAINT `fk_drivers_project`
-- FOREIGN KEY (`project_id`) REFERENCES `project`(`id`)
-- ON DELETE SET NULL ON UPDATE CASCADE;

-- ملاحظة: الحقل قابل للقيمة NULL لأن بعض المشغلين قد لا يكونون مرتبطين بمشروع معين
