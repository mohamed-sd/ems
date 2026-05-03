-- تحسين أداء التقارير عند أحجام بيانات كبيرة (+50k)
-- شغّل هذا الملف مرة واحدة على قاعدة بيانات equipation_manage

DELIMITER $$

DROP PROCEDURE IF EXISTS add_index_if_missing $$
CREATE PROCEDURE add_index_if_missing(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_ddl   TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = p_table
          AND index_name = p_index
    ) THEN
        SET @sql_stmt = p_ddl;
        PREPARE stmt FROM @sql_stmt;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

-- timesheet
CALL add_index_if_missing('timesheet', 'idx_timesheet_operator', 'CREATE INDEX idx_timesheet_operator ON timesheet (operator)') $$
CALL add_index_if_missing('timesheet', 'idx_timesheet_driver', 'CREATE INDEX idx_timesheet_driver ON timesheet (driver)') $$
CALL add_index_if_missing('timesheet', 'idx_timesheet_date_id', 'CREATE INDEX idx_timesheet_date_id ON timesheet (date, id)') $$

-- operations
CALL add_index_if_missing('operations', 'idx_operations_project', 'CREATE INDEX idx_operations_project ON operations (project_id)') $$
CALL add_index_if_missing('operations', 'idx_operations_supplier', 'CREATE INDEX idx_operations_supplier ON operations (supplier_id)') $$
CALL add_index_if_missing('operations', 'idx_operations_equipment', 'CREATE INDEX idx_operations_equipment ON operations (equipment)') $$
CALL add_index_if_missing('operations', 'idx_operations_mine', 'CREATE INDEX idx_operations_mine ON operations (mine_id)') $$
CALL add_index_if_missing('operations', 'idx_operations_start', 'CREATE INDEX idx_operations_start ON operations (start)') $$

-- contracts
CALL add_index_if_missing('contracts', 'idx_contracts_mine_status_deleted', 'CREATE INDEX idx_contracts_mine_status_deleted ON contracts (mine_id, status, is_deleted)') $$
CALL add_index_if_missing('contracts', 'idx_contracts_signing_date', 'CREATE INDEX idx_contracts_signing_date ON contracts (contract_signing_date)') $$
CALL add_index_if_missing('contracts', 'idx_contracts_status_contract_status', 'CREATE INDEX idx_contracts_status_contract_status ON contracts (status, contract_status)') $$

-- supplierscontracts
CALL add_index_if_missing('supplierscontracts', 'idx_sc_supplier_project_mine', 'CREATE INDEX idx_sc_supplier_project_mine ON supplierscontracts (supplier_id, project_id, mine_id)') $$
CALL add_index_if_missing('supplierscontracts', 'idx_sc_status_signing', 'CREATE INDEX idx_sc_status_signing ON supplierscontracts (status, contract_signing_date)') $$

-- drivercontracts
CALL add_index_if_missing('drivercontracts', 'idx_dc_driver_project_mine', 'CREATE INDEX idx_dc_driver_project_mine ON drivercontracts (driver_id, project_id, mine_id)') $$
CALL add_index_if_missing('drivercontracts', 'idx_dc_status_signing', 'CREATE INDEX idx_dc_status_signing ON drivercontracts (status, contract_signing_date)') $$

-- masters
CALL add_index_if_missing('mines', 'idx_mines_project_status', 'CREATE INDEX idx_mines_project_status ON mines (project_id, status)') $$
CALL add_index_if_missing('equipments', 'idx_equipments_supplier_status_type', 'CREATE INDEX idx_equipments_supplier_status_type ON equipments (suppliers, status, type)') $$
CALL add_index_if_missing('drivers', 'idx_drivers_supplier_status', 'CREATE INDEX idx_drivers_supplier_status ON drivers (supplier_id, status)') $$
CALL add_index_if_missing('project', 'idx_project_status_deleted', 'CREATE INDEX idx_project_status_deleted ON project (status, is_deleted)') $$

DROP PROCEDURE IF EXISTS add_index_if_missing $$

DELIMITER ;
