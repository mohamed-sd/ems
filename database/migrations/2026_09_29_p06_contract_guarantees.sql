-- ═══════════════════════════════════════════════════════════════════════════
-- P-06 · فصلُ المحتجَز عن خطاب الضمان — 2026-08-01
-- البطاقة: docs/specs/P-06_contract_guarantees.md
-- المصدر: PLAN-03 §3.1 «المحتجَز والضمانات»: «**فصلٌ إلزامي:** ① محتجزٌ نقديٌّ
--         يُخصم من المستخلص — **أصلٌ لدى العميل** يُتابَع كذمةٍ مؤجَّلة ·
--         ② خطابُ ضمانٍ بنكي — **التزامٌ محتملٌ خارج الميزانية**، لا نقدَ
--         محجوزًا ولا يُخصم من مستخلص · ③ تأمينٌ أو كفالة. **والخلطُ بينها
--         خطأٌ محاسبيٌّ**: الأولُ أصلٌ والثاني التزامٌ محتمل» ·
--         §9-⑯: «خطابُ ضمانٍ بنكي: **لا يُخصم من مستخلصٍ ولا يظهر أصلًا**».
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء:
--   · **لا جدولَ للضمانات في القاعدة كلِّها** — والقائمُ عمودٌ واحد:
--     `contracts.guarantees` **MEDIUMTEXT نثرًا حرًّا**، وفيه حيًّا:
--     «رهن سيارة» (×5) · «تأمين المشروع» (×3) · ونصُّ لوريمَ لا معنى له —
--     **9 عقودٍ من 10**. فرهنٌ وتأمينٌ وخطابُ ضمانٍ **كلامٌ واحدُ الشكل**،
--     ولا يُعرف من نصِّه أهو أصلٌ أم التزامٌ محتمل.
--   · والمحتجَزُ النقديُّ **مبالغُه مسجَّلةٌ** (`claims.retention_amount` وردُّه
--     بـ`claim_lines.source_kind='retention_release'` — CON-02)، لكن **بلا
--     تاريخِ ردٍّ ولا شروطِه**: «متى يُرد؟» **سؤالٌ بلا موضعٍ يجيبه**.
--   · والمفارقةُ المقيسة: **جانبُ الموردين مُهيكَل** بالفعل
--     (`supplier_contracts.performance_guarantee` · `guarantee_retention_days` ·
--     `supplier_contract_closures.guarantee_due_date/released_at`) —
--     **وجانبُ العملاء نثر**.
--
-- ⚠ **ولا جدولَ ثالثٌ لمبالغ المحتجَز**: هي مسجَّلةٌ في مصدرَين قائمَين،
--   وتكرارُها يفتح مصدرًا ثانيًا للرقم الواحد (درسُ M-01 حرفيًّا). فهذا
--   الجدولُ **سجلُّ أدواتٍ وشروطٍ وتواريخِ ردّ** — **والرصيدُ يُقرأ من مصدره**.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `contract_guarantees` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `contract_id` INT NOT NULL,

  -- ── الأداةُ ونوعُها — والنوعُ هو ما يحسم الطبيعة ──────────────────────────
  `kind` ENUM('cash_retention','bank_guarantee','insurance','surety','pledge','other')
      NOT NULL COMMENT 'محتجزٌ نقديّ · خطابُ ضمانٍ بنكي · تأمين · كفالة · رهن · أخرى',

  -- **الطبيعةُ محكومةٌ بالنوع** — والمحتجَزُ النقديُّ وحدَه أصل
  `nature` ENUM('asset','off_balance') NOT NULL DEFAULT 'off_balance'
      COMMENT 'أصلٌ لدى العميل · أو التزامٌ محتملٌ خارج الميزانية',

  -- **ولا يُخصم من مستخلصٍ إلا المحتجَزُ النقدي** — قيدٌ لا سياسة
  `deductible_from_claim` TINYINT(1) NOT NULL DEFAULT 0,

  `amount` DECIMAL(18,2) NOT NULL DEFAULT 0
      COMMENT 'قيمةُ الأداة — وللمحتجَز النقديِّ **سقفٌ متعاقَدٌ عليه لا رصيدٌ**',
  `percent_value` DECIMAL(7,3) NULL DEFAULT NULL COMMENT 'نسبتُه من قيمة العقد إن كان بنسبة',
  `currency` VARCHAR(8) NOT NULL,

  `issuer` VARCHAR(190) NULL DEFAULT NULL COMMENT 'البنكُ المُصدر أو شركةُ التأمين أو الكفيل',
  `instrument_ref` VARCHAR(120) NULL DEFAULT NULL COMMENT 'رقمُ الخطاب/الوثيقة',
  `issue_date` DATE NULL DEFAULT NULL,
  `expiry_date` DATE NULL DEFAULT NULL COMMENT 'انتهاءُ سريان الأداة — إلزاميٌّ لغير المحتجَز',
  `due_release_date` DATE NULL DEFAULT NULL COMMENT 'تاريخُ ردِّ المحتجَز — إلزاميٌّ له',
  `release_condition` VARCHAR(200) NULL DEFAULT NULL,

  `state` ENUM('draft','active','expired','released','called') NOT NULL DEFAULT 'draft',
  `state_reason` VARCHAR(255) NULL DEFAULT NULL,
  `state_at` DATE NULL DEFAULT NULL,

  `source_text` VARCHAR(500) NULL DEFAULT NULL
      COMMENT 'نصُّ `contracts.guarantees` الذي جاءت منه — **والنصُّ لا يُمحى**',
  `needs_review` TINYINT(1) NOT NULL DEFAULT 0
      COMMENT 'صُنّفت آليًّا من نثرٍ فتنتظر إقرارَ المالك',

  `note` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `deleted_by` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `ix_cg_lookup` (`company_id`, `contract_id`, `state`),
  KEY `ix_cg_expiry` (`expiry_date`),

  -- ① **الطبيعةُ محكومةٌ بالنوع** — والخلطُ **خطأٌ محاسبيٌّ** لا اختيارُ مستخدم:
  --    المحتجَزُ النقديُّ **أصلٌ** حتمًا، وخطابُ الضمان والتأمينُ والكفالةُ
  --    والرهنُ **خارجَ الميزانية** حتمًا. و«أخرى» **تُحسَب خارجَ الميزانية**
  --    لأن الافتراضَ الآمن ألّا يصير شيءٌ أصلًا إلا بإعلانٍ صريح.
  CONSTRAINT `ck_cg_nature` CHECK (
      (`kind` = 'cash_retention' AND `nature` = 'asset') OR
      (`kind` <> 'cash_retention' AND `nature` = 'off_balance')),

  -- ② **ولا يُخصم من مستخلصٍ إلا المحتجَزُ النقدي** — §9-⑯ حرفيًّا
  CONSTRAINT `ck_cg_deduct` CHECK (
      `deductible_from_claim` = 0 OR `kind` = 'cash_retention'),

  -- ③ **ولكلِّ أداةٍ تاريخُها**: المحتجَزُ **تاريخُ ردٍّ أو شرطُه**،
  --    وغيرُه **تاريخُ انتهاءِ سريان** — فلا أداةَ بلا أفق
  CONSTRAINT `ck_cg_dates` CHECK (
      (`kind` =  'cash_retention' AND (`due_release_date` IS NOT NULL
                                       OR `release_condition` IS NOT NULL)) OR
      (`kind` <> 'cash_retention' AND `expiry_date` IS NOT NULL)),

  CONSTRAINT `ck_cg_amount` CHECK (`amount` >= 0),
  CONSTRAINT `ck_cg_percent` CHECK (
      `percent_value` IS NULL OR (`percent_value` >= 0 AND `percent_value` <= 100)),
  CONSTRAINT `ck_cg_state_reason` CHECK (
      `state` NOT IN ('released','called','expired') OR `state_reason` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PLAN-03 §3.1 — سجلُّ الضمانات: الأصلُ والالتزامُ المحتمل لا يختلطان';

-- ── ② نقلُ النثر إلى السجل — **والنصُّ الأصليُّ يبقى في مكانه** ─────────────
-- التصنيفُ من **مطابقةٍ حرفيةٍ لنصٍّ قائم** لا من تخمين، و`needs_review = 1`
-- على كل صفٍّ مبذور: **الآلةُ تقترح والمالكُ يُقرّ**. والافتراضُ حين لا تُعرف
-- الأداةُ **خارجَ الميزانية** — فلا يصير شيءٌ أصلًا بالصدفة.
INSERT INTO `contract_guarantees`
    (`company_id`, `contract_id`, `kind`, `nature`, `deductible_from_claim`,
     `amount`, `currency`, `expiry_date`, `state`, `source_text`, `needs_review`, `note`)
SELECT c.`company_id`, c.`id`,
       CASE
         WHEN c.`guarantees` LIKE '%رهن%'   THEN 'pledge'
         WHEN c.`guarantees` LIKE '%تأمين%' THEN 'insurance'
         WHEN c.`guarantees` LIKE '%كفال%'  THEN 'surety'
         WHEN c.`guarantees` LIKE '%خطاب%'  THEN 'bank_guarantee'
         WHEN c.`guarantees` LIKE '%ضمان%'  THEN 'bank_guarantee'
         ELSE 'other'
       END,
       'off_balance', 0, 0,
       COALESCE(NULLIF(c.`price_currency_contract`, ''), 'USD'),
       COALESCE(c.`actual_end`, DATE_ADD(CURDATE(), INTERVAL 1 YEAR)),
       'draft', LEFT(c.`guarantees`, 500), 1,
       'نُقل من نص `contracts.guarantees` — **يُقرّ أو يُصحَّح ولا يُفترض**'
  FROM `contracts` c
 WHERE c.`guarantees` IS NOT NULL AND TRIM(c.`guarantees`) <> ''
   AND COALESCE(c.`is_deleted`, 0) = 0
   AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `contract_guarantees`) g
                    WHERE g.`contract_id` = c.`id` AND g.`source_text` IS NOT NULL);

