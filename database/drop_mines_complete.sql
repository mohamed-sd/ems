-- ====================================================================
-- Migration: حذف كل ما يتعلق بالمناجم من قاعدة البيانات
-- تشغيل هذا الملف في phpMyAdmin بعد التأكد من تشغيل:
--   remove_mine_from_contracts.sql  (لإضافة project_id للعقود أولاً)
-- ====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ════════════════════════════════════════════════════════════
-- 1. حذف FK Constraints المرتبطة بـ mines
-- ════════════════════════════════════════════════════════════

-- contracts
ALTER TABLE `contracts`
    DROP FOREIGN KEY IF EXISTS `fk_contracts_mine`;

-- drivercontracts
ALTER TABLE `drivercontracts`
    DROP FOREIGN KEY IF EXISTS `fk_drivercontracts_mine`;

-- supplierscontracts
ALTER TABLE `supplierscontracts`
    DROP FOREIGN KEY IF EXISTS `fk_supplierscontracts_mine`;

-- ════════════════════════════════════════════════════════════
-- 2. حذف Indexes المرتبطة بـ mine_id
-- ════════════════════════════════════════════════════════════

ALTER TABLE `contracts`
    DROP INDEX IF EXISTS `fk_contracts_mine`;

ALTER TABLE `drivercontracts`
    DROP INDEX IF EXISTS `idx_drivercontracts_mine_id`;

ALTER TABLE `supplierscontracts`
    DROP INDEX IF EXISTS `idx_supplierscontracts_mine_id`;

ALTER TABLE `operations`
    DROP INDEX IF EXISTS `idx_mine_id`;

-- ════════════════════════════════════════════════════════════
-- 3. حذف عمود mine_id من الجداول المختلفة
-- ════════════════════════════════════════════════════════════

-- من جدول العقود (contracts)
ALTER TABLE `contracts`
    DROP COLUMN IF EXISTS `mine_id`;

-- من جدول عقود المشغلين (drivercontracts)
ALTER TABLE `drivercontracts`
    DROP COLUMN IF EXISTS `mine_id`;

-- من جدول عقود الموردين (supplierscontracts)
ALTER TABLE `supplierscontracts`
    DROP COLUMN IF EXISTS `mine_id`;

-- من جدول التشغيل (operations)
ALTER TABLE `operations`
    DROP COLUMN IF EXISTS `mine_id`;

-- من جدول المستخدمين (users)
ALTER TABLE `users`
    DROP COLUMN IF EXISTS `mine_id`;

-- ════════════════════════════════════════════════════════════
-- 4. حذف صفحة المناجم من جدول الموديولات
-- (يحذف أيضاً الصلاحيات المرتبطة بها من role_permissions)
-- ════════════════════════════════════════════════════════════

-- أولاً حذف الصلاحيات المرتبطة
DELETE FROM `role_permissions`
WHERE `module_id` = (SELECT `id` FROM `modules` WHERE `code` = 'Projects/project_mines.php' LIMIT 1);

-- ثم حذف الموديول نفسه
DELETE FROM `modules`
WHERE `code` = 'Projects/project_mines.php';

-- ════════════════════════════════════════════════════════════
-- 5. حذف جدول mines نهائياً
-- ════════════════════════════════════════════════════════════

DROP TABLE IF EXISTS `mines`;

-- ════════════════════════════════════════════════════════════
SET FOREIGN_KEY_CHECKS = 1;
-- ════════════════════════════════════════════════════════════
-- تم: جميع بيانات المناجم حُذفت من قاعدة البيانات
-- ════════════════════════════════════════════════════════════
