-- ═══════════════════════════════════════════════════════════════════════════
-- أساسُ العملة: سجلُّ العملات وأسعارُ الصرف بتواريخها — 2026-07-28
-- ───────────────────────────────────────────────────────────────────────────
-- FES-01 §3.1 و§3.3 نصًّا: «ثلاثيةُ العملة — `fx_rate` + `base_amount` وقيدُ
-- `base = round(amount × fx, 2)`»؛ والدفترُ الحيُّ يحمل `currency` وحدها فلا
-- معادلَ موحّدًا لأيِّ مبلغ. وUX-02 §10 يوجب «المعادلَ الموحّد بسعر يومه» عمودًا
-- في مواصفة الجدول القالبية.
--
-- **قرارُ المالك (2026-07-28)**: «النظام كاملًا يعمل بالدولار، وأيُّ عملةٍ جديدة
-- يُضاف لها سعرُ صرفٍ يحسب قيمتَها بالدولار». وعملةُ الأساس **معلَنةٌ سلفًا**
-- في `admin_companies.currency` = USD للشركتين — فلا تُكتب هنا بيدٍ، بل تُشتق
-- منها (المبدأ ١٤: تُشتق لا تُدخل).
--
-- **المقيسُ قبل هذا الملف** (وهو سببُ وجوده): الجنيهُ هو السائدُ في البيانات لا
-- الدولار — 350 صفًّا في الدفتر بالجنيه مقابل 4 بالدولار، و9 ذممٍ مقابل 3.
-- فالسياسةُ دولاريةٌ والواقعُ المسجَّل جنيهيّ، ولا جسرَ بينهما اليوم.
--
-- **قاعدةُ عدم التلفيق نافذةٌ هنا حرفيًّا**: لا يُخترع سعرُ صرفٍ للجنيه. الصفوفُ
-- الجنيهيةُ تبقى `base_amount = NULL` (أي «بانتظار سعرِ صرفٍ لفترتها») حتى
-- يُدخَل السعرُ من شاشته فتُحسب آليًّا. والذي يُملأ الآن هو ما لا اجتهادَ فيه
-- وحده: الصفوفُ التي عملتُها **هي عملةُ الأساس** — فمعادلُها نفسُها وسعرُها ١.
--
-- **دلالةُ `rate_to_base`**: كم وحدةَ أساسٍ يساوي واحدٌ من هذه العملة، فيكون
--     base_amount = ROUND(amount × rate_to_base, 2)
-- ضربًا لا قسمةً — مطابقةً لنصِّ FES-01 §3.3 حرفًا. فإن كان الدولارُ أساسًا
-- و1 دولار = 600 جنيه، فسعرُ الجنيه 0.00166667.
--
-- **السعرُ بتاريخه لا ثابتًا**: FES-01 §3.1 يوجب السعرَ **لحظةَ الحدث**، والجنيهُ
-- متحرّكٌ عبر سنتين من البيانات. فالجدولُ بـ`effective_from` والبحثُ عن آخر سعرٍ
-- سابقٍ للتاريخ أو مساوٍ له. وهو ينحدر بسلاسةٍ إلى «سعرٍ واحدٍ ثابت» لمن أدخل
-- صفًّا واحدًا — فلا كلفةَ على البسيط ولا عجزَ عن المركّب.
--
-- إضافيٌّ محضٌ (Backward Compatible): جدولان جديدان وأربعةُ أعمدةٍ Nullable،
-- ولا عمودَ قائمٌ يُمسّ ولا صفَّ يُحذف.
-- الرجوع: إسقاطُ الجدولين والأعمدة الأربعة — والبياناتُ القائمة كما هي.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① سجلُّ العملات ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `fin_currencies` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL COMMENT 'عزل المستأجر',
  `code`       VARCHAR(8)  NOT NULL COMMENT 'رمز العملة ISO — USD · SDG',
  `name_ar`    VARCHAR(64) NOT NULL COMMENT 'الاسم كما يظهر للمستخدم',
  `symbol`     VARCHAR(8)  NULL COMMENT 'الرمز المختصر للعرض ($ · ج.س)',
  `decimals`   TINYINT NOT NULL DEFAULT 2 COMMENT 'خاناتُ الكسر عند العرض',
  `is_base`    TINYINT(1) NOT NULL DEFAULT 0
               COMMENT 'عملةُ الأساس — واحدةٌ لكل شركة، مشتقّةٌ من admin_companies.currency',
  `active`     TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL,
  `deleted_by` INT NULL,
  `created_by` INT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_currency_code` (`company_id`, `code`),
  KEY `ix_currency_base` (`company_id`, `is_base`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='سجلُّ العملات — عملةُ الأساس وما يُقاس بها (FES-01 §3.3)';

-- ── ② أسعارُ الصرف بتواريخ سريانها ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `fin_fx_rates` (
  `id`             INT NOT NULL AUTO_INCREMENT,
  `company_id`     INT NOT NULL COMMENT 'عزل المستأجر',
  `currency_code`  VARCHAR(8) NOT NULL COMMENT 'العملة المُسعَّرة',
  `rate_to_base`   DECIMAL(20,8) NOT NULL
                   COMMENT 'كم وحدةَ أساسٍ يساوي واحدٌ منها — base = ROUND(amount × rate, 2)',
  `effective_from` DATE NOT NULL COMMENT 'أولُ يومٍ يسري فيه — والسعرُ النافذ آخرُ سعرٍ سابقٍ للتاريخ أو مساوٍ',
  `source`         VARCHAR(32) NULL COMMENT 'مصدرُ السعر: system · بنك مركزي · قرارٌ إداري',
  `note`           VARCHAR(255) NULL,
  `is_deleted`     TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at`     DATETIME NULL,
  `deleted_by`     INT NULL,
  `created_by`     INT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fx_currency_date` (`company_id`, `currency_code`, `effective_from`)
    COMMENT 'سعرٌ واحدٌ لكل (عملة × تاريخ سريان) — التصحيحُ تعديلُ الصفّ لا صفٌّ ثانٍ',
  KEY `ix_fx_lookup` (`company_id`, `currency_code`, `effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='أسعارُ الصرف بتواريخها — السعرُ لحظةَ الحدث (FES-01 §3.1)';

