-- ═══════════════════════════════════════════════════════════════════════════
-- M-16 · الطاقةُ النظرية والجاهزيةُ ومهلةُ الإحلال — 2026-07-30
-- البطاقة: docs/specs/M-16_supplier_capacity.md
-- المصدر: CON-03 §3 («لكل معدةٍ مخصَّصةٍ **طاقةٌ نظريةٌ يوميةٌ بنموذجها** …
--         **تُثبَّت في العقد** — ومنها يُقاس أداءُ المورد **لا من تقديرٍ لاحق**»
--         · «نسبةُ الجاهزية = **زمنُ الصلاحية للعمل ÷ الزمنِ المخطط**، تُقرأ من
--         **سجل الزمن الموحّد** — ونقصُها عن الحد التعاقدي **يفعّل الجزاءَ
--         بقاعدته**» · «**مهلةُ إحلالِ المعدة المعطلة مكتوبةٌ بالساعات**؛
--         وتجاوزُها **يحوّل التوقفَ إلى عجزِ تغطيةٍ بجزائه الأشد**»)
--         · §6 (Schema `supplier_capacity`) · §5 (شاشةُ «الطاقة والجاهزية»)
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء: الجدولُ **معدوم** (صفرُ نتائجَ في `Suppliers/`).
-- والمصدرُ الذي تُقرأ منه الجاهزيةُ **قائمٌ وحيّ**: `unit_time_log` بحالاته
-- السبع (1399.5 ساعةً على 14 معدة) — فالناقصُ **الحدُّ التعاقدي** لا القياس.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `supplier_capacity` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `contract_id` INT NOT NULL COMMENT 'عقدُ المورد (H-07) — «تُثبَّت في العقد»',
  `equipment_id` INT NOT NULL,
  `work_model` ENUM('hour','ton','trip','meter') NOT NULL DEFAULT 'hour'
      COMMENT 'نموذجُ الطاقة — «طاقةٌ نظريةٌ يوميةٌ **بنموذجها**»',
  `theoretical_daily` DECIMAL(18,2) NOT NULL
      COMMENT 'الطاقةُ النظريةُ اليومية — ومنها يُقاس الأداءُ لا من تقديرٍ لاحق',
  `min_readiness_percent` DECIMAL(5,2) NULL DEFAULT NULL
      COMMENT 'الحدُّ التعاقديُّ للجاهزية — NULL = لم يُشترط (يُعلَن ولا يُفترض)',
  `replace_hours` INT NULL DEFAULT NULL
      COMMENT 'مهلةُ الإحلال بالساعات — وتجاوزُها يحوّل التوقفَ إلى عجزِ تغطية',
  `valid_from` DATE NOT NULL,
  `valid_to` DATE NULL DEFAULT NULL,
  `state` ENUM('active','ended') NOT NULL DEFAULT 'active',
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `is_deleted` TINYINT NOT NULL DEFAULT 0,
  `created_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sup_capacity` (`contract_id`, `equipment_id`, `valid_from`),
  KEY `ix_sup_capacity_eq` (`company_id`, `equipment_id`, `state`),
  CONSTRAINT `fk_sup_capacity_contract` FOREIGN KEY (`contract_id`)
      REFERENCES `supplier_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sup_capacity_equipment` FOREIGN KEY (`equipment_id`)
      REFERENCES `equipments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_sup_capacity_daily` CHECK (`theoretical_daily` > 0),
  CONSTRAINT `ck_sup_capacity_readiness` CHECK (
      `min_readiness_percent` IS NULL
      OR (`min_readiness_percent` > 0 AND `min_readiness_percent` <= 100)),
  CONSTRAINT `ck_sup_capacity_replace` CHECK (`replace_hours` IS NULL OR `replace_hours` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── تسجيلُ شاشة «الطاقة والجاهزية» — الوحدة 160 (CON-03 §5) ────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 160, 'الطاقة والجاهزية ومهلة الإحلال', 'Suppliers/supplier_capacity.php', 2, 0, 0, 'fa fa-gauge-high', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Suppliers/supplier_capacity.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 160, 1, r.a, r.e, 0
  FROM (SELECT 2  AS rid, 1 AS a, 1 AS e
        UNION ALL SELECT 17, 0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 160);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 160, 'الطاقة والجاهزية ومهلة الإحلال', 'Suppliers/supplier_capacity.php',
       'fa fa-gauge-high', 59, NULL, 'Suppliers/supplier_capacity.php', 1
  FROM (SELECT 2 AS rid UNION ALL SELECT 17) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Suppliers/supplier_capacity.php');
