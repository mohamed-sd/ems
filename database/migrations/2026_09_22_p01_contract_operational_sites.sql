-- ═══════════════════════════════════════════════════════════════════════════
-- P-01 · تنظيفُ الهرم: نطاقُ العقد التشغيلي — 2026-08-01
-- البطاقة: docs/specs/P-01_contract_operational_sites.md
-- المصدر: EXECUTION_ADDENDUM_PLAN03 §3-P-01: «`contract_operational_sites`
--         (نطاقٌ داخل العقد **باسمه وتاريخه وبنوده**) + **نقلُ `contracts.site_id`
--         إليه** + **`project_id` NOT NULL** + خطةُ إعادة ربطٍ بمطابقة».
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء (يطابق §1 الملزمة حرفًا بحرف):
--   · `contracts.project_id` قائمٌ و**صفرُ NULL** في العشرة — فالهرمُ سليمٌ
--     والمطلوبُ تنظيفٌ لا تصحيحُ مسار.
--   · `contracts.site_id` بمفتاحٍ خارجي `fk_contracts_site` و**صفرُ NULL** —
--     و**المواقعُ الـ19 كلُّها `is_default=1` وواحدٌ لكل مشروع**، أي أن العمودَ
--     **مرآةٌ للمشروع** لا بُعدٌ مستقل.
--   · **صفرُ استعمالٍ** لـ`site_id` في `Contracts/` و`app/Services/Contract/` —
--     فالنقلُ رخيصٌ و**ما لا يُقرأ لا يغيّر رقمًا**.
--
-- ⚠ ولا يُحذف عمودٌ ولا صف (§0-④): `contracts.site_id` **يبقى مرآةً موسومة**.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① النطاقُ التشغيلي — «باسمه وتاريخه» ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `contract_operational_sites` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `contract_id` INT NOT NULL,
  `site_id` INT NOT NULL COMMENT 'الموقع/المنجم من `sites` (H-05) — الكيانُ المستقل',
  `scope_name` VARCHAR(190) NOT NULL COMMENT 'اسمُ النطاق داخل العقد — قد يخالف اسمَ الموقع',
  `start_date` DATE NULL DEFAULT NULL COMMENT 'NULL = من بداية العقد',
  `end_date` DATE NULL DEFAULT NULL COMMENT 'NULL = إلى نهايته',
  `state` ENUM('planned','active','paused','closed') NOT NULL DEFAULT 'active',
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'النطاقُ الرئيسيُّ للعقد — واحدٌ على الأكثر',
  `primary_flag` TINYINT(1) GENERATED ALWAYS AS (IF(`is_primary` = 1, 1, NULL)) STORED
      COMMENT 'حيلةُ الفريد: NULL لغير الرئيسي — وMySQL لا تقيّد الـNULLات، فينتج «رئيسٌ واحدٌ على الأكثر»',
  `close_reason` VARCHAR(255) NULL DEFAULT NULL,
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `deleted_by` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cos_contract_site` (`company_id`, `contract_id`, `site_id`)
      COMMENT 'الموقعُ مرةً واحدةً في العقد — فلا نطاقان لموقعٍ واحد',
  UNIQUE KEY `uq_cos_primary` (`contract_id`, `primary_flag`)
      COMMENT '«رئيسٌ واحدٌ على الأكثر» بنيويًّا',
  KEY `ix_cos_lookup` (`company_id`, `contract_id`, `state`),
  KEY `ix_cos_site` (`company_id`, `site_id`),
  CONSTRAINT `fk_cos_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  CONSTRAINT `ck_cos_span` CHECK (
      `start_date` IS NULL OR `end_date` IS NULL OR `end_date` >= `start_date`),
  CONSTRAINT `ck_cos_name` CHECK (`scope_name` <> ''),
  CONSTRAINT `ck_cos_closed` CHECK (
      `state` <> 'closed' OR (`close_reason` IS NOT NULL AND `close_reason` <> ''))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PLAN-03 §2.1 — نطاقُ العقد التشغيلي: الموقعُ داخل العقد باسمه ومدته';

