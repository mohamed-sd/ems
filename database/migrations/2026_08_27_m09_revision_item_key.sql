-- ═══════════════════════════════════════════════════════════════════════════
-- M-09 (تكملة) · مفتاحُ عطالة المراجعة يشمل البند — 2026-07-30
-- ───────────────────────────────────────────────────────────────────────────
-- الشرطُ قد يكون **على مستوى العقد كلِّه** (`contract_item_id` NULL)، وأسعارُ
-- بنوده مختلفة — فمراجعةٌ واحدةٌ لا تسع عقدًا ببندين. فالمراجعةُ صفٌّ **لكل
-- بندٍ متأثر**، ومفتاحُ العطالة (شرط × دورة × **بند**) لا (شرط × دورة).
-- والجدولُ فارغٌ تمامًا (بُني قبل دقائق) فلا صفَّ يُمسّ.
-- ═══════════════════════════════════════════════════════════════════════════

-- المفتاحُ القديم هو ما يستند إليه FK الشرط — فيُبنى بديلُه أولًا وإلا رفض
-- MySQL إسقاطَه («needed in a foreign key constraint»).
ALTER TABLE `contract_price_revisions`
  ADD KEY `ix_price_revision_term` (`term_id`);

ALTER TABLE `contract_price_revisions`
  DROP INDEX `uq_price_revision_period`;

ALTER TABLE `contract_price_revisions`
  MODIFY COLUMN `contract_item_id` INT NOT NULL
    COMMENT 'سطرُ contractequipments المتأثر — صفٌّ لكل بندٍ ولو كان الشرطُ عقديًّا';

ALTER TABLE `contract_price_revisions`
  ADD UNIQUE KEY `uq_price_revision_period_item` (`term_id`, `period_key`, `contract_item_id`);
