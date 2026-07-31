-- ═══════════════════════════════════════════════════════════════════════════
-- M-30 · الإهلاكُ حدثًا دوريًّا بمفتاح (أصل × فترة) — 2026-07-31
-- البطاقة: docs/specs/M-30_depreciation_event.md
-- المصدر: SPEC-01 #32: «**الإهلاكُ حدثٌ دوريٌّ بمفتاح (الأصل × الفترة)** بطريقةٍ
--         من إعداده» · «أثر: **قيدُ الإهلاك الدوري آليًّا بمرجع الأصل والفترة**».
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء: `fin_depreciation` قائمٌ (9 صفوف) و**مفتاحُ منع التكرار
-- موجودٌ سلفًا** (`uq_fin_dep`) — والناقصُ أربعةٌ: `journal_entry_id` **NULL في
-- 9 من 9** (صفرُ قيدٍ في تاريخ النظام) · والمحرّكُ داخلَ الشاشة · و`acquisition_date`
-- **متجاهَلٌ تمامًا** (FA-001 اقتُني 2025-01 وأولُ إهلاكٍ له 2026-07) · والفترةُ
-- `date('Y-m')` جبرًا فشهرٌ فات لا يُدرَك.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① أعمدةُ الأثر واللقطة والمصدر ─────────────────────────────────────────
SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'fin_depreciation'
                  AND COLUMN_NAME = 'event_id'),
    'ALTER TABLE `fin_depreciation`
       ADD COLUMN `event_id` INT NULL DEFAULT NULL
           COMMENT ''الحدثُ المالي المنشور (fin_financial_events) — «كلُّ حدثٍ يُقرأ بالاتجاهين»'' AFTER `journal_entry_id`,
       ADD COLUMN `method` VARCHAR(24) NULL DEFAULT NULL
           COMMENT ''طريقةُ الإهلاك ساعةَ الاحتساب — من إعداد الأصل لا من اجتهاد'' AFTER `event_id`,
       ADD COLUMN `basis_json` TEXT NULL DEFAULT NULL
           COMMENT ''لقطةُ الأساس: التكلفةُ والخردةُ والعمرُ والمجمّعُ قبلَه — لا اشتقاقٌ لاحق'' AFTER `method`,
       ADD COLUMN `source` ENUM(''screen'',''cron'',''legacy'') NOT NULL DEFAULT ''screen''
           COMMENT ''من أوقعه — والقديمُ يُصرَّح legacy لا يُدَّعى أنه من الخدمة'' AFTER `basis_json`',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

SET @ddl = (SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'fin_depreciation'
                  AND INDEX_NAME = 'ix_fin_dep_event'),
    'ALTER TABLE `fin_depreciation` ADD KEY `ix_fin_dep_event` (`event_id`)',
    'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ── ② تصريحُ الموروث — «موروثٌ بلا مرجع» يُعلَن ولا يُمحى ولا يُختلق له حدث ──
-- الصفوفُ التسعةُ القائمةُ وقعت من الشاشة قبل هذه المهمة، **وبلا حدثٍ ولا قيد**.
-- فتُوسم `legacy` صراحةً؛ ولا يُنشأ لها حدثٌ رجعيٌّ لأن ذلك يخترع تاريخًا لم يقع.
UPDATE `fin_depreciation`
   SET `source` = 'legacy',
       `method` = COALESCE(`method`, 'straight_line'),
       `basis_json` = COALESCE(`basis_json`,
           '{"note":"صفٌّ سابقٌ لـM-30 — احتُسب من الشاشة بلا حدثٍ ولا لقطةِ أساس؛ يُعلَن ولا يُختلق له مرجع"}')
 WHERE `event_id` IS NULL AND `source` = 'screen';
