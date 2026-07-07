-- ═══════════════════════════════════════════════════════════════════════════
-- 2026_05_11_add_reports_performance_indexes.sql
-- فهارس أداء التقارير عند أحجام بيانات كبيرة (+50k)
--
-- ملاحظة تحويل (2026-07-07 · ADR-03): الصيغة الأصلية استخدمت DELIMITER $$
-- وإجراءً مخزَّنًا — وهي توجيهات خاصة بعميل mysql لا يقبلها المُشغِّل عبر
-- multi_query. حُوِّلت إلى النمط الحارس الموحّد أدناه بنفس النية تمامًا.
-- الحارس يتحقق من (وجود كل الأعمدة المطلوبة) و(غياب الفهرس) قبل الإنشاء؛
-- فما تقادم من الملف الأصلي (جدول drivers المُزال، وأعمدة mine_id/driver
-- التي أزالتها موجات إعادة التسمية) يُتخطّى تلقائيًا بلا فشل — idempotent.
-- ═══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ── timesheet ────────────────────────────────────────────────────────────────

-- idx_timesheet_operator ON timesheet (operator)
SET @ddl = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='timesheet' AND COLUMN_NAME IN ('operator')) = 1
    AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='timesheet' AND INDEX_NAME='idx_timesheet_operator'),
    'CREATE INDEX idx_timesheet_operator ON timesheet (operator)', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- idx_timesheet_driver ON timesheet (driver) — يُتخطّى إن كان العمود قد أُزيل
SET @ddl = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='timesheet' AND COLUMN_NAME IN ('driver')) = 1
    AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='timesheet' AND INDEX_NAME='idx_timesheet_driver'),
    'CREATE INDEX idx_timesheet_driver ON timesheet (driver)', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- idx_timesheet_date_id ON timesheet (date, id)
SET @ddl = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='timesheet' AND COLUMN_NAME IN ('date','id')) = 2
    AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='timesheet' AND INDEX_NAME='idx_timesheet_date_id'),
    'CREATE INDEX idx_timesheet_date_id ON timesheet (`date`, id)', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ── operations ───────────────────────────────────────────────────────────────

-- idx_operations_project ON operations (project_id)
SET @ddl = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operations' AND COLUMN_NAME IN ('project_id')) = 1
    AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operations' AND INDEX_NAME='idx_operations_project'),
    'CREATE INDEX idx_operations_project ON operations (project_id)', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- idx_operations_supplier ON operations (supplier_id)
SET @ddl = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operations' AND COLUMN_NAME IN ('supplier_id')) = 1
    AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operations' AND INDEX_NAME='idx_operations_supplier'),
    'CREATE INDEX idx_operations_supplier ON operations (supplier_id)', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- idx_operations_equipment ON operations (equipment)
SET @ddl = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operations' AND COLUMN_NAME IN ('equipment')) = 1
    AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operations' AND INDEX_NAME='idx_operations_equipment'),
    'CREATE INDEX idx_operations_equipment ON operations (equipment)', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- idx_operations_mine ON operations (mine_id) — يُتخطّى إن كان العمود قد أُزيل
SET @ddl = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operations' AND COLUMN_NAME IN ('mine_id')) = 1
    AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operations' AND INDEX_NAME='idx_operations_mine'),
    'CREATE INDEX idx_operations_mine ON operations (mine_id)', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- idx_operations_start ON operations (start)
SET @ddl = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operations' AND COLUMN_NAME IN ('start')) = 1
    AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operations' AND INDEX_NAME='idx_operations_start'),
    'CREATE INDEX idx_operations_start ON operations (`start`)', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ── contracts ────────────────────────────────────────────────────────────────

-- idx_contracts_mine_status_deleted ON contracts (mine_id, status, is_deleted) — يُتخطّى إن أُزيل mine_id
SET @ddl = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND COLUMN_NAME IN ('mine_id','status','is_deleted')) = 3
    AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND INDEX_NAME='idx_contracts_mine_status_deleted'),
    'CREATE INDEX idx_contracts_mine_status_deleted ON contracts (mine_id, status, is_deleted)', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- idx_contracts_signing_date ON contracts (contract_signing_date)
