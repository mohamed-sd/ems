-- ═══════════════════════════════════════════════════════════════════════════
-- H-21 · دورةُ عروض أسعار الموردين (RFQ) — 2026-07-31
-- البطاقة: docs/specs/H-21_supplier_rfq.md
-- المصدر: UX-05 §2.1 «مساحةُ عملٍ جديدة: **بنودُ الاحتياج من التزامات عقد
--         العميل** — إرسالٌ للمؤهلين وتتبّعُ الردود» · §8.2 «**بنودُ RFQ من
--         الالتزامات اشتقاقًا لا إدخالًا**» · «عقدٌ بلا التزاماتٍ → **422** ·
--         عرضٌ بعد الإقفال → **423** · موردٌ يقرأ عرضَ غيره → **403 مسجَّلة** ·
--         تخصيصٌ يجاوز الالتزام → **409 بقيمة المتاح**» · «Awarded جزئيًّا
--         (12k+8k) وΣ=20k — ومحاولةُ 21k → 409».
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء: الجداولُ الثلاثةُ **معدومة**. و`quotations` القائم
-- **عروضُ مبيعاتٍ للعملاء** لا RFQ موردين — فلا يُعاد استعمالُه (غرضان لا غرض).
-- ومصدرُ الكميات: `contract_commitments` (party_scope='client' ·
-- obliged_party='company') — **ما نلتزم به للعميل هو ما نطلب له موردًا**.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① رأسُ الطلب ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `supplier_rfqs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `rfq_no` VARCHAR(40) NOT NULL,
  `client_contract_id` INT UNSIGNED NOT NULL COMMENT 'العقدُ الذي اشتُقت منه البنود',
  `title` VARCHAR(160) NULL DEFAULT NULL,
  `due_date` DATE NOT NULL COMMENT 'موعدُ الإقفال — «عرضٌ بعد الإقفال 423»',
  `state` ENUM('draft','sent','closed','awarded','contracted','cancelled')
      NOT NULL DEFAULT 'draft' COMMENT 'UX-05 §8.2: Awarded → Contracted → ContainersAllocated',
  `sent_at` DATETIME NULL DEFAULT NULL,
  `closed_at` DATETIME NULL DEFAULT NULL,
  `awarded_at` DATETIME NULL DEFAULT NULL,
  `awarded_by` INT UNSIGNED NULL DEFAULT NULL,
  `cancel_reason` VARCHAR(255) NULL DEFAULT NULL,
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `deleted_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rfq_no` (`company_id`, `rfq_no`),
  KEY `ix_rfq_contract` (`company_id`, `client_contract_id`, `state`),
  CONSTRAINT `ck_rfq_cancel` CHECK (
      `state` <> 'cancelled' OR (`cancel_reason` IS NOT NULL AND `cancel_reason` <> '')),
  CONSTRAINT `ck_rfq_awarded` CHECK (
      `state` NOT IN ('awarded','contracted') OR `awarded_by` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='UX-05 §2.1 — طلبُ عروض الموردين: بنودُه من التزامات عقد العميل';

-- ── ② البنود — «اشتقاقًا لا إدخالًا» ───────────────────────────────────────
-- گوتشا مثبَتة: `CHECK` لا يرى صفوفًا أخرى. فلقيدِ «Σ المرسى ≤ المطلوب»
-- **البندُ يحمل عدّادَ المرسى** ويُحرَس بـ`CHECK`، والترسيةُ معاملةٌ واحدة —
-- نمطُ `op_containers.allocated_qty` حرفيًّا.
CREATE TABLE IF NOT EXISTS `rfq_lines` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `rfq_id` INT UNSIGNED NOT NULL,
  `commitment_id` INT UNSIGNED NOT NULL COMMENT 'مصدرُ البند — «من الالتزامات اشتقاقًا»',
  `line_no` INT NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `unit_type` VARCHAR(16) NULL DEFAULT NULL,
  `qty_required` DECIMAL(16,2) NOT NULL COMMENT 'من الالتزام — لا يُكتب بيد',
  `qty_awarded` DECIMAL(16,2) NOT NULL DEFAULT 0 COMMENT 'عدّادُ المرسى — يُحرَس بـCHECK',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rfq_line` (`rfq_id`, `commitment_id`)
      COMMENT 'التزامٌ واحدٌ = بندٌ واحدٌ في الطلب — لا اشتقاقَ مضاعف',
  KEY `ix_rfq_line` (`company_id`, `rfq_id`),
  CONSTRAINT `fk_rfq_line_rfq` FOREIGN KEY (`rfq_id`)
      REFERENCES `supplier_rfqs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_rfq_line_qty` CHECK (`qty_required` > 0),
  -- «تخصيصٌ يجاوز الالتزام → 409» — **بنيويًّا** لا بفحصٍ يُنسى
  CONSTRAINT `ck_rfq_line_award` CHECK (`qty_awarded` >= 0 AND `qty_awarded` <= `qty_required`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ③ العروض — «موردٌ يقرأ عرضَ غيره → 403» (العزلُ في الخدمة والشاشة) ─────
CREATE TABLE IF NOT EXISTS `rfq_quotes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `rfq_id` INT UNSIGNED NOT NULL,
  `line_id` INT UNSIGNED NOT NULL,
  `supplier_id` INT UNSIGNED NOT NULL,
  `unit_price` DECIMAL(14,4) NOT NULL COMMENT 'المعيارُ الأول: السعر',
  `currency` VARCHAR(8) NOT NULL DEFAULT 'SDG',
  `qty_offered` DECIMAL(16,2) NOT NULL COMMENT 'ما يقدر عليه — قد يكون جزءًا من المطلوب',
  `readiness_days` INT NULL DEFAULT NULL COMMENT 'المعيارُ الثاني: الجاهزية (أيامًا)',
  `record_rating` DECIMAL(4,2) NULL DEFAULT NULL COMMENT 'المعيارُ الثالث: السجل — من M-17 لا من رأي',
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `submitted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `submitted_by` INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rfq_quote` (`line_id`, `supplier_id`)
      COMMENT 'عرضٌ واحدٌ لكل (بند × مورد) — والتعديلُ استبدالٌ لا تكديس',
  KEY `ix_rfq_quote` (`company_id`, `rfq_id`, `supplier_id`),
  CONSTRAINT `fk_rfq_quote_line` FOREIGN KEY (`line_id`)
      REFERENCES `rfq_lines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_rfq_quote_price` CHECK (`unit_price` > 0 AND `qty_offered` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ④ الترسية — جزئيةٌ بالبنود (12k + 8k = 20k) ────────────────────────────
CREATE TABLE IF NOT EXISTS `rfq_awards` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `rfq_id` INT UNSIGNED NOT NULL,
  `line_id` INT UNSIGNED NOT NULL,
  `supplier_id` INT UNSIGNED NOT NULL,
  `quote_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'العرضُ الذي رُسي عليه — والسعرُ يُقرأ منه',
  `qty_awarded` DECIMAL(16,2) NOT NULL,
  `unit_price` DECIMAL(14,4) NOT NULL,
  `currency` VARCHAR(8) NOT NULL DEFAULT 'SDG',
  `reason` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حجّةُ الاختيار حين لا يكون الأرخص',
  `awarded_by` INT UNSIGNED NULL DEFAULT NULL,
  `awarded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rfq_award` (`line_id`, `supplier_id`)
      COMMENT 'ترسيةٌ واحدةٌ لكل (بند × مورد)',
  KEY `ix_rfq_award` (`company_id`, `rfq_id`),
  CONSTRAINT `fk_rfq_award_line` FOREIGN KEY (`line_id`)
      REFERENCES `rfq_lines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ck_rfq_award_qty` CHECK (`qty_awarded` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ⑤ تسجيلُ شاشة «طلبات عروض الموردين» — الوحدة 171 ───────────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 171, 'طلبات عروض الموردين', 'Suppliers/rfq_requests.php', 2, 0, 0, 'fa fa-file-contract', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Suppliers/rfq_requests.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 171, 1, r.a, r.e, 0
  FROM (SELECT 2 AS rid, 1 AS a, 1 AS e
        UNION ALL SELECT 16, 1, 0
        UNION ALL SELECT 17, 0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 171);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 171, 'طلبات عروض الموردين', 'Suppliers/rfq_requests.php',
       'fa fa-file-contract', 70, NULL, 'Suppliers/rfq_requests.php', 1
  FROM (SELECT 2 AS rid UNION ALL SELECT 16 UNION ALL SELECT 17) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Suppliers/rfq_requests.php');
