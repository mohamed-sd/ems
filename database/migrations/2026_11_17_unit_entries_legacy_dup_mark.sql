-- DEC-C (2026-08-06 · تفويض المالك جلسة update0009): وسمُ التصادمات التاريخية
-- ═══════════════════════════════════════════════════════════════════════════
-- يغلق DEF-010 وسمًا لا دمجًا: 5,986 مجموعةَ تصادمٍ (معدة×تاريخ×وردية) موروثةً
-- (12,465 صفًّا · كلُّها قبل عتبة 2026-08-05) تُوسم صراحةً legacy_dup_exempt=1 —
-- فالدمجُ الآليُّ يفقد ساعاتٍ فعليةً والمراجعةُ اليدويةُ مستحيلة، والوسمُ يحفظ
-- التاريخَ كما وقع (PR-06) ويعزل القياسَ الجديدَ عنه.
-- درعُ السكة الجديدة قائمٌ منذ ق-18 (قادحا 2026_11_16 · ue_dup_shield_test 5/5).
-- idempotent: العمودُ يُضاف إن غاب والوسمُ لا يتكرر.

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'unit_entries'
              AND COLUMN_NAME = 'legacy_dup_exempt');
SET @ddl := IF(@c = 0,
  'ALTER TABLE `unit_entries`
     ADD COLUMN `legacy_dup_exempt` TINYINT(1) NOT NULL DEFAULT 0
       COMMENT ''DEC-C: صفٌّ في مجموعةِ تصادمٍ (معدة×تاريخ×وردية) موروثةٍ قبل عتبة الدرع 2026-08-05 — استثناءٌ تاريخيٌّ معلَنٌ لا يُدمج ولا يُحذف''',
  'SELECT 1');
PREPARE s1 FROM @ddl; EXECUTE s1; DEALLOCATE PREPARE s1;

UPDATE unit_entries ue
  JOIN (SELECT equipment_id, entry_date, shift
          FROM unit_entries
         GROUP BY equipment_id, entry_date, shift
        HAVING COUNT(*) > 1) d
    ON  (ue.equipment_id <=> d.equipment_id)
    AND (ue.entry_date   <=> d.entry_date)
    AND (ue.shift        <=> d.shift)
   SET ue.legacy_dup_exempt = 1
 WHERE ue.legacy_dup_exempt = 0;
