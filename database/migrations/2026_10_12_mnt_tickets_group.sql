-- ═══════════════════════════════════════════════════════════════════════════
-- M-31 · M-32 · M-34 · M-36 · E-14 — بنيةُ الصيانة والبلاغات — 2026-08-01
-- المصدر: UX-04 §3/§5 · UX-07 §5.2/§8.1 · SPEC-04 بطاقة 3
-- (MySQL بلا ADD COLUMN IF NOT EXISTS — فالفحصُ عبر information_schema)
-- ═══════════════════════════════════════════════════════════════════════════

-- ── M-31 · وصلةُ التصنيف الموحّد على ticket_categories ─────────────────────
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ticket_categories'
             AND COLUMN_NAME = 'failure_main_code');
SET @sql = IF(@c = 0, 'ALTER TABLE `ticket_categories` ADD COLUMN `failure_main_code` VARCHAR(20) NULL COMMENT ''M-31: وصلةُ التصنيف الموحد — main_category_code؛ NULL = موروثٌ بلا مقابلٍ يُعلَن''', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- التعبئةُ الرجعية بمطابقة الاسم — وما لا مقابلَ له يبقى NULL **معلَنًا**
UPDATE `ticket_categories` tc
   SET tc.`failure_main_code` = (
        SELECT fc.`main_category_code` FROM `failure_codes` fc
         WHERE fc.`main_category_name` = tc.`name` LIMIT 1)
 WHERE tc.`failure_main_code` IS NULL;

-- الواجهةُ الموحّدة للقراءة — «جدولٌ واحدٌ + View»
CREATE OR REPLACE VIEW `unified_fault_taxonomy` AS
SELECT DISTINCT fc.`main_category_code` AS `code`,
       fc.`main_category_name` AS `name`,
       fc.`equipment_type`,
       'failure_codes' AS `source`
  FROM `failure_codes` fc
 WHERE fc.`main_category_code` IS NOT NULL AND fc.`main_category_code` <> '';

-- ── M-32 · تاريخُ دخول «قطعةٌ منتظرة» ──────────────────────────────────────
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mnt_order'
             AND COLUMN_NAME = 'waiting_part_since');
SET @sql = IF(@c = 0, 'ALTER TABLE `mnt_order` ADD COLUMN `waiting_part_since` DATE NULL COMMENT ''M-32: تاريخُ دخول WaitingPart — العدّادُ يُحسب منه''', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── M-34 · صورةُ بند التفتيش + بلاغُه المولَّد ─────────────────────────────
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mnt_inspection_line'
             AND COLUMN_NAME = 'photo_ref');
SET @sql = IF(@c = 0, 'ALTER TABLE `mnt_inspection_line` ADD COLUMN `photo_ref` VARCHAR(190) NULL COMMENT ''M-34: مرجعُ صورة البند''', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mnt_inspection_line'
             AND COLUMN_NAME = 'converted_ticket_id');
SET @sql = IF(@c = 0, 'ALTER TABLE `mnt_inspection_line` ADD COLUMN `converted_ticket_id` INT NULL COMMENT ''M-34: بلاغُ NoteConverted — ولا يتكرر''', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── M-36 · مفتاحُ عدم تكرار الوقائية ───────────────────────────────────────
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mnt_order'
             AND COLUMN_NAME = 'pm_cycle_key');
SET @sql = IF(@c = 0, 'ALTER TABLE `mnt_order` ADD COLUMN `pm_cycle_key` VARCHAR(80) NULL COMMENT ''M-36: plan:{id}:eq:{id}:due:{date} — يمنع توليدَ الدورة مرتين''', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- التعبئةُ الرجعية لأوامر الخطط القائمة (بمعرّف الأمر — فريدةٌ حتمًا وتُعلَن قديمة)
UPDATE `mnt_order` o
   SET o.`pm_cycle_key` = CONCAT('plan:', o.`plan_id`, ':eq:', COALESCE(o.`equipment_id`, 0),
                                 ':gen:', DATE(o.`created_at`), ':', o.`id`)
 WHERE o.`plan_id` IS NOT NULL AND o.`pm_cycle_key` IS NULL;

SET @c = (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mnt_order'
             AND INDEX_NAME = 'uq_mnt_pm_cycle');
SET @sql = IF(@c = 0, 'ALTER TABLE `mnt_order` ADD UNIQUE KEY `uq_mnt_pm_cycle` (`pm_cycle_key`)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── E-14 · مستوى التصعيد — «منعُ تكرار المستوى بنيويًّا» ───────────────────
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tickets'
             AND COLUMN_NAME = 'escalation_level');
SET @sql = IF(@c = 0, 'ALTER TABLE `tickets` ADD COLUMN `escalation_level` TINYINT NOT NULL DEFAULT 0 COMMENT ''E-14: أعلى مستوًى صُعّد إليه — كان المفتاحُ يوميًّا فيتكرر غدًا''', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── الشاشة 197 — تقريرُ الأعطال من التصنيف الموحّد (M-35) ──────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 197, 'تقرير الأعطال (التصنيف الموحد)', 'Maintenance/failure_report.php', 13, 0, 1, 'fa fa-chart-column', 0
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `modules`) m
                    WHERE m.`code` = 'Maintenance/failure_report.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT p.rid, 197, 1, 0, 0, 0
  FROM (SELECT 13 rid UNION ALL SELECT 3 UNION ALL SELECT 24 UNION ALL SELECT 1) p
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
                    WHERE rp.`role_id` = p.rid AND rp.`module_id` = 197);
