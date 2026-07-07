-- ═══════════════════════════════════════════════════════════════════════════
-- 2026_07_07_archive_bak_tables.sql — أرشفة جداول النسخ القديمة (المرحلة 0)
--
-- بند مخرَجات المرحلة 0 في EQUIP-ARC-R02 §3.1: «أرشفة جداول _bak_* خارج
-- القاعدة الحيّة»، وبند التحقق §6: «التأكد من عدم رجوع أي شيءٍ لجداول
-- _bak_*/legacy قبل أرشفتها».
--
-- التحقق المُثبَت (2026-07-07): grep شامل على كامل الكود = صفر إشارة لأي
-- جدولٍ من هذه القائمة. النسخة الأرشيفية الكاملة (بنية + بيانات) محفوظة في:
--   database/backups/archive_bak_tables_20260707.sql   (خارج git)
-- الاستعادة عند الحاجة: mysql < الملف أعلاه.
--
-- المصدر: نسخ ما-قبل-توحيد-الموظفين (2026-06-27) + نسخة drivers القديمة
-- (اكتمل استبدالها بـ employees) + نسخة تقاعد worker_allocation.
-- idempotent: DROP TABLE IF EXISTS.
-- ═══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ─────────────────────────────────────────────────────────────────────────────
-- 0) اكتشاف أثناء التطبيق الأول (أوقفه المُشغِّل — قيمة الأداة بعينها):
--    drivercontracts.fk_drivercontracts_driver (عمود employee_id) كان ما يزال
--    يشير إلى drivers_legacy_backup بعد توحيد الموظفين — خلل كامن: أي عقدٍ
--    لموظفٍ أُنشئ بعد 2026-06-27 كان سيُرفض (الموظف غير موجود في النسخة
--    المتجمدة). العلاج: إعادة توجيه القيد إلى employees(id) بنفس القواعد
--    (ON DELETE NO ACTION · ON UPDATE CASCADE) ثم حذف الجدول القديم.
--    التحقق المسبق: صفر صفوف يتيمة (drivercontracts فارغ محليًا).
-- ─────────────────────────────────────────────────────────────────────────────

-- إسقاط القيد القديم إن كان ما يزال يشير للنسخة المتجمدة
SET @ddl = (SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='drivercontracts'
              AND CONSTRAINT_NAME='fk_drivercontracts_driver'
              AND REFERENCED_TABLE_NAME='drivers_legacy_backup'),
    'ALTER TABLE drivercontracts DROP FOREIGN KEY fk_drivercontracts_driver', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- إنشاء القيد الصحيح نحو employees(id) إن لم يوجد
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='drivercontracts'
                  AND CONSTRAINT_NAME='fk_drivercontracts_employee'
                  AND CONSTRAINT_TYPE='FOREIGN KEY'),
    'ALTER TABLE drivercontracts ADD CONSTRAINT fk_drivercontracts_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE NO ACTION ON UPDATE CASCADE',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

DROP TABLE IF EXISTS `_bak_premerge_20260627_employees`;
DROP TABLE IF EXISTS `_bak_premerge_20260627_housing_unit`;
DROP TABLE IF EXISTS `_bak_premerge_20260627_worker_allocation`;
DROP TABLE IF EXISTS `_bak_premerge_20260627_worker_backup`;
DROP TABLE IF EXISTS `_bak_premerge_20260627_worker_contract`;
DROP TABLE IF EXISTS `_bak_premerge_20260627_worker_evaluation`;
DROP TABLE IF EXISTS `_bak_premerge_20260627_worker_evaluation_kpi`;
DROP TABLE IF EXISTS `_bak_premerge_20260627_worker_leave_absence`;
DROP TABLE IF EXISTS `_bak_premerge_20260627_worker_movement`;
DROP TABLE IF EXISTS `_bak_premerge_20260627_worker_profile`;
DROP TABLE IF EXISTS `_bak_premerge_20260627_worker_qualification`;
DROP TABLE IF EXISTS `_bak_premerge_20260627_worker_restricted_site`;
DROP TABLE IF EXISTS `_bak_premerge_20260627_worker_settlement`;
DROP TABLE IF EXISTS `_bak_premerge_20260627_worker_settlement_line`;
DROP TABLE IF EXISTS `_bak_premerge_20260627_workforce_requirement`;
DROP TABLE IF EXISTS `_bak_retire_worker_allocation`;
DROP TABLE IF EXISTS `drivers_legacy_backup`;