-- ── ② نقلُ `contracts.site_id` — تعبئةٌ رجعيةٌ من الواقع لا من تخمين ────────
-- لكل عقدٍ له موقعٌ يُنشأ نطاقٌ **رئيسيٌّ** باسم موقعه ومدةِ عقده. والعطالةُ
-- بـ`NOT EXISTS` فإعادةُ التشغيل لا تُنشئ ثانيًا.
-- ⚠ سببُ الإقفال يُكتب **في الإدراج نفسِه**: `ck_cos_closed` يقع لحظةَ الإدراج،
-- فتعبئتُه بـUPDATE لاحقٍ تصل بعد فوات الأوان (مقيسٌ: القيدُ رفض الدفعةَ كلَّها).
INSERT INTO `contract_operational_sites`
    (`company_id`, `contract_id`, `site_id`, `scope_name`, `start_date`, `end_date`,
     `state`, `is_primary`, `close_reason`, `note`, `created_at`)
SELECT c.`company_id`, c.`id`, c.`site_id`,
       COALESCE(NULLIF(s.`name`, ''), CONCAT('نطاق العقد ', c.`id`)),
       c.`actual_start`, c.`actual_end`,
       CASE WHEN c.`contract_status` IN ('منتهٍ','مقفل','مصفّى') THEN 'closed' ELSE 'active' END,
       1,
       CASE WHEN c.`contract_status` IN ('منتهٍ','مقفل','مصفّى')
            THEN CONCAT('العقدُ «', c.`contract_status`, '» عند النقل — الحالةُ مصرَّحةٌ لا مفترَضة')
            ELSE NULL END,
       'نُقل من `contracts.site_id` عند P-01 — تعبئةٌ رجعيةٌ بمرجعها لا بتخمين',
       NOW()
  FROM `contracts` c
  JOIN `sites` s ON s.`id` = c.`site_id`
 WHERE c.`site_id` IS NOT NULL
   AND COALESCE(c.`is_deleted`, 0) = 0
   AND NOT EXISTS (
       SELECT 1 FROM (SELECT * FROM `contract_operational_sites`) x
        WHERE x.`contract_id` = c.`id` AND x.`site_id` = c.`site_id`);

-- ── ③ وسمُ العمود الموروث — «جمِّد للقراءة» لا «احذف» ──────────────────────
ALTER TABLE `contracts`
  MODIFY COLUMN `site_id` INT NULL DEFAULT NULL
  COMMENT '⚠ مرآةٌ موروثةٌ (P-01) — المصدرُ `contract_operational_sites`. لا يُكتب ولا يُقرأ في حسابٍ جديد، ويبقى لأن الحذفَ ممنوع (§0-④)';

-- ── ④ «صفرُ عقدٍ بلا مشروع» بنيويًّا ───────────────────────────────────────
-- آمنٌ بالقياس: صفرُ صفٍّ فيه `project_id` NULL أو صفر.
ALTER TABLE `contracts`
  MODIFY COLUMN `project_id` INT NOT NULL
  COMMENT 'PLAN-03 §2.1: لا عقدَ بلا مشروع — بنيويًّا لا رجاءً';

-- ── ⑤ VIEW `client_contracts` (الملحق §2-①) ────────────────────────────────
-- «لا جدولين لغرضٍ واحد» يُستوفى **بالخريطة والVIEW** لا بالتسمية القسرية:
-- إعادةُ تسمية `contracts` تمسّ عشرات الملفات بلا مكسبٍ وظيفي.
CREATE OR REPLACE VIEW `client_contracts` AS
SELECT c.*, cos.`id` AS `primary_scope_id`, cos.`site_id` AS `primary_site_id`,
       cos.`scope_name` AS `primary_scope_name`
  FROM `contracts` c
  LEFT JOIN `contract_operational_sites` cos
         ON cos.`contract_id` = c.`id` AND cos.`is_primary` = 1
        AND COALESCE(cos.`is_deleted`, 0) = 0;

-- ── ⑥ تسجيلُ شاشة «نطاقات العقد التشغيلية» — الوحدة 172 ────────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 172, 'نطاقات العقد التشغيلية', 'Contracts/contract_sites.php', 12, 0, 0, 'fa fa-map-location-dot', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Contracts/contract_sites.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 172, 1, r.a, r.e, 0
  FROM (SELECT 12 AS rid, 1 AS a, 1 AS e
        UNION ALL SELECT 19, 0, 0
        UNION ALL SELECT 6, 0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 172);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 172, 'نطاقات العقد التشغيلية', 'Contracts/contract_sites.php',
       'fa fa-map-location-dot', 71, NULL, 'Contracts/contract_sites.php', 1
  FROM (SELECT 12 AS rid UNION ALL SELECT 19 UNION ALL SELECT 6) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Contracts/contract_sites.php');
