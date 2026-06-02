// Curated route list for visual-regression screenshots.
// These are full-page list/index views that render the global stylesheets.
// Detail/profile pages that carry their own <style> blocks are added with a
// concrete id in DETAIL_ROUTES when the file they live in is being refactored.
module.exports.ROUTES = [
  // Core app
  'main/dashboard.php',
  'main/users.php',
  'main/user_profile.php',
  // Equipments
  'Equipments/equipments.php',
  'Equipments/equipments_fleet.php',
  'Equipments/equipments_drivers.php',
  'Equipments/equipments_types.php',
  'Equipments/fleet_failures.php',
  'Equipments/manage_failure_codes.php',
  // Drivers
  'Drivers/drivers.php',
  'Drivers/drivercontracts.php',
  // Operators
  'Oprators/oprators.php',
  // Projects
  'Projects/projects.php',
  'Projects/oprationprojects.php',
  // Clients
  'Clients/clients.php',
  // Contracts
  'Contracts/contracts.php',
  // Suppliers
  'Suppliers/suppliers.php',
  'Suppliers/supplierscontracts.php',
  // Timesheet
  'Timesheet/timesheet.php',
  'Timesheet/timesheet_type.php',
  // Approvals
  'Approvals/requests.php',
  'Approvals/hours_approval.php',
  // Reports
  'Reports/reports.php',
  'Reports/new_reports.php',
  // Settings
  'Settings/settings.php',
  'Settings/roles.php',
  'Settings/modules.php',
  'Settings/change_password.php',
  // Misc
  'ActivityLogs/activity_logs.php',
  'chats/index.php',
  'movement/movement_operations.php',
  // Pre-login pages (rendered without auth; harness handles separately)
];

// Pages that render fine without a logged-in session (captured in a clean ctx).
module.exports.PUBLIC_ROUTES = [
  'login.php',
  'company/login.php',
  'company/register.php',
  'admin/login.php',
];

module.exports.VIEWPORTS = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'mobile', width: 390, height: 844 },
];
