-- ═══════════════════════════════════════════════════════════════════════════
-- H-09-① · بوابةُ اللقطة — هيكلُ المسيّر وسطرُه المربوطُ بلقطته — 2026-07-30
-- البطاقة: docs/specs/H-09_1_payroll_snapshot_gate.md
-- المصدر: ENT-01 §2 (بوابةُ اللقطة: «**كلُّ سطر احتسابٍ يحمل لقطتَه** ومرجعَها
--         في العقد والملحق؛ فأيُّ تغيّرٍ لاحقٍ في العقد **لا يمسّ ما احتُسب**»)
--         · §8 (Schema: payroll_runs · payroll_lines) · PLAN-01 §6.1-①
-- ───────────────────────────────────────────────────────────────────────────
-- الشريحةُ ① **لا تحتسب زمنًا ولا إنتاجًا** (بيتُهما ②③): تبني الهيكلَ وتفرض
-- البوابةَ **بنيويًّا** — `snapshot_id` **NOT NULL** على كل سطر. فسطرُ احتسابٍ
-- بلا لقطةٍ مستحيلٌ في القاعدة، لا مرفوضٌ بفحصٍ يُنسى.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① رأسُ الدورة (ENT-01 §8 حرفيًّا) ──────────────────────────────────────
-- الحالاتُ السبع بنصّها · و**UQ (شركة × من × إلى × فئة)** ⇒ «409 دورةٌ قائمةٌ
-- للمفتاح» بنيويًّا لا بفحصٍ تطبيقيّ.
CREATE TABLE IF NOT EXISTS `payroll_runs` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `period_from` DATE NOT NULL,
  `period_to` DATE NOT NULL,
  `category_filter` VARCHAR(32) NOT NULL DEFAULT 'all'
      COMMENT 'فئةُ CON-01 §2 أو all — جزءٌ من المفتاح الفريد فلا تُخلط الدورات',
  `project_filter` INT NULL DEFAULT NULL,
  `state` ENUM('Open','Calculated','Blocked','Review','Approved','Paid','Closed')
      NOT NULL DEFAULT 'Open' COMMENT 'دورةُ ENT-01 §8 السباعية نصًّا',
  `persons_count` INT NOT NULL DEFAULT 0,
  `lines_count` INT NOT NULL DEFAULT 0,
  `blocked_count` INT NOT NULL DEFAULT 0,
  `gross_total` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'NULL = لم يكتمل الاحتساب (الشريحتان ②③)',
  `currency` VARCHAR(8) NULL DEFAULT NULL,
  `version` INT NOT NULL DEFAULT 1,
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `is_deleted` TINYINT NOT NULL DEFAULT 0,
  `created_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payroll_run_key` (`company_id`, `period_from`, `period_to`, `category_filter`),
  KEY `ix_payroll_run_state` (`company_id`, `state`),
  CONSTRAINT `ck_payroll_run_period` CHECK (`period_to` >= `period_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ② سطرُ الاحتساب — **البوابةُ هنا** ────────────────────────────────────
-- `snapshot_id` **NOT NULL** بـFK RESTRICT: لا سطرَ بلا لقطة، ولا تُحذف لقطةٌ
-- استُند إليها. وهذا هو تجسيدُ «كلُّ سطر احتسابٍ يحمل لقطتَه» (§2).
--
-- و`amount` **يقبل NULL عمدًا**: مكوّنٌ يحتاج زمنًا أو إنتاجًا (عن يومٍ · عن
-- ساعةٍ · عن وحدةٍ · شرائح) يُسجَّل سطرًا بحالة `pending_slice` **معلَنًا** —
-- «لا احتسابَ ناقصٌ صامت» (ENT-01 §5). فالنقصُ يُرى ولا يُبتلع صفرًا.
CREATE TABLE IF NOT EXISTS `payroll_lines` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `run_id` INT NOT NULL,
  `person_id` INT NOT NULL COMMENT 'employees.id — «العقدُ يشير إلى سجل الأشخاص»',
  `contract_id` INT NOT NULL,
  `snapshot_id` INT NOT NULL COMMENT '**البوابة**: لا سطرَ احتسابٍ بلا لقطته (ENT-01 §2)',
  `path` ENUM('institutional','project') NOT NULL DEFAULT 'institutional' COMMENT 'مسارا §3',
  `component_ref` VARCHAR(64) NOT NULL COMMENT 'component#N أو rule#N — مرجعُه داخل اللقطة',
  `component_type` VARCHAR(40) NULL DEFAULT NULL,
  `calc_method` VARCHAR(40) NULL DEFAULT NULL,
  `qty` DECIMAL(18,2) NULL DEFAULT NULL,
  `rate` DECIMAL(18,4) NULL DEFAULT NULL,
  `amount` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'NULL = لم يُحتسب بعد (بحالته وسببه) — لا صفرَ ملفَّق',
  `unit_record_id` INT NULL DEFAULT NULL COMMENT 'للمسار التشغيلي — الشريحة ③',
  `bearer_type` VARCHAR(20) NULL DEFAULT NULL COMMENT 'جهةُ التحمّل من اللقطة',
  `bearer_id` INT NULL DEFAULT NULL,
  `percent` DECIMAL(6,2) NULL DEFAULT NULL COMMENT 'نسبةُ الجهة — Σ لكل مكوّنٍ = 100',
  `calc_state` ENUM('computed','pending_slice','blocked') NOT NULL DEFAULT 'computed',
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_payroll_line_run_person` (`run_id`, `person_id`),
  KEY `ix_payroll_line_snapshot` (`snapshot_id`),
  CONSTRAINT `fk_payroll_line_run` FOREIGN KEY (`run_id`) REFERENCES `payroll_runs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payroll_line_snapshot` FOREIGN KEY (`snapshot_id`) REFERENCES `contract_snapshots` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ③ قائمةُ الموانع — «Blocked بقائمة الموانع وروابطها» (ENT-01 §5) ──────
-- المانعُ صفٌّ بمرجعه لا نصٌّ في تذييل: من يُمنع يُعرف ولماذا وبأي رمز.
CREATE TABLE IF NOT EXISTS `payroll_run_blocks` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `run_id` INT NOT NULL,
  `contract_id` INT NOT NULL,
  `person_id` INT NULL DEFAULT NULL,
  `block_code` VARCHAR(40) NOT NULL COMMENT 'snapshot_missing · contract_not_readable · bearer_sum_invalid …',
  `block_http` SMALLINT NOT NULL DEFAULT 422,
  `reason` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payroll_block` (`run_id`, `contract_id`, `block_code`),
  KEY `ix_payroll_block_run` (`run_id`),
  CONSTRAINT `fk_payroll_block_run` FOREIGN KEY (`run_id`) REFERENCES `payroll_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ④ تسجيلُ شاشة «مسيّر الرواتب» — الوحدة 156 (بعد 155) ───────────────────
-- الملكيةُ للقوى العاملة (4 — مالكةُ سجل العقود 151) والماليةُ (17) عرضًا.
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 156, 'مسيّر الرواتب', 'Workforce/payroll_runs.php', 4, 0, 0, 'fa fa-money-check-dollar', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Workforce/payroll_runs.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 156, 1, r.a, r.e, 0
  FROM (SELECT 4  AS rid, 1 AS a, 1 AS e      -- القوى: تفتح الدورة وتربط لقطاتها
        UNION ALL SELECT 17, 0, 0) r          -- المالية: عرضًا حتى تكتمل الشرائح
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 156);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 156, 'مسيّر الرواتب', 'Workforce/payroll_runs.php',
       'fa fa-money-check-dollar', 55, NULL, 'Workforce/payroll_runs.php', 1
  FROM (SELECT 4 AS rid UNION ALL SELECT 17) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Workforce/payroll_runs.php');
