-- =====================================================================
-- ???? ??????? ???????? (????)
-- role_id + report_code ???, ???? FK constraints
-- =====================================================================

-- ????? ??????
CREATE TABLE IF NOT EXISTS `report_role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `report_code` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_report` (`role_id`, `report_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ???????? ??????????: ???? ???????? (role_id=1) ??? ???? ????????
INSERT IGNORE INTO `report_role_permissions` (`role_id`, `report_code`) VALUES
(1, 'timesheet_summary'),
(1, 'timesheet_detailed'),
(1, 'timesheet_by_project'),
(1, 'timesheet_by_equipment'),
(1, 'timesheet_by_driver'),
(1, 'project_summary'),
(1, 'project_detailed'),
(1, 'contracts_summary'),
(1, 'contracts_detailed'),
(1, 'supplier_contracts_summary'),
(1, 'supplier_contracts_detailed'),
(1, 'supplier_timesheet'),
(1, 'supplier_equipment_performance'),
(1, 'fleet_equipment_summary'),
(1, 'fleet_equipment_detailed'),
(1, 'fleet_operations'),
(1, 'fleet_timesheet'),
(1, 'drivers_summary'),
(1, 'drivers_detailed'),
(1, 'drivers_timesheet'),
(1, 'drivers_contracts'),
(1, 'operations_summary'),
(1, 'operations_detailed');
