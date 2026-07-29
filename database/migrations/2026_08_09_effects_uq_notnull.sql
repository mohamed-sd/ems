-- ═══════════════════════════════════════════════════════════════════════════
-- H-12 (تكملة) · إحكامُ عطالة الأثر — 2026-07-30
-- ───────────────────────────────────────────────────────────────────────────
-- **العطب المقيس**: UNIQUE في MySQL يسمح بتكرار NULL — فأثران متطابقان بلا
-- بندِ عقدٍ (contract_line_id NULL) يمرّان معًا ويموت القيدُ الفريد صامتًا.
-- (كُشف بمحاولة خرقٍ فعلية في fes_event_contract_test ⑤.)
--
-- **العلاج البنيوي**: أعمدةُ المفتاح الفريد لا تقبل NULL — الغيابُ قيمةٌ
-- محايدةٌ معلَنة ('' للطرف · 0 للمعرّفَين) فيصير التكرارُ تصادمًا حقيقيًّا.
-- ═══════════════════════════════════════════════════════════════════════════

UPDATE `fin_event_effects` SET `party_type` = ''       WHERE `party_type` IS NULL;
UPDATE `fin_event_effects` SET `party_id` = 0          WHERE `party_id` IS NULL;
UPDATE `fin_event_effects` SET `contract_line_id` = 0  WHERE `contract_line_id` IS NULL;

ALTER TABLE `fin_event_effects`
  MODIFY COLUMN `party_type` VARCHAR(16) NOT NULL DEFAULT ''
      COMMENT 'الطرف — فارغٌ = أثرٌ بلا طرفٍ (تكلفة) · جزءٌ من المفتاح الفريد فلا NULL',
  MODIFY COLUMN `party_id` INT NOT NULL DEFAULT 0
      COMMENT 'معرّفُ الطرف — 0 = بلا طرف · جزءٌ من المفتاح الفريد فلا NULL',
  MODIFY COLUMN `contract_line_id` INT NOT NULL DEFAULT 0
      COMMENT 'بندُ العقد — 0 = بلا بند · جزءٌ من المفتاح الفريد فلا NULL';
