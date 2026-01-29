-- تغيير اسم جدول المشاريع من projects إلى operationproject
-- Rename projects table to operationproject
-- Database: equipation_manage

-- التأكد من أن الجدول موجود قبل التغيير
-- Make sure the table exists before renaming
SELECT 'Renaming table from projects to operationproject...' AS status;

-- تغيير اسم الجدول
-- Rename the table
RENAME TABLE `projects` TO `operationproject`;

-- التحقق من نجاح العملية
-- Verify the operation succeeded
SELECT 'Table renamed successfully!' AS status;
SHOW TABLES LIKE 'operationproject';
