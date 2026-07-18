-- ══════════════════════════════════════════════════════════════════════════════
-- EQUIP-OPE-S05-EMS · قسم «ت.2» — التزامات العقد (contract_commitments)
-- كيانٌ جديد واحد، أثره صفرٌ على الجداول القائمة (Additive only). الشاشة في Clients/.
-- يُكمل عائلة «الوحدة التعاقدية» (ت): contract_hour_policies (ت.1) موجودٌ سلفًا،
-- و unit_party_awards (D02 §3.7) موجود — وهذا الجدول هو ت.2 المتبقّي.
-- المرجع: INJAZ-S05 §ت.2 — «الالتزامُ لا يُختزل في حقلٍ واحد: نوعُه ووحدتُه وكميتُه
-- ودوريتُه والطرفُ الملتزم وحكما العجز والزيادة».
-- naming: contract_ref (لا contract_id) اتساقًا مع شقيقَي عائلة «ت».
-- ENUMات إنجليزية (لا قيمًا عربية مخزَّنة) — العرض العربي في الشاشة فقط.
-- التنفيذ: mysql -u <user> --default-character-set=utf8mb4 < الملف. التراجع في النهاية.
-- ══════════════════════════════════════════════════════════════════════════════
SET NAMES utf8mb4;

-- ── الجدول: التزامات العقد (§ت.2) — FK منطقي → contracts (يعيش هنا فقط) ──
CREATE TABLE IF NOT EXISTS `contract_commitments` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id`      INT NOT NULL,
  `commitment_code` VARCHAR(50) NOT NULL,
  `party_scope`     ENUM('client','supplier') NOT NULL DEFAULT 'client',
  `contract_ref`    INT NOT NULL,
  `commitment_type` ENUM('equipment_count','daily_availability_hours','period_hours','min_guaranteed','period_qty','total_qty','capacity_support') NOT NULL DEFAULT 'total_qty',
  `unit_type`       ENUM('hour','ton','meter','cbm','day','shift','trip') NULL,
  `qty`             DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `period`          ENUM('daily','monthly','contract') NOT NULL DEFAULT 'monthly',
  `obliged_party`   ENUM('company','client','supplier') NOT NULL DEFAULT 'company',
  `shortfall_rule`  ENUM('invoice_actual','penalty','carry_over','extend_term','waive_if_client','negotiate') NOT NULL DEFAULT 'invoice_actual',
  `surplus_rule`    ENUM('same_price','different_price','pre_approval','open','not_billable') NOT NULL DEFAULT 'same_price',
  `note`            VARCHAR(160) NULL,
  `created_by`      INT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`      TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at`      DATETIME NULL,
  `deleted_by`      INT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_commit_company_code` (`company_id`,`commitment_code`),
  KEY `idx_commit_scope`    (`company_id`,`is_deleted`),
  KEY `idx_commit_contract` (`contract_ref`),
  KEY `idx_commit_type`     (`commitment_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='INJAZ-S05 §ت.2 — التزامات العقد: نوعٌ ووحدةٌ وكميةٌ ودوريةٌ وطرفٌ ملتزم وحكما العجز والزيادة';

-- ── تسجيل الوحدة (نمط module 66) — id 127 owner_role_id=12 ──
-- idempotent: حارس NOT EXISTS يمنع Duplicate عند إعادة التطبيق (يتطلّبه المُشغِّل).
INSERT INTO `modules` (`id`,`name`,`code`,`owner_role_id`,`is_link`,`icon`,`display_order`)
SELECT 127,'التزامات العقد','Clients/contract_commitments.php',12,'1','fa fa-handshake',14
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `id` = 127);

INSERT INTO `role_permissions` (`role_id`,`module_id`,`can_view`,`can_add`,`can_edit`,`can_delete`)
SELECT 12,127,1,1,1,1
WHERE NOT EXISTS (SELECT 1 FROM `role_permissions` WHERE `role_id` = 12 AND `module_id` = 127);

-- ══════════════════════════════════════════════════════════════════════════════
-- ROLLBACK (نفّذ فقط عند الطلب):
--   DELETE FROM role_permissions WHERE module_id = 127;
--   DELETE FROM modules WHERE id = 127;
--   DROP TABLE IF EXISTS contract_commitments;
-- ══════════════════════════════════════════════════════════════════════════════
