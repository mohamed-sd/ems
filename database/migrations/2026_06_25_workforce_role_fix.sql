-- ════════════════════════════════════════════════════════════════════════════
-- 2026-06-25 — طبقة القوى التشغيلية (EQUIP-OPE-S04) · تصحيح ملكية الدور (3 → 4)
-- ════════════════════════════════════════════════════════════════════════════
-- الخطأ: سُجِّلت موديولات الطبقة وصلاحياتها للدور 3 (ادارة الاسطول/Fleet)،
--        والصحيح أنها لإدارة الموارد البشرية = الدور 4 (تحقّق عكسيٌّ من جدول roles:
--        4 = «ادارة الموارد البشرية»). هذا التهجير يصحّح ذلك دون لمس بقية الأدوار.
--
-- idempotent: يعمل بأمانٍ سواء طُبِّقت التهجيرات الأصلية أو لا، وعند إعادة التشغيل.
-- يقتصر أثره على موديولات code LIKE 'Workforce/%' — لا يمسّ موديولات الاسطول الأخرى.
-- ⚠️ خذ نسخةً احتياطيةً قبل التطبيق على الإنتاج.
-- ════════════════════════════════════════════════════════════════════════════
SET NAMES utf8mb4;

-- 1) نقل ملكية موديولات الطبقة من الدور 3 إلى 4 (HR)
UPDATE `modules`
   SET `owner_role_id` = 4
 WHERE `code` LIKE 'Workforce/%'
   AND `owner_role_id` = 3;

-- 2) نقل صلاحيات الدور 3 إلى الدور 4 لموديولات الطبقة (دون تكرارٍ إن وُجد صفّ 4 سلفاً)
--    (الجدول المشتق يلتفّ على قيد MySQL: لا يمكن تحديث جدولٍ مُشار إليه في استعلامٍ فرعي)
UPDATE `role_permissions` rp
  JOIN `modules` m ON m.id = rp.module_id
   SET rp.role_id = 4
 WHERE m.code LIKE 'Workforce/%'
   AND rp.role_id = 3
   AND NOT EXISTS (
        SELECT 1 FROM (SELECT role_id, module_id FROM role_permissions) x
         WHERE x.role_id = 4 AND x.module_id = rp.module_id
   );

-- 3) إزالة أي بقايا للدور 3 على موديولات الطبقة (حالة وجود صفّ 4 مسبقاً فتخطّاه التحديث)
DELETE rp FROM `role_permissions` rp
  JOIN `modules` m ON m.id = rp.module_id
 WHERE m.code LIKE 'Workforce/%'
   AND rp.role_id = 3;

-- ════════════════════════════════════════════════════════════════════════════
-- تحقّق (للتشغيل اليدوي):
--   SELECT m.code, m.owner_role_id, rp.role_id
--     FROM modules m LEFT JOIN role_permissions rp ON rp.module_id = m.id
--    WHERE m.code LIKE 'Workforce/%' ORDER BY m.code;  -- يجب أن تكون كلها 4
--
-- التراجع (إعادة الملكية للدور 3 — عند الحاجة فقط):
--   UPDATE modules SET owner_role_id = 3 WHERE code LIKE 'Workforce/%' AND owner_role_id = 4;
--   UPDATE role_permissions rp JOIN modules m ON m.id = rp.module_id SET rp.role_id = 3
--     WHERE m.code LIKE 'Workforce/%' AND rp.role_id = 4;
-- ════════════════════════════════════════════════════════════════════════════
