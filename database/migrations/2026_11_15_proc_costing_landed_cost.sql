-- الإضافات الثلاث لدور المشتريات (قرار المالك 2026-08-06) — طبقة التكاليف
-- ───────────────────────────────────────────────────────────────────────────
-- ① تقييمُ المخزون بالمتوسط المرجح: `proc_item.avg_cost` (بمعادل الدفاتر —
--    unit_price × fx_rate للأمر، نفسُ عرف base_amount) يُشتق حتميًّا من دفتر
--    الحركات: متوسطُ استلاماتٍ **مُكلَّفة** فقط (unit_cost NOT NULL) — فالتاريخُ
--    غيرُ المسعَّر خارج المتوسط بالتصميم (تقييمٌ مستقبليٌّ من لحظة التفعيل).
-- ② `proc_stock_move.unit_cost`: كلُّ حركةٍ تحمل تكلفتَها — الاستلامُ بتكلفته
--    الفعلية (+ نصيبِه الوصولي)، والصرفُ بالمتوسط لحظتَه — دفترٌ دائمٌ للقيمة.
-- ③ `proc_landed_cost`: مصاريفُ الوصول (شحن/جمارك/تخليص) على أمر الشراء —
--    تُرسمَل على تكلفة استلاماته توزيعًا بقيمة البنود (ProcCostingService).

SET @c1 := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proc_item' AND COLUMN_NAME = 'avg_cost');
SET @ddl := IF(@c1 = 0,
  'ALTER TABLE `proc_item`
     ADD COLUMN `avg_cost` DECIMAL(18,4) NULL DEFAULT NULL COMMENT ''المتوسط المرجح بمعادل الدفاتر — يشتقه ProcCostingService من دفتر الحركات'' AFTER `safety_stock`,
     ADD COLUMN `avg_cost_updated_at` DATETIME NULL DEFAULT NULL COMMENT ''لحظة آخر إعادة احتساب'' AFTER `avg_cost`',
  'SELECT 1');
PREPARE s1 FROM @ddl; EXECUTE s1; DEALLOCATE PREPARE s1;

SET @c2 := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proc_stock_move' AND COLUMN_NAME = 'unit_cost');
SET @ddl := IF(@c2 = 0,
  'ALTER TABLE `proc_stock_move`
     ADD COLUMN `unit_cost` DECIMAL(18,4) NULL DEFAULT NULL COMMENT ''تكلفة الوحدة بمعادل الدفاتر: الاستلام بفعليته + نصيبه الوصولي · الصرف بالمتوسط لحظته · NULL=حركة تاريخية غير مسعرة'' AFTER `qty`',
  'SELECT 1');
PREPARE s2 FROM @ddl; EXECUTE s2; DEALLOCATE PREPARE s2;

CREATE TABLE IF NOT EXISTS `proc_landed_cost` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `company_id` INT NOT NULL COMMENT 'الكيان المالك — عزل المستأجر',
  `order_id` INT NOT NULL COMMENT 'أمر الشراء المحمَّل (proc_order.id — بلا FK كسائر proc_*)',
  `doc_no` VARCHAR(60) NOT NULL COMMENT 'رقم مستند المصروف (بوليصة/إيصال جمركي…)',
  `cost_type` VARCHAR(30) NOT NULL DEFAULT 'شحن' COMMENT 'شحن · جمارك · تخليص · نقل داخلي · أخرى',
  `amount` DECIMAL(18,2) NOT NULL COMMENT 'المبلغ بعملة المستند',
  `currency` VARCHAR(8) NOT NULL DEFAULT 'SDG',
  `fx_rate` DECIMAL(12,4) NOT NULL DEFAULT 1 COMMENT 'إلى معادل الدفاتر — ضربًا (عرف base_amount)',
  `base_amount` DECIMAL(18,2) NOT NULL COMMENT 'المعادل = amount × fx_rate',
  `supplier_id` INT NULL DEFAULT NULL COMMENT 'مقدم الخدمة (proc_supplier) إن وُجد',
  `notes` VARCHAR(255) NULL DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `deleted_by` INT NULL DEFAULT NULL,
  `created_by` INT NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_landed_order` (`company_id`, `order_id`, `is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='التكلفة الوصولية لأوامر الشراء — ترسمل على تكلفة الاستلام توزيعا بالقيمة';
