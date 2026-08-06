-- UX-09 §8.2 (تكملة): حسمُ الفرق المعلَّق في المطابقة الثلاثية — بقرارٍ موثَّق
-- ───────────────────────────────────────────────────────────────────────────
-- كان var_pending طريقًا مسدودًا: الفاتورةُ فوق السماح تقف بلا دَينٍ «حتى قرارٍ
-- موثَّق» — ولا موضعَ للقرار. الأعمدةُ الأربعة توثّقه على الأمر نفسِه:
--   var_decision: قبول الفرق · إشعار دائن · رفض الفاتورة (NULL = لم يُحسم)
--   var_decision_reason/by/at: التفسيرُ والمخوَّلُ ولحظتُه — «لا فرقَ بلا تفسير»
-- المنفِّذ proc_match_resolve() في proc_helpers والواجهة في po_match.php.

SET @c1 := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proc_order' AND COLUMN_NAME = 'var_decision');
SET @ddl := IF(@c1 = 0,
  'ALTER TABLE `proc_order`
     ADD COLUMN `var_decision` VARCHAR(30) NULL DEFAULT NULL COMMENT ''حسم الفرق: قبول الفرق · إشعار دائن · رفض الفاتورة'' AFTER `match_state`,
     ADD COLUMN `var_decision_reason` VARCHAR(255) NULL DEFAULT NULL COMMENT ''تفسير الحسم — إلزامي مع القرار'' AFTER `var_decision`,
     ADD COLUMN `var_decided_by` INT NULL DEFAULT NULL COMMENT ''مخوَّل الحسم users.id'' AFTER `var_decision_reason`,
     ADD COLUMN `var_decided_at` DATETIME NULL DEFAULT NULL COMMENT ''لحظة الحسم'' AFTER `var_decided_by`',
  'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
