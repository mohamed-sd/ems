-- =====================================================
-- سكريبت ترحيل العقود من المشروع إلى المنجم
-- Migration Script: Contracts from Project to Mine
-- =====================================================
-- التاريخ: 2026-02-03
-- الوصف: تحويل ربط العقود من project إلى mine_id
-- =====================================================

-- الخطوة 1: إضافة حقل mine_id الجديد
ALTER TABLE `contracts` 
ADD COLUMN `mine_id` INT(250) NULL COMMENT 'معرف المنجم من جدول mines' AFTER `id`;

-- الخطوة 2: تحديث البيانات الموجودة (ربط العقود بأول منجم في كل مشروع)
-- ملاحظة: يجب مراجعة هذا الربط يدوياً إذا كان للمشروع أكثر من منجم
UPDATE `contracts` c
INNER JOIN (
    SELECT m.id AS mine_id, m.project_id
    FROM mines m
    WHERE m.status = 1
    GROUP BY m.project_id
) AS first_mine ON c.project = first_mine.project_id
SET c.mine_id = first_mine.mine_id
WHERE c.mine_id IS NULL;

-- الخطوة 3: جعل الحقل إلزامي بعد ملء البيانات
ALTER TABLE `contracts` 
MODIFY COLUMN `mine_id` INT(250) NOT NULL COMMENT 'معرف المنجم من جدول mines';

-- الخطوة 4: حذف حقل project القديم
-- تحذير: احفظ نسخة احتياطية قبل تنفيذ هذا الأمر
ALTER TABLE `contracts` 
DROP COLUMN `project`;

-- الخطوة 5: إضافة مفتاح خارجي للعلاقة مع جدول mines
ALTER TABLE `contracts`
ADD CONSTRAINT `fk_contracts_mines` 
FOREIGN KEY (`mine_id`) REFERENCES `mines`(`id`) 
ON DELETE RESTRICT ON UPDATE CASCADE;

-- الخطوة 6: إنشاء فهرس لتحسين الأداء
CREATE INDEX `idx_contracts_mine_id` ON `contracts`(`mine_id`);

-- =====================================================
-- ملاحظات مهمة:
-- 1. قم بعمل نسخة احتياطية من قاعدة البيانات قبل التنفيذ
-- 2. راجع العقود المرتبطة بمشاريع لها أكثر من منجم
-- 3. قد تحتاج لتعديل الخطوة 2 حسب منطق الربط المطلوب
-- 4. تأكد من تحديث جميع ملفات PHP المرتبطة
-- =====================================================
