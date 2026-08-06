-- H-Fix §15.3/§15.6: الاستلامُ الموجَّه للمخزن يكتب حركةَ «استلام» في proc_stock_move
-- ───────────────────────────────────────────────────────────────────────────
-- الفجوة المقيسة (جولة مدير المشتريات 2026-08-06): أمرٌ استُلم 100٪ وصفرُ
-- حركةِ مخزونٍ له — «المتاح» لا يزيد بالشراء أبدًا لأن العهدة بلا مخزنِ إدخال.
-- الإصلاح: عمودُ warehouse_id على رأس العهدة (NULL = وجهةٌ غير مخزنية:
-- معدة/مشروع/ورشة — تبقى عهدةً بلا أثر مخزون، وهو السلوك الصحيح §15.3).
-- الكاتبُ في receipt_custody_proc.php — الحركةُ بمرجع ref_type/ref_id فتُعاد
-- كتابتُها عند التعديل ولا تتضاعف.

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'proc_receipt_custody'
     AND COLUMN_NAME  = 'warehouse_id'
);
SET @ddl := IF(@col_exists = 0,
  'ALTER TABLE `proc_receipt_custody` ADD COLUMN `warehouse_id` INT NULL DEFAULT NULL COMMENT ''مخزن الإدخال — إلزامي حين الوجهة مخزن؛ NULL لغير المخزنية'' AFTER `receipt_location`',
  'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
