-- ═══════════════════════════════════════════════════════════════════════════
-- P-02 · بنودُ المبيعات وفصلُها عن خطة الموارد — 2026-08-01
-- البطاقة: docs/specs/P-02_client_contract_lines.md
-- المصدر: الملحق §3-P-02: «**بنودُ المبيعات** `client_contract_lines` بنموذجها
--         وسعرها وسريانها وحالتها الضريبية — **وفصلُها عن خطة الموارد**» ·
--         و§4: «**عقدُ طنٍّ بخطة معدات: قيمةُ العقد لم تتضاعف** — وهذا هو
--         برهانُ P-02».
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء:
--   · **لا دالةَ تحسب «قيمة العقد» في الشجرة كلِّها** — فالخطرُ لم يقع بعدُ
--     لأن أحدًا لم يحسب القيمةَ قط. ويتحقق **لحظةَ بنائها**.
--   · `contract_commitments.commitment_type` **يخلط محورين في ENUM واحد**:
--       كمياتٌ تُفوتَر: total_qty(2) · period_qty(2) · min_guaranteed(1)
--       طاقةٌ لا تُفوتَر: equipment_count(2) · daily_availability_hours(4)
--                        · capacity_support(1)
--   · وحاملُ السعر اليوم `contractequipments.equip_price` (12 صفًّا) — سعرٌ
--     **لكل معدة**. فحسابُ القيمة منه **ومن الأطنان معًا** يضاعف الإيراد.
--
-- فالفصلُ **بجدولٍ مستقلٍّ للقيمة**: `contract_commitments` يبقى للالتزام،
-- و`contractequipments` يبقى للطاقة — و**هذا الجدولُ وحدَه يحمل المال**.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `client_contract_lines` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `contract_id` INT NOT NULL,
  `line_no` INT NOT NULL,
  `pricing_model` ENUM('hour','ton','trip','meter','cbm','day','shift','lump_sum','standby')
      NOT NULL COMMENT 'نموذجُ التسعير — و`lump_sum` مقطوعٌ بكميةٍ 1',
  `description` VARCHAR(255) NOT NULL,
  `qty_contracted` DECIMAL(16,2) NOT NULL COMMENT 'الكميةُ المتعاقَد عليها لهذا البند',
  `unit_price` DECIMAL(14,4) NOT NULL,
  `currency` VARCHAR(8) NOT NULL DEFAULT 'SDG' COMMENT 'لا تُجمع عملتان في رقم',
  `valid_from` DATE NOT NULL COMMENT 'السريان — «ملحقٌ يغيّر السعر ⇒ نسختان»',
  `valid_to` DATE NULL DEFAULT NULL,
  `tax_status` ENUM('taxable','exempt','zero_rated','reverse_charge') NOT NULL DEFAULT 'taxable',
  `tax_code_id` INT NULL DEFAULT NULL COMMENT 'من `fin_tax_codes` — «الضريبةُ سطرٌ بمرجعها»',
  `source_commitment_id` INT UNSIGNED NULL DEFAULT NULL
      COMMENT 'الالتزامُ الذي اشتُق منه — **الكمياتُ وحدَها**، ولا يقبل التزامَ طاقة',
  `supersedes_line_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'البندُ الذي أخلفه — للمقارنة التاريخية',
  `state` ENUM('draft','active','superseded','ended') NOT NULL DEFAULT 'draft',
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `deleted_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ccl_line_no` (`company_id`, `contract_id`, `line_no`),
  UNIQUE KEY `uq_ccl_source` (`contract_id`, `source_commitment_id`, `valid_from`)
      COMMENT 'التزامٌ واحدٌ بسريانٍ واحد — «نسختان لا تكديس»',
  KEY `ix_ccl_lookup` (`company_id`, `contract_id`, `state`, `valid_from`, `valid_to`),
  CONSTRAINT `ck_ccl_price` CHECK (`unit_price` > 0),
  CONSTRAINT `ck_ccl_qty` CHECK (`qty_contracted` > 0),
  CONSTRAINT `ck_ccl_span` CHECK (`valid_to` IS NULL OR `valid_to` >= `valid_from`),
  -- «الضريبةُ سطرٌ بمرجعها»: الخاضعُ يلزمه رمزٌ ضريبيٌّ — بنيويًّا لا بفحصٍ يُنسى
  CONSTRAINT `ck_ccl_tax_ref` CHECK (`tax_status` <> 'taxable' OR `tax_code_id` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PLAN-03 §2 — بندُ بيع عقد العميل: **الجدولُ الوحيدُ الذي يحمل القيمة**';

-- ── تسجيلُ شاشة «بنود عقد العميل» — الوحدة 173 ─────────────────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 173, 'بنود عقد العميل وقيمته', 'Contracts/contract_lines.php', 12, 0, 0, 'fa fa-list-ol', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Contracts/contract_lines.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 173, 1, r.a, r.e, 0
  FROM (SELECT 12 AS rid, 1 AS a, 1 AS e
        UNION ALL SELECT 19, 0, 0
        UNION ALL SELECT 17, 0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 173);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 173, 'بنود عقد العميل وقيمته', 'Contracts/contract_lines.php',
       'fa fa-list-ol', 72, NULL, 'Contracts/contract_lines.php', 1
  FROM (SELECT 12 AS rid UNION ALL SELECT 19 UNION ALL SELECT 17) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Contracts/contract_lines.php');
