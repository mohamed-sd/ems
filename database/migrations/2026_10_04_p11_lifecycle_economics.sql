-- ═══════════════════════════════════════════════════════════════════════════
-- P-11 · اقتصادُ دورة الحياة — الحالاتُ الثماني بأثرها — 2026-08-01
-- البطاقة: docs/specs/P-11_lifecycle_economics.md
-- المصدر: الملحق §3-`P-11`: «**اقتصادُ دورة الحياة** — الحالاتُ الثماني (§6)
--         بأثرها على **المقدم والضمان والمنفَّذ غير المفوتر والغرامات
--         والحاويات**» · PLAN-03 §6 الجدولُ الحاكم · §6.1 القواعدُ الملزمةُ
--         الخمس · §9-⑥ و§9-⑦.
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء: `contracts` تحمل `termination_type` و`termination_reason`
--   و`pause_*` — وهي **حالاتُ العلاقة** (`H-02`). و**لا موضعَ يحمل أثرَها
--   المالي**: فإنهاءٌ «بخطأ العميل» وإنهاءٌ «بخطئنا» **يُسجَّلان نصًّا واحدَ
--   الشكل**، مع أن أثرَهما على المقدم والضمان والمنفَّذ **متعاكس**:
--     · بخطأ العميل: المقدمُ **يُرد كاملًا** والضمانُ **يُرد** والمنفَّذُ
--       **يُفوتر كاملًا** — **والشركةُ تُطالِب بتعويض**.
--     · بخطئنا: المقدمُ **يُرد بعد خصم ما استُحق** والضمانُ **قد يُصادر**
--       والمنفَّذُ **المقبولُ فقط** — **وغراماتُ الإخلال تُحتسب علينا**.
--   والخلطُ بينهما **يقلب اتجاهَ المال**.
--
-- ⚠ **والأثرُ محكومٌ بالحالة لا يُختار**: خمسةُ أعمدةٍ تحمل الآثارَ الخمسة،
--   و`CHECK` واحدٌ بثمانية فروعٍ **يمنع أيَّ تركيبةٍ خارج جدول §6**. فمن أراد
--   أثرًا مخالفًا **يلزمه تغييرُ الحالة** لا تعديلُ الأثر.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `contract_lifecycle_events` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `contract_id` INT NOT NULL,

  -- ── الحالاتُ الثماني — §6 بأسمائها ────────────────────────────────────────
  `state` ENUM('extension','renewal','suspension','natural_end',
               'client_fault_end','our_fault_end','pre_start_cancel','dispute') NOT NULL,

  -- §6.1-⑤: «**لا شيءَ من هذا بأثرٍ رجعي** — لكلِّ حالةٍ **تاريخُ أثرٍ** محدد،
  --          وما قبله بحكمه القديم وما بعده بالجديد».
  `effect_date` DATE NOT NULL COMMENT 'تاريخُ الأثر — وما قبله بحكمه القديم',
  `decision_ref` VARCHAR(120) NULL DEFAULT NULL COMMENT 'مرجعُ القرار — إلزاميٌّ للإنهاء والإلغاء',

  -- ── الآثارُ الخمسةُ **مشتقّةً من الحالة** (جدولُ §6 حرفيًّا) ───────────────
  `advance_effect` ENUM('continue','settle_and_new','pause_recovery','consume_then_refund',
                        'refund_all_after_offset','refund_after_dues','refund_full','freeze') NOT NULL,
  `retention_effect` ENUM('hold','release_after_grace','release','may_forfeit') NOT NULL,
  `unbilled_effect` ENUM('bill_cycle','final_claim_old','bill_before_pause','final_claim',
                         'bill_all','bill_accepted_only','none','freeze_disputed_bill_rest') NOT NULL,
  `penalty_effect` ENUM('continue','close_old_start_new','pause_time_not_performance',
                        'accrue_to_effect_date','company_claims_compensation',
                        'breach_penalties_capped','mobilization_cost_if_article',
                        'suspend_until_resolution') NOT NULL,
  `container_effect` ENUM('extend','new_tree','suspend','close_readonly','close_with_ref',
                          'close','cancel') NOT NULL,

  -- §6.1-④: «**كلُّ خصمٍ عند الإلغاء بنصٍّ تعاقدي** — لا تُخصم «خسائرُ» ولا
  --          «تكاليفُ تعبئةٍ» إلا **بمادةٍ في العقد وحسابٍ موثَّقٍ بمستنداته**».
  `claim_amount` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'تعويضٌ أو غرامةٌ — موجبٌ لنا وسالبٌ علينا',
  `claim_currency` VARCHAR(8) NULL DEFAULT NULL,
  `contract_article` VARCHAR(200) NULL DEFAULT NULL COMMENT 'مادةُ العقد الحاكمة — **إلزاميةٌ مع أيِّ مبلغ**',
  `claim_doc_ref` VARCHAR(120) NULL DEFAULT NULL COMMENT 'مستندُ الحساب الموثَّق',

  `note` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `deleted_by` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  -- **العطالة**: واقعةٌ واحدةٌ لكل (عقد × حالة × تاريخِ أثر)
  UNIQUE KEY `uq_cle_event` (`contract_id`, `state`, `effect_date`),
  KEY `ix_cle_lookup` (`company_id`, `state`, `effect_date`),

  -- ① **الأثرُ محكومٌ بالحالة** — جدولُ §6 مسطورًا في القيد نفسِه.
  --    فلا تركيبةَ خارجَه، **ومن أراد أثرًا مخالفًا يلزمه تغييرُ الحالة**.
  CONSTRAINT `ck_cle_effects` CHECK (
    (`state` = 'extension'        AND `advance_effect` = 'continue'
                                  AND `retention_effect` = 'hold'
                                  AND `unbilled_effect` = 'bill_cycle'
                                  AND `penalty_effect` = 'continue'
                                  AND `container_effect` = 'extend') OR
    (`state` = 'renewal'          AND `advance_effect` = 'settle_and_new'
                                  AND `retention_effect` = 'release_after_grace'
                                  AND `unbilled_effect` = 'final_claim_old'
                                  AND `penalty_effect` = 'close_old_start_new'
                                  AND `container_effect` = 'new_tree') OR
    (`state` = 'suspension'       AND `advance_effect` = 'pause_recovery'
                                  AND `retention_effect` = 'hold'
                                  AND `unbilled_effect` = 'bill_before_pause'
                                  AND `penalty_effect` = 'pause_time_not_performance'
                                  AND `container_effect` = 'suspend') OR
    (`state` = 'natural_end'      AND `advance_effect` = 'consume_then_refund'
                                  AND `retention_effect` = 'release_after_grace'
                                  AND `unbilled_effect` = 'final_claim'
                                  AND `penalty_effect` = 'accrue_to_effect_date'
                                  AND `container_effect` = 'close_readonly') OR
    (`state` = 'client_fault_end' AND `advance_effect` = 'refund_all_after_offset'
                                  AND `retention_effect` = 'release'
                                  AND `unbilled_effect` = 'bill_all'
                                  AND `penalty_effect` = 'company_claims_compensation'
                                  AND `container_effect` = 'close_with_ref') OR
    (`state` = 'our_fault_end'    AND `advance_effect` = 'refund_after_dues'
                                  AND `retention_effect` = 'may_forfeit'
                                  AND `unbilled_effect` = 'bill_accepted_only'
                                  AND `penalty_effect` = 'breach_penalties_capped'
                                  AND `container_effect` = 'close') OR
    (`state` = 'pre_start_cancel' AND `advance_effect` = 'refund_full'
                                  AND `retention_effect` = 'release'
                                  AND `unbilled_effect` = 'none'
                                  AND `penalty_effect` = 'mobilization_cost_if_article'
                                  AND `container_effect` = 'cancel') OR
    (`state` = 'dispute'          AND `advance_effect` = 'freeze'
                                  AND `retention_effect` = 'hold'
                                  AND `unbilled_effect` = 'freeze_disputed_bill_rest'
                                  AND `penalty_effect` = 'suspend_until_resolution'
                                  AND `container_effect` = 'suspend')),

  -- ② **ولا مبلغَ بلا مادةٍ ومستند** (§6.1-④) — «وإلا فهي مطالبةٌ تفاوضيةٌ
  --    لا خصمٌ نظامي»
  CONSTRAINT `ck_cle_claim_article` CHECK (
      `claim_amount` IS NULL OR
      (`contract_article` IS NOT NULL AND `claim_doc_ref` IS NOT NULL
       AND `claim_currency` IS NOT NULL AND `claim_amount` <> 0)),

  -- ③ **والإنهاءُ والإلغاءُ بمرجع قرار** — ولا يخرج عقدٌ صامتًا
  CONSTRAINT `ck_cle_decision` CHECK (
      `state` NOT IN ('natural_end','client_fault_end','our_fault_end','pre_start_cancel')
      OR `decision_ref` IS NOT NULL),

  -- ④ **وإلغاءُ شجرة الحاويات لا يقع إلا قبل البدء** — «تُلغى ولا تُقفل
  --    (لم تُستهلك)»؛ وإلغاءُ ما استُهلك محوٌ للتاريخ
  CONSTRAINT `ck_cle_cancel_tree` CHECK (
      `container_effect` <> 'cancel' OR `state` = 'pre_start_cancel')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PLAN-03 §6 — اقتصادُ دورة الحياة: الأثرُ محكومٌ بالحالة لا يُختار';

-- ── تسجيلُ شاشة «اقتصاد دورة حياة العقد» — الوحدة 180 ──────────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 180, 'اقتصاد دورة حياة العقد', 'Contracts/contract_lifecycle.php', 12, 0, 0, 'fa fa-scale-unbalanced', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Contracts/contract_lifecycle.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 180, 1, r.a, r.e, 0
  FROM (SELECT 12 AS rid, 1 AS a, 1 AS e
        UNION ALL SELECT 17, 1, 1
        UNION ALL SELECT 19, 0, 0
        UNION ALL SELECT 20, 0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 180);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 180, 'اقتصاد دورة حياة العقد', 'Contracts/contract_lifecycle.php',
       'fa fa-scale-unbalanced', 79, NULL, 'Contracts/contract_lifecycle.php', 1
  FROM (SELECT 12 AS rid UNION ALL SELECT 17 UNION ALL SELECT 19 UNION ALL SELECT 20) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Contracts/contract_lifecycle.php');
