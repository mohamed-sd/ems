-- =====================================================
-- إضافة حقل mine_id لجدول supplierscontracts
-- Add mine_id field to supplierscontracts table
-- =====================================================
-- التاريخ: 2026-02-03
-- الوصف: ربط عقود الموردين بالمناجم بدلاً من المشاريع مباشرة
-- =====================================================

-- إضافة حقل mine_id
ALTER TABLE `supplierscontracts` 
ADD COLUMN `mine_id` INT(11) NULL COMMENT 'معرف المنجم' AFTER `project_id`;

-- تحديث البيانات الموجودة (ربط عقود الموردين بالمناجم من خلال عقود المشاريع)
UPDATE `supplierscontracts` sc
INNER JOIN `contracts` c ON sc.project_contract_id = c.id
SET sc.mine_id = c.mine_id
WHERE sc.project_contract_id IS NOT NULL AND sc.mine_id IS NULL;

-- إضافة فهرس لتحسين الأداء
CREATE INDEX `idx_supplierscontracts_mine_id` ON `supplierscontracts`(`mine_id`);

-- =====================================================
-- ملاحظات:
-- 1. الحقل mine_id يسمح بـ NULL للبيانات القديمة
-- 2. العقود الجديدة يجب أن تحتوي على mine_id
-- 3. mine_id يتم الحصول عليه من عقد المشروع المرتبط
-- =====================================================
