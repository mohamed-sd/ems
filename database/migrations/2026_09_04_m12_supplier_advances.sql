-- ═══════════════════════════════════════════════════════════════════════════
-- M-12 · بوابةُ سلفيات الموردين — 2026-07-30
-- البطاقة: docs/specs/M-12_supplier_advances.md
-- المصدر: ENT-02 §3 («**بوابةٌ واحدةٌ** لكل ما يُصرف للمورد خارج التسوية (نقدًا ·
--         نيابةً · **عهدةً**) **بمستندٍ وجدولِ استرداد** — ورصيدُها **ظاهرٌ في
--         بطاقته دائمًا**») · §3-«لا إدخالَ حرًّا … وما لا مستندَ له لا يُحمَّل»
--         · §7 (Schema: `supplier_advance_requests`)
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء: **صفرُ صفٍّ** في `fin_dues` بـ(مورد × advance) — فالسلفةُ
-- اليوم «نوعٌ يدويٌّ» بلا جدولِ استردادٍ ولا رصيدٍ متتبَّع (دليلُ الكتالوج).
--
-- والبنيةُ توأمُ `employee_advances` (H-09-④) عمدًا: النمطُ نفسُه يُثبت نفسَه
-- مرتين — مستندٌ إلزاميٌّ بنيويًّا · رصيدٌ **مولَّد** لا يُكتب · واستردادٌ
-- بمفتاحٍ فريدٍ يمنع الخصمَ مرتين.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① بوابةُ الصرف للمورد (ENT-02 §7 حرفيًّا) ──────────────────────────────
CREATE TABLE IF NOT EXISTS `supplier_advance_requests` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `supplier_id` INT NOT NULL,
  `supplier_contract_id` INT NULL DEFAULT NULL COMMENT 'عقدُ المورد إن خُصّصت به (H-07)',
  `advance_type` ENUM('cash','on_behalf','custody') NOT NULL DEFAULT 'cash'
      COMMENT 'نقدًا · نيابةً عنه · **عهدةً** — قائمةُ §3 نصًّا',
  `amount` DECIMAL(18,2) NOT NULL,
  `currency` VARCHAR(8) NULL DEFAULT NULL,
  `doc_ref` VARCHAR(120) NOT NULL COMMENT 'سندُ الصرف — إلزاميٌّ بنيويًّا («ما لا مستندَ له لا يُحمَّل»)',
  `issued_date` DATE NOT NULL,
  `installments_count` INT NOT NULL DEFAULT 1,
  `installment_amount` DECIMAL(18,2) NOT NULL COMMENT 'قسطُ التصفية الواحدة',
  `first_recovery_period` DATE NULL DEFAULT NULL,
  `recovered` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `balance` DECIMAL(18,2) AS (`amount` - `recovered`) STORED
      COMMENT '**مولَّد** — «ورصيدُها ظاهرٌ في بطاقته دائمًا» بلا انحراف',
  `state` ENUM('draft','approved','active','settled','cancelled') NOT NULL DEFAULT 'draft',
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `approved_by` INT NULL DEFAULT NULL,
  `approved_at` DATETIME NULL DEFAULT NULL,
  `is_deleted` TINYINT NOT NULL DEFAULT 0,
  `created_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_sadv_supplier_state` (`supplier_id`, `state`),
  KEY `ix_sadv_co` (`company_id`, `state`),
  CONSTRAINT `fk_sadv_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_sadv_amount` CHECK (`amount` > 0),
  CONSTRAINT `ck_sadv_inst` CHECK (`installments_count` >= 1 AND `installment_amount` > 0),
  CONSTRAINT `ck_sadv_recovered` CHECK (`recovered` >= 0 AND `recovered` <= `amount`),
  CONSTRAINT `ck_sadv_doc` CHECK (CHAR_LENGTH(TRIM(`doc_ref`)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ② سجلُّ الاسترداد — «فلا يُخصم بندٌ مرتين» بمفتاحه ─────────────────────
-- الاستردادُ يقع **عند اعتماد التسوية** لا عند توليدها: التسويةُ المسودةُ نيّةٌ،
-- والمعتمَدةُ واقعة — ولا يُنقص رصيدٌ بنيّة.
CREATE TABLE IF NOT EXISTS `supplier_advance_recoveries` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `advance_id` INT NOT NULL,
  `settlement_id` INT NOT NULL,
  `amount` DECIMAL(18,2) NOT NULL,
  `doc_ref` VARCHAR(120) NOT NULL COMMENT 'يرث سندَ سلفته — لا استردادَ يتيم',
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sadv_recovery` (`advance_id`, `settlement_id`),
  KEY `ix_sadv_rec_settlement` (`settlement_id`),
  CONSTRAINT `fk_sadv_rec_advance` FOREIGN KEY (`advance_id`)
      REFERENCES `supplier_advance_requests` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_sadv_rec_amount` CHECK (`amount` > 0),
  CONSTRAINT `ck_sadv_rec_doc` CHECK (CHAR_LENGTH(TRIM(`doc_ref`)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ③ تسجيلُ شاشة «سلفيات الموردين» — الوحدة 158 ──────────────────────────
-- الملكيةُ لمالك عقود الموردين (2)، والماليةُ (17) تعتمد ولا تنشئ.
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 158, 'سلفيات الموردين', 'Suppliers/supplier_advances.php', 2, 0, 0, 'fa fa-money-bill-transfer', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Suppliers/supplier_advances.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 158, 1, r.a, r.e, 0
  FROM (SELECT 2  AS rid, 1 AS a, 1 AS e
        UNION ALL SELECT 17, 0, 1) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 158);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 158, 'سلفيات الموردين', 'Suppliers/supplier_advances.php',
       'fa fa-money-bill-transfer', 57, NULL, 'Suppliers/supplier_advances.php', 1
  FROM (SELECT 2 AS rid UNION ALL SELECT 17) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Suppliers/supplier_advances.php');
