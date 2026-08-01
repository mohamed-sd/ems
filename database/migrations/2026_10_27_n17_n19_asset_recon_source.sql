-- ═══════════════════════════════════════════════════════════════════════════
-- N-17 مطابقة الأصول + N-19 فصل المصدر عن الملكية (PLAN-04 §2.4 · §1.2 · البوابة ③)
-- ───────────────────────────────────────────────────────────────────────────
-- N-17: ساعات السجل مقابل التايم شيت **بتفسير إلزامي للفرق** — فلا فرق بلا
--       سبب (CHECK) · ومنه معدل الإهلاك بالساعة والإهلاك غير المحتسب.
-- N-19: المحوران لا يُخلطان — operational_source ثنائي (واردة عبر التمويل ·
--       واردة عبر مورد خارجي) **في المجال المقيَّد** · و«غير محددة الملكية»
--       حالة نقص تُغلق بقرار لكل معدة لا صنف دائم.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `asset_hour_reconciliations` (
  `rec_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `equipment_id` INT NOT NULL,
  `period` CHAR(7) NOT NULL,
  `register_hours` DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'ساعات سجل الأصول (فرق العدّادات في الفترة)',
  `timesheet_hours` DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'ساعات التايم شيت المعتمدة',
  `diff_hours` DECIMAL(12,2) GENERATED ALWAYS AS (`register_hours` - `timesheet_hours`) STORED,
  `depreciation_amount` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'إهلاك الفترة المحتسب للأصل',
  `depreciation_per_hour` DECIMAL(18,4) NULL DEFAULT NULL COMMENT 'معدل الإهلاك بالساعة — من الفعلي لا التقدير',
  `undepreciated_flag` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'معدة عملت ولم تُهلك — تشوه تكلفة المشروع',
  `state` ENUM('open','explained') NOT NULL DEFAULT 'open',
  `explanation` VARCHAR(500) NULL DEFAULT NULL,
  `explained_by` INT NULL DEFAULT NULL,
  `explained_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`rec_id`),
  UNIQUE KEY `uq_ahr` (`company_id`, `equipment_id`, `period`),
  CONSTRAINT `ck_ahr_explained` CHECK (`state` <> 'explained' OR (`explanation` IS NOT NULL AND `explained_by` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='N-17: مطابقة ساعات السجل بالتايم شيت — لا فرق بلا سبب (CHECK بنيوي)';

-- N-19: المحور التشغيلي الثنائي في المجال المقيَّد + قرار إقفال غير المحدد
SET @n = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='equipment_ownership_registry' AND COLUMN_NAME='operational_source');
SET @ddl = IF(@n=0, 'ALTER TABLE `equipment_ownership_registry`
  ADD COLUMN `operational_source` ENUM(''financed'',''supplier_external'') NULL DEFAULT NULL COMMENT ''N-19: قيمتان لا ثالثة — واردة عبر التمويل (لنا) أو عبر مورد خارجي؛ NULL = غير محددة (حالة نقص تُغلق)'' AFTER `owner_supplier_relation`,
  ADD COLUMN `source_decided_by` INT NULL DEFAULT NULL COMMENT ''N-19: قرار الإقفال لكل معدة'',
  ADD COLUMN `source_decided_at` DATETIME NULL DEFAULT NULL,
  ADD COLUMN `source_decision_note` VARCHAR(255) NULL DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
