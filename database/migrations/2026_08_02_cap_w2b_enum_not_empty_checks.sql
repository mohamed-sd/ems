-- ═══════════════════════════════════════════════════════════════════════════
-- update0005 · الموجة ② (متمم) — قيودُ «لا فارغَ في ENUM حاكم»
-- الگوتشا المقيسة: الاتصالُ غيرُ الصارم يبتلع قيمةَ ENUM الخاطئةَ إلى ''
-- صامتًا — فقائمةُ §6.1-① «المحكومة» تنكسر بكتابةٍ خام. القيدُ CHECK (col <> '')
-- يقلب الابتلاعَ الصامتَ رفضًا بنيويًّا في الوضعين معًا.
-- idempotent: الفحص عبر information_schema قبل كل إضافة.
-- ═══════════════════════════════════════════════════════════════════════════

SET @has = (SELECT COUNT(*) FROM information_schema.check_constraints
            WHERE constraint_schema = DATABASE() AND constraint_name = 'ck_cov_reason_governed');
SET @ddl = IF(@has = 0,
  'ALTER TABLE substitute_coverages ADD CONSTRAINT ck_cov_reason_governed CHECK (reason_code <> '''' AND level <> '''')',
  'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @has = (SELECT COUNT(*) FROM information_schema.check_constraints
            WHERE constraint_schema = DATABASE() AND constraint_name = 'ck_led_enums_not_empty');
SET @ddl = IF(@has = 0,
  'ALTER TABLE capacity_consumption_ledger ADD CONSTRAINT ck_led_enums_not_empty CHECK (effect_type <> '''' AND effect_target_type <> '''' AND measure_code <> '''')',
  'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

SET @has = (SELECT COUNT(*) FROM information_schema.check_constraints
            WHERE constraint_schema = DATABASE() AND constraint_name = 'ck_csl_enums_not_empty');
SET @ddl = IF(@has = 0,
  'ALTER TABLE coverage_settlement_lines ADD CONSTRAINT ck_csl_enums_not_empty CHECK (party <> '''' AND effect <> '''')',
  'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
