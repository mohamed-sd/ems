-- ═══════════════════════════════════════════════════════════════════════════
-- M-06 · فعلُ النزاع على بند المستخلص — 2026-07-31
-- البطاقة: docs/specs/M-06_claim_disputes.md
-- المصدر: ENT-03 §3-⑤ («**اعتراضُ العميل على بندٍ يحوّله متنازَعًا عليه بسببٍ
--         ومستند** — **والبقيةُ تمضي للفوترة، ولا يُجمَّد المستخلصُ كلُّه**»)
--         · §4 («Review → (بندٌ Disputed) · بندٌ محددٌ **بسببٍ ومستند** ·
--         Disputed **بعدّاده** — والبقيةُ تمضي»)
-- ───────────────────────────────────────────────────────────────────────────
-- المقيسُ قبل البناء: `claim_lines.dispute_flag` و`dispute_reason` **قائمان**
-- و`claim_recalc` **يستثني المتنازَعَ عليه فعلًا** — فالمحرّكُ يعمل. والناقصُ
-- **الفعلُ نفسُه**: لا مستندَ ولا رافعَ ولا وقتَ ولا **قرارَ حسم**، ولا زرَّ في
-- الواجهة (عرضٌ باهتٌ فقط). فالبناءُ **يكمل ما بُني** ولا يُعيده.
-- ═══════════════════════════════════════════════════════════════════════════

ALTER TABLE `claim_lines`
  ADD COLUMN `dispute_doc_ref` VARCHAR(120) NULL DEFAULT NULL
      COMMENT 'مستندُ الاعتراض — «بسببٍ **ومستند**» (§3-⑤)' AFTER `dispute_reason`,
  ADD COLUMN `disputed_by` INT NULL DEFAULT NULL AFTER `dispute_doc_ref`,
  ADD COLUMN `disputed_at` DATETIME NULL DEFAULT NULL AFTER `disputed_by`,
  ADD COLUMN `dispute_state` ENUM('none','open','resolved') NOT NULL DEFAULT 'none'
      COMMENT 'حالُ النزاع — والحسمُ قرارٌ يُسجَّل لا وسمٌ يُمحى' AFTER `disputed_at`,
  ADD COLUMN `resolution` ENUM('upheld','rejected') NULL DEFAULT NULL
      COMMENT 'upheld = أُقرَّ اعتراضُ العميل (البندُ يسقط) · rejected = رُدَّ (البندُ يعود محتسَبًا)' AFTER `dispute_state`,
  ADD COLUMN `resolution_note` VARCHAR(255) NULL DEFAULT NULL AFTER `resolution`,
  ADD COLUMN `resolved_by` INT NULL DEFAULT NULL AFTER `resolution_note`,
  ADD COLUMN `resolved_at` DATETIME NULL DEFAULT NULL AFTER `resolved_by`;

-- الموروثُ المتنازَعُ عليه يُرفع إلى الحالة الصريحة **بوسمٍ معلَن** لا يُمحى
-- ولا يُختلق له مستند (نمطُ M-11/M-05 `legacy_no_ref`).
UPDATE `claim_lines`
   SET `dispute_state`   = 'open',
       `dispute_reason`  = COALESCE(NULLIF(`dispute_reason`, ''), 'legacy_no_ref'),
       `dispute_doc_ref` = 'legacy_no_ref'
 WHERE `dispute_flag` = 1 AND `dispute_state` = 'none';

-- ── ثلاثةُ قيودٍ بنيوية ────────────────────────────────────────────────────
ALTER TABLE `claim_lines`
  -- ① **نزاعٌ بلا سببٍ ومستندٍ مستحيل** — «بسببٍ ومستند» نصًّا
  ADD CONSTRAINT `ck_dispute_evidence` CHECK (
      `dispute_state` = 'none'
      OR (`dispute_reason` IS NOT NULL AND `dispute_reason` <> ''
          AND `dispute_doc_ref` IS NOT NULL AND `dispute_doc_ref` <> '')),
  -- ② **والحسمُ يلزمه قرارٌ وحاسمٌ وسببٌ مكتوب**
  ADD CONSTRAINT `ck_dispute_resolution` CHECK (
      `dispute_state` <> 'resolved'
      OR (`resolution` IS NOT NULL AND `resolved_by` IS NOT NULL
          AND `resolution_note` IS NOT NULL AND `resolution_note` <> '')),
  -- ③ **والعَلَمُ مرآةُ الحالة لا رأيٌ ثانٍ**: يُرفع ما دام النزاعُ مفتوحًا
  --    أو حُسم بإقرار اعتراض العميل، ويعود صفرًا فيما عدا ذلك.
  ADD CONSTRAINT `ck_dispute_flag_mirror` CHECK (
      `dispute_flag` = (CASE
          WHEN `dispute_state` = 'open' THEN 1
          WHEN `dispute_state` = 'resolved' AND `resolution` = 'upheld' THEN 1
          ELSE 0 END));
