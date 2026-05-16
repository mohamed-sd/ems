-- =====================================================
-- إضافة عمود mine_code (كود المنجم) إلى جدول project
-- =====================================================
-- التاريخ: 2026-05-16
-- الهدف: تخزين كود المنجم في جدول المشاريع مباشرة
-- =====================================================

-- إضافة عمود mine_code إلى جدول project
ALTER TABLE `project`
ADD COLUMN `mine_code` VARCHAR(100) NULL DEFAULT NULL COMMENT 'كود المنجم' AFTER `project_code`;

-- إضافة فهرس على mine_code لتحسين الأداء عند البحث
CREATE INDEX `idx_mine_code` ON `project`(`mine_code`);
