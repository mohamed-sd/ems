-- ═══════════════════════════════════════════════════════════════════════════
-- M-18 · تصفيةُ إنهاء عقد المورد — 2026-07-31
-- البطاقة: docs/specs/M-18_supplier_closure.md
-- المصدر: ENT-02 §4 («**تصفية إنهاء العقد** — عند الإنهاء: **إقفالُ الحصة** ·
--         **تسويةُ العهد والسلف** · **ردُّ الضمان بعد مهلته** · و**شهادةُ إخلاءٍ
--         موثَّقة** — بحدثٍ ماليٍّ بمفتاح (**العقد × التصفية**)»)
--         · CON-03 §2-⑦ («… **وضمانُ الأداء والدفعةُ المقدمة**»)
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء: `supplier_contracts` **بلا ضمانٍ ولا دفعةٍ مقدمة** (17
-- عمودًا وليس فيها واحدٌ منهما)، و**لا جدولَ تصفيةِ إنهاءٍ ألبتة**. وردُّ
-- الضمان المنفَّذ (CON-02) **يخصُّ عقودَ العملاء وحدها** ولا يُخلط.
-- والمصدران المقيسان للخطوتين الأوليين **حيّان**: `op_containers` بحصص المورد
-- (52 صفًّا) و`supplier_advance_requests` برصيدها المولَّد (M-12).
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① ضمانُ الأداء والدفعةُ المقدمة على العقد (CON-03 §2-⑦) ────────────────
ALTER TABLE `supplier_contracts`
  ADD COLUMN `performance_guarantee` DECIMAL(18,2) NULL DEFAULT NULL
      COMMENT 'ضمانُ الأداء — NULL = لم يُشترط (يُعلَن ولا يُفترض)' AFTER `currency`,
  ADD COLUMN `guarantee_retention_days` INT NULL DEFAULT NULL
      COMMENT 'مهلةُ ردّ الضمان بالأيام بعد الانتهاء — «ردُّ الضمان **بعد مهلته**»' AFTER `performance_guarantee`,
  ADD COLUMN `advance_payment` DECIMAL(18,2) NULL DEFAULT NULL
      COMMENT 'الدفعةُ المقدمة — تُستهلك استقطاعًا في التصفية الدورية' AFTER `guarantee_retention_days`;

-- **ضمانٌ بلا مهلةٍ مكتوبةٍ مستحيل**: «بعد مهلته» بلا مهلةٍ نصٌّ بلا معنى،
-- وردٌّ بلا موعدٍ يصير اجتهادَ موظف.
ALTER TABLE `supplier_contracts`
  ADD CONSTRAINT `ck_sup_guarantee_amount` CHECK (
      `performance_guarantee` IS NULL OR `performance_guarantee` > 0),
  ADD CONSTRAINT `ck_sup_guarantee_days` CHECK (
      `performance_guarantee` IS NULL OR
      (`guarantee_retention_days` IS NOT NULL AND `guarantee_retention_days` > 0)),
  ADD CONSTRAINT `ck_sup_advance_payment` CHECK (
      `advance_payment` IS NULL OR `advance_payment` > 0);

