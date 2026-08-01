-- ═══════════════════════════════════════════════════════════════════════════
-- P-08 · العملاتُ الثلاث والفروقُ الأربعة — 2026-08-01
-- البطاقة: docs/specs/P-08_three_currencies.md
-- المصدر: الملحق §3-`P-08`: «**العملاتُ الثلاث** (`contract` · `settlement` ·
--         `functional`) + **الفروقُ الأربعة** التي لا تُخلط (رصيدٌ غيرُ مسدد ·
--         فرقٌ محقق · فرقٌ غيرُ محقق · زيادةُ سداد)» ·
--         §4 و§9-⑨: «قبضٌ بعملةٍ أخرى: **الذمةُ تُطفأ بالمعادل، والمتبقي
--         رصيدٌ غيرُ مسددٍ لا فرقَ صرف**، وفرقُ الصرف بسطره في العملة الوظيفية» ·
--         §9-⑫: «زيادةُ سداد: **رصيدٌ دائنٌ للعميل لا إيراد**».
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء:
--   · **`fin_receivables` بلا عمود عملةٍ أصلًا** — 18 عمودًا فيها `amount`
--     و`collected` و`outstanding` **ولا يُعرف بأيِّ عملةٍ هي**. فذمّةٌ بألفٍ
--     لا يُعلم أهي ألفُ دولارٍ أم ألفُ جنيه — **والفرقُ بينهما 5,400 ضعفًا**
--     بسعر اليوم (0.000185).
--   · والأساسُ الصرفيُّ **قائمٌ ومحكم**: `fin_currencies` (بـ`is_base`) و
--     `fin_fx_rates` (`rate_to_base`) و`ems_fx_to_base` — فلا يُعاد بناؤه.
--   · و**لا جدولَ لفروق الصرف في القاعدة كلِّها**: `ems_fx_revalue_open_dues`
--     تعيد التقييم **وتُرجع أرقامًا لا تُخزَّن**. فالفرقُ **يُحسَب ولا يُحفَظ**.
--   · والفروقُ الأربعةُ **لا اسمَ لأيٍّ منها** في البنية — فتُخلط بالضرورة.
--
-- ⚠ **والقاعدةُ الحاكمة**: «الذمةُ تُطفأ بالمعادل» — فالنقصُ بعد التحويل
--   **رصيدٌ غيرُ مسددٍ يبقى في الذمّة**، **لا فرقُ صرفٍ يُقفل به الباب**.
--   وخلطُهما يُظهر ذمّةً مسدَّدةً وهي ناقصة.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① الذمّةُ بعملتها وسعرِها المجمَّد ─────────────────────────────────────
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'fin_receivables' AND COLUMN_NAME = 'currency'),
    'ALTER TABLE `fin_receivables`
       ADD COLUMN `currency` VARCHAR(8) NOT NULL DEFAULT ''''
           COMMENT ''عملةُ الذمّة — كانت مجهولةً قبل P-08'' AFTER `amount`,
       ADD COLUMN `fx_rate_recognized` DECIMAL(20,8) NULL DEFAULT NULL
           COMMENT ''سعرُ الصرف يومَ الاعتراف — **مجمَّدٌ** فلا يتغيّر الماضي بتغيّر السعر'' AFTER `currency`,
       ADD COLUMN `base_amount` DECIMAL(18,2) NULL DEFAULT NULL
           COMMENT ''القيمةُ بالعملة الوظيفية يومَ الاعتراف'' AFTER `fx_rate_recognized`',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- عملةُ الذمّة من مستخلصها إن وُجد، وإلا **عملةُ الأساس للشركة** —
-- والافتراضُ يُعلَن في `fx_rate_recognized IS NULL` فلا يُخفى.
UPDATE `fin_receivables` r
  LEFT JOIN `claims` c ON c.`receivable_id` = r.`id` AND COALESCE(c.`is_deleted`,0) = 0
   SET r.`currency` = COALESCE(NULLIF(c.`currency`, ''),
                               (SELECT f.`code` FROM `fin_currencies` f
                                 WHERE f.`company_id` = r.`company_id` AND f.`is_base` = 1
                                   AND COALESCE(f.`is_deleted`,0) = 0 LIMIT 1),
                               'USD')
 WHERE r.`currency` = '';

-- السعرُ المجمَّدُ من جدول الأسعار بتاريخ الاعتراف — والقيمةُ **ضربًا** (نمطُ FX)
UPDATE `fin_receivables` r
   SET r.`fx_rate_recognized` = (
        SELECT x.`rate_to_base` FROM `fin_fx_rates` x
         WHERE x.`company_id` = r.`company_id` AND x.`currency_code` = r.`currency`
           AND COALESCE(x.`is_deleted`,0) = 0 AND x.`effective_from` <= DATE(r.`created_at`)
         ORDER BY x.`effective_from` DESC, x.`id` DESC LIMIT 1)
 WHERE r.`fx_rate_recognized` IS NULL;

UPDATE `fin_receivables`
   SET `base_amount` = ROUND(`amount` * `fx_rate_recognized`, 2)
 WHERE `base_amount` IS NULL AND `fx_rate_recognized` IS NOT NULL;

-- ── ② التخصيصُ يحمل عملتَي طرفيه وسعرَهما ──────────────────────────────────
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'fin_collection_allocations'
                  AND COLUMN_NAME = 'target_currency'),
    'ALTER TABLE `fin_collection_allocations`
       ADD COLUMN `pay_currency` VARCHAR(8) NOT NULL DEFAULT ''''
           COMMENT ''عملةُ السداد (settlement)'' AFTER `amount`,
       ADD COLUMN `target_currency` VARCHAR(8) NOT NULL DEFAULT ''''
           COMMENT ''عملةُ الهدف (contract غالبًا)'' AFTER `pay_currency`,
       ADD COLUMN `amount_target` DECIMAL(18,2) NOT NULL DEFAULT 0
           COMMENT ''**المعادلُ الذي أُطفئت به الذمّة** بعملة الهدف'' AFTER `target_currency`,
       ADD COLUMN `fx_rate_pay` DECIMAL(20,8) NULL DEFAULT NULL AFTER `amount_target`,
       ADD COLUMN `fx_rate_target` DECIMAL(20,8) NULL DEFAULT NULL AFTER `fx_rate_pay`,
       ADD COLUMN `base_amount` DECIMAL(18,2) NOT NULL DEFAULT 0
           COMMENT ''قيمةُ المقبوض بالعملة الوظيفية'' AFTER `fx_rate_target`,
       ADD COLUMN `fx_diff_base` DECIMAL(18,2) NOT NULL DEFAULT 0
           COMMENT ''**فرقُ الصرف المحقق** بالعملة الوظيفية — بسطره لا مبتلعًا في المبلغ'' AFTER `base_amount`,
       ADD CONSTRAINT `ck_alloc_fx` CHECK (
           `amount_target` >= 0 AND `base_amount` >= 0)',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ── ③ سجلُّ فروق الصرف — **بابٌ لكلِّ فرقٍ باسمه** ─────────────────────────
CREATE TABLE IF NOT EXISTS `fin_fx_differences` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,

  -- **الفرقان اللذان يُخزَّنان**: المحقَّقُ عند السداد وغيرُ المحقَّق عند
  -- إعادة التقييم. أما **الرصيدُ غيرُ المسدد** فبيتُه `fin_receivables.outstanding`
  -- و**زيادةُ السداد** فبيتُها `fin_payments.unallocated_amount` (P-07) —
  -- **ولا يُنقل أيٌّ منهما إلى هنا**، وذلك عينُ «لا تُخلط».
  `kind` ENUM('realized','unrealized') NOT NULL,
  `source_kind` ENUM('allocation','revaluation') NOT NULL,
  `source_ref` INT NOT NULL COMMENT 'سطرُ التخصيص أو الذمّةُ المُعاد تقييمُها',

  `party_ref` INT NULL DEFAULT NULL COMMENT 'العميلُ إن عُرف',
  `from_currency` VARCHAR(8) NOT NULL COMMENT 'العملةُ التي نشأ منها الفرق',
  `functional_currency` VARCHAR(8) NOT NULL COMMENT '**العملةُ الوظيفية** — وفيها وحدَها يُقاس الفرق',
  `amount` DECIMAL(18,2) NOT NULL COMMENT 'موجبٌ ربحُ صرفٍ · سالبٌ خسارتُه',
  `rate_from` DECIMAL(20,8) NULL DEFAULT NULL,
  `rate_to` DECIMAL(20,8) NULL DEFAULT NULL,
  `occurred_on` DATE NOT NULL,
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `event_id` INT NULL DEFAULT NULL COMMENT 'وصلُ الدفتر — **مؤجَّلٌ إلى H-09**',
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  -- **العطالة**: فرقٌ واحدٌ لكل (نوع × مصدر) — فإعادةُ النداء لا تضاعفه
  UNIQUE KEY `uq_fxd_source` (`kind`, `source_kind`, `source_ref`),
  KEY `ix_fxd_lookup` (`company_id`, `kind`, `occurred_on`),

  -- **وصفرٌ ليس فرقًا**: سطرٌ بفرقٍ صفرٍ ضوضاءٌ تُخفي الفروقَ الحقيقية
  CONSTRAINT `ck_fxd_amount` CHECK (`amount` <> 0),
  CONSTRAINT `ck_fxd_currency` CHECK (`functional_currency` <> '' AND `from_currency` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PLAN-03 §3.8 — فروقُ الصرف: المحقَّقُ وغيرُ المحقَّق، ولكلٍّ بابُه';

-- ── ④ **ولا شاشةَ جديدة**: الفروقُ تُعرض في «الذمم والتحصيل» (166) ─────────
-- حيث تنشأ. وبابٌ ثانٍ لعرضِ ما ينشأ هنا يفترق عنه بعد شهر (درسُ H-13).
