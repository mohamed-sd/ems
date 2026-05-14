-- ====================================================================
-- Migration: إزالة المنجم من العقود والتشغيل
-- ربط العقود مباشرة بالمشروع بدون وسيط المنجم
-- ====================================================================

-- 1. إضافة project_id للعقود إذا لم يكن موجوداً
ALTER TABLE contracts ADD COLUMN IF NOT EXISTS project_id INT(11) NULL DEFAULT NULL;

-- 2. نقل البيانات: ملء project_id من mines.project_id
UPDATE contracts c
JOIN mines m ON c.mine_id = m.id
SET c.project_id = m.project_id
WHERE c.project_id IS NULL OR c.project_id = 0;

-- 3. للعقود التي لم يُحدَّد لها منجم، ابحث عن project_id من مصادر أخرى
UPDATE contracts c
JOIN supplierscontracts sc ON sc.project_contract_id = c.id
SET c.project_id = sc.project_id
WHERE (c.project_id IS NULL OR c.project_id = 0) AND sc.project_id > 0;

-- 4. إضافة index على project_id
ALTER TABLE contracts ADD INDEX IF NOT EXISTS idx_contracts_project_id (project_id);

-- 5. إضافة project_id لجدول supplierscontracts إذا لم يكن موجوداً
-- (هذا الجدول عادةً لديه project_id بالفعل)

-- 6. إزالة mine_id من operations (اختياري - يمكن الإبقاء عليه للتوافق)
-- ALTER TABLE operations DROP COLUMN mine_id;
-- ملاحظة: يمكن تعطيل السطر أعلاه وترك mine_id كعمود غير مستخدم بدلاً من الحذف الفوري

-- ====================================================================
-- تحقق: عرض العقود بعد التحديث
-- SELECT c.id, c.mine_id, c.project_id, p.name as project_name
-- FROM contracts c LEFT JOIN project p ON c.project_id = p.id
-- ORDER BY c.id DESC LIMIT 20;
-- ====================================================================
