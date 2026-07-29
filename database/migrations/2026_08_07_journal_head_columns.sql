-- ═══════════════════════════════════════════════════════════════════════════
-- M-38 · أعمدةُ دفتر القيود — رأسُ الحدث المالي (UX-02 §15.2-أ · SPEC-01 #13)
-- 2026-07-30 — البطاقة: docs/specs/M-38_journal_head_columns.md
-- ───────────────────────────────────────────────────────────────────────────
-- **الفجوة**: صفرٌ من سبعة أعمدةٍ في `fin_journal_entries` (تاريخُ الحركة ·
-- خيطُ الطلب ×3 · ثلاثيةُ العملة)، و`fin_journal_lines.cost_center` نصٌّ حرٌّ
-- بلا FK — فلا يُتتبَّع قيدٌ إلى مستنده ولا يُقرأ معادلُه الموحّد.
--
-- **قراراتُ التعبئة** (مقيسةٌ لا مخمَّنة — انظر البطاقة §3):
--   • القيودُ التسعُ القائمة آليةٌ من أحداثٍ كلُّها SDG بمبلغٍ مطابقٍ حرفيًّا
--     ⇒ currency='SDG'. وسعرُ SDG→USD غيرُ مُدخَل ⇒ fx_rate/base_amount يبقيان
--     NULL **معلَنَين** حتى يُدخل المالكُ السعر (لا تلفيق).
--   • txn_date ← تاريخُ وقوع الحدث المولِّد، وإلا posting_date.
--   • خيطُ الطلب من fin_requests.event_id — والقيودُ الحالية أحداثُها ليست
--     من طلباتٍ فيبقى NULL بحق.
--   • مركزُ تكلفة السطر من تطابق اسم المشروع مع دليل المراكز حرفيًّا.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① الأعمدةُ السبعة على الرأس (ALTER إضافي — لا مساسَ بقائم) ──────────────
ALTER TABLE `fin_journal_entries`
  ADD COLUMN `txn_date` DATE DEFAULT NULL
      COMMENT 'M-38: تاريخُ الحركة الفعلي (بجانب posting_date تاريخِ الترحيل)'
      AFTER `posting_date`,
  ADD COLUMN `request_no` VARCHAR(64) DEFAULT NULL
      COMMENT 'M-38: خيطُ الطلب — رقمُ الطلب المالي المولِّد إن وُجد'
      AFTER `txn_date`,
  ADD COLUMN `request_owner` VARCHAR(64) DEFAULT NULL
      COMMENT 'M-38: صاحبُ الطلب (اسمُ الرافع لحظةَ التوليد — لقطة)'
      AFTER `request_no`,
  ADD COLUMN `request_group` VARCHAR(64) DEFAULT NULL
      COMMENT 'M-38: مجموعةُ الطلب (request_type)'
      AFTER `request_owner`,
  ADD COLUMN `currency` VARCHAR(8) DEFAULT NULL
      COMMENT 'M-38: عملةُ القيد — NOT NULL بعد التعبئة'
      AFTER `request_group`,
  ADD COLUMN `fx_rate` DECIMAL(18,6) DEFAULT NULL
      COMMENT 'M-38: سعرُ الصرف إلى عملة الأساس يومَ الحركة — NULL = سعرٌ غيرُ مُدخَل (فجوةٌ معلَنة)'
      AFTER `currency`,
  ADD COLUMN `base_amount` DECIMAL(18,2) DEFAULT NULL
      COMMENT 'M-38: المعادلُ الموحّد بعملة الأساس = ROUND(total_debit × fx_rate, 2)'
      AFTER `fx_rate`,
  ADD INDEX `ix_je_txn_date` (`company_id`, `txn_date`),
  ADD INDEX `ix_je_request_no` (`company_id`, `request_no`);

-- ── ② مركزُ التكلفة FK على السطور (النصُّ الحرُّ القائم يبقى — يُسقَط من النماذج فقط) ──
ALTER TABLE `fin_journal_lines`
  ADD COLUMN `cost_center_id` INT DEFAULT NULL
      COMMENT 'M-38: مركزُ التكلفة من الدليل — بديلُ النص الحر cost_center'
      AFTER `equipment_id`,
  ADD INDEX `ix_jl_cost_center` (`company_id`, `cost_center_id`),
  ADD CONSTRAINT `fk_fin_jl_cc` FOREIGN KEY (`cost_center_id`)
      REFERENCES `fin_cost_centers` (`id`);

-- ── ③ التعبئةُ الرجعية ──────────────────────────────────────────────────────
-- تاريخُ الحركة: وقوعُ الحدث المولِّد، وإلا تاريخُ الترحيل
UPDATE `fin_journal_entries` je
  LEFT JOIN `fin_financial_events` ev ON ev.`id` = je.`event_id`
   SET je.`txn_date` = COALESCE(DATE(ev.`occurred_at`), je.`posting_date`)
 WHERE je.`txn_date` IS NULL;

-- العملة: من الحدث المولِّد (قيست التسعة: كلُّها SDG بمبلغٍ مطابق)، وإلا SDG
UPDATE `fin_journal_entries` je
  LEFT JOIN `fin_financial_events` ev ON ev.`id` = je.`event_id`
   SET je.`currency` = COALESCE(ev.`currency`, 'SDG')
 WHERE je.`currency` IS NULL;

-- خيطُ الطلب: حيث وُلد الحدثُ من طلبٍ مالي (لا شيءَ في التسعة الحالية — بحق)
UPDATE `fin_journal_entries` je
  JOIN `fin_requests` fr ON fr.`event_id` = je.`event_id` AND fr.`company_id` = je.`company_id`
  LEFT JOIN `users` u ON u.`id` = fr.`requester_id`
   SET je.`request_no`    = fr.`request_no`,
       je.`request_owner` = LEFT(COALESCE(u.`name`, CONCAT('user#', fr.`requester_id`)), 64),
       je.`request_group` = fr.`request_type`
 WHERE je.`request_no` IS NULL AND je.`event_id` IS NOT NULL;

-- مركزُ تكلفة السطر: تطابقُ اسم المشروع مع دليل المراكز حرفيًّا (اشتقاقٌ مسجَّل)
UPDATE `fin_journal_lines` jl
  JOIN `project` p ON p.`id` = jl.`project_id` AND p.`company_id` = jl.`company_id`
  JOIN `fin_cost_centers` cc ON cc.`name` = p.`name` AND cc.`company_id` = jl.`company_id`
                             AND COALESCE(cc.`is_deleted`, 0) = 0 AND cc.`active` = 1
   SET jl.`cost_center_id` = cc.`id`
 WHERE jl.`cost_center_id` IS NULL AND jl.`project_id` IS NOT NULL;

-- ── ④ الإلزامُ بعد التعبئة (والافتراضاتُ تحمي الكاتبَ غيرَ الموصول من فشلٍ صامت) ──
ALTER TABLE `fin_journal_entries`
  MODIFY COLUMN `txn_date` DATE NOT NULL DEFAULT (CURDATE())
      COMMENT 'M-38: تاريخُ الحركة الفعلي (بجانب posting_date تاريخِ الترحيل)',
  MODIFY COLUMN `currency` VARCHAR(8) NOT NULL DEFAULT 'SDG'
      COMMENT 'M-38: عملةُ القيد (افتراضُ SDG يطابق نمطَ fin_financial_events)';

-- ── ⑤ القيودُ البنيوية الثلاثة ──────────────────────────────────────────────
-- توازنُ Σ شرطُ الحفظ (SPEC-01 #13 «المتطلب النظامي») — قيست التسعة: متوازنة
ALTER TABLE `fin_journal_entries`
  ADD CONSTRAINT `ck_je_balanced`
  CHECK (ROUND(`total_debit`, 2) = ROUND(`total_credit`, 2));

-- تزاوجُ الثلاثية: لا معادلَ بلا سعرٍ ولا سعرَ بلا معادل — وbase محسوبٌ لا مكتوبٌ حرًّا
ALTER TABLE `fin_journal_entries`
  ADD CONSTRAINT `ck_je_fx_pair`
  CHECK ((`fx_rate` IS NULL AND `base_amount` IS NULL)
      OR (`fx_rate` IS NOT NULL AND `base_amount` = ROUND(`total_debit` * `fx_rate`, 2)));
