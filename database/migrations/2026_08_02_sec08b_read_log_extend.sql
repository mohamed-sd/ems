-- ═══════════════════════════════════════════════════════════════════════════
-- update0004 · الموجة ⑧ (تصحيح) — sensitive_read_log كان قائمًا بمخطط LEG-01 §9
-- (person_id · element_code · subject_type/id · result) وبصفوف حية —
-- فيُوسَّع بأعمدة SEC-21 الإضافية (company_id · grant_ref · context) بلا مساس
-- بالقائم. النمط الحارس: information_schema + PREPARE.
-- ═══════════════════════════════════════════════════════════════════════════
SET @c = (SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = 'sensitive_read_log' AND column_name = 'company_id');
SET @ddl = IF(@c = 0,
  'ALTER TABLE sensitive_read_log
     ADD COLUMN company_id INT NULL COMMENT ''SEC-21: شركة السياق'' AFTER read_id,
     ADD COLUMN grant_ref VARCHAR(120) NULL COMMENT ''مرجع المنح المسوِّغ (GR-… · policy:…)'' AFTER result,
     ADD COLUMN context VARCHAR(190) NULL COMMENT ''الشاشة أو الخدمة'' AFTER grant_ref',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
