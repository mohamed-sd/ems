-- ق-18 (2026-08-06 · تفويض إكمال الـ66): الدرع البنيوي ضد تكرار (معدة×تاريخ×وردية)
-- ═══════════════════════════════════════════════════════════════════════════
-- المعوق كان: 5,986 تصادمًا موروثًا يمنع UNIQUE الكامل (قرار مالك سابق: لا قيد
-- قبل معالجة). الحسم بلا حذفٍ ولا كسر موروث: قادحان BEFORE INSERT/UPDATE
-- يرفضان التكرار الجديد على السكة الجديدة (entry_date >= عتبة 2026-08-05 —
-- المقيسة صفر تصادم) من القاعدة نفسها مهما كان مسار الوصول — فيسقط «الحارس
-- التطبيقي نقطة فشل واحدة»، والموروث قبل العتبة يبقى كما هو معلَنًا.
-- DDL إضافي خالص (CREATE TRIGGER) — سابقة قوادح BR-CEO-08 في هجرة 2026_11_14.

DROP TRIGGER IF EXISTS `trg_ue_dup_shield_ins`;
CREATE TRIGGER `trg_ue_dup_shield_ins` BEFORE INSERT ON `unit_entries`
FOR EACH ROW
BEGIN
  IF NEW.entry_date >= '2026-08-05' AND EXISTS (
       SELECT 1 FROM unit_entries ue
        WHERE ue.equipment_id = NEW.equipment_id
          AND ue.entry_date = NEW.entry_date
          AND ue.shift <=> NEW.shift
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ق-18: تكرار (معدة×تاريخ×وردية) مرفوض بنيويًّا على السكة الجديدة';
  END IF;
END;

DROP TRIGGER IF EXISTS `trg_ue_dup_shield_upd`;
CREATE TRIGGER `trg_ue_dup_shield_upd` BEFORE UPDATE ON `unit_entries`
FOR EACH ROW
BEGIN
  IF NEW.entry_date >= '2026-08-05'
     AND (NOT (NEW.equipment_id <=> OLD.equipment_id)
       OR NOT (NEW.entry_date <=> OLD.entry_date)
       OR NOT (NEW.shift <=> OLD.shift))
     AND EXISTS (
       SELECT 1 FROM unit_entries ue
        WHERE ue.equipment_id = NEW.equipment_id
          AND ue.entry_date = NEW.entry_date
          AND ue.shift <=> NEW.shift
          AND ue.id <> OLD.id
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ق-18: نقل الصف إلى (معدة×تاريخ×وردية) مشغولة مرفوض بنيويًّا';
  END IF;
END;
