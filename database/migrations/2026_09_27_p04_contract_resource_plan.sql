-- ═══════════════════════════════════════════════════════════════════════════
-- P-04 · خطةُ الموارد بحصص الأنواع — 2026-08-01
-- البطاقة: docs/specs/P-04_contract_resource_plan.md
-- المصدر: الملحق §3-`P-04`: «**خطةُ الموارد** `contract_resource_plan` بحصص
--         الأنواع (`capacity_share_percent`) — **تُغذّي الحاويات ولا تدخل القيمة**».
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء (وهو **أخطرُ ما في هذه المهمة**):
--   `contractequipments` (12 صفًّا · 24 عمودًا) هي خطةُ الموارد القائمة —
--   وفيها `equip_price` و`equip_price_currency` و`equip_total_contract`.
--   و**الحاوياتُ الجذرُ تُبذَر منها حرفيًّا**: قياسٌ على الحيّ يُظهر
--   `op_containers.contract_item_id` = `contractequipments.id` و
--   `op_containers.cap_qty` = `equip_total_contract` **بالضبط** (6 حاويات جذر).
--   ⇒ **خطةُ المعدات هي مصدرُ المال ومصدرُ الطاقة معًا** — وهو عينُ الازدواج
--   الذي حسمته `P-02` في جانب القيمة. و`P-04` تحسمه في جانب الطاقة:
--   **بنيةٌ لا تحمل سعرًا أصلًا** — فالفصلُ صارَ في الجدول لا في الاتفاق.
--
-- ولا يُمَسّ `contractequipments`: يبقى كما هو ويظلّ يغذّي الحاويات القائمة،
-- وخطةُ الربط في docs/reports/p04_resource_backfill_plan_20260801.md.
--
-- ⚠ گوتشا مثبَتة (للمرة الرابعة): `CHECK` لا يرى صفوفًا أخرى — فقيدُ
--   «Σ الحصص ≤ 100» **يُحمَل على البند** (`resource_share_total`) ويُحرَس
--   بـ`CHECK`، والكتابةُ **معاملةٌ واحدة**. و**المساواةُ التامة (100) شرطُ
--   الاكتمال** لا شرطُ الإدراج — كما في `P-03`.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① عدّادُ الحصص على بند البيع ───────────────────────────────────────────
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'client_contract_lines'
                  AND COLUMN_NAME = 'resource_share_total'),
    'ALTER TABLE `client_contract_lines`
       ADD COLUMN `resource_share_total` DECIMAL(9,3) NOT NULL DEFAULT 0
           COMMENT ''Σ حصص خطة الموارد النافذة — يُحرَس بـCHECK فلا يتجاوز 100'' AFTER `plan_sealed_version`,
       ADD CONSTRAINT `ck_ccl_share` CHECK (
           `resource_share_total` >= 0 AND `resource_share_total` <= 100)',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ── ② خطةُ الموارد — **ولا عمودَ مالٍ فيها بتاتًا** ────────────────────────
