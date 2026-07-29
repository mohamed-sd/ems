-- ═══════════════════════════════════════════════════════════════════════════
-- أمر الشراء: عشرون حقلًا لاستيفاء مواصفته — 2026-07-27
-- ───────────────────────────────────────────────────────────────────────────
-- UX-09 §8.1 نصًّا: «تبقى بجدولها — تُضاف حالاتُ 8.2 **وأعمدةُ الربط بالاستلامات
-- والفاتورة**»؛ و§5.1: «الإلزاميُّ وحده: المورد المختار والسعر المتفق **والموعد**»
-- و«الحالات: … متأخر (بعدّاد أيامه) · مستلمٌ جزئيًّا …»؛ و§8.2: المطابقةُ الثلاثية
-- بحالتها. وFES §3.1/§3.3: العملةُ بثلاثيتها (عملة · سعر · **معادلٌ موحّد**)
-- وتاريخُ الاستحقاق والمشروعُ بُعدًا إلزاميًّا؛ والدستور §8: المتطلبُ النظامي
-- (الضريبة) عمودٌ واجب.
--
-- **البُعد المفقود المقيس**: `proc_order` بلا `project_id` إطلاقًا — فكلُّ مصروف
-- مشترياتٍ يصل الدفترَ بلا مشروع، وربحيةُ المشروع تفقده. يُشتق من طلب الشراء.
--
-- إضافيٌّ محضٌ (Backward Compatible): كلُّ عمودٍ Nullable أو بافتراضٍ صفري،
-- ولا عمودَ قائمٌ يُمسّ ولا يُحذف. الرجوع: إسقاطُ الأعمدة العشرين وحدها.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── أ · التوريد والموعد (UX-09 §5.1 · §8.2 حالتا Sent وLate) ───────────────
ALTER TABLE `proc_order`
  ADD COLUMN `expected_delivery_date` DATE NULL COMMENT 'موعد التوريد المتفق — الإلزامي الثالث (§5.1)' AFTER `expected_receipt_type`,
  ADD COLUMN `sent_at`         DATETIME NULL COMMENT 'لحظة الإرسال للمورد (Approved→Sent §8.2)' AFTER `expected_delivery_date`,
  ADD COLUMN `sent_by`         INT      NULL COMMENT 'مُرسِل الأمر' AFTER `sent_at`,
  ADD COLUMN `late_alerted_at` DATETIME NULL COMMENT 'آخر إنذار تأخّر توريد (Late بعدّاده §8.2)' AFTER `sent_by`;

-- ── ب · الاستلام: أعمدة الربط (§8.1) والمتبقي محسوبًا (§5.1-②) ─────────────
ALTER TABLE `proc_order`
  ADD COLUMN `received_pct`     DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'نسبة المستلَم — 100 = اكتمال' AFTER `late_alerted_at`,
  ADD COLUMN `first_receipt_at` DATETIME NULL COMMENT 'أول استلام (PartialReceived)' AFTER `received_pct`,
  ADD COLUMN `final_receipt_at` DATETIME NULL COMMENT 'الاستلام النهائي — زنادُ الأثر المالي' AFTER `first_receipt_at`,
  ADD COLUMN `closed_at`        DATETIME NULL COMMENT 'إقفال الأمر' AFTER `final_receipt_at`,
  ADD COLUMN `closed_by`        INT      NULL COMMENT 'مُقفِل الأمر' AFTER `closed_at`;

-- ── ج · الفاتورة والمطابقة الثلاثية (§8.1 · §8.2) ──────────────────────────
ALTER TABLE `proc_order`
  ADD COLUMN `invoice_no`     VARCHAR(64)  NULL COMMENT 'رقم فاتورة المورد' AFTER `closed_by`,
  ADD COLUMN `invoice_date`   DATE         NULL COMMENT 'تاريخ الفاتورة' AFTER `invoice_no`,
  ADD COLUMN `invoice_amount` DECIMAL(18,2) NULL COMMENT 'قيمة الفاتورة (لمضاهاة الفرق)' AFTER `invoice_date`,
  ADD COLUMN `match_state`    VARCHAR(16)  NOT NULL DEFAULT 'unmatched' COMMENT 'unmatched·matched·var_pending·rejected (§8.2)' AFTER `invoice_amount`,
  ADD COLUMN `matched_at`     DATETIME     NULL COMMENT 'لحظة المطابقة' AFTER `match_state`,
  ADD COLUMN `matched_by`     INT          NULL COMMENT 'من طابق' AFTER `matched_at`;

-- ── د · الأثر المالي وأبعاده (FES §3.1 · §3.3 · §5-⑥ · الدستور §8) ────────
ALTER TABLE `proc_order`
  ADD COLUMN `project_id`  INT NULL COMMENT 'البُعد المفقود — يُشتق من طلب الشراء (FES: المشروع إلزامي)' AFTER `supplier_id`,
  ADD COLUMN `base_amount` DECIMAL(18,2) NULL COMMENT 'المعادل الموحّد = total_amount × fx_rate (FES §3.3)' AFTER `total_amount`,
  ADD COLUMN `tax_amount`  DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'الضريبة — متطلبٌ نظامي (الدستور §8)' AFTER `base_amount`,
  ADD COLUMN `due_date`    DATE NULL COMMENT 'تاريخ استحقاق السداد (FES §3.1 فهرس الاستحقاق)' AFTER `tax_amount`,
  ADD COLUMN `event_id`    INT NULL COMMENT 'مرجع الحدث المالي المنشور — قراءةً بمرجعه (§5.1-③)' AFTER `due_date`;

-- ── الفهارس: ما يُرشَّح ويُرتَّب به فعلًا ───────────────────────────────────
ALTER TABLE `proc_order`
  ADD INDEX `ix_po_receipt`  (`state`, `final_receipt_at`),
  ADD INDEX `ix_po_project`  (`project_id`),
  ADD INDEX `ix_po_due`      (`due_date`),
  ADD INDEX `ix_po_match`    (`match_state`),
  ADD INDEX `ix_po_event`    (`event_id`),
  ADD INDEX `ix_po_expected` (`expected_delivery_date`);
