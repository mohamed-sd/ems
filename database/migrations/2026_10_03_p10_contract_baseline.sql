-- ═══════════════════════════════════════════════════════════════════════════
-- P-10 · دورةُ حالة خط الأساس وبوابةُ الفوترة — 2026-08-01
-- البطاقة: docs/specs/P-10_contract_baseline.md
-- المصدر: الملحق §3-`P-10`: «**دورةُ حالة خط الأساس**
--         (Draft→Reviewed→Approved→Locked→Amended→Superseded) + علَمُ
--         `EMS_BASELINE_GATE` **يمنع الفوترة قبل القفل**» ·
--         PLAN-03 §3.6: «عند الاعتماد **تُقفل كلُّ المكوّنات** — **ومن هنا فقط
--         تبدأ الفوترة**» · §9-⑱: «فوترةٌ قبل قفل خط الأساس: **تُرفض**».
-- ───────────────────────────────────────────────────────────────────────────
-- ⚠ **والملحق §2-② مُلزِمٌ نصًّا**: القاعدةُ **تسري على الجديد لا على القائم**.
--   العقودُ العشرةُ القائمةُ **تُفوتر كما هي**، والعلَمُ **يبدأ مطفأً**، ثم
--   يُفعَّل **لعقدٍ رائدٍ واحد** بعد اكتمال خط أساسه، ثم يُعمَّم.
--   **ولا يُقلب على الجميع دفعةً واحدة — فخُّ `E-08` نفسُه.**
--
-- المقيسُ قبل البناء: **لا جدولَ لخط الأساس ولا حالةَ له**. و«الاعتماد» اليوم
--   **حالةُ العقد** (`contracts.contract_status` · H-02) — وهي **حالةُ العلاقة
--   التجارية** لا **حالةُ اكتمال المكوّنات**. فعقدٌ «نافذ» قد لا يكون له بندُ
--   بيعٍ واحد، **ويُفوتر**.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `contract_baseline` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `contract_id` INT NOT NULL,
  `version` INT NOT NULL DEFAULT 1,

  -- ── الحالاتُ الستّ — بأسمائها لا بأرقامها ────────────────────────────────
  `state` ENUM('draft','reviewed','approved','locked','amended','superseded')
      NOT NULL DEFAULT 'draft',
  `state_note` VARCHAR(255) NULL DEFAULT NULL,

  `reviewed_by` INT UNSIGNED NULL DEFAULT NULL,
  `reviewed_at` DATETIME NULL DEFAULT NULL,
  `approved_by` INT UNSIGNED NULL DEFAULT NULL,
  `approved_at` DATETIME NULL DEFAULT NULL,
  `locked_by` INT UNSIGNED NULL DEFAULT NULL,
  `locked_at` DATETIME NULL DEFAULT NULL,

  -- ── **القفلُ يُثبّت ما قُفل**: عدّاتُ المكوّنات وبصمتُها لحظةَ القفل ───────
  `comp_lines` INT NOT NULL DEFAULT 0,
  `comp_plan_months` INT NOT NULL DEFAULT 0,
  `comp_plan_sealed` INT NOT NULL DEFAULT 0,
  `comp_resource_rows` INT NOT NULL DEFAULT 0,
  `comp_payment_rows` INT NOT NULL DEFAULT 0,
  `comp_sites` INT NOT NULL DEFAULT 0,
  `fingerprint` CHAR(40) NULL DEFAULT NULL
      COMMENT 'sha1 لحالة المكوّنات وقتَ القفل — **فيُعرف إن تغيّر شيءٌ بعده**',

  `amendment_id` INT NULL DEFAULT NULL,
  `supersedes_baseline_id` INT UNSIGNED NULL DEFAULT NULL,

  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `deleted_by` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cb_version` (`contract_id`, `version`),
  KEY `ix_cb_state` (`company_id`, `state`),

  -- ① **ولا حالةَ متقدّمةٌ بلا فاعلِها ووقتِها** — «من أجاز ومتى» لا يُخمَّن
  CONSTRAINT `ck_cb_actors` CHECK (
      (`state` <> 'reviewed' OR (`reviewed_by` IS NOT NULL AND `reviewed_at` IS NOT NULL)) AND
      (`state` NOT IN ('approved','locked') OR (`approved_by` IS NOT NULL AND `approved_at` IS NOT NULL)) AND
      (`state` <> 'locked' OR (`locked_by` IS NOT NULL AND `locked_at` IS NOT NULL
                               AND `fingerprint` IS NOT NULL))),

  CONSTRAINT `ck_cb_counts` CHECK (
      `comp_lines` >= 0 AND `comp_plan_months` >= 0 AND `comp_plan_sealed` >= 0
      AND `comp_resource_rows` >= 0 AND `comp_payment_rows` >= 0 AND `comp_sites` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PLAN-03 §3.6 — خطُّ الأساس بحالته: ومن القفل فقط تبدأ الفوترة';

-- ── تسجيلُ شاشة «خط أساس العقد» — الوحدة 179 ───────────────────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 179, 'خط أساس العقد', 'Contracts/contract_baseline.php', 12, 0, 0, 'fa fa-lock', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Contracts/contract_baseline.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 179, 1, r.a, r.e, 0
  FROM (SELECT 12 AS rid, 1 AS a, 1 AS e
        UNION ALL SELECT 19, 0, 1
        UNION ALL SELECT 17, 0, 1
        UNION ALL SELECT 20, 0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 179);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 179, 'خط أساس العقد', 'Contracts/contract_baseline.php',
       'fa fa-lock', 78, NULL, 'Contracts/contract_baseline.php', 1
  FROM (SELECT 12 AS rid UNION ALL SELECT 19 UNION ALL SELECT 17 UNION ALL SELECT 20) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Contracts/contract_baseline.php');
