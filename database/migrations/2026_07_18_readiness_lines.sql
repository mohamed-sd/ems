-- ══════════════════════════════════════════════════════════════════════════════
-- EQUIP-OPE-S05-EMS · §6.12 — بنود فحص الجاهزية (readiness_lines) + مؤشر العقد
-- كيانٌ جديد واحد + عمودٌ محسوبٌ على contracts (readiness_state). Additive.
-- المرجع: INJAZ-S05 §6.12 — بنود الجاهزية الستة (أسطول/موردين/قوى/تمويل/صيانة/موقع)
--   بحالاتها (مجتاز/فجوة/قيد المعالجة)؛ و§6.6: readiness_state (لم يبدأ/جارٍ/مجتاز).
-- naming: contract_ref (اتساقًا مع عائلة العقد المستحدثة).
-- ENUMات عربية → التنفيذ بعميل utf8mb4 حصرًا (وإلا ترميزٌ مزدوج).
-- readiness_state «عرضٌ لا إنفاذ»: يُعاد حسابه من البنود (لا يمنع اعتمادًا — لا آلة
--   حالةٍ رباعيةً بعد). التنفيذ: mysql --default-character-set=utf8mb4 < الملف.
-- ══════════════════════════════════════════════════════════════════════════════
SET NAMES utf8mb4;

-- ── 1) بنود فحص الجاهزية (§6.12) — FK منطقي → contracts (يعيش هنا فقط) ──
CREATE TABLE IF NOT EXISTS `readiness_lines` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`     INT NOT NULL,
  `readiness_code` VARCHAR(50) NOT NULL,
  `contract_ref`   INT NOT NULL,
  `name`           ENUM('جاهزية الأسطول','جاهزية الموردين','جاهزية القوى','جاهزية التمويل','جاهزية الصيانة','جاهزية الموقع') NOT NULL DEFAULT 'جاهزية الأسطول',
  `source_ref`     VARCHAR(60) NULL,
  `required`       VARCHAR(255) NULL,
  `available`      VARCHAR(255) NULL,
  `state`          ENUM('مجتاز','فجوة','قيد المعالجة') NOT NULL DEFAULT 'قيد المعالجة',
  `gap_note`       TEXT NULL,
  `created_by`     INT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`     TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at`     DATETIME NULL,
  `deleted_by`     INT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rdl_company_code` (`company_id`,`readiness_code`),
  KEY `idx_rdl_scope`    (`company_id`,`is_deleted`),
  KEY `idx_rdl_contract` (`contract_ref`),
  KEY `idx_rdl_state`    (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='INJAZ-S05 §6.12 — بنود فحص الجاهزية الستة بحالاتها لكل عقد';

-- ── 2) مؤشر جاهزية العقد (§6.6) — محسوبٌ من البنود، عرضٌ لا إنفاذ ──
-- إضافةٌ آمنة idempotent (MySQL 8.4 لا يدعم ADD COLUMN IF NOT EXISTS): حارس
-- information_schema عبر PREPARE — يتطلّبه المُشغِّل (إعادة تطبيقٍ بلا خطأ).
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contracts' AND COLUMN_NAME = 'readiness_state');
SET @ddl := IF(@col_exists = 0,
  "ALTER TABLE `contracts` ADD COLUMN `readiness_state` ENUM('لم يبدأ','جارٍ','مجتاز') NOT NULL DEFAULT 'لم يبدأ' COMMENT 'INJAZ-S05 §6.6 — محسوبٌ من readiness_lines (عرضٌ لا إنفاذ)'",
  "DO 0");
PREPARE _rdl_alter FROM @ddl; EXECUTE _rdl_alter; DEALLOCATE PREPARE _rdl_alter;

-- ── 3) تسجيل الوحدة (نمط module 66) — id 129 owner_role_id=12
--    (128 أُخذ لـ Finance/operator_pay_fin.php على القاعدة الحيّة) ──
-- idempotent: حارس NOT EXISTS يمنع Duplicate عند إعادة التطبيق (يتطلّبه المُشغِّل).
INSERT INTO `modules` (`id`,`name`,`code`,`owner_role_id`,`is_link`,`icon`,`display_order`)
SELECT 129,'فحص الجاهزية','Clients/readiness_lines.php',12,'1','fa fa-clipboard-check',15
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `id` = 129);

INSERT INTO `role_permissions` (`role_id`,`module_id`,`can_view`,`can_add`,`can_edit`,`can_delete`)
SELECT 12,129,1,1,1,1
WHERE NOT EXISTS (SELECT 1 FROM `role_permissions` WHERE `role_id` = 12 AND `module_id` = 129);

-- ══════════════════════════════════════════════════════════════════════════════
-- ROLLBACK (نفّذ فقط عند الطلب):
--   DELETE FROM role_permissions WHERE module_id = 129;
--   DELETE FROM modules WHERE id = 129;
--   ALTER TABLE contracts DROP COLUMN readiness_state;
--   DROP TABLE IF EXISTS readiness_lines;
-- ══════════════════════════════════════════════════════════════════════════════
