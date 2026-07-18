-- ═══════════════════════════════════════════════════════════════════════════
-- D02 §2.6: تسجيل أثر «أحكام الأطراف» في خريطة التفريع التصريحية
-- ───────────────────────────────────────────────────────────────────────────
-- القاعدة ① في محرّك المروحة: ما يتولّد قواعدُ بياناتٍ لا كود. فالأثر الجديد
-- يُعلَن هنا لا في شرطٍ داخل PHP — كما فعلت الآثار الخمسة قبله.
--
-- ⚠️ display_order = 5: الأحكام تُكتب **قبل** آثار المال في العرض والقراءة،
-- لأنها القرار التعاقدي الذي يقرؤه التحويل لا نتيجتَه. (الترتيب عرضيٌّ فقط —
-- الذرّية تضمن أن الكل يقع أو لا شيء.)
--
-- يُبذر لكل شركةٍ لها خريطةٌ قائمة، وبلا تكرارٍ عند إعادة التشغيل.
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `fin_effect_map`
  (`company_id`,`source_kind`,`effect_type`,`effect_label`,`target_table`,
   `is_active`,`param_value`,`unavailable_reason`,`display_order`)
SELECT DISTINCT m.company_id, 'timesheet', 'party_award',
       'أحكام استحقاق الأطراف', 'unit_party_awards',
       1, NULL, NULL, 5
FROM `fin_effect_map` m
WHERE m.source_kind = 'timesheet'
  AND NOT EXISTS (
    SELECT 1 FROM `fin_effect_map` x
     WHERE x.company_id = m.company_id
       AND x.source_kind = 'timesheet'
       AND x.effect_type = 'party_award'
  );
