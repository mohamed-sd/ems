-- ق-18 تصحيح (2026-08-06 مساءً): الدرع يطابق مفتاح الخدمة الخدمي حرفيًّا
-- ═══════════════════════════════════════════════════════════════════════════
-- اصطاده timesheet_entry_service_test: الدرع الأول حسب الملغاة والمرفوضة
-- شاغلةً للخانة فمنع إعادة إدخال يومٍ رُفض صفُّه — والخدمة تستثنيها نصًّا
-- («يومٌ رُفض صفُّه يُدخَل من جديد»). القادحان يعادان بالاستثناء نفسه.

DROP TRIGGER IF EXISTS `trg_ue_dup_shield_ins`;
CREATE TRIGGER `trg_ue_dup_shield_ins` BEFORE INSERT ON `unit_entries`
FOR EACH ROW
BEGIN
  IF NEW.entry_date >= '2026-08-05'
     AND NEW.state NOT IN ('rejected','cancelled','superseded','reversed')
     AND EXISTS (
       SELECT 1 FROM unit_entries ue
        WHERE ue.equipment_id = NEW.equipment_id
          AND ue.entry_date = NEW.entry_date
          AND ue.shift <=> NEW.shift
          AND ue.state NOT IN ('rejected','cancelled','superseded','reversed')
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ق-18: تكرار (معدة×تاريخ×وردية) مرفوض بنيويًّا على السكة الجديدة';
  END IF;
END;

DROP TRIGGER IF EXISTS `trg_ue_dup_shield_upd`;
CREATE TRIGGER `trg_ue_dup_shield_upd` BEFORE UPDATE ON `unit_entries`
FOR EACH ROW
BEGIN
  IF NEW.entry_date >= '2026-08-05'
     AND NEW.state NOT IN ('rejected','cancelled','superseded','reversed')
     AND (NOT (NEW.equipment_id <=> OLD.equipment_id)
       OR NOT (NEW.entry_date <=> OLD.entry_date)
       OR NOT (NEW.shift <=> OLD.shift))
     AND EXISTS (
       SELECT 1 FROM unit_entries ue
        WHERE ue.equipment_id = NEW.equipment_id
          AND ue.entry_date = NEW.entry_date
          AND ue.shift <=> NEW.shift
          AND ue.id <> OLD.id
          AND ue.state NOT IN ('rejected','cancelled','superseded','reversed')
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ق-18: نقل الصف إلى (معدة×تاريخ×وردية) مشغولة مرفوض بنيويًّا';
  END IF;
END;
