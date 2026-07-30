-- ═══════════════════════════════════════════════════════════════════════════
-- M-13 · حالتا Invoiced وClosed + مطابقةُ فاتورة المورد — 2026-07-30
-- البطاقة: docs/specs/M-13_invoiced_closed.md
-- المصدر: ENT-02 §4 («الشؤون · **استلامُ فاتورة المورد ومطابقتُها بالصافي
--         المعتمد** · Invoiced — **واختلافُها يفتح فرقًا بقرارٍ لا تعديلًا
--         صامتًا**» · «الإقفال · **لا بندَ معلَّقًا** · Closed — والتصحيحُ بعدها
--         **بعكسٍ موثَّقٍ لا بتعديل**») · §5 («**الاعترافُ بالتكلفة من التسوية
--         المعتمدة لا من الفاتورة**؛ والفاتورةُ **مستندٌ ضريبيٌّ يُطابَق بها**
--         — **والفرقُ بندُ تسويةٍ موثَّق**»)
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء: `settlements.state` ست حالاتٍ
-- (draft·review·approved·payment_requested·paid·cancelled) — **والاثنتان
-- مفقودتان** (يطابق دليلَ الكتالوج حرفيًّا).
--
-- ⚠ والقاعدةُ التي تحكم كلَّ عمودٍ أدناه: **الفاتورةُ لا تغيّر الصافي**.
-- `net_amount` يبقى كما اعتُمد، والفرقُ يُسجَّل **بندًا موثَّقًا بقراره** —
-- فلو غيّرت الفاتورةُ الصافيَ لصار المستندُ الضريبيُّ مصدرَ الاعتراف، وهو
-- عكسُ §5 حرفيًّا.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① الحالتان الناقصتان (ENT-02 §4) ──────────────────────────────────────
ALTER TABLE `settlements`
  MODIFY COLUMN `state`
    ENUM('draft','review','approved','payment_requested','invoiced','paid','closed','cancelled')
    NOT NULL DEFAULT 'draft'
    COMMENT 'دورةُ ENT-02 §4 — وInvoiced/Closed أُضيفتا في M-13';

-- ── ② بياناتُ فاتورة المورد ومطابقتُها ────────────────────────────────────
ALTER TABLE `settlements`
  ADD COLUMN `invoice_no` VARCHAR(64) NULL DEFAULT NULL
      COMMENT 'رقمُ فاتورة المورد — مستندٌ ضريبيٌّ يُطابَق به لا مصدرُ اعتراف' AFTER `paid_at`,
  ADD COLUMN `invoice_date` DATE NULL DEFAULT NULL AFTER `invoice_no`,
  ADD COLUMN `invoice_amount` DECIMAL(18,2) NULL DEFAULT NULL
      COMMENT 'مبلغُ الفاتورة كما ورد — لا يُعدَّل ولا يُعدِّل الصافي' AFTER `invoice_date`,
  ADD COLUMN `invoice_currency` VARCHAR(8) NULL DEFAULT NULL AFTER `invoice_amount`,
  ADD COLUMN `invoice_diff` DECIMAL(18,2) NULL DEFAULT NULL
      COMMENT 'الفاتورة − الصافي المعتمد (موجبٌ = زيادةُ المورد)' AFTER `invoice_currency`,
  ADD COLUMN `invoice_diff_reason` VARCHAR(255) NULL DEFAULT NULL
      COMMENT '**إلزاميٌّ متى وُجد فرق** — «فرقٌ بقرارٍ لا تعديلًا صامتًا»' AFTER `invoice_diff`,
  ADD COLUMN `invoice_diff_doc_ref` VARCHAR(120) NULL DEFAULT NULL AFTER `invoice_diff_reason`,
  ADD COLUMN `invoiced_by` INT NULL DEFAULT NULL AFTER `invoice_diff_doc_ref`,
  ADD COLUMN `invoiced_at` DATETIME NULL DEFAULT NULL AFTER `invoiced_by`,
  ADD COLUMN `closed_by` INT NULL DEFAULT NULL AFTER `invoiced_at`,
  ADD COLUMN `closed_at` DATETIME NULL DEFAULT NULL AFTER `closed_by`;

-- ── ③ قيدُ «الفرقُ بقرارٍ» بنيويًّا ────────────────────────────────────────
-- فرقٌ غيرُ صفريٍّ بلا سببٍ ومستندٍ **مستحيلٌ في القاعدة** — لا مرفوضٌ بفحصٍ يُنسى.
ALTER TABLE `settlements`
  ADD CONSTRAINT `ck_settlement_invoice_diff` CHECK (
      `invoice_diff` IS NULL
      OR ABS(`invoice_diff`) < 0.005
      OR (`invoice_diff_reason` IS NOT NULL AND CHAR_LENGTH(TRIM(`invoice_diff_reason`)) > 0
          AND `invoice_diff_doc_ref` IS NOT NULL AND CHAR_LENGTH(TRIM(`invoice_diff_doc_ref`)) > 0)
  );

-- ── ④ فهرسُ رقم الفاتورة لكل مورد — كشفُ التكرار قراءةً ────────────────────
-- (ليس UNIQUE عمدًا: موردٌ قد يعيد إصدارَ رقمٍ بعد إلغاءٍ ضريبيّ، والحسمُ
--  قرارُ الشؤون لا رفضٌ أعمى — لكن التكرارَ يجب أن يُرى.)
ALTER TABLE `settlements`
  ADD KEY `ix_settlement_invoice` (`company_id`, `party_ref`, `invoice_no`);
