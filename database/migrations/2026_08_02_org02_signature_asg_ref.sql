-- ═══════════════════════════════════════════════════════════════════════════
-- update0004 · الموجة ② · ORG-07/O8 — التوقيع بمرجع التكليف
-- «كل اعتماد تشغيلي يحمل مرجع تكليف معتمِده» (ORG-01 §8 القبول · O8)
-- عمود مرجعي اختياري على سجل التوقيعات القائم — nullable فلا يمس الصفوف الحية.
-- النمط الحارس: MySQL بلا ADD COLUMN IF NOT EXISTS — فالفحص عبر information_schema
-- ثم PREPARE/EXECUTE (نموذج 2026_10_12_mnt_tickets_group.sql).
-- ═══════════════════════════════════════════════════════════════════════════

SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns
                   WHERE table_schema = DATABASE()
                     AND table_name = 'approval_signatures'
                     AND column_name = 'org_asg_id');
SET @ddl = IF(@col_exists = 0,
  'ALTER TABLE approval_signatures ADD COLUMN org_asg_id INT UNSIGNED NULL COMMENT ''مرجع التكليف التنظيمي المعتمِد — ORG-01 O8'' AFTER auth_id',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.statistics
                   WHERE table_schema = DATABASE()
                     AND table_name = 'approval_signatures'
                     AND index_name = 'idx_sig_org_asg');
SET @ddl = IF(@idx_exists = 0,
  'ALTER TABLE approval_signatures ADD KEY idx_sig_org_asg (org_asg_id)',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
