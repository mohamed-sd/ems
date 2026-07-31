-- ═══════════════════════════════════════════════════════════════════════════
-- P-03 · الجدولُ الشهريُّ للعقد بنسخه — 2026-08-01
-- البطاقة: docs/specs/P-03_contract_monthly_plan.md
-- المصدر: الملحق §3-P-03: «**الجدولُ الشهري** `contract_monthly_plan` بنسخه
--         (`plan_version` + `effective_from`) و**قيدِ Σ الكميات = المتعاقَد**» ·
--         §1: «الجدولُ الشهريُّ مفقود — **صحيحٌ حرفيًّا**. القائم كميةٌ واحدة +
--         دورية، **لا يستوعب شهرَ تعبئةٍ جزئيةٍ ولا توقفًا موسميًّا**» ·
--         §4 القبول: «جدولٌ شهريٌّ لعقدٍ فيه **شهرُ تعبئةٍ وشهرُ توقف**:
--         **القيمةُ السنوية صحيحة**».
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء: `contract_commitments` يحمل **كميةً واحدةً + دورية**
-- (`period` = daily/monthly/contract) — فـ«20,000 طنٍّ على مدى العقد» تُقسَّم
-- **بالتساوي ضمنًا** أو لا تُقسَّم أصلًا. ولا موضعَ يقول «آذارُ تعبئةٌ بـ500
-- وتموزُ توقفٌ بصفر» — وهو واقعُ كل عقدٍ موسمي.
--
-- ⚠ گوتشا مثبَتة: `CHECK` لا يرى صفوفًا أخرى. فقيدُ «Σ الأشهر ≤ المتعاقَد»
-- **يُحمَل على البند** (`qty_planned_total`) ويُحرَس بـ`CHECK`، والكتابةُ
-- **معاملةٌ واحدة** — نمطُ `op_containers.allocated_qty` و`rfq_lines.qty_awarded`.
-- و**المساواةُ التامة** (Σ = المتعاقَد) شرطُ **الختم** لا شرطُ الإدراج.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① عدّادُ المخطَّط على بند البيع ────────────────────────────────────────
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'client_contract_lines'
                  AND COLUMN_NAME = 'qty_planned_total'),
    'ALTER TABLE `client_contract_lines`
       ADD COLUMN `qty_planned_total` DECIMAL(16,2) NOT NULL DEFAULT 0
           COMMENT ''Σ أشهر النسخة النافذة — يُحرَس بـCHECK فلا يتجاوز المتعاقَد'' AFTER `qty_contracted`,
       ADD COLUMN `plan_sealed_version` INT NULL DEFAULT NULL
           COMMENT ''رقمُ النسخة المختومة — والختمُ يشترط Σ = المتعاقَد بالضبط'' AFTER `qty_planned_total`,
       ADD CONSTRAINT `ck_ccl_planned` CHECK (
           `qty_planned_total` >= 0 AND `qty_planned_total` <= `qty_contracted`)',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ── ② الجدولُ الشهري بنسخه ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `contract_monthly_plan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `contract_id` INT NOT NULL,
  `line_id` INT UNSIGNED NOT NULL COMMENT 'بندُ البيع (P-02) — والجدولُ يقسّم كميتَه',
  `plan_version` INT NOT NULL DEFAULT 1 COMMENT 'نسخةُ الخطة — والملحقُ يفتح نسخةً لا يعدّل',
  `effective_from` DATE NOT NULL COMMENT 'سريانُ النسخة — «أثرُ ما قبله بالنسخة السابقة»',
  `period_month` VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
  `qty_planned` DECIMAL(16,2) NOT NULL COMMENT 'كميةُ الشهر — **وصفرٌ يعني توقفًا معلَنًا لا غيابَ بيان**',
  `month_kind` ENUM('normal','mobilization','ramp_up','shutdown','maintenance')
      NOT NULL DEFAULT 'normal' COMMENT 'طبيعةُ الشهر — «شهرُ تعبئةٍ وشهرُ توقف» بأسمائهما',
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cmp_month` (`line_id`, `plan_version`, `period_month`)
      COMMENT 'شهرٌ واحدٌ لكل (بند × نسخة) — لا تكديسَ ولا ازدواج',
  KEY `ix_cmp_lookup` (`company_id`, `contract_id`, `plan_version`, `period_month`),
  CONSTRAINT `fk_cmp_line` FOREIGN KEY (`line_id`)
      REFERENCES `client_contract_lines` (`id`) ON DELETE CASCADE,
  -- **صفرٌ مسموحٌ عمدًا**: «شهرُ توقف» كميتُه صفر — والسالبُ لا معنى له
  CONSTRAINT `ck_cmp_qty` CHECK (`qty_planned` >= 0),
  CONSTRAINT `ck_cmp_month_fmt` CHECK (`period_month` REGEXP '^[0-9]{4}-[0-9]{2}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PLAN-03 §2 — الجدولُ الشهريُّ لبند البيع بنسخه: شهرُ تعبئةٍ وشهرُ توقف';

-- ── ③ تسجيلُ شاشة «الجدول الشهري للعقد» — الوحدة 174 ───────────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 174, 'الجدول الشهري للعقد', 'Contracts/contract_monthly_plan.php', 12, 0, 0, 'fa fa-calendar-days', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Contracts/contract_monthly_plan.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 174, 1, r.a, r.e, 0
  FROM (SELECT 12 AS rid, 1 AS a, 1 AS e
        UNION ALL SELECT 19, 0, 0
        UNION ALL SELECT 6, 0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 174);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 174, 'الجدول الشهري للعقد', 'Contracts/contract_monthly_plan.php',
       'fa fa-calendar-days', 73, NULL, 'Contracts/contract_monthly_plan.php', 1
  FROM (SELECT 12 AS rid UNION ALL SELECT 19 UNION ALL SELECT 6) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Contracts/contract_monthly_plan.php');
