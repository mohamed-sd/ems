-- ═══════════════════════════════════════════════════════════════════════════
-- M-03 · الفاتورةُ الضريبية — 2026-07-31
-- البطاقة: docs/specs/M-03_tax_invoices.md
-- المصدر: ENT-03 §4 («الفوترة · مستخلصٌ معتمد · **فاتورةٌ ضريبيةٌ مولَّدةٌ
--         بحقولها النظامية ورقمِها التسلسلي** — **ولا فاتورةَ بلا مستخلص**»)
--         · §6 («تفاصيل: الفاتورةُ بحقولها النظامية ورقمِها التسلسلي · زرُّ
--         الطباعة الرسمية · **لا تعديلَ بعد الإصدار** — والتصحيحُ زرُّ «إشعار
--         دائن/مدين»») · §7-Schema `tax_invoices` («**ولا صفَّ بلا claim_id**»)
--         · §7-Validation («فاتورةٌ بلا مستخلصٍ معتمد → **422**» · «تعديلُ
--         فاتورةٍ صادرة → **423** «التصحيح بإشعار»») · §5 («**والضريبةُ سطرٌ
--         مستقلٌّ بمرجعها**»)
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء: **الجدولُ معدوم**. والقائمُ عمودان على المستخلص
-- (`claims.invoice_no` = `INV-{claim_no}` و`invoice_date`) — فالرقمُ **مشتقٌّ
-- من رقم المستخلص لا تسلسلٌ نظاميٌّ**، ولا حقلَ ضريبيًّا نظاميًّا واحدًا،
-- ولا مانعَ لتعديل مستخلصٍ بعد إصدار فاتورته.
-- و`credit_debit_notes` (M-02) **قائمٌ** — فمسارُ التصحيح موجودٌ ويُشار إليه.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `tax_invoices` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `claim_id` INT NOT NULL COMMENT '«ولا صفَّ بلا claim_id» — ولا فاتورةَ بلا مستخلص',
  `client_id` INT NOT NULL,
  `serial_no` VARCHAR(40) NOT NULL COMMENT 'الرقمُ التسلسليُّ النظامي INV-{سنة}-{تسلسل}',
  `serial_year` SMALLINT NOT NULL,
  `serial_seq` INT NOT NULL COMMENT 'تسلسلٌ متصلٌ لكل (شركة × سنة) — والثغرةُ تُرى',
  `currency` VARCHAR(8) NOT NULL,
  `net_amount` DECIMAL(18,2) NOT NULL COMMENT 'صافي المستخلص كما اعتُمد — **لا يُكتب يدًا**',
  `tax_code` VARCHAR(16) NULL DEFAULT NULL,
  `tax_rate` DECIMAL(5,2) NULL DEFAULT NULL,
  `tax_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT '«والضريبةُ سطرٌ مستقلٌّ بمرجعها» (§5)',
  `total_amount` DECIMAL(18,2) NOT NULL COMMENT 'الصافي + الضريبة',
  `tax_fields_json` TEXT NULL DEFAULT NULL COMMENT 'الحقولُ النظامية لحظةَ الإصدار — لقطةٌ لا اشتقاق',
  `state` ENUM('issued','cancelled') NOT NULL DEFAULT 'issued',
  `issued_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `issued_by` INT NULL DEFAULT NULL,
  `cancel_reason` VARCHAR(255) NULL DEFAULT NULL,
  `cancelled_at` DATETIME NULL DEFAULT NULL,
  `cancelled_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tax_serial` (`company_id`, `serial_no`),
  UNIQUE KEY `uq_tax_seq` (`company_id`, `serial_year`, `serial_seq`),
  KEY `ix_tax_claim` (`claim_id`),
  KEY `ix_tax_client` (`company_id`, `client_id`, `state`),
  CONSTRAINT `fk_tax_invoice_claim` FOREIGN KEY (`claim_id`)
      REFERENCES `claims` (`id`) ON DELETE RESTRICT,
  -- المجموعُ لا يُخترع: الصافي + الضريبةُ بالضبط
  CONSTRAINT `ck_tax_total` CHECK (`total_amount` = `net_amount` + `tax_amount`),
  -- وضريبةٌ بمبلغٍ بلا رمزٍ ونسبةٍ مكتوبين مستحيلة («سطرٌ مستقلٌّ **بمرجعها**»)
  CONSTRAINT `ck_tax_ref` CHECK (
      `tax_amount` = 0 OR (`tax_code` IS NOT NULL AND `tax_code` <> '' AND `tax_rate` IS NOT NULL)),
  -- والإلغاءُ الضريبيُّ يلزمه سببٌ مكتوب — «لا تعديلَ بعد الإصدار»
  CONSTRAINT `ck_tax_cancel` CHECK (
      `state` <> 'cancelled' OR (`cancel_reason` IS NOT NULL AND `cancel_reason` <> ''))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── تسجيلُ شاشة «الفاتورة الضريبية» — الوحدة 164 (ENT-03 §6) ───────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 164, 'الفاتورة الضريبية', 'Contracts/tax_invoices.php', 12, 0, 0, 'fa fa-file-invoice-dollar', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Contracts/tax_invoices.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 164, 1, r.a, r.e, 0
  FROM (SELECT 12 AS rid, 0 AS a, 0 AS e
        UNION ALL SELECT 17, 1, 1
        UNION ALL SELECT 19, 0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 164);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 164, 'الفاتورة الضريبية', 'Contracts/tax_invoices.php',
       'fa fa-file-invoice-dollar', 63, NULL, 'Contracts/tax_invoices.php', 1
  FROM (SELECT 12 AS rid UNION ALL SELECT 17 UNION ALL SELECT 19) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Contracts/tax_invoices.php');