CREATE TABLE IF NOT EXISTS `contract_resource_plan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `contract_id` INT NOT NULL,
  `line_id` INT UNSIGNED NOT NULL COMMENT 'بندُ البيع (P-02) — والخطةُ تقول كيف تُنتَج كميتُه',
  `equipment_type_id` INT NOT NULL COMMENT 'نوعُ المعدة (equipments_types) — لا معدةٌ بعينها: الخطةُ نوعٌ لا أصل',
  `equipment_size` INT NULL DEFAULT NULL COMMENT 'الحجمُ/السعةُ التصنيفية كما في العقد',
  `count_basic` INT NOT NULL DEFAULT 0 COMMENT 'الأساسيةُ — هي التي تُنتج',
  `count_backup` INT NOT NULL DEFAULT 0 COMMENT 'الاحتياطيةُ — جاهزيةٌ لا إنتاجٌ مخطَّط',
  `shifts_per_day` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `hours_per_shift` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `operators_count` INT NOT NULL DEFAULT 0 COMMENT 'طلبُ عمالةٍ مخطَّط — **لا استحقاقَ ولا كلفة**',
  `supervisors_count` INT NOT NULL DEFAULT 0,
  `technicians_count` INT NOT NULL DEFAULT 0,
  `assistants_count` INT NOT NULL DEFAULT 0,
  `capacity_share_percent` DECIMAL(6,3) NOT NULL DEFAULT 0
      COMMENT 'حصةُ هذا النوع من كمية البند — Σ الحصص = 100 عند الاكتمال',
  `share_kind` ENUM('productive','backup_only','support') NOT NULL DEFAULT 'productive'
      COMMENT 'المنتجُ يحمل حصةً · والاحتياطيُّ والمساندُ صفرًا **معلَنًا**',
  `operational_site_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'نطاقُ العقد (P-01) إن خُصّصت الخطةُ لموقع',
  `valid_from` DATE NOT NULL,
  `valid_to` DATE NULL DEFAULT NULL,
  `state` ENUM('draft','active','ended') NOT NULL DEFAULT 'draft'
      COMMENT 'المنتهيةُ تبقى للتاريخ — والتعديلُ إنهاءٌ وإضافةٌ لا محو',
  `end_reason` VARCHAR(200) NULL DEFAULT NULL,
  `source_contract_equipment_id` INT NULL DEFAULT NULL
      COMMENT 'أصلُها في contractequipments إن جاءت من القديم — والقديمُ لا يُمَس',
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `deleted_by` INT UNSIGNED NULL DEFAULT NULL,
  -- **حيلةُ العمود المولَّد**: صفٌّ نافذٌ واحدٌ لكل (بند × نوع) — والمنتهيةُ
  -- والمحذوفةُ تخرج من القيد لأن MySQL لا يقيّد NULL. (ويأتي **بعد** العمودين
  -- اللذين يقرؤهما: المولَّدُ لا يشير إلى ما لم يُعرَّف قبله.)
  `live_type_key` INT GENERATED ALWAYS AS (
      IF(`state` = 'ended' OR `is_deleted` = 1, NULL, `equipment_type_id`)) STORED,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crp_live_type` (`line_id`, `live_type_key`)
      COMMENT 'نوعٌ واحدٌ نافذٌ لكل بند — ولا صفَّان يتنازعان الحصةَ نفسَها',
  KEY `ix_crp_lookup` (`company_id`, `contract_id`, `state`),
  KEY `ix_crp_type` (`equipment_type_id`),
  CONSTRAINT `fk_crp_line` FOREIGN KEY (`line_id`)
      REFERENCES `client_contract_lines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_crp_type` FOREIGN KEY (`equipment_type_id`)
      REFERENCES `equipments_types` (`id`),
  CONSTRAINT `ck_crp_share` CHECK (`capacity_share_percent` >= 0 AND `capacity_share_percent` <= 100),
  CONSTRAINT `ck_crp_counts` CHECK (
      `count_basic` >= 0 AND `count_backup` >= 0
      AND `shifts_per_day` >= 1 AND `shifts_per_day` <= 4
      AND `hours_per_shift` >= 0 AND `hours_per_shift` <= 24
      AND `operators_count` >= 0 AND `supervisors_count` >= 0
      AND `technicians_count` >= 0 AND `assistants_count` >= 0),
  -- **المنتجُ لا يكون بحصةِ صفر**: صفرٌ بنوعٍ منتجٍ تناقضٌ — فإمّا حصةٌ
  -- وإمّا اسمٌ يفسّر الصفر (احتياطيٌّ أو مساند). نظيرُ «شهرِ التوقف» في P-03.
  CONSTRAINT `ck_crp_productive` CHECK (
      `share_kind` <> 'productive' OR `capacity_share_percent` > 0),
  CONSTRAINT `ck_crp_zero_share` CHECK (
      `share_kind` = 'productive' OR `capacity_share_percent` = 0),
  CONSTRAINT `ck_crp_window` CHECK (`valid_to` IS NULL OR `valid_to` >= `valid_from`),
  CONSTRAINT `ck_crp_ended` CHECK (`state` <> 'ended' OR `end_reason` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PLAN-03 §2 — خطةُ الموارد: حصصُ الأنواع تغذّي الحاويات **ولا تحمل سعرًا**';

-- ── ③ وصلُ الحاوية بمصدرها من الخطة — إضافيٌّ يقبل NULL فلا يغيّر سلوكًا ────
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'op_containers'
                  AND COLUMN_NAME = 'resource_plan_id'),
    'ALTER TABLE `op_containers`
       ADD COLUMN `resource_plan_id` INT UNSIGNED NULL DEFAULT NULL
           COMMENT ''صفُّ خطة الموارد الذي بُذرت منه الحاوية (P-04) — والقديمُ يبقى على contract_item_id''
           AFTER `contract_item_id`,
       ADD KEY `ix_oc_resource_plan` (`resource_plan_id`)',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ── ④ تسجيلُ شاشة «خطة موارد العقد» — الوحدة 175 ───────────────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 175, 'خطة موارد العقد', 'Contracts/contract_resource_plan.php', 12, 0, 0, 'fa fa-truck-ramp-box', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Contracts/contract_resource_plan.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 175, 1, r.a, r.e, 0
  FROM (SELECT 12 AS rid, 1 AS a, 1 AS e
        UNION ALL SELECT 1, 0, 0
        UNION ALL SELECT 6, 0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 175);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 175, 'خطة موارد العقد', 'Contracts/contract_resource_plan.php',
       'fa fa-truck-ramp-box', 74, NULL, 'Contracts/contract_resource_plan.php', 1
  FROM (SELECT 12 AS rid UNION ALL SELECT 1 UNION ALL SELECT 6) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Contracts/contract_resource_plan.php');