-- ── ③ والمحتجَزُ النقديُّ لمن نصَّ عقدُه على نسبةٍ ────────────────────────────
-- سقفٌ متعاقَدٌ عليه **لا رصيد** — والرصيدُ يُقرأ من `claims` كما هو.
INSERT INTO `contract_guarantees`
    (`company_id`, `contract_id`, `kind`, `nature`, `deductible_from_claim`,
     `amount`, `percent_value`, `currency`, `release_condition`, `state`, `needs_review`, `note`)
SELECT c.`company_id`, c.`id`, 'cash_retention', 'asset', 1,
       0, c.`retention_pct`,
       COALESCE(NULLIF(c.`price_currency_contract`, ''), 'USD'),
       'بعد انقضاء فترة الضمان وقبولِ الأعمال نهائيًّا — **يُحدَّد تاريخُه**',
       'active', 1,
       'من `contracts.retention_pct` — **والرصيدُ يُقرأ من المستخلصات لا من هنا**'
  FROM `contracts` c
 WHERE COALESCE(c.`retention_pct`, 0) > 0 AND COALESCE(c.`is_deleted`, 0) = 0
   AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM `contract_guarantees`) g
                    WHERE g.`contract_id` = c.`id` AND g.`kind` = 'cash_retention');

-- ── ④ تسجيلُ شاشة «ضمانات العقد» — الوحدة 177 ──────────────────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT 177, 'ضمانات العقد', 'Contracts/contract_guarantees.php', 12, 0, 0, 'fa fa-shield-halved', 0
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `modules`) m WHERE m.`code` = 'Contracts/contract_guarantees.php');

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT r.rid, 177, 1, r.a, r.e, 0
  FROM (SELECT 12 AS rid, 1 AS a, 1 AS e
        UNION ALL SELECT 19, 0, 0
        UNION ALL SELECT 20, 0, 0
        UNION ALL SELECT 21, 0, 0) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
     WHERE rp.`role_id` = r.rid AND rp.`module_id` = 177);

INSERT INTO `nav_items`
    (`role_id`, `door`, `group_id`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `counter_source`, `permission_code`, `active`)
SELECT r.rid, 'REC', NULL, 177, 'ضمانات العقد', 'Contracts/contract_guarantees.php',
       'fa fa-shield-halved', 76, NULL, 'Contracts/contract_guarantees.php', 1
  FROM (SELECT 12 AS rid UNION ALL SELECT 19 UNION ALL SELECT 20 UNION ALL SELECT 21) r
 WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT * FROM `nav_items`) n
     WHERE n.`role_id` = r.rid AND n.`route` = 'Contracts/contract_guarantees.php');