-- ── ③ بذرُ العملتين الحاضرتين فعلًا في البيانات، والأساسُ مشتقٌّ لا مكتوب ───
--    (المصدرُ جدولٌ مشتقٌّ مسمًّى `src` — وإلا ظنَّ المحلّلُ أن `ON DUPLICATE`
--     شرطُ وصلٍ للـ CROSS JOIN فيسقط بخطأ نحوي)
INSERT INTO `fin_currencies`
       (`company_id`, `code`, `name_ar`, `symbol`, `decimals`, `is_base`, `active`, `sort_order`)
SELECT src.`company_id`, src.`code`, src.`name_ar`, src.`symbol`,
       src.`decimals`, src.`is_base`, src.`active`, src.`sort_order`
  FROM (
        SELECT c.`id`   AS `company_id`,
               x.`code` AS `code`,
               x.`name_ar` AS `name_ar`,
               x.`symbol`  AS `symbol`,
               2 AS `decimals`,
               CASE WHEN x.`code` = c.`currency` THEN 1 ELSE 0 END AS `is_base`,
               1 AS `active`,
               x.`ord` AS `sort_order`
          FROM `admin_companies` c
         CROSS JOIN (
                SELECT 'USD' AS `code`, 'الدولار الأمريكي' AS `name_ar`, '$'   AS `symbol`, 1 AS `ord`
          UNION ALL
                SELECT 'SDG',           'الجنيه السوداني',              'ج.س',            2
         ) x
       ) AS src
    ON DUPLICATE KEY UPDATE
       `name_ar` = src.`name_ar`,
       `symbol`  = src.`symbol`,
       `is_base` = src.`is_base`,
       `active`  = 1;

-- ── ④ سعرُ عملة الأساس إلى نفسها = ١ أبدًا (بديهيٌّ لا مخترَع) ─────────────
INSERT INTO `fin_fx_rates`
       (`company_id`, `currency_code`, `rate_to_base`, `effective_from`, `source`, `note`)
SELECT c.`id`, c.`currency`, 1.00000000, '2000-01-01', 'system',
       'عملةُ الأساس — نسبتُها إلى نفسها واحدٌ أبدًا'
  FROM `admin_companies` c
 WHERE c.`currency` IS NOT NULL AND c.`currency` <> ''
    ON DUPLICATE KEY UPDATE `rate_to_base` = 1.00000000;

-- ── ⑤ ثلاثيةُ العملة على الدفتر ───────────────────────────────────────────
ALTER TABLE `ems_business_events`
  ADD COLUMN `fx_rate` DECIMAL(20,8) NULL
      COMMENT 'سعرُ الصرف لحظةَ الحدث (FES-01 §3.1) — NULL أي لا سعرَ لفترته بعد'
      AFTER `currency`,
  ADD COLUMN `base_amount` DECIMAL(18,2) NULL
      COMMENT 'المعادلُ بعملة الأساس = ROUND(amount × fx_rate, 2) — NULL أي بانتظار سعر'
      AFTER `fx_rate`;

ALTER TABLE `fin_dues`
  ADD COLUMN `fx_rate` DECIMAL(20,8) NULL
      COMMENT 'سعرُ الصرف بتاريخ نشوء الذمّة'
      AFTER `currency`,
  ADD COLUMN `base_amount` DECIMAL(18,2) NULL
      COMMENT 'المعادلُ بعملة الأساس — عليه تُجمع تسويةُ الطرف متعددِ العملات'
      AFTER `fx_rate`;

-- ── ⑥ الملءُ الوحيد الذي لا اجتهادَ فيه: ما عملتُه هي عملةُ الأساس ─────────
--     (وما سواه يبقى NULL حتى يُدخَل سعرُه — لا رقمَ مخترَع)
UPDATE `ems_business_events` be
  JOIN `admin_companies` c ON c.`id` = be.`company_id`
   SET be.`fx_rate`     = 1.00000000,
       be.`base_amount` = ROUND(be.`amount`, 2)
 WHERE be.`currency` = c.`currency`
   AND be.`amount` IS NOT NULL
   AND be.`base_amount` IS NULL;

UPDATE `fin_dues` d
  JOIN `admin_companies` c ON c.`id` = d.`company_id`
   SET d.`fx_rate`     = 1.00000000,
       d.`base_amount` = ROUND(d.`amount`, 2)
 WHERE d.`currency` = c.`currency`
   AND d.`amount` IS NOT NULL
   AND d.`base_amount` IS NULL;
