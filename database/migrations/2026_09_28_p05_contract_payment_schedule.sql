-- ═══════════════════════════════════════════════════════════════════════════
-- P-05 · خطةُ الدفع بأنماطها الثمانية وأنواعِ المقدم الأربعة — 2026-08-01
-- البطاقة: docs/specs/P-05_contract_payment_schedule.md
-- المصدر: PLAN-03 §3.5 (نسخةُ الوثيقة صارت في docs/update0001/) ·
--         الملحق §3-`P-05`: «**خطةُ الدفع** `contract_payment_schedule`
--         بأنماطها الثمانية + **أنواعُ المقدم الأربعة** (مقدمٌ مستهلَك ·
--         تعبئةٌ · حجزٌ غيرُ مسترد · معلَم) — **توليدٌ آليٌّ من الرأس والجدول**».
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء:
--   · `contract_payment_schedule` **غيرُ موجود** — ولا جدولَ يقوم مقامَه.
--   · القائمُ يعرف **ما قُبض** (`contract_advances` · M-01) و**ما فُوتر**
--     (`claims` + `fin_receivables`) — **ولا موضعَ يقول «دفعةٌ مستحقةٌ يومَ كذا
--     بمبلغِ كذا»**. فلا توقُّعَ ولا «متأخر»، والتأخرُ يُكتشف بالمصادفة.
--   · وشروطُ السداد في الرأس **نصٌّ حرّ**: `contracts.payment_time` VARCHAR(50)
--     و`payment_date` **تاريخٌ واحدٌ للعقد كلِّه** — لا جدولَ فيه.
--   · و`contract_advances` **بلا نوع**: فالمقدمُ المستهلَك ورسومُ التعبئة
--     ورسومُ الحجز غيرِ المستردة ودفعةُ المعلَم تسكن **صفًّا واحدَ الشكل** —
--     و PLAN-03 §6: «**الخلطُ بينها يقلب التزامًا إلى إيرادٍ أو العكس**».
--
-- ⚠ ولا يُفرَض «Σ الخطة = قيمةُ العقد»: المقدمُ **يُستهلك من المستخلصات**
--   فلا يُجمع معها — وقيدٌ كهذا **يكذب** على كل عقدٍ فيه مقدَّم.
--   والقيدُ الصادقُ صفٌّ صفًّا: المقبوضُ لا يتجاوز المتوقَّع، والنسبةُ في [0,100].
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `contract_payment_schedule` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `contract_id` INT NOT NULL,

  -- ── الإصدارات: «كأيِّ مكوّنٍ في خط الأساس» (PLAN-03 §3.5 · §3.7) ──────────
  `version` INT NOT NULL DEFAULT 1,
  `effective_from` DATE NOT NULL,
  `effective_to` DATE NULL DEFAULT NULL COMMENT 'NULL = النسخةُ النافذة · والقديمةُ تُختم ولا تُمحى',
  `amendment_id` INT NULL DEFAULT NULL COMMENT 'الملحقُ الذي فتح النسخة',

  `seq` INT NOT NULL COMMENT 'ترتيبُ السطر داخل النسخة',

  -- ── الأنماطُ الثمانية (PLAN-03 §3.5) — نمطُ الخطة التي وُلد منها السطر ────
  `pattern` ENUM('single_payment','advance_then_monthly','partial_advance',
                 'advance_installments','milestone_payments','monthly_claim',
                 'final_payment','retention_release') NOT NULL DEFAULT 'monthly_claim',

  -- ── نوعُ الدفعة — أولُ حقول السطر في §3.5 ────────────────────────────────
  `payment_kind` ENUM('advance','monthly_settlement','milestone','final',
                      'retention_release','single') NOT NULL,

  -- ── أنواعُ المقدم الأربعة (PLAN-03 §3.1) — **لا تُخلط** ───────────────────
  --    ① مقدمٌ على حساب المستخلصات: دَينٌ يُستهلك أو يُرد
  --    ② رسومُ تعبئة: **قد تكون إيرادًا لا دَينًا — بحسب نص العقد**
  --    ③ رسومُ حجزٍ غيرُ قابلةٍ للرد: إيرادٌ عند الاستحقاق
  --    ④ دفعةُ معلَمٍ مكتمل: إيرادٌ لا مقدم
  `advance_type` ENUM('recoverable','mobilization','non_refundable_booking',
                      'milestone_earned') NULL DEFAULT NULL,
  `treatment` ENUM('liability','revenue') NULL DEFAULT NULL
      COMMENT 'المعالجةُ المحاسبية — محكومةٌ بالنوع إلا في التعبئة فبنص العقد',
  `treatment_basis` VARCHAR(255) NULL DEFAULT NULL
      COMMENT 'نصُّ العقد الذي حكم معالجةَ التعبئة — إلزاميٌّ لها وحدَها',

  -- ── النسبةُ أو المبلغ · وتاريخُ أو شرطُ الاستحقاق ─────────────────────────
  `amount_basis` ENUM('percent','fixed') NOT NULL DEFAULT 'fixed',
  `percent_value` DECIMAL(7,3) NULL DEFAULT NULL,
  `amount_expected` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `currency` VARCHAR(8) NOT NULL,
  `due_date` DATE NULL DEFAULT NULL,
  `due_condition` VARCHAR(200) NULL DEFAULT NULL COMMENT 'شرطُ الاستحقاق حين لا تاريخَ ثابت',
  `period_month` VARCHAR(7) NULL DEFAULT NULL COMMENT 'شهرُ الجدول (P-03) الذي وُلد منه السطر',
  `line_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'بندُ البيع إن كان السطرُ لبندٍ بعينه',

  -- ── المستلم والمتبقي والحال ومرجعُ التحصيل ───────────────────────────────
  `received_amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `remaining_amount` DECIMAL(18,2) GENERATED ALWAYS AS
      (`amount_expected` - `received_amount`) STORED,
  `state` ENUM('not_due','due','partial','completed','overdue') NOT NULL DEFAULT 'not_due',
  `collection_ref` VARCHAR(120) NULL DEFAULT NULL,
  `advance_id` INT UNSIGNED NULL DEFAULT NULL
      COMMENT 'صفُّ القبض في contract_advances (M-01) — **للالتزام وحدَه**',

  `source` ENUM('generated','manual') NOT NULL DEFAULT 'generated'
      COMMENT '«تُولَّد آليًّا … ولا تُدخل كلُّها يدويًّا»',
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `deleted_by` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cps_seq` (`contract_id`, `version`, `seq`),
  KEY `ix_cps_lookup` (`company_id`, `contract_id`, `state`, `due_date`),
  KEY `ix_cps_live` (`contract_id`, `effective_to`),

  -- ① **النوعُ إلزاميٌّ للمقدم وممنوعٌ لغيره** — فلا صفَّ مقدمٍ بلا نوع
  CONSTRAINT `ck_cps_advance_type` CHECK (
      (`payment_kind` <> 'advance' OR `advance_type` IS NOT NULL) AND
      (`payment_kind` =  'advance' OR `advance_type` IS NULL)),

  -- ② **والمعالجةُ محكومةٌ بالنوع** — ثلاثةٌ تحسمها المحاسبة وواحدةٌ يحسمها
  --    نصُّ العقد، **وتُعلَن ولا تُفترض**. وهذا هو مانعُ «قلبِ الالتزام إيرادًا».
  CONSTRAINT `ck_cps_treatment` CHECK (
      (`advance_type` IS NULL AND `treatment` IS NULL) OR
      (`advance_type` = 'recoverable'            AND `treatment` = 'liability') OR
      (`advance_type` = 'non_refundable_booking' AND `treatment` = 'revenue')   OR
      (`advance_type` = 'milestone_earned'       AND `treatment` = 'revenue')   OR
      (`advance_type` = 'mobilization'           AND `treatment` IS NOT NULL
                                                 AND `treatment_basis` IS NOT NULL)),

  -- ③ **ولا سطرَ بلا استحقاق**: تاريخٌ أو شرطٌ — والفراغُ ليس خيارًا
  CONSTRAINT `ck_cps_due` CHECK (`due_date` IS NOT NULL OR `due_condition` IS NOT NULL),

  -- ④ **والمقبوضُ لا يتجاوز المتوقَّع** — والزائدُ يُعلَن في قناته لا يُبتلع هنا
  CONSTRAINT `ck_cps_amounts` CHECK (
      `amount_expected` >= 0 AND `received_amount` >= 0
      AND `received_amount` <= `amount_expected`),

  CONSTRAINT `ck_cps_percent` CHECK (
      (`amount_basis` <> 'percent' OR (`percent_value` IS NOT NULL
                                       AND `percent_value` > 0 AND `percent_value` <= 100))
      AND (`percent_value` IS NULL OR (`percent_value` >= 0 AND `percent_value` <= 100))),

  CONSTRAINT `ck_cps_window` CHECK (`effective_to` IS NULL OR `effective_to` >= `effective_from`),

  -- ⑤ **ودفترُ السلف للالتزام وحدَه**: ربطُ سطرٍ إيراديٍّ بـ`contract_advances`
  --    يجعله يُستقطع من مستخلصٍ — وهو الخطأُ الذي تمنعه §6 نصًّا.
  CONSTRAINT `ck_cps_advance_link` CHECK (
      `advance_id` IS NULL OR `treatment` = 'liability'),

  CONSTRAINT `ck_cps_month_fmt` CHECK (
      `period_month` IS NULL OR `period_month` REGEXP '^[0-9]{4}-[0-9]{2}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PLAN-03 §3.5 — خطةُ الدفع بأنماطها الثمانية وأنواعِ المقدم الأربعة';

-- ── تسجيلُ شاشة «خطة دفع العقد» — الوحدة 176 ───────────────────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 176, 'خطة دفع العقد', 'Contracts/contract_payment_schedule.php', 12, 0, 0, 'fa fa-money-check-dollar', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Contracts/contract_payment_schedule.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 176, 1, r.a, r.e, 0
  FROM (SELECT 12 AS rid, 1 AS a, 1 AS e
        UNION ALL SELECT 19, 0, 0
        UNION ALL SELECT 21, 0, 0
        UNION ALL SELECT 18, 0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 176);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 176, 'خطة دفع العقد', 'Contracts/contract_payment_schedule.php',
       'fa fa-money-check-dollar', 75, NULL, 'Contracts/contract_payment_schedule.php', 1
  FROM (SELECT 12 AS rid UNION ALL SELECT 19 UNION ALL SELECT 21 UNION ALL SELECT 18) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Contracts/contract_payment_schedule.php');