SET @ddl = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND COLUMN_NAME IN ('contract_signing_date')) = 1
    AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND INDEX_NAME='idx_contracts_signing_date'),
    'CREATE INDEX idx_contracts_signing_date ON contracts (contract_signing_date)', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- idx_contracts_status_contract_status ON contracts (status, contract_status(32))
-- contract_status عمود MEDIUMTEXT — الفهرسة تتطلب طول بادئة (كان هذا سبب فشل
-- الصيغة الأصلية بصمت)؛ 32 حرفًا تكفي قيم الحالة المخزّنة.
SET @ddl = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND COLUMN_NAME IN ('status','contract_status')) = 2
    AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contracts' AND INDEX_NAME='idx_contracts_status_contract_status'),
    'CREATE INDEX idx_contracts_status_contract_status ON contracts (status, contract_status(32))', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ── supplierscontracts ──────────────────────────────────────────────────────

-- idx_sc_supplier_project_mine ON supplierscontracts (supplier_id, project_id, mine_id)
SET @ddl = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='supplierscontracts' AND COLUMN_NAME IN ('supplier_id','project_id','mine_id')) = 3
    AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='supplierscontracts' AND INDEX_NAME='idx_sc_supplier_project_mine'),
    'CREATE INDEX idx_sc_supplier_project_mine ON supplierscontracts (supplier_id, project_id, mine_id)', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- idx_sc_status_signing ON supplierscontracts (status, contract_signing_date)
SET @ddl = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='supplierscontracts' AND COLUMN_NAME IN ('status','contract_signing_date')) = 2
    AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='supplierscontracts' AND INDEX_NAME='idx_sc_status_signing'),
    'CREATE INDEX idx_sc_status_signing ON supplierscontracts (status, contract_signing_date)', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ── drivercontracts ─────────────────────────────────────────────────────────

-- idx_dc_driver_project_mine ON drivercontracts (driver_id, project_id, mine_id)
SET @ddl = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='drivercontracts' AND COLUMN_NAME IN ('driver_id','project_id','mine_id')) = 3
    AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='drivercontracts' AND INDEX_NAME='idx_dc_driver_project_mine'),
    'CREATE INDEX idx_dc_driver_project_mine ON drivercontracts (driver_id, project_id, mine_id)', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- idx_dc_status_signing ON drivercontracts (status, contract_signing_date)
SET @ddl = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='drivercontracts' AND COLUMN_NAME IN ('status','contract_signing_date')) = 2
    AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='drivercontracts' AND INDEX_NAME='idx_dc_status_signing'),
    'CREATE INDEX idx_dc_status_signing ON drivercontracts (status, contract_signing_date)', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- ── masters ─────────────────────────────────────────────────────────────────

-- idx_mines_project_status ON mines (project_id, status) — يُتخطّى إن أُزيل project_id
SET @ddl = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mines' AND COLUMN_NAME IN ('project_id','status')) = 2
    AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='mines' AND INDEX_NAME='idx_mines_project_status'),
    'CREATE INDEX idx_mines_project_status ON mines (project_id, status)', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- idx_equipments_supplier_status_type ON equipments (suppliers, status, type)
SET @ddl = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='equipments' AND COLUMN_NAME IN ('suppliers','status','type')) = 3
    AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='equipments' AND INDEX_NAME='idx_equipments_supplier_status_type'),
    'CREATE INDEX idx_equipments_supplier_status_type ON equipments (suppliers, status, type)', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- idx_drivers_supplier_status ON drivers (supplier_id, status) — جدول drivers أُزيل
-- (استُبدل بـ employees في توحيد 2026-06-27)؛ الحارس يتخطّاه حيثما لا يوجد.
SET @ddl = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='drivers' AND COLUMN_NAME IN ('supplier_id','status')) = 2
    AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='drivers' AND INDEX_NAME='idx_drivers_supplier_status'),
    'CREATE INDEX idx_drivers_supplier_status ON drivers (supplier_id, status)', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;

-- idx_project_status_deleted ON project (status, is_deleted)
SET @ddl = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='project' AND COLUMN_NAME IN ('status','is_deleted')) = 2
    AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='project' AND INDEX_NAME='idx_project_status_deleted'),
    'CREATE INDEX idx_project_status_deleted ON project (status, is_deleted)', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;