-- ── ② تصفيةُ الإنهاء — «بمفتاح (العقد × التصفية)» ──────────────────────────
CREATE TABLE IF NOT EXISTS `supplier_contract_closures` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `contract_id` INT NOT NULL,
  `supplier_id` INT NOT NULL,
  `state` ENUM('open','cleared','closed') NOT NULL DEFAULT 'open',
  -- الخطوةُ ①: إقفالُ الحصة
  `quota_open_count` INT NOT NULL DEFAULT 0 COMMENT 'حاوياتٌ مفتوحةٌ عند آخر قياس',
  `quota_closed_at` DATETIME NULL DEFAULT NULL,
  `quota_close_reason` VARCHAR(255) NULL DEFAULT NULL
      COMMENT 'سببُ إقفال حصةٍ لم تُستهلك — «ولا تجاوزَ صامتًا للسقف» ولا إقفالَ صامتٌ دونه',
  -- الخطوةُ ②: تسويةُ العهد والسلف
  `advances_balance` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `advances_settled_at` DATETIME NULL DEFAULT NULL,
  -- الخطوةُ ③: ردُّ الضمان بعد مهلته
  `guarantee_amount` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'لقطةٌ من العقد وقت فتح التصفية',
  `guarantee_currency` VARCHAR(8) NULL DEFAULT NULL,
  `guarantee_due_date` DATE NULL DEFAULT NULL COMMENT 'نهايةُ العقد + مهلةُ الردّ',
  `guarantee_released_at` DATETIME NULL DEFAULT NULL,
  `guarantee_due_ref` INT NULL DEFAULT NULL COMMENT 'الذمّةُ الدائنةُ التي وُلّدت بالردّ — أثرٌ لا وعد',
  -- الخطوةُ ④: شهادةُ الإخلاء
  `clearance_doc` VARCHAR(120) NULL DEFAULT NULL COMMENT 'مرجعُ شهادة الإخلاء الموثَّقة',
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `opened_by` INT NULL DEFAULT NULL,
  `closed_by` INT NULL DEFAULT NULL,
  `closed_at` DATETIME NULL DEFAULT NULL,
  `is_deleted` TINYINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sup_closure` (`contract_id`) COMMENT 'تصفيةٌ واحدةٌ للعقد — «بمفتاح (العقد × التصفية)»',
  KEY `ix_sup_closure` (`company_id`, `supplier_id`, `state`),
  CONSTRAINT `fk_sup_closure_contract` FOREIGN KEY (`contract_id`)
      REFERENCES `supplier_contracts` (`id`) ON DELETE CASCADE,
  -- «وشهادةُ إخلاءٍ **موثَّقة**»: إقفالٌ بلا مستندٍ مستحيلٌ بنيويًّا
  CONSTRAINT `ck_sup_closure_doc` CHECK (
      `state` <> 'closed' OR (`clearance_doc` IS NOT NULL AND `clearance_doc` <> '')),
  -- وردُّ الضمان **يترك أثرًا ماليًّا** — لا وسمًا في خانة
  CONSTRAINT `ck_sup_closure_release` CHECK (
      `guarantee_released_at` IS NULL OR `guarantee_due_ref` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ③ الذمّةُ تُسمّى باسمها — لا «أخرى» ولا «تسوية» ────────────────────────
ALTER TABLE `fin_dues`
  MODIFY COLUMN `due_type` ENUM('hours','tons','meters','advance','discount','penalty','purchase',
      'fuel','parts','catering','water','transport','salary','allowance','overtime','deduction',
      'custody','settlement','end_of_service','guarantee_release','other') NOT NULL,
  MODIFY COLUMN `source_doc_type` ENUM('proc_issue','mnt_order','transfer_order','penalty_assessment',
      'settlement','supplier_closure','legacy_no_ref','pending_source') NULL DEFAULT NULL;

-- ── ④ تسجيلُ شاشة «تصفية إنهاء العقد» — الوحدة 162 ────────────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 162, 'تصفية إنهاء عقد المورد', 'Suppliers/supplier_closure.php', 2, 0, 0, 'fa fa-file-circle-check', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Suppliers/supplier_closure.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 162, 1, r.a, r.e, 0
  FROM (SELECT 2  AS rid, 1 AS a, 1 AS e
        UNION ALL SELECT 17, 0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 162);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 162, 'تصفية إنهاء عقد المورد', 'Suppliers/supplier_closure.php',
       'fa fa-file-circle-check', 61, NULL, 'Suppliers/supplier_closure.php', 1
  FROM (SELECT 2 AS rid UNION ALL SELECT 17) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Suppliers/supplier_closure.php');
