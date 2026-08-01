-- ═══════════════════════════════════════════════════════════════════════════
-- P-07 · توسيعُ التخصيص القائم — 2026-08-01
-- البطاقة: docs/specs/P-07_allocation_targets.md
-- المصدر: الملحق §3-`P-07`: «**توسيعُ التخصيص القائم**: `fin_collection_allocations`
--         تقبل `target_kind` الخمسة (مقدمٌ · فاتورةٌ · معلَمٌ · محتجَزٌ · ختامية)
--         + `unallocated_amount` **رصيدًا ظاهرًا** + **قيدُ Σ ≤ السند**» ·
--         §4 القبول: «سندٌ واحدٌ على مقدمٍ وفاتورتين: **Σ التخصيصات = السند**».
--         و§1 مُلزِمة: **لا تُعِد بناءَ التخصيص — منفَّذٌ في M-05**. فهذه
--         **توسعةٌ لا هدم**.
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء:
--   · `fin_collection_allocations` قائمةٌ (M-05) بـ8 أعمدة: `payment_id` ·
--     `receivable_id` **NOT NULL بمفتاحٍ أجنبيّ** · `amount` · `basis`
--     (explicit/oldest_first) — **وهدفُها الوحيدُ الفاتورة**. فلا سبيلَ
--     لتخصيص سندٍ على **مقدَّمٍ** أو **معلَمٍ** أو **محتجَزٍ** أو **ختامية**.
--   · و«الفائضُ» في M-05 **يُعلَن في رسالةٍ ثم يختفي**: `unallocated` قيمةٌ
--     في مصفوفة الإرجاع **ولا عمودَ يحملها**. فبعد إغلاق الشاشة **لا أثرَ له**
--     — وهو عينُ ما تمنعه القاعدةُ «رقمٌ يختفي أسوأُ من رقمٍ معلَن».
--   · و**لا قيدَ بنيويًّا يمنع Σ التخصيصات من تجاوز السند**: حلقةُ الخدمة
--     تحدّه، لكن **نداءً ثانيًا أو كتابةً مباشرةً تتجاوزه**.
--
-- ⚠ گوتشا مثبَتة (للمرة الخامسة): `CHECK` لا يرى صفوفًا أخرى — فقيدُ
--   «Σ ≤ السند» **يُحمَل على السند** (`fin_payments.allocated_amount`)،
--   و`unallocated_amount` **عمودٌ مولَّد** فلا يُكتب بيدٍ ولا ينحرف.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① عدّادُ التخصيص على السند + الرصيدُ الظاهر ─────────────────────────────
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'fin_payments'
                  AND COLUMN_NAME = 'allocated_amount'),
    'ALTER TABLE `fin_payments`
       ADD COLUMN `allocated_amount` DECIMAL(18,2) NOT NULL DEFAULT 0
           COMMENT ''Σ التخصيصات — يُحرَس بـCHECK فلا يتجاوز مبلغ السند'' AFTER `amount`,
       ADD COLUMN `unallocated_amount` DECIMAL(18,2)
           GENERATED ALWAYS AS (`amount` - `allocated_amount`) STORED
           COMMENT ''**رصيدٌ ظاهر** — لا رقمٌ في رسالةٍ تختفي'' AFTER `allocated_amount`,
       ADD CONSTRAINT `ck_fp_allocated` CHECK (
           `allocated_amount` >= 0 AND `allocated_amount` <= `amount`)',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ── ② أهدافُ التخصيص الخمسة ────────────────────────────────────────────────
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'fin_collection_allocations'
                  AND COLUMN_NAME = 'target_kind'),
    'ALTER TABLE `fin_collection_allocations`
       ADD COLUMN `target_kind` ENUM(''advance'',''invoice'',''milestone'',''retention'',''final'')
           NOT NULL DEFAULT ''invoice''
           COMMENT ''هدفُ التخصيص — والفاتورةُ واحدٌ من خمسةٍ لا الوحيد'' AFTER `receivable_id`,
       ADD COLUMN `target_ref` INT NOT NULL DEFAULT 0
           COMMENT ''معرّفُ الهدف: fin_receivables للفاتورة · contract_payment_schedule لغيرها'' AFTER `target_kind`,
       ADD COLUMN `note` VARCHAR(255) NULL DEFAULT NULL AFTER `basis`',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- القائمُ كلُّه فواتير — والمرجعُ يُملأ من عموده (وهو صفرُ صفٍّ حيًّا اليوم)
UPDATE `fin_collection_allocations`
   SET `target_ref` = `receivable_id`
 WHERE `target_ref` = 0 AND `receivable_id` IS NOT NULL;

-- ── ③ و`receivable_id` يقبل NULL — **لأن الهدفَ لم يعد الفاتورةَ وحدَها** ──
SET @ddl = (SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'fin_collection_allocations'
              AND COLUMN_NAME = 'receivable_id' AND IS_NULLABLE = 'NO'),
    'ALTER TABLE `fin_collection_allocations`
       MODIFY COLUMN `receivable_id` INT NULL DEFAULT NULL
           COMMENT ''ذمّةُ الفاتورة — NULL لغير الفاتورة (والمفتاحُ الأجنبيُّ يقبل NULL)''',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ── ④ مفتاحُ التفرّد الجديد + قيودُ الاتساق ────────────────────────────────
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'fin_collection_allocations'
                  AND INDEX_NAME = 'uq_alloc_target'),
    'ALTER TABLE `fin_collection_allocations`
       ADD UNIQUE KEY `uq_alloc_target` (`payment_id`, `target_kind`, `target_ref`)',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.CHECK_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                  AND CONSTRAINT_NAME = 'ck_alloc_target'),
    -- **الفاتورةُ وحدَها تحمل ذمّة**: هدفٌ فاتورةٌ بلا ذمّةٍ كذبٌ، وهدفٌ غيرُ
    -- فاتورةٍ بذمّةٍ خلطٌ. والمرجعُ **موجبٌ دائمًا** فلا هدفَ مجهول.
    'ALTER TABLE `fin_collection_allocations`
       ADD CONSTRAINT `ck_alloc_target` CHECK (
           `target_ref` > 0 AND (
             (`target_kind` =  ''invoice'' AND `receivable_id` IS NOT NULL
                                           AND `target_ref` = `receivable_id`) OR
             (`target_kind` <> ''invoice'' AND `receivable_id` IS NULL)))',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ── ⑤ **ولا شاشةَ جديدة**: «الذمم والتحصيل» (الوحدة 166) هي بيتُ التخصيص ────
-- درسٌ مقيسٌ في H-13: قبل تسجيل وحدةٍ جديدة **يُقاس القائم**. والتخصيصُ
-- توسعةُ M-05 لا بابٌ ثانٍ — وبابان لفعلٍ واحدٍ يفترقان بعد شهر.
