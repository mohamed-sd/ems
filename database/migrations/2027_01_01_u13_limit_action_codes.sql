-- update0013 · الحدُّ الصريحُ يُسمّي الفعلَ الذي يمنعه
-- ═══════════════════════════════════════════════════════════════════════════
-- FACC-0081 يجعل «المنعَ الصريح» العاملَ الحاديَ عشرَ في اشتقاقِ الصلاحية —
-- وإشارتُه سالبةٌ: نقضٌ لا موازنة. ولمَّا نُفِّذ الاشتقاقُ كشف عيبًا في القراءة:
--
--   الحدُّ في `gov_authority_limits` يقول «لا يملك الدورُ ١٨ اعتمادَ طلبِ
--   الإدارةِ النهائي» — فعلًا **بعينِه**. وكان الاشتقاقُ يقرؤه «الدورُ ١٨ ممنوعٌ
--   من كلِّ شيء»، فيسقط كلُّ محاسبٍ في كلِّ طلبٍ مهما كان.
--
-- ◆ فالعمودُ التالي يجعل المنعَ **مُصوَّبًا**: رموزُ الأفعالِ التي يمنعها هذا
--   الحدُّ مفصولةً بفاصلة. والفارغُ لا يمنع فعلًا بعينِه — وهو إعلانُ نقصٍ
--   ظاهرٌ في التقرير (كما `enforced_by` الفارغُ في الجدولِ نفسِه)، لا سكوتٌ
--   يجعل الحدَّ يمنع كلَّ شيءٍ أو لا شيء.
--
-- المصدر: FIN-ACC-01 §٤-٧ · FACC-0081
-- idempotent: يُفحص وجودُ العمودِ قبلَ إضافته.

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gov_authority_limits'
      AND COLUMN_NAME = 'action_codes') = 0,
  'ALTER TABLE `gov_authority_limits`
     ADD COLUMN `action_codes` VARCHAR(400) NOT NULL DEFAULT ''''
     COMMENT ''رموزُ الأفعالِ التي يمنعها هذا الحدُّ — والفارغُ لا يمنع فعلًا بعينِه''
     AFTER `forbidden`',
  'SELECT ''gov_authority_limits.action_codes موجودٌ سلفًا'''));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
