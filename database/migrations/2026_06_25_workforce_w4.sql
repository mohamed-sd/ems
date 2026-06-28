-- ════════════════════════════════════════════════════════════════════════════
-- 2026-06-25 — طبقة القوى التشغيلية (EQUIP-OPE-S04) · الموجة 4
-- التقييم/الحوافز/الجزاءات (8.5) · التسوية (8.7) · الاحتياج وتخطيط القوى (8.10)
-- ════════════════════════════════════════════════════════════════════════════
-- Bolt-on: CREATE TABLE فقط. صفر ALTER. المالية: قيمة + *_finance_note (قرار 5).
-- ════════════════════════════════════════════════════════════════════════════
SET NAMES utf8mb4;

-- ── التقييم والحوافز والجزاءات (8.5) — incentive/penalty مدموجٌ أعمدةً ──────────
CREATE TABLE IF NOT EXISTS `worker_evaluation` (
  `id`                   INT(11) NOT NULL AUTO_INCREMENT,
  `company_id`           INT(11) DEFAULT NULL,
  `worker_id`            INT(11) NOT NULL,
  `period`               DATE DEFAULT NULL,
  `score`                DECIMAL(6,2) DEFAULT NULL COMMENT 'محسوبٌ مبدئياً يدوي',
  `incentive_penalty_type` ENUM('بلا','حافز','جزاء') NOT NULL DEFAULT 'بلا',
  `amount`               DECIMAL(12,2) DEFAULT NULL COMMENT 'مالي — يدوي',
  `amount_finance_note`  VARCHAR(255) DEFAULT NULL COMMENT 'تعليق مرجعي للمالية لاحقاً',
  `operating_hours`      DECIMAL(10,2) DEFAULT NULL,
  `attendance_rate`      DECIMAL(5,2) DEFAULT NULL,
  `productivity`         DECIMAL(10,2) DEFAULT NULL,
  `misuse_faults`        INT(11) DEFAULT NULL,
  `fuel_consumption`     DECIMAL(10,2) DEFAULT NULL,
  `safety_score`         DECIMAL(5,2) DEFAULT NULL,
  `state`                ENUM('مسودة','معتمد','مرحّل') NOT NULL DEFAULT 'مسودة',
  `notes`                TEXT DEFAULT NULL,
  `created_by`           INT(11) DEFAULT NULL,
  `created_at`           TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_we_worker` (`worker_id`),
  KEY `idx_we_company` (`company_id`),
  KEY `idx_we_state` (`state`),
  CONSTRAINT `fk_we_worker` FOREIGN KEY (`worker_id`) REFERENCES `worker_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

CREATE TABLE IF NOT EXISTS `worker_evaluation_kpi` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `evaluation_id` INT(11) NOT NULL,
  `kpi_name`      VARCHAR(150) NOT NULL,
  `weight`        DECIMAL(5,2) DEFAULT NULL,
  `score`         DECIMAL(6,2) DEFAULT NULL,
  `notes`         VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wek_eval` (`evaluation_id`),
  CONSTRAINT `fk_wek_eval` FOREIGN KEY (`evaluation_id`) REFERENCES `worker_evaluation` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── تسوية العامل (8.7) ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `worker_settlement` (
  `id`                 INT(11) NOT NULL AUTO_INCREMENT,
  `company_id`         INT(11) DEFAULT NULL,
  `worker_id`          INT(11) NOT NULL,
  `worker_contract_id` INT(11) DEFAULT NULL COMMENT 'بالقيمة إلى worker_contract.id',
  `source_type`        ENUM('شركة','مورد','مقاول') DEFAULT NULL,
  `settlement_party`   VARCHAR(255) DEFAULT NULL COMMENT 'الجهة (شركة/مورد/مقاول) — نصّي الآن',
  `settlement_basis`   ENUM('عمالة شركة','فاتورة مورد','مستخلص مقاول') DEFAULT NULL,
  `net_amount`         DECIMAL(12,2) DEFAULT NULL COMMENT 'مالي — محسوبٌ من البنود/يدوي',
  `net_finance_note`   VARCHAR(255) DEFAULT NULL,
  `state`              ENUM('محتسب','معتمد','مدفوع') NOT NULL DEFAULT 'محتسب',
  `notes`              TEXT DEFAULT NULL,
  `created_by`         INT(11) DEFAULT NULL,
  `created_at`         TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ws_worker` (`worker_id`),
  KEY `idx_ws_company` (`company_id`),
  KEY `idx_ws_state` (`state`),
  CONSTRAINT `fk_ws_worker` FOREIGN KEY (`worker_id`) REFERENCES `worker_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FUTURE: worker<->employee merge — see Workforce/FUTURE_MERGE_NOTES.md';

CREATE TABLE IF NOT EXISTS `worker_settlement_line` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `settlement_id` INT(11) NOT NULL,
  `line_type`     ENUM('مستحق','خصم') NOT NULL,
  `description`   VARCHAR(255) DEFAULT NULL,
  `amount`        DECIMAL(12,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wsl_set` (`settlement_id`),
  CONSTRAINT `fk_wsl_set` FOREIGN KEY (`settlement_id`) REFERENCES `worker_settlement` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── الاحتياج وتخطيط القوى (8.10) ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `workforce_requirement` (
  `id`                INT(11) NOT NULL AUTO_INCREMENT,
  `company_id`        INT(11) DEFAULT NULL,
  `project_id`        INT(11) DEFAULT NULL COMMENT 'بالقيمة إلى project.id',
  `worker_category`   VARCHAR(40) NOT NULL,
  `required_qty`      INT(11) NOT NULL DEFAULT 0,
  `available_qty`     INT(11) DEFAULT 0,
  `shortage_qty`      INT(11) DEFAULT 0,
  `surplus_qty`       INT(11) DEFAULT 0,
  `is_critical`       TINYINT(1) DEFAULT 0,
  `priority`          ENUM('عادية','عالية','حرجة') NOT NULL DEFAULT 'عادية',
  `need_date`         DATE DEFAULT NULL,
  `fulfillment_stage` ENUM('مفتوح','استقطاب','ترشيح واعتماد','تعاقد','تحرّك','مُلبّى') NOT NULL DEFAULT 'مفتوح',
  `state`             ENUM('مخطّط','متوازن','عجز','فائض') NOT NULL DEFAULT 'مخطّط',
  `candidates_note`   TEXT DEFAULT NULL COMMENT 'مرشّحون — إدخال يدوي (قرار 6)',
  `notes`             TEXT DEFAULT NULL,
  `created_by`        INT(11) DEFAULT NULL,
  `created_at`        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wr_company` (`company_id`),
  KEY `idx_wr_project` (`project_id`),
  KEY `idx_wr_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── تسجيل الموديولات والصلاحيات ───────────────────────────────────────────────
-- الملكية لإدارة الموارد البشرية = الدور 4 (تحقّق عكسيٌّ من جدول roles).
INSERT INTO `modules` (`name`,`code`,`owner_role_id`,`is_link`,`icon`,`display_order`)
SELECT 'تقييم العاملين','Workforce/worker_evaluation.php',4,'1','fa fa-star-half-stroke',65
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code`='Workforce/worker_evaluation.php');
INSERT INTO `modules` (`name`,`code`,`owner_role_id`,`is_link`,`icon`,`display_order`)
SELECT 'تسوية العاملين','Workforce/worker_settlement.php',4,'1','fa fa-hand-holding-dollar',66
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code`='Workforce/worker_settlement.php');
INSERT INTO `modules` (`name`,`code`,`owner_role_id`,`is_link`,`icon`,`display_order`)
SELECT 'الاحتياج والتخطيط','Workforce/workforce_requirement.php',4,'1','fa fa-clipboard-list',67
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `code`='Workforce/workforce_requirement.php');

INSERT INTO `role_permissions` (`role_id`,`module_id`,`can_view`,`can_add`,`can_edit`,`can_delete`)
SELECT 4, m.id, 1,1,1,1 FROM `modules` m
WHERE m.code IN ('Workforce/worker_evaluation.php','Workforce/worker_settlement.php','Workforce/workforce_requirement.php')
  AND NOT EXISTS (SELECT 1 FROM `role_permissions` rp WHERE rp.role_id=4 AND rp.module_id=m.id);

-- ════════════════════════════════════════════════════════════════════════════
-- التراجع:
--   DROP TABLE IF EXISTS `worker_settlement_line`,`worker_settlement`,`worker_evaluation_kpi`,`worker_evaluation`,`workforce_requirement`;
--   DELETE rp FROM role_permissions rp JOIN modules m ON m.id=rp.module_id
--     WHERE m.code IN ('Workforce/worker_evaluation.php','Workforce/worker_settlement.php','Workforce/workforce_requirement.php');
--   DELETE FROM modules WHERE code IN ('Workforce/worker_evaluation.php','Workforce/worker_settlement.php','Workforce/workforce_requirement.php');
-- ════════════════════════════════════════════════════════════════════════════
