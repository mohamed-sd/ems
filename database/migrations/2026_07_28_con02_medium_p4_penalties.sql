-- ═══════════════════════════════════════════════════════════════════════════
-- CON-02 · المستوى المتوسط — المرحلة ④ : الجزاءاتُ والحوافزُ والحدُّ الأدنى
-- 2026-07-28
-- ───────────────────────────────────────────────────────────────────────────
-- `contract_penalty_rules` (المرحلة ①) يحمل **القاعدة**؛ وهذا الملفُّ يحمل
-- **الواقعة**: احتسابُ القاعدة على فترةٍ بعينها بأرقامها ومسارِ اعتمادها.
-- فصلُهما مقصود: القاعدةُ نصُّ عقدٍ يعيش سنواتٍ، والاحتسابُ حدثٌ شهريٌّ يُراجَع
-- ويُجاز أو يُعفى — وخلطُهما يجعل تعديلَ نسبةٍ يعيد كتابةَ تاريخِ ما فُوتر.
--
-- ═══ القرارات المُجسَّدة ═══
--   **ق-13 الملكية:** النظامُ يحتسب · 12 (المبيعات) يراجع · 19 (المالية) يُجيز
--     **ويملك الإعفاء بسببٍ إلزاميٍّ موثَّق**. وحالاتُ العمود `state` هي هذه
--     الدورةُ حرفًا بحرف: `computed → reviewed → approved → posted` · و`waived`.
--   **ق-7 · ق-8 المسار المالي:** لا بندَ مستخلصٍ معزول. الاحتسابُ المُجاز
--     **ينشر قيدًا عبر `EventPublisher`** (الجذرُ المحايد ثم إسقاطُ الدفتر)،
--     ثم يقرؤه المستخلصُ من `fin_event_links` كما يقرأ الإيرادَ تمامًا. ولذلك
--     `event_id` هنا **نتيجةٌ لا مُدخَل** — يُملأ لحظةَ الإجازة.
--   **ق-11 الدورية:** لا احتسابَ نسبيًّا. الفترةُ الناقصةُ تُترك حتى تكتمل،
--     والغرامةُ تلحق في مستخلص الإغلاق. ولذلك `period_from/to` جزءٌ من مفتاح
--     التفرد: احتسابٌ واحدٌ لكل (قاعدة × فترة) لا احتسابان.
--   **ق-12 السقف:** `base_amount` = قيمةُ البند الملتزَم في الفترة، والسقفُ
--     نسبةٌ منها — فالسقفُ والأساسُ من جنسٍ واحد. ويُخزَّن `raw_amount` قبل
--     السقف و`amount` بعده، فيُرى **أين قُصَّ الرقمُ ولماذا** لا رقمٌ أخيرٌ مبهم.
--   **ق-15 اتجاهٌ واحد:** الغرامةُ حين نكون **نحن** المقصّرين فقط. وتقصيرُ
--     العميل تعالجه مصفوفةُ §4 فيصير استعدادًا مفوترًا — فالمحرّكان لا يتقاطعان.
--   **هـ-6:** قيدُ الحد الأدنى بوحدةٍ **مميزة** `min_guarantee` لا بوحدة الإنتاج
--     (§5 تنهى عن دسّه في كميات الوحدات، و`hour` تفسد قياسَ الإنتاج).
--
-- **صفرُ هجرةٍ لبنود المستخلص**: `claim_lines.source_kind` نوعُه `varchar(24)`
-- لا ENUM — فأنواعُ `penalty · incentive · min_guarantee` تدخل بلا توسيعٍ.
--
-- إضافيٌّ محض: جدولٌ جديدٌ وصفوفُ تسجيلٍ — ولا عمودَ قائمٌ يُمسّ.
-- الرجوع: DROP TABLE contract_penalty_assessments؛ وحذفُ صفوف الوحدة 147.
-- ═══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `contract_penalty_assessments` (
  `id`                 INT NOT NULL AUTO_INCREMENT,
  `company_id`         INT NOT NULL COMMENT 'عزل المستأجر',
  `client_contract_id` INT NOT NULL COMMENT 'عقدُ العميل',
  `rule_id`            INT NULL
                       COMMENT 'قاعدةُ الجزاء المطبَّقة — NULL للحد الأدنى المضمون (مصدرُه contract_commitments لا قاعدةَ جزاء)',
  `commitment_ref`     INT UNSIGNED NULL COMMENT 'البندُ الملتزَمُ المرساة',

  `kind`               ENUM('penalty','incentive','min_guarantee') NOT NULL
                       COMMENT 'غرامةٌ تُخصم · حافزٌ يُضاف · حدٌّ أدنى يُكمَّل — وثلاثتُها بنودٌ ظاهرةٌ لا خصمٌ صامت (§6)',
  `rule_kind`          VARCHAR(24) NULL COMMENT 'لقطةُ نوع القاعدة وقتَ الاحتساب — للتدقيق بعد تغيّر القاعدة',

  `period_from`        DATE NOT NULL,
  `period_to`          DATE NOT NULL,
  `periodicity`        ENUM('daily','monthly','contract') NOT NULL DEFAULT 'monthly',

  `committed_qty`      DECIMAL(18,4) NULL COMMENT 'الكميةُ الملتزمُ بها في الفترة',
  `actual_qty`         DECIMAL(18,4) NULL COMMENT 'المنفَّذُ فعلًا (من قيود الإيراد لا من تقديرٍ)',
  `gap_qty`            DECIMAL(18,4) NULL COMMENT 'الفارق — موجبٌ عجزًا وسالبٌ تجاوزًا',
  `readiness_pct`      DECIMAL(6,2) NULL COMMENT 'ساعاتُ العمل ÷ ساعاتِ الوردية — لـreadiness_min',

  `unit_price`         DECIMAL(18,4) NULL COMMENT 'سعرُ الوحدة المستعمل — لقطةٌ لا اشتقاقٌ لاحق',
  `base_amount`        DECIMAL(18,2) NOT NULL DEFAULT 0.00
                       COMMENT 'قيمةُ البند الملتزَم في الفترة (ق-12) — أساسُ السقف',
  `raw_amount`         DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'المبلغُ قبل السقف',
  `cap_amount`         DECIMAL(18,2) NULL COMMENT 'السقفُ المطبَّق — NULL أي بلا سقف',
  `amount`             DECIMAL(18,2) NOT NULL DEFAULT 0.00
                       COMMENT 'المبلغُ النهائي (موجبٌ دائمًا) — والاتجاهُ من kind لا من الإشارة',
  `currency`           VARCHAR(8) NOT NULL DEFAULT 'USD',

  `state`              ENUM('computed','reviewed','approved','waived','posted') NOT NULL DEFAULT 'computed'
                       COMMENT 'دورةُ ق-13: النظامُ يحتسب · 12 يراجع · 19 يُجيز أو يُعفي · ثم يُنشر القيد',
  `waive_reason`       VARCHAR(255) NULL COMMENT 'سببُ الإعفاء — **إلزاميٌّ** عند waived (تفرضه الخدمة)',
  `note`               VARCHAR(255) NULL COMMENT 'المعيارُ اليدويُّ لـbonus_fixed (الجودةُ والسلامة · ق-10)',

  `event_id`           INT NULL
                       COMMENT 'قيدُ الدفتر المولَّد عند الإجازة — **نتيجةٌ لا مُدخَل** (ق-7)',
  `reviewed_by`        INT NULL, `reviewed_at` DATETIME NULL,
  `approved_by`        INT NULL, `approved_at` DATETIME NULL,

  `is_deleted`         TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at`         DATETIME NULL,
  `deleted_by`         INT NULL,
  `created_by`         INT NULL,
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  -- بصمةُ التفرد بقيمٍ حارسةٍ بديلةٍ عن NULL — نفسُ درس `uq_policy_rule`:
  -- `rule_id` فارغٌ مشروعٌ (الحدُّ الأدنى بلا قاعدةِ جزاء)، فلولا الحارسُ لمرّ
  -- احتسابان متطابقان للفترة نفسِها صامتين ولفوتِرَ الحدُّ الأدنى مرتين.
  `rule_key`           VARCHAR(24) GENERATED ALWAYS AS
                       (CONCAT(IFNULL(CAST(`rule_id` AS CHAR), '*'), ':',
                               IFNULL(CAST(`commitment_ref` AS CHAR), '*'))) STORED
                       COMMENT 'مرساةُ الاحتساب للمفتاح الفريد — * أي بلا قاعدةٍ/بند',

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_assessment_period`
      (`client_contract_id`, `kind`, `rule_key`, `period_from`, `period_to`)
      COMMENT 'احتسابٌ واحدٌ لكل (عقد × نوع × مرساة × فترة) — إعادةُ التشغيل تُحدّث ولا تُضاعف (ق-11)',
  KEY `ix_assessment_scope` (`company_id`, `is_deleted`),
  KEY `ix_assessment_state` (`state`),
  KEY `ix_assessment_period` (`client_contract_id`, `period_from`, `period_to`),
  CONSTRAINT `fk_assessment_contract`
      FOREIGN KEY (`client_contract_id`) REFERENCES `contracts` (`id`)
      ON DELETE RESTRICT ON UPDATE CASCADE,
  -- ⚠️ RESTRICT لا SET NULL: `rule_id` ليس أساسًا لعمودٍ محسوبٍ STORED هنا…
  --    بل هو كذلك (`rule_key`)، فيمنع الخادمُ SET NULL/CASCADE — نفسُ القيد
  --    البنيويِّ المقيس في المرحلة ①. وRESTRICT أصحُّ دلالةً على كلٍّ: قاعدةٌ
  --    احتُسب عليها مالٌ لا تُحذف حذفًا صلبًا من تحت احتسابها.
  CONSTRAINT `fk_assessment_rule`
      FOREIGN KEY (`rule_id`) REFERENCES `contract_penalty_rules` (`id`)
      ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='CON-02 §6 — احتسابُ الجزاء والحافز والحد الأدنى لفترةٍ بعينها بدورة اعتماده';


-- ── تسجيلُ شاشة احتساب الجزاءات — الوحدة 147 (ق-17: مستقلةٌ في Contracts/) ──
--    «بجوار `claims.php` — والمالية تدخلها للإجازة بمنحةٍ كما تدخل الموازنة».
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 147, 'احتساب الجزاءات والحوافز', 'Contracts/penalties.php', 12, 0, 0, 'fa fa-gavel', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Contracts/penalties.php');

--    فصلُ اليدين (ق-13): can_add = الاحتسابُ والمراجعة (12) · can_edit = الإجازةُ والإعفاء (19)
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 147, 1, r.a, r.e, 0
  FROM (SELECT 12 AS rid, 1 AS a, 0 AS e   -- المبيعات: تحتسب وتراجع ولا تُجيز
        UNION ALL SELECT 19, 0, 1          -- مدير المالية: يُجيز ويُعفي
        UNION ALL SELECT 17, 0, 0
        UNION ALL SELECT 18, 0, 0
        UNION ALL SELECT 1,  0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 147);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 147, 'احتساب الجزاءات والحوافز', 'Contracts/penalties.php',
       'fa fa-gavel', 48, NULL, 'Contracts/penalties.php', 1
  FROM (SELECT 12 AS rid UNION ALL SELECT 19 UNION ALL SELECT 17
        UNION ALL SELECT 18 UNION ALL SELECT 1) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`module_id` = 147);
